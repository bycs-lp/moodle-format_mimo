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
 * Activity description manager for format_mimo.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo;

defined('MOODLE_INTERNAL') || die();

/**
 * Manages activity type descriptions for the tag chooser modal.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_description_manager {
    /** @var string Cache key for activity descriptions */
    const CACHE_KEY = 'activity_descriptions';

    /**
     * Get all activity descriptions with tag information.
     *
     * @return array Array of stdClass objects with activitytype, description, and tag properties
     */
    public static function get_all_descriptions(): array {
        $cache = \cache::make('format_mimo', 'activity_descriptions');
        $descriptions = $cache->get(self::CACHE_KEY);

        if ($descriptions === false) {
            global $DB;

            // Fetch descriptions with tag data via LEFT JOIN.
            $sql = "SELECT ad.id, ad.activitytype, ad.description, ad.desctagid,
                           ad.timecreated, ad.timemodified,
                           dt.name AS tagname, dt.color AS tagcolor
                      FROM {format_mimo_actdesc} ad
                 LEFT JOIN {format_mimo_desc_tags} dt ON ad.desctagid = dt.id
                  ORDER BY ad.activitytype ASC";

            $descriptions = $DB->get_records_sql($sql);
            $cache->set(self::CACHE_KEY, $descriptions);
        }

        return $descriptions;
    }

    /**
     * Get description for a specific activity type.
     *
     * @param string $activitytype The activity module name (e.g., 'assign', 'quiz')
     * @return string|null The description or null if not found
     */
    public static function get_description(string $activitytype): ?string {
        $descriptions = self::get_all_descriptions();

        foreach ($descriptions as $desc) {
            if ($desc->activitytype === $activitytype) {
                return $desc->description;
            }
        }

        return null;
    }

    /**
     * Get description with tag info for a specific activity type.
     *
     * @param string $activitytype The activity module name (e.g., 'assign', 'quiz')
     * @return \stdClass|null Object with description, tagname, tagcolor or null if not found
     */
    public static function get_description_with_tag(string $activitytype): ?\stdClass {
        $descriptions = self::get_all_descriptions();

        foreach ($descriptions as $desc) {
            if ($desc->activitytype === $activitytype) {
                $result = new \stdClass();
                $result->description = $desc->description;
                $result->tagname = $desc->tagname ?? null;
                $result->tagcolor = $desc->tagcolor ?? null;

                return $result;
            }
        }

        return null;
    }

    /**
     * Save or update an activity description.
     *
     * @param string $activitytype The activity module name
     * @param string $description The description text
     * @param int|null $desctagid The tag ID (optional)
     * @return bool Success status
     */
    public static function save_description(string $activitytype, string $description, ?int $desctagid = null): bool {
        global $DB;

        $time = \core\di::get(\core\clock::class)->time();
        $record = $DB->get_record('format_mimo_actdesc', ['activitytype' => $activitytype]);

        if ($record) {
            $record->description = $description;
            $record->desctagid = $desctagid;
            $record->timemodified = $time;
            $result = $DB->update_record('format_mimo_actdesc', $record);
        } else {
            $record = new \stdClass();
            $record->activitytype = $activitytype;
            $record->description = $description;
            $record->desctagid = $desctagid;
            $record->timecreated = $time;
            $record->timemodified = $time;
            $result = $DB->insert_record('format_mimo_actdesc', $record);
        }

        if ($result) {
            self::clear_cache();
        }

        return (bool)$result;
    }

    /**
     * Delete an activity description.
     *
     * @param string $activitytype The activity module name
     * @return bool Success status
     */
    public static function delete_description(string $activitytype): bool {
        global $DB;
        $result = $DB->delete_records('format_mimo_actdesc', ['activitytype' => $activitytype]);

        if ($result) {
            self::clear_cache();
        }

        return $result;
    }

    /**
     * Clear the activity descriptions cache.
     *
     * @return void
     */
    public static function clear_cache(): void {
        $cache = \cache::make('format_mimo', 'activity_descriptions');
        $cache->delete(self::CACHE_KEY);
    }

    /**
     * Get all available activity modules.
     *
     * @return array Array of activity types with name and displayname
     */
    public static function get_available_activity_types(): array {
        $modules = [];
        $mods = \core_component::get_plugin_list('mod');

        foreach ($mods as $modname => $modpath) {
            if (!file_exists("$modpath/lib.php")) {
                continue;
            }

            // Skip non-activity modules.
            if (in_array($modname, ['label', 'folder'])) {
                continue;
            }

            try {
                $displayname = get_string('modulename', 'mod_' . $modname);
            } catch (\Exception $e) {
                $displayname = ucfirst($modname);
            }

            $modules[$modname] = [
                'name' => $modname,
                'displayname' => $displayname,
            ];
        }

        // Sort by display name.
        uasort($modules, function ($a, $b) {
            return strcmp($a['displayname'], $b['displayname']);
        });

        return $modules;
    }

    /**
     * Initialize default activity descriptions for all available activity types.
     *
     * Maps each activity type to a description (from lang strings) and a
     * description tag (Input, Practice, Share, Think). Only runs if no
     * descriptions exist yet (idempotent on first install).
     *
     * @return bool Success
     */
    public static function initialize_default_activity_descriptions(): bool {
        global $DB;

        // Check if any descriptions already exist.
        if ($DB->record_exists('format_mimo_actdesc', [])) {
            return true;
        }

        // Get all description tags keyed by their display name.
        $desctags = description_tag_manager::get_all_tags();
        $tagbyname = [];
        foreach ($desctags as $tag) {
            $tagbyname[$tag->name] = $tag->id;
        }

        // Resolve tag IDs by lang string. Falls back to null if tag not found.
        $inputid = $tagbyname[get_string('desctag_input', 'format_mimo')] ?? null;
        $practiceid = $tagbyname[get_string('desctag_practice', 'format_mimo')] ?? null;
        $shareid = $tagbyname[get_string('desctag_share', 'format_mimo')] ?? null;
        $thinkid = $tagbyname[get_string('desctag_think', 'format_mimo')] ?? null;

        // Activity type to description tag mapping.
        $tagmap = [
            // Input: consuming/receiving content.
            'page' => $inputid,
            'book' => $inputid,
            'resource' => $inputid,
            'url' => $inputid,
            'imscp' => $inputid,
            'scorm' => $inputid,
            'lesson' => $inputid,
            'hvp' => $inputid,
            'h5pactivity' => $inputid,
            'lti' => $inputid,
            'learningmap' => $inputid,
            'unilabel' => $inputid,
            'subcourse' => $inputid,
            // Practice: active exercises and drills.
            'quiz' => $practiceid,
            'game' => $practiceid,
            'mootyper' => $practiceid,
            'geogebra' => $practiceid,
            'qbank' => $practiceid,
            // Share: producing and sharing work.
            'forum' => $shareid,
            'assign' => $shareid,
            'glossary' => $shareid,
            'wiki' => $shareid,
            'board' => $shareid,
            'journal' => $shareid,
            'moodleoverflow' => $shareid,
            'lightboxgallery' => $shareid,
            'data' => $shareid,
            // Think: reflection, collaboration, decisions.
            'choice' => $thinkid,
            'feedback' => $thinkid,
            'workshop' => $thinkid,
            'ratingallocate' => $thinkid,
            'bigbluebuttonbn' => $thinkid,
            'individualfeedback' => $thinkid,
            'kanban' => $thinkid,
            'aichat' => $thinkid,
            'mootimeter' => $thinkid,
            'checklist' => $thinkid,
        ];

        // Only create descriptions for modules that are actually installed.
        $availabletypes = self::get_available_activity_types();

        foreach ($availabletypes as $type) {
            $modname = $type['name'];
            $stringid = 'actdesc_' . $modname;

            // Skip if no lang string defined for this module.
            if (!get_string_manager()->string_exists($stringid, 'format_mimo')) {
                continue;
            }

            $description = get_string($stringid, 'format_mimo');
            $desctagid = $tagmap[$modname] ?? null;

            self::save_description($modname, $description, $desctagid);
        }

        return true;
    }
}
