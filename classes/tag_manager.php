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
 * Tag manager for format_mimo.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo;

use context_system;
use core_component;
use moodle_url;


/**
 * Tag manager class for handling tag sets and tags.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
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
    public static function normalize_hex_color(?string $color): ?string {
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
            \core\context\system::instance()->id,
            'format_mimo',
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
            \core\context\system::instance()->id,
            'format_mimo',
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
            \core\context\system::instance()->id,
            'format_mimo',
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
            \core\context\system::instance()->id,
            'format_mimo',
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
     * @param int|null $sectionid Optional section id (course_sections.id) to scope counts to a specific section
     * @return array tagid => usage count
     */
    public static function get_tag_usage_counts(int $courseid, array $tagids, ?int $sectionid = null): array {
        global $DB;

        if (empty($tagids)) {
            return [];
        }

        $tagids = array_map('intval', $tagids);
        [$insql, $params] = $DB->get_in_or_equal($tagids, SQL_PARAMS_NAMED);
        $params['courseid'] = $courseid;

        $sectionwhere = '';
        if ($sectionid !== null) {
            $sectionwhere = ' AND cm.section = :sectionid';
            $params['sectionid'] = $sectionid;
        }

        $sql = "SELECT cmt.tagid, COUNT(1) AS usecount
                  FROM {format_mimo_cmtags} cmt
                  JOIN {course_modules} cm ON cm.id = cmt.cmid
                 WHERE cm.course = :courseid AND cmt.tagid $insql{$sectionwhere}
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

        $context = \core\context\system::instance();
        $fs = get_file_storage();
        if ($fs->file_exists($context->id, 'format_mimo', $filearea, $tagid, '/', $filename)) {
            return;
        }

        $componentdir = core_component::get_component_directory('format_mimo');
        $source = $componentdir . '/pix/tags/' . $filename;
        if (!file_exists($source)) {
            return;
        }

        $filerecord = [
            'contextid' => $context->id,
            'component' => 'format_mimo',
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
        'accepted_types' => ['.svg', 'image/svg+xml', '.png', 'image/png'],
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
            self::$tagcache = \cache::make('format_mimo', 'tagconfigurations');
        }
        if (self::$mappingcache === null) {
            self::$mappingcache = \cache::make('format_mimo', 'activitytagmappings');
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
            $tags = $DB->get_records('format_mimo_tags', null, 'sortorder ASC, id ASC');
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

        // Get all global base tags.
        $alltags = self::get_all_tags();

        // Merge in imported tags bound to this course.
        $importedtags = self::get_imported_tags_for_course($courseid);
        foreach ($importedtags as $id => $tag) {
            if (!isset($alltags[$id])) {
                $alltags[$id] = $tag;
            }
        }

        if (empty($alltags)) {
            self::$tagcache->set($cachekey, []);
            return [];
        }

        // Get the course's activity profile.
        $profilename = $DB->get_field('course_format_options', 'value', [
            'courseid' => $courseid,
            'format' => 'mimo',
            'name' => 'activityprofile',
        ]);
        if (empty($profilename)) {
            $profilename = 'explore';
        }

        // Resolve profile ID.
        $profile = profile_manager::get_profile_by_name($profilename);
        if (!$profile) {
            // Fallback to explore if profile doesn't exist.
            $profile = profile_manager::get_profile_by_name('explore');
        }

        if (!$profile) {
            // No profiles at all — return all tags unfiltered.
            self::$tagcache->set($cachekey, $alltags);
            return $alltags;
        }

        // Return only enabled tags with profile overrides applied.
        $tags = profile_manager::resolve_tags_for_profile($alltags, $profile->id, true);

        // Pre-compute image URLs so they are cached in the MUC payload,
        // avoiding repeated get_area_files() calls on every page load.
        foreach ($tags as $tag) {
            $cardurl = self::get_cardimage_url($tag, $profilename);
            $tag->cached_cardimage_url = $cardurl ? $cardurl->out(false) : null;
            $filterurl = self::get_filterimage_url($tag, $profilename);
            $tag->cached_filterimage_url = $filterurl ? $filterurl->out(false) : null;
        }

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
     * @param string|null $imgsize Image size (bigger, normal, or smaller)
     * @param string $scope Tag scope: 'global' or 'imported'
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
        ?string $imgplacement = 'center',
        ?string $imgsize = 'normal',
        string $scope = 'global'
    ): int {
        global $DB;

        // Get next sort order globally.
        $maxsort = $DB->get_field_sql(
            "SELECT MAX(sortorder) FROM {format_mimo_tags}"
        );
        $sortorder = ($maxsort !== null && $maxsort !== false) ? (int)$maxsort + 1 : 0;

        $record = new \stdClass();
        $record->name = $name;
        $record->scope = $scope;
        $record->cardimage = $cardimage;
        $record->filterimage = $filterimage;
        $record->activitytype1 = $activitytype1;
        $record->activitytype2 = $activitytype2;
        $record->activitytype3 = $activitytype3;
        $record->bgcolor = self::normalize_hex_color($bgcolor);
        $record->imgplacement = $imgplacement ?? 'center';
        $record->imgsize = $imgsize ?? 'normal';
        $record->sortorder = $sortorder;
        $now = \core\di::get(\core\clock::class)->time();
        $record->timecreated = $now;
        $record->timemodified = $now;

        $id = $DB->insert_record('format_mimo_tags', $record);

        // Invalidate all tags cache and cache the new tag.
        self::init_caches();
        self::$tagcache->delete('all_tags');

        // Fetch and cache the created tag to ensure consistency.
        $tag = $DB->get_record('format_mimo_tags', ['id' => $id]);
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
            $tag = $DB->get_record('format_mimo_tags', ['id' => $id]);
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
        $record->timemodified = \core\di::get(\core\clock::class)->time();

        foreach ($data as $key => $value) {
            if ($key === 'bgcolor') {
                $value = self::normalize_hex_color($value);
            }
            $record->$key = $value;
        }

        $result = $DB->update_record('format_mimo_tags', $record);

        // Purge entire tag cache — course_tags_* entries contain resolved tag data
        // (including bgcolor) that becomes stale when any base tag field changes.
        self::clear_tag_cache();

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
        $DB->delete_records('format_mimo_cmtags', ['tagid' => $id]);

        // Delete course_tags bindings for this tag.
        $DB->delete_records('format_mimo_course_tags', ['tagid' => $id]);

        // Delete profile_tags records for this tag (includes profile-specific images).
        profile_manager::delete_profile_tags_for_tag($id);

        // Delete base tag image files.
        $fs = get_file_storage();
        $contextid = \core\context\system::instance()->id;
        $fs->delete_area_files($contextid, 'format_mimo', self::FILEAREA_CARDIMAGE, $id);
        $fs->delete_area_files($contextid, 'format_mimo', self::FILEAREA_FILTERIMAGE, $id);

        $result = $DB->delete_records('format_mimo_tags', ['id' => $id]);

        // Purge entire tag cache — course_tags_* entries reference this tag.
        self::clear_tag_cache();
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
        if ($DB->record_exists('format_mimo_cmtags', ['cmid' => $cmid])) {
            // Update existing mapping.
            $record = $DB->get_record('format_mimo_cmtags', ['cmid' => $cmid]);
            $record->tagid = $tagid;
            $result = $DB->update_record('format_mimo_cmtags', $record);
        } else {
            // Create new mapping.
            $record = new \stdClass();
            $record->cmid = $cmid;
            $record->tagid = $tagid;
            $record->timecreated = \core\di::get(\core\clock::class)->time();
            $result = $DB->insert_record('format_mimo_cmtags', $record);
            $result = !empty($result);
        }

        self::clear_mapping_cache();
        return $result;
    }

    /**
     * Unassign a tag from a course module.
     *
     * @deprecated Use remove_cm_tag() instead.
     * @param int $cmid Course module ID
     * @return bool Success
     */
    public static function unassign_tag_from_cm(int $cmid): bool {
        return self::remove_cm_tag($cmid);
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
            $mapping = $DB->get_record('format_mimo_cmtags', ['cmid' => $cmid]);
            if ($mapping) {
                $tagid = $mapping->tagid;
                self::$mappingcache->set($cachekey, $tagid);
            } else {
                // Cache a sentinel value so we don't hit the DB again for untagged CMs.
                self::$mappingcache->set($cachekey, 0);
                return false;
            }
        }

        // Sentinel value 0 means "no tag assigned".
        if ($tagid === 0) {
            return false;
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

        $result = $DB->delete_records('format_mimo_cmtags', ['cmid' => $cmid]);
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
     * Reset static cache references so they are re-created on next use.
     *
     * This is needed for PHPUnit tests where \cache_factory::reset() invalidates
     * existing cache instances between tests. Without this, the stale static
     * references cause silent cache misses or corrupt reads.
     *
     * @return void
     */
    public static function reset_caches(): void {
        self::$tagcache = null;
        self::$mappingcache = null;
    }

    /**
     * Bind an imported tag to a course (make it available in that course).
     *
     * @param int $tagid Tag ID
     * @param int $courseid Course ID
     */
    public static function bind_tag_to_course(int $tagid, int $courseid): void {
        global $DB;

        if ($DB->record_exists('format_mimo_course_tags', ['tagid' => $tagid, 'courseid' => $courseid])) {
            return;
        }

        $record = new \stdClass();
        $record->courseid = $courseid;
        $record->tagid = $tagid;
        $record->timecreated = \core\di::get(\core\clock::class)->time();
        $DB->insert_record('format_mimo_course_tags', $record);

        self::clear_course_tags_cache($courseid);
    }

    /**
     * Unbind an imported tag from a course.
     *
     * @param int $tagid Tag ID
     * @param int $courseid Course ID
     */
    public static function unbind_tag_from_course(int $tagid, int $courseid): void {
        global $DB;

        $DB->delete_records('format_mimo_course_tags', ['tagid' => $tagid, 'courseid' => $courseid]);
        self::clear_course_tags_cache($courseid);
    }

    /**
     * Promote an imported tag to global scope.
     *
     * Removes all course_tags bindings and changes scope to 'global'.
     *
     * @param int $tagid Tag ID
     */
    public static function promote_tag_to_global(int $tagid): void {
        global $DB;

        $DB->set_field('format_mimo_tags', 'scope', 'global', ['id' => $tagid]);
        $DB->delete_records('format_mimo_course_tags', ['tagid' => $tagid]);
        self::clear_tag_cache();
    }

    /**
     * Get imported tags bound to a specific course.
     *
     * @param int $courseid Course ID
     * @return array Array of tag records keyed by tag ID
     */
    public static function get_imported_tags_for_course(int $courseid): array {
        global $DB;

        $sql = "SELECT t.*
                  FROM {format_mimo_tags} t
                  JOIN {format_mimo_course_tags} ct ON ct.tagid = t.id
                 WHERE ct.courseid = :courseid AND t.scope = :scope
              ORDER BY t.sortorder ASC, t.id ASC";
        return $DB->get_records_sql($sql, ['courseid' => $courseid, 'scope' => 'imported']);
    }

    /**
     * Delete all course_tags bindings for a given course.
     *
     * @param int $courseid Course ID
     */
    public static function unbind_all_tags_from_course(int $courseid): void {
        global $DB;

        $DB->delete_records('format_mimo_course_tags', ['courseid' => $courseid]);
        self::clear_course_tags_cache($courseid);
    }

    /**
     * Clean up orphaned imported tags that have no course bindings and no cmtag references.
     */
    public static function cleanup_orphaned_imported_tags(): void {
        global $DB;

        $sql = "SELECT t.id
                  FROM {format_mimo_tags} t
                 WHERE t.scope = :scope
                   AND NOT EXISTS (
                       SELECT 1 FROM {format_mimo_course_tags} ct WHERE ct.tagid = t.id
                   )
                   AND NOT EXISTS (
                       SELECT 1 FROM {format_mimo_cmtags} cmt WHERE cmt.tagid = t.id
                   )";
        $orphans = $DB->get_fieldset_sql($sql, ['scope' => 'imported']);

        foreach ($orphans as $tagid) {
            self::delete_tag($tagid);
        }
    }

    /**
     * Find an existing tag that matches a composite fingerprint.
     *
     * Matches on name + bgcolor + activitytype1 + activitytype2 + activitytype3 (NULL-safe).
     * Optionally excludes already-matched tag IDs.
     *
     * @param object $data Tag data to match against (name, bgcolor, activitytype1-3)
     * @param array $excludeids Tag IDs to exclude from matching
     * @return \stdClass|null Matching tag record or null
     */
    public static function find_tag_by_fingerprint(object $data, array $excludeids = []): ?\stdClass {
        global $DB;

        $params = [];
        $conditions = ['name = :name'];
        $params['name'] = $data->name;

        // NULL-safe comparisons for each fingerprint field.
        foreach (['bgcolor', 'activitytype1', 'activitytype2', 'activitytype3'] as $field) {
            $value = $data->$field ?? null;
            if ($value === null || $value === '') {
                $conditions[] = "($field IS NULL OR $field = '')";
            } else {
                $conditions[] = "$field = :$field";
                $params[$field] = $value;
            }
        }

        if (!empty($excludeids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($excludeids, SQL_PARAMS_NAMED, 'excl', false);
            $conditions[] = "id $insql";
            $params = array_merge($params, $inparams);
        }

        $where = implode(' AND ', $conditions);
        $sql = "SELECT * FROM {format_mimo_tags} WHERE $where ORDER BY sortorder ASC";
        $records = $DB->get_records_sql($sql, $params, 0, 1);

        return $records ? reset($records) : null;
    }

    /**
     * Find an existing tag by name only (case-sensitive).
     *
     * Used as a lenient fallback when fingerprint matching fails — e.g. an admin
     * changed the color or activity types after the backup was made.
     *
     * @param string $name Tag name to search for
     * @param array $excludeids Tag IDs to exclude from matching
     * @return \stdClass|null Matching tag record or null
     */
    public static function find_tag_by_name(string $name, array $excludeids = []): ?\stdClass {
        global $DB;

        $params = ['name' => $name];
        $conditions = ['name = :name'];

        if (!empty($excludeids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($excludeids, SQL_PARAMS_NAMED, 'excl', false);
            $conditions[] = "id $insql";
            $params = array_merge($params, $inparams);
        }

        $where = implode(' AND ', $conditions);
        $sql = "SELECT * FROM {format_mimo_tags} WHERE $where ORDER BY sortorder ASC";
        $records = $DB->get_records_sql($sql, $params, 0, 1);

        return $records ? reset($records) : null;
    }

    /**
     * Initialize default tags for a new installation.
     *
     * @return bool Success
     */
    public static function initialize_default_tags(): bool {
        global $DB;

        // Check if any tags already exist.
        if ($DB->record_exists('format_mimo_tags', [])) {
            return true; // Already initialized.
        }

        // Create default tags.
        $defaulttags = [
            ['name' => get_string('tag_reading', 'format_mimo'),
                'cardimage' => 'read_base.svg', 'filterimage' => 'read_base.svg',
                'activitytype1' => 'page', 'activitytype2' => 'forum', 'activitytype3' => null,
                'bgcolor' => '#7fc3d8'],
            ['name' => get_string('tag_discover', 'format_mimo'),
                'cardimage' => 'explore_base.svg', 'filterimage' => 'explore_base.svg',
                'activitytype1' => 'page', 'activitytype2' => 'forum', 'activitytype3' => 'glossary',
                'bgcolor' => '#facc15'],
            ['name' => get_string('tag_writing', 'format_mimo'),
                'cardimage' => 'write_base.svg', 'filterimage' => 'write_base.svg',
                'activitytype1' => 'forum', 'activitytype2' => 'assign', 'activitytype3' => null,
                'bgcolor' => '#de5a72'],
            ['name' => get_string('tag_show', 'format_mimo'),
                'cardimage' => 'share_base.svg', 'filterimage' => 'share_base.svg',
                'activitytype1' => 'forum', 'activitytype2' => 'assign', 'activitytype3' => null,
                'bgcolor' => '#de5a72'],
            ['name' => get_string('tag_practice_base', 'format_mimo'),
                'cardimage' => 'practice_base.svg', 'filterimage' => 'practice_base.svg',
                'activitytype1' => 'assign', 'activitytype2' => 'quiz', 'activitytype3' => null,
                'bgcolor' => '#8ccb90'],
            ['name' => get_string('tag_teamwork', 'format_mimo'),
                'cardimage' => 'collaborate_base.svg', 'filterimage' => 'collaborate_base.svg',
                'activitytype1' => 'forum', 'activitytype2' => 'assign', 'activitytype3' => null,
                'bgcolor' => '#de5a72'],
            ['name' => get_string('tag_create', 'format_mimo'),
                'cardimage' => 'create_base.png', 'filterimage' => 'create_base.png',
                'activitytype1' => 'forum', 'activitytype2' => 'assign', 'activitytype3' => null,
                'bgcolor' => '#de5a72'],
        ];

        foreach ($defaulttags as $tag) {
            $tagid = self::create_tag(
                $tag['name'],
                $tag['cardimage'],
                $tag['filterimage'],
                $tag['activitytype1'],
                $tag['activitytype2'],
                $tag['activitytype3'],
                $tag['bgcolor'],
                'center'
            );

            self::copy_default_image($tagid, $tag['cardimage'], self::FILEAREA_CARDIMAGE);
            self::copy_default_image($tagid, $tag['filterimage'], self::FILEAREA_FILTERIMAGE);
        }

        return true;
    }
}
