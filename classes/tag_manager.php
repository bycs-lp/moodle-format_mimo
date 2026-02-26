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
 * Tag manager for format_minimoodlewall.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall;

use context_system;
use core_component;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * Tag manager class for handling tag sets and tags.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tag_manager {
    /** File area for the large card image associated with a tag. */
    public const FILEAREA_CARDIMAGE = 'tagcard';

    /** File area reserved for future filter bar imagery. */
    public const FILEAREA_FILTERIMAGE = 'tagfilter';

    /** @var \cache_application Cache for tag configurations */
    private static $tagcache = null;

    /** @var \cache_application Cache for activity-tag mappings */
    private static $mappingcache = null;

    /** Pastel accents used by the "starters" style when no custom colour is set. */
    private const STARTER_ACCENT_COLORS = [
        '#cfe5fa',
        '#fde9c9',
        '#eadaf8',
        '#fff3b0',
        '#d3f2c2',
        '#f0f1f0',
        '#dcecff',
        '#ffe1db',
        '#dff5d1',
    ];

    /**
     * Public accessor to the default accent palette (used by upgrade/install routines).
     *
     * @return array
     */
    public static function get_default_accent_palette(): array {
        return self::STARTER_ACCENT_COLORS;
    }

    /**
     * Normalise an incoming colour value to the #rrggbb format or null when invalid.
     *
     * @param string|null $color Raw user input
     * @return string|null
     */
    private static function normalize_hex_color(?string $color): ?string {
        if ($color === null) {
            return null;
        }

        $trimmed = trim($color);
        if ($trimmed === '') {
            return null;
        }

        if ($trimmed[0] !== '#') {
            $trimmed = '#' . $trimmed;
        }

        if (!preg_match('/^#([0-9a-fA-F]{6})$/', $trimmed, $matches)) {
            return null;
        }

        return '#' . strtolower($matches[1]);
    }

    /**
     * Retrieve the shared filemanager options for tag image uploads.
     *
     * @return array
     */
    public static function get_image_filemanager_options(): array {
        return self::FILEMANAGER_OPTIONS;
    }

    /**
     * Prepare a draft area for the card image filemanager field.
     *
     * @param int|null $tagid Tag id when editing, null when creating
     * @return int Draft item id populated with existing files (if any)
     */
    public static function prepare_cardimage_draft(?int $tagid = null): int {
        $draftitemid = file_get_submitted_draft_itemid('cardimagefile');
        file_prepare_draft_area(
            $draftitemid,
            context_system::instance()->id,
            'format_minimoodlewall',
            self::FILEAREA_CARDIMAGE,
            $tagid ?? 0,
            self::get_image_filemanager_options()
        );

        return $draftitemid;
    }

    /**
     * Prepare a draft area for the filter image filemanager field.
     *
     * @param int|null $tagid Tag id when editing, null when creating
     * @return int Draft item id populated with existing files (if any)
     */
    public static function prepare_filterimage_draft(?int $tagid = null): int {
        $draftitemid = file_get_submitted_draft_itemid('filterimagefile');
        file_prepare_draft_area(
            $draftitemid,
            context_system::instance()->id,
            'format_minimoodlewall',
            self::FILEAREA_FILTERIMAGE,
            $tagid ?? 0,
            self::get_image_filemanager_options()
        );

        return $draftitemid;
    }

    /**
     * Persist the uploaded card image stored in a draft area.
     *
     * @param int $tagid Tag id
     * @param int $draftitemid Draft area identifier
     */
    public static function save_cardimage_from_draft(int $tagid, int $draftitemid): void {
        self::save_image_from_draft($tagid, $draftitemid, self::FILEAREA_CARDIMAGE, 'cardimage');
    }

    /**
     * Persist the uploaded filter image stored in a draft area.
     *
     * @param int $tagid Tag id
     * @param int $draftitemid Draft area identifier
     */
    public static function save_filterimage_from_draft(int $tagid, int $draftitemid): void {
        self::save_image_from_draft($tagid, $draftitemid, self::FILEAREA_FILTERIMAGE, 'filterimage');
    }

    /**
     * Whether a stored filter image already exists for the given tag.
     *
     * @param int $tagid Tag id
     * @return bool
     */
    public static function has_filterimage(int $tagid): bool {
        return (bool)self::get_image_file($tagid, self::FILEAREA_FILTERIMAGE);
    }

    /**
     * Shared helper to move files from a draft area into storage and persist the filename.
     *
     * @param int $tagid Tag id
     * @param int $draftitemid Draft area identifier
     * @param string $filearea Target file area constant
     * @param string $dbfield Database column to update
     */
    private static function save_image_from_draft(int $tagid, int $draftitemid, string $filearea, string $dbfield): void {
        file_save_draft_area_files(
            $draftitemid,
            context_system::instance()->id,
            'format_minimoodlewall',
            $filearea,
            $tagid,
            self::get_image_filemanager_options()
        );

        $file = self::get_image_file($tagid, $filearea);
        $filename = $file ? $file->get_filename() : null;
        self::update_tag($tagid, [$dbfield => $filename]);
    }

    /**
     * Whether a stored card image already exists for the given tag.
     *
     * @param int $tagid Tag id
     * @return bool
     */
    public static function has_cardimage(int $tagid): bool {
        return (bool)self::get_image_file($tagid, self::FILEAREA_CARDIMAGE);
    }

    /**
     * Build the display URL for the card image.
     *
     * @param \stdClass $tag Tag record
     * @param string|null $profilename Optional profile name to get profile-specific image
     * @return moodle_url|null
     */
    public static function get_cardimage_url(\stdClass $tag, ?string $profilename = null): ?moodle_url {
        // If profile specified, try to get profile-specific image first.
        if ($profilename !== null) {
            $url = profile_manager::get_cardimage_url_by_name($tag->id, $profilename);
            if ($url) {
                return $url;
            }
        }
        // Fall back to legacy image storage.
        return self::get_image_url($tag, self::FILEAREA_CARDIMAGE);
    }

    /**
     * Build the display URL for the filter image area.
     *
     * @param \stdClass $tag Tag record
     * @param string|null $profilename Optional profile name to get profile-specific image
     * @return moodle_url|null
     */
    public static function get_filterimage_url(\stdClass $tag, ?string $profilename = null): ?moodle_url {
        // If profile specified, try to get profile-specific image first.
        if ($profilename !== null) {
            $url = profile_manager::get_filterimage_url_by_name($tag->id, $profilename);
            if ($url) {
                return $url;
            }
        }
        // Fall back to legacy image storage.
        return self::get_image_url($tag, self::FILEAREA_FILTERIMAGE);
    }

    /**
     * Resolve the pluginfile URL for a stored file in the given file area.
     *
     * @param \stdClass $tag Tag record
     * @param string $filearea File area constant
     * @return moodle_url|null
     */
    private static function get_image_url(\stdClass $tag, string $filearea): ?moodle_url {
        $file = self::get_image_file($tag->id, $filearea);
        if (!$file) {
            return null;
        }

        return moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        );
    }

    /**
     * Fetch the stored file object for a tag image.
     *
     * @param int $tagid Tag id
     * @param string $filearea File area constant
     * @return \stored_file|null
     */
    private static function get_image_file(int $tagid, string $filearea): ?\stored_file {
        $files = get_file_storage()->get_area_files(
            context_system::instance()->id,
            'format_minimoodlewall',
            $filearea,
            $tagid,
            '',
            false
        );

        if (empty($files)) {
            return null;
        }

        return reset($files);
    }

    /**
     * Return how many course modules reference each tag within a course.
     *
     * @param int $courseid Course id
     * @param int[] $tagids List of tag ids
     * @return array tagid => usage count
     */
    public static function get_tag_usage_counts(int $courseid, array $tagids): array {
        global $DB;

        if (empty($tagids)) {
            return [];
        }

        $tagids = array_map('intval', $tagids);
        [$insql, $params] = $DB->get_in_or_equal($tagids, SQL_PARAMS_NAMED);
        $params['courseid'] = $courseid;

        $sql = "SELECT cmt.tagid, COUNT(1) AS usecount
                  FROM {format_minimoodlewall_cmtags} cmt
                  JOIN {course_modules} cm ON cm.id = cmt.cmid
                 WHERE cm.course = :courseid AND cmt.tagid $insql
              GROUP BY cmt.tagid";

        return $DB->get_records_sql_menu($sql, $params);
    }

    /**
     * Copy a bundled pix/tags asset into the managed file area.
     *
     * @param int $tagid Tag id
     * @param string|null $filename Bundled filename
     * @param string $filearea Destination file area constant
     */
    private static function copy_default_image(int $tagid, ?string $filename, string $filearea): void {
        if (empty($filename)) {
            return;
        }

        $context = context_system::instance();
        $fs = get_file_storage();
        if ($fs->file_exists($context->id, 'format_minimoodlewall', $filearea, $tagid, '/', $filename)) {
            return;
        }

        $componentdir = core_component::get_component_directory('format_minimoodlewall');
        $source = $componentdir . '/pix/tags/' . $filename;
        if (!file_exists($source)) {
            return;
        }

        $filerecord = [
            'contextid' => $context->id,
            'component' => 'format_minimoodlewall',
            'filearea' => $filearea,
            'itemid' => $tagid,
            'filepath' => '/',
            'filename' => $filename,
        ];
        $fs->create_file_from_pathname($filerecord, $source);
    }

    /**
     * Options shared by all tag image filemanager instances.
     */
    private const FILEMANAGER_OPTIONS = [
        'subdirs' => 0,
        'maxfiles' => 1,
        'maxbytes' => 0,
        'accepted_types' => ['.svg', 'image/svg+xml'],
    ];

    /**
     * Initialize caches.
     *
     * Cache hierarchy and key usage:
     * - all_tags: Complete list of all tags (global)
     * - tag_{id}: Individual tag record (complete tag data)
     * - course_tags_{id}: Array of selected tag IDs for course {id}
     * - cm_{id}: Course module to tag ID mapping (in mappingcache)
     *
     * Note: Tagset-related cache keys (tagset_tags_*, all_tagsets, tagset_*) were removed
     * in the tagset→flat migration.
     *
     * @return void
     */
    private static function init_caches(): void {
        if (self::$tagcache === null) {
            self::$tagcache = \cache::make('format_minimoodlewall', 'tagconfigurations');
        }
        if (self::$mappingcache === null) {
            self::$mappingcache = \cache::make('format_minimoodlewall', 'activitytagmappings');
        }
    }

    /**
     * Get all tags.
     *
     * @return array Array of tag records sorted by sortorder
     */
    public static function get_all_tags(): array {
        global $DB;
        self::init_caches();

        $cachekey = 'all_tags';
        $tags = self::$tagcache->get($cachekey);

        if ($tags === false) {
            $tags = $DB->get_records('format_minimoodlewall_tags', null, 'sortorder ASC, id ASC');
            self::$tagcache->set($cachekey, $tags);
        }

        return $tags;
    }

    /**
     * Get tags enabled for a specific course based on its activity profile.
     *
     * Returns all tags that are enabled in the course's activity profile,
     * with profile-specific overrides (name, bgcolor, activity types) applied.
     *
     * @param int $courseid Course ID
     * @return array Array of resolved tag records enabled for this course's profile
     */
    public static function get_tags_for_course(int $courseid): array {
        global $DB;
        self::init_caches();

        $cachekey = 'course_tags_' . $courseid;
        $cachedtags = self::$tagcache->get($cachekey);

        if ($cachedtags !== false) {
            return $cachedtags;
        }

        // Get all base tags.
        $alltags = self::get_all_tags();
        if (empty($alltags)) {
            self::$tagcache->set($cachekey, []);
            return [];
        }

        // Get the course's activity profile.
        $profilename = $DB->get_field('course_format_options', 'value', [
            'courseid' => $courseid,
            'format' => 'minimoodlewall',
            'name' => 'activityprofile',
        ]);
        if (empty($profilename)) {
            $profilename = 'classic';
        }

        // Resolve profile ID.
        $profile = profile_manager::get_profile_by_name($profilename);
        if (!$profile) {
            // Fallback to classic if profile doesn't exist.
            $profile = profile_manager::get_profile_by_name('classic');
        }

        if (!$profile) {
            // No profiles at all — return all tags unfiltered.
            self::$tagcache->set($cachekey, $alltags);
            return $alltags;
        }

        // Return only enabled tags with profile overrides applied.
        $tags = profile_manager::resolve_tags_for_profile($alltags, $profile->id, true);

        self::$tagcache->set($cachekey, $tags);

        return $tags;
    }

    /**
     * Clear the course tags cache for a specific course.
     *
     * @param int $courseid Course ID
     */
    public static function clear_course_tags_cache(int $courseid): void {
        self::init_caches();
        self::$tagcache->delete('course_tags_' . $courseid);
    }

    /**
     * Create a new tag.
     *
     * @param string $name Tag name
     * @param string|null $cardimage Card image filename
     * @param string|null $filterimage Filter image filename
     * @param string|null $activitytype1 First suggested activity type
     * @param string|null $activitytype2 Second suggested activity type
     * @param string|null $activitytype3 Third suggested activity type
     * @param string|null $bgcolor Background color in hex format
     * @param string|null $imgplacement Image placement (center or lower)
     * @return int ID of the created tag
     */
    public static function create_tag(
        string $name,
        ?string $cardimage = null,
        ?string $filterimage = null,
        ?string $activitytype1 = null,
        ?string $activitytype2 = null,
        ?string $activitytype3 = null,
        ?string $bgcolor = null,
        ?string $imgplacement = 'center'
    ): int {
        global $DB;

        // Get next sort order globally.
        $maxsort = $DB->get_field(
            'format_minimoodlewall_tags',
            'MAX(sortorder)',
            []
        );
        $sortorder = $maxsort ? (int)$maxsort + 1 : 0;

        $record = new \stdClass();
        $record->name = $name;
        $record->cardimage = $cardimage;
        $record->filterimage = $filterimage;
        $record->activitytype1 = $activitytype1;
        $record->activitytype2 = $activitytype2;
        $record->activitytype3 = $activitytype3;
        $record->bgcolor = self::normalize_hex_color($bgcolor);
        $record->imgplacement = $imgplacement ?? 'center';
        $record->sortorder = $sortorder;
        $record->timecreated = time();
        $record->timemodified = time();

        $id = $DB->insert_record('format_minimoodlewall_tags', $record);

        // Invalidate all tags cache and cache the new tag.
        self::init_caches();
        self::$tagcache->delete('all_tags');

        // Fetch and cache the created tag to ensure consistency.
        $tag = $DB->get_record('format_minimoodlewall_tags', ['id' => $id]);
        if ($tag) {
            self::$tagcache->set('tag_' . $id, $tag);
        }

        return $id;
    }

    /**
     * Get a specific tag.
     *
     * @param int $id Tag ID
     * @return \stdClass|false Tag record or false
     */
    public static function get_tag(int $id): \stdClass|false {
        global $DB;
        self::init_caches();

        $cachekey = 'tag_' . $id;
        $tag = self::$tagcache->get($cachekey);

        if ($tag === false) {
            $tag = $DB->get_record('format_minimoodlewall_tags', ['id' => $id]);
            if ($tag) {
                self::$tagcache->set($cachekey, $tag);
            }
        }

        return $tag;
    }

    /**
     * Update a tag.
     *
     * @param int $id Tag ID
     * @param array $data Associative array of fields to update
     * @return bool Success
     */
    public static function update_tag(int $id, array $data): bool {
        global $DB;

        $record = new \stdClass();
        $record->id = $id;
        $record->timemodified = time();

        foreach ($data as $key => $value) {
            if ($key === 'bgcolor') {
                $value = self::normalize_hex_color($value);
            }
            $record->$key = $value;
        }

        $result = $DB->update_record('format_minimoodlewall_tags', $record);

        // Invalidate cache entries.
        self::init_caches();
        self::$tagcache->delete('tag_' . $id);
        self::$tagcache->delete('all_tags');

        return $result;
    }

    /**
     * Delete a tag and all its mappings.
     *
     * @param int $id Tag ID
     * @return bool Success
     */
    public static function delete_tag(int $id): bool {
        global $DB;

        // Delete all mappings for this tag.
        $DB->delete_records('format_minimoodlewall_cmtags', ['tagid' => $id]);

        // Delete profile_tags records for this tag.
        profile_manager::delete_profile_tags_for_tag($id);

        $result = $DB->delete_records('format_minimoodlewall_tags', ['id' => $id]);

        // Invalidate cache entries.
        self::init_caches();
        self::$tagcache->delete('tag_' . $id);
        self::$tagcache->delete('all_tags');
        self::clear_mapping_cache();

        return $result;
    }

    /**
     * Assign a tag to a course module.
     *
     * @param int $cmid Course module ID
     * @param int $tagid Tag ID
     * @return bool Success
     */
    public static function assign_tag_to_cm(int $cmid, int $tagid): bool {
        global $DB;

        // Check if mapping already exists.
        if ($DB->record_exists('format_minimoodlewall_cmtags', ['cmid' => $cmid])) {
            // Update existing mapping.
            $record = $DB->get_record('format_minimoodlewall_cmtags', ['cmid' => $cmid]);
            $record->tagid = $tagid;
            $result = $DB->update_record('format_minimoodlewall_cmtags', $record);
        } else {
            // Create new mapping.
            $record = new \stdClass();
            $record->cmid = $cmid;
            $record->tagid = $tagid;
            $record->timecreated = time();
            $result = $DB->insert_record('format_minimoodlewall_cmtags', $record);
            $result = !empty($result);
        }

        self::clear_mapping_cache();
        return $result;
    }

    /**
     * Unassign a tag from a course module.
     *
     * @param int $cmid Course module ID
     * @return bool Success
     */
    public static function unassign_tag_from_cm(int $cmid): bool {
        global $DB;

        $result = $DB->delete_records('format_minimoodlewall_cmtags', ['cmid' => $cmid]);
        self::clear_mapping_cache();

        return $result;
    }

    /**
     * Get the tag assigned to a course module.
     *
     * @param int $cmid Course module ID
     * @return \stdClass|false Tag record or false
     */
    public static function get_cm_tag(int $cmid): \stdClass|false {
        global $DB;
        self::init_caches();

        $cachekey = 'cm_' . $cmid;
        $tagid = self::$mappingcache->get($cachekey);

        if ($tagid === false) {
            $mapping = $DB->get_record('format_minimoodlewall_cmtags', ['cmid' => $cmid]);
            if ($mapping) {
                $tagid = $mapping->tagid;
                self::$mappingcache->set($cachekey, $tagid);
            } else {
                return false;
            }
        }

        return self::get_tag($tagid);
    }

    /**
     * Return the accent colour for a tag (stored colour first, palette fallback).
     *
     * @param \stdClass $tag Tag record
     * @return string
     */
    public static function get_tag_accent_color(\stdClass $tag): string {
        if (!empty($tag->bgcolor)) {
            return $tag->bgcolor;
        }

        $palette = self::STARTER_ACCENT_COLORS;
        if (empty($palette)) {
            return '#dcecff';
        }

        $indexsource = isset($tag->sortorder) ? (int)$tag->sortorder : (int)$tag->id;
        $index = $indexsource % count($palette);

        return $palette[$index];
    }

    /**
     * Remove tag assignment from a course module.
     *
     * @param int $cmid Course module ID
     * @return bool Success
     */
    public static function remove_cm_tag(int $cmid): bool {
        global $DB;

        $result = $DB->delete_records('format_minimoodlewall_cmtags', ['cmid' => $cmid]);
        self::clear_mapping_cache();

        return $result;
    }

    /**
     * Clear tag configuration cache.
     *
     * @return void
     */
    public static function clear_tag_cache(): void {
        self::init_caches();
        self::$tagcache->purge();
    }

    /**
     * Clear activity-tag mapping cache.
     *
     * @return void
     */
    public static function clear_mapping_cache(): void {
        self::init_caches();
        self::$mappingcache->purge();
    }

    /**
     * Initialize default tags for a new installation.
     *
     * @return bool Success
     */
    public static function initialize_default_tags(): bool {
        global $DB;

        // Check if any tags already exist.
        if ($DB->record_exists('format_minimoodlewall_tags', [])) {
            return true; // Already initialized.
        }

        // Create default tags.
        $defaulttags = [
            ['name' => get_string('tag_reading', 'format_minimoodlewall'),
                'cardimage' => 'reading.svg', 'filterimage' => 'reading.svg',
                'activitytype1' => 'assign', 'activitytype2' => 'page', 'activitytype3' => null,
                'bgcolor' => '#cfe5fa'],
            ['name' => get_string('tag_discover', 'format_minimoodlewall'),
                'cardimage' => 'discover.svg', 'filterimage' => 'discover.svg',
                'activitytype1' => 'assign', 'activitytype2' => 'page', 'activitytype3' => null,
                'bgcolor' => '#fde9c9'],
            ['name' => get_string('tag_writing', 'format_minimoodlewall'),
                'cardimage' => 'writing.svg', 'filterimage' => 'writing.svg',
                'activitytype1' => 'assign', 'activitytype2' => 'page', 'activitytype3' => null,
                'bgcolor' => '#cfe5fa'],
            ['name' => get_string('tag_show', 'format_minimoodlewall'),
                'cardimage' => 'show.svg', 'filterimage' => 'show.svg',
                'activitytype1' => 'assign', 'activitytype2' => 'glossary', 'activitytype3' => null,
                'bgcolor' => '#eadaf8'],
            ['name' => get_string('tag_practice', 'format_minimoodlewall'),
                'cardimage' => 'practice.svg', 'filterimage' => 'practice.svg',
                'activitytype1' => 'h5pactivity', 'activitytype2' => 'quiz', 'activitytype3' => null,
                'bgcolor' => '#fff3b0'],
            ['name' => get_string('tag_teamwork', 'format_minimoodlewall'),
                'cardimage' => 'teamwork.svg', 'filterimage' => 'teamwork.svg',
                'activitytype1' => 'forum', 'activitytype2' => 'page', 'activitytype3' => null,
                'bgcolor' => '#fff3b0'],
            ['name' => get_string('tag_watch', 'format_minimoodlewall'),
                'cardimage' => 'watch.svg', 'filterimage' => 'watch.svg',
                'activitytype1' => 'assign', 'activitytype2' => 'page', 'activitytype3' => null,
                'bgcolor' => '#d3f2c2'],
            ['name' => get_string('tag_calculations', 'format_minimoodlewall'),
                'cardimage' => 'calculations.svg', 'filterimage' => 'calculations.svg',
                'activitytype1' => 'h5pactivity', 'activitytype2' => 'assign', 'activitytype3' => 'quiz',
                'bgcolor' => '#cfe5fa'],
            ['name' => get_string('tag_listen', 'format_minimoodlewall'),
                'cardimage' => 'listen.svg', 'filterimage' => 'listen.svg',
                'activitytype1' => 'assign', 'activitytype2' => 'page', 'activitytype3' => null,
                'bgcolor' => '#f0f1f0'],
        ];

        $index = 0;

        foreach ($defaulttags as $tag) {
            $tagid = self::create_tag(
                $tag['name'],
                $tag['cardimage'],
                $tag['filterimage'],
                $tag['activitytype1'],
                $tag['activitytype2'],
                $tag['activitytype3'],
                $tag['bgcolor'],
                'lower'
            );

            $index++;

            self::copy_default_image($tagid, $tag['cardimage'], self::FILEAREA_CARDIMAGE);
            self::copy_default_image($tagid, $tag['filterimage'], self::FILEAREA_FILTERIMAGE);
        }

        return true;
    }
}
