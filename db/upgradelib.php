<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Upgrade helper functions for format_mimo.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use format_mimo\profile_manager;
use format_mimo\tag_manager;

/**
 * Locate the anchor tag that was seeded from a default tag definition.
 *
 * Default tags are identified by their bundled image filename first (language
 * independent), falling back to the localised default name.
 *
 * @param tag_manager $tagmanager Tag manager
 * @param array $definition Entry of {@see tag_manager::get_default_tag_definitions()}
 * @param string[] $legacyfilenames Former bundled filenames of the same tag
 * @return stdClass|null
 */
function format_mimo_upgrade_find_default_tag(tag_manager $tagmanager, array $definition, array $legacyfilenames = []): ?stdClass {
    global $DB;

    $filenames = array_values(array_unique(array_filter(array_merge([$definition['cardimage']], $legacyfilenames))));
    if (!empty($filenames)) {
        [$insql, $params] = $DB->get_in_or_equal($filenames, SQL_PARAMS_NAMED);
        $params['scope'] = 'global';
        $records = $DB->get_records_select(
            'format_mimo_tags',
            "scope = :scope AND cardimage $insql",
            $params,
            'sortorder ASC, id ASC',
            '*',
            0,
            1
        );
        if ($records) {
            return reset($records);
        }
    }

    return $tagmanager->find_tag_by_name($definition['name']);
}

/**
 * Refresh stored copies of bundled pix/tags artwork.
 *
 * Every file in the tag/profile-tag image areas whose filename matches a
 * bundled asset is replaced when its content differs from the shipped file.
 * Renamed assets are mapped via $renames and the referencing DB field is
 * updated when it still holds the old filename.
 *
 * @param array $renames Old filename => new filename
 */
function format_mimo_upgrade_refresh_default_images(array $renames = []): void {
    global $DB;

    $fs = get_file_storage();
    $ctxid = \core\context\system::instance()->id;
    $pixdir = core_component::get_component_directory('format_mimo') . '/pix/tags/';

    $areas = [
        tag_manager::FILEAREA_CARDIMAGE => ['table' => 'format_mimo_tags', 'field' => 'cardimage'],
        tag_manager::FILEAREA_FILTERIMAGE => ['table' => 'format_mimo_tags', 'field' => 'filterimage'],
        profile_manager::FILEAREA_PROFILE_CARDIMAGE => ['table' => 'format_mimo_profile_tags', 'field' => 'cardimage'],
        profile_manager::FILEAREA_PROFILE_FILTERIMAGE => ['table' => 'format_mimo_profile_tags', 'field' => 'filterimage'],
    ];

    foreach ($areas as $filearea => $target) {
        $files = $fs->get_area_files($ctxid, 'format_mimo', $filearea, false, 'itemid, filename', false);
        foreach ($files as $file) {
            $oldname = $file->get_filename();
            $newname = $renames[$oldname] ?? $oldname;
            $source = $pixdir . $newname;
            if (!file_exists($source)) {
                continue;
            }
            if ($newname === $oldname && $file->get_contenthash() === sha1_file($source)) {
                continue;
            }

            $filerecord = [
                'contextid' => $ctxid,
                'component' => 'format_mimo',
                'filearea' => $filearea,
                'itemid' => $file->get_itemid(),
                'filepath' => $file->get_filepath(),
                'filename' => $newname,
            ];
            $file->delete();
            $fs->create_file_from_pathname($filerecord, $source);

            if ($newname !== $oldname) {
                $DB->set_field_select(
                    $target['table'],
                    $target['field'],
                    $newname,
                    "id = :id AND {$target['field']} = :oldname",
                    ['id' => $file->get_itemid(), 'oldname' => $oldname]
                );
            }
        }
    }
}

/**
 * Check whether a record's suggested activity types still equal an expected trio.
 *
 * Each expected slot is either a scalar or a list of accepted alternatives.
 *
 * @param stdClass $record Tag or profile-tag record
 * @param array $expected Three slots of accepted values
 * @return bool
 */
function format_mimo_upgrade_types_match(stdClass $record, array $expected): bool {
    foreach (['activitytype1', 'activitytype2', 'activitytype3'] as $i => $field) {
        $accepted = is_array($expected[$i]) ? $expected[$i] : [$expected[$i]];
        $value = $record->$field ?? null;
        if ($value === '') {
            $value = null;
        }
        if (!in_array($value, $accepted, true)) {
            return false;
        }
    }
    return true;
}

/**
 * Align seeded default tags (anchor + default profile rows) with the current
 * default definitions, touching only values that still equal the old defaults.
 *
 * Changes covered (release 1.0.1 → 1.0.2):
 * - image placement 'center' → 'lower' on all default tags
 * - "Listen" drops the third suggested type (hvp/h5pactivity)
 * - "Cooperate" third suggested type 'wiki' → 'glossary'
 * - "Project" gets the 'forum' fallback when 'kanban' was unavailable
 * - primary_horst: images 'bigger'; "Show" third type → 'glossary' and
 *   "Design" second type → 'glossary', both keeping 'center' placement
 */
function format_mimo_upgrade_default_tag_values(): void {
    global $DB;

    $tagmanager = \core\di::get(tag_manager::class);
    $profilemanager = \core\di::get(profile_manager::class);
    $definitions = $tagmanager->get_default_tag_definitions();
    $now = \core\di::get(\core\clock::class)->time();

    $profiles = [];
    foreach (['base', 'base_symbols', 'primary_horst'] as $name) {
        if ($profile = $profilemanager->get_profile_by_name($name)) {
            $profiles[$name] = (int) $profile->id;
        }
    }

    // Definition index => [old accepted trio, new trio].
    $typerules = [
        8 => [['page', 'resource', ['hvp', 'h5pactivity']], ['page', 'resource', null]],
        9 => [['board', 'forum', ['wiki', null]], ['board', 'forum', 'glossary']],
        10 => [[null, null, null], ['forum', null, null]],
    ];
    $horsttyperules = [
        5 => [['forum', 'board', ['hvp', 'h5pactivity', null]], ['forum', 'board', 'glossary']],
        6 => [['assign', 'forum', null], ['assign', 'glossary', null]],
    ];
    $horstbigger = [0, 1, 2, 3, 5, 6, 7, 8, 9, 10, 11, 12];
    $horstcenter = [5, 6];
    $legacyfilenames = [3 => ['base_practise.png']];

    foreach ($definitions as $index => $definition) {
        $tag = format_mimo_upgrade_find_default_tag($tagmanager, $definition, $legacyfilenames[$index] ?? []);
        if (!$tag) {
            continue;
        }

        $rows = [['table' => 'format_mimo_tags', 'record' => $tag, 'profile' => null]];
        foreach ($profiles as $profilename => $profileid) {
            $pt = $DB->get_record('format_mimo_profile_tags', ['tagid' => $tag->id, 'profileid' => $profileid]);
            if ($pt) {
                $rows[] = ['table' => 'format_mimo_profile_tags', 'record' => $pt, 'profile' => $profilename];
            }
        }

        foreach ($rows as $row) {
            $record = $row['record'];
            $ishorst = $row['profile'] === 'primary_horst';
            $update = new stdClass();

            if (($record->imgplacement ?? null) === 'center' && !($ishorst && in_array($index, $horstcenter, true))) {
                $update->imgplacement = 'lower';
            }
            if ($ishorst && in_array($index, $horstbigger, true) && ($record->imgsize ?? null) === 'normal') {
                $update->imgsize = 'bigger';
            }

            $rule = $ishorst ? ($horsttyperules[$index] ?? $typerules[$index] ?? null) : ($typerules[$index] ?? null);
            if ($rule && format_mimo_upgrade_types_match($record, $rule[0])) {
                [$update->activitytype1, $update->activitytype2, $update->activitytype3] =
                    $tagmanager->sanitize_default_activitytypes(...$rule[1]);
                foreach (['activitytype1', 'activitytype2', 'activitytype3'] as $field) {
                    if (($record->$field ?? null) === $update->$field) {
                        unset($update->$field);
                    }
                }
            }

            if (empty((array) $update)) {
                continue;
            }
            $update->id = $record->id;
            $update->timemodified = $now;
            $DB->update_record($row['table'], $update);
        }
    }

    $profilemanager->clear_request_caches();
    $tagmanager->clear_tag_cache();
}

/**
 * Remove a formerly seeded default profile, repointing courses to the default profile.
 *
 * @param string $name Internal profile name
 */
function format_mimo_upgrade_delete_default_profile(string $name): void {
    global $DB;

    $profilemanager = \core\di::get(profile_manager::class);
    $profile = $profilemanager->get_profile_by_name($name);
    if (!$profile) {
        return;
    }

    $profilemanager->delete_profile((int) $profile->id);

    $fallback = $profilemanager->get_default_profile_name();
    if ($fallback !== '' && $fallback !== $name) {
        $valcompare = $DB->sql_compare_text('value', 255);
        $DB->set_field_select(
            'course_format_options',
            'value',
            $fallback,
            "format = 'mimo' AND name = 'activityprofile' AND $valcompare = :oldname",
            ['oldname' => $name]
        );
    }
}

/**
 * Recolour default description tags and drop the leading colour-circle emoji
 * from their names, touching only values that still equal the old defaults.
 *
 * @param array $colormap Old colour => new colour (uppercase hex)
 */
function format_mimo_upgrade_description_tags(array $colormap): void {
    global $DB;

    $manager = \core\di::get(\format_mimo\description_tag_manager::class);
    foreach ($DB->get_records('format_mimo_desc_tags') as $tag) {
        $color = $colormap[strtoupper($tag->color)] ?? $tag->color;
        $name = preg_replace('/^[\x{1F7E1}\x{1F7E2}\x{1F7E3}\x{1F535}]\s*/u', '', $tag->name);
        if ($color !== $tag->color || $name !== $tag->name) {
            $manager->update_tag((int) $tag->id, $name, $color);
        }
    }
}
