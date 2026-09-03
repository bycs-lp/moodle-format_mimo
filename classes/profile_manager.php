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
 * Activity profile manager for format_mimo.
 *
 * Manages activity profiles (formerly called styles) and per-profile
 * tag overrides (name, bgcolor, activity types, enabled flag, images).
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo;

use moodle_url;
use stdClass;


/**
 * Activity profile manager class for handling activity profiles.
 *
 * An activity profile controls the visual appearance and behaviour of
 * the mimo course format. Per-profile overrides allow each
 * profile to show different tag names, colours, activity types, images
 * and an enabled/disabled flag for each tag.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_manager {
    /** File area for profile-specific card images. */
    public const FILEAREA_PROFILE_CARDIMAGE = 'profiletagcard';

    /** File area for profile-specific filter images. */
    public const FILEAREA_PROFILE_FILTERIMAGE = 'profiletagfilter';

    /** @var array Request-level cache for profiles keyed by name. */
    private array $profilebynamecache = [];

    /** @var array Request-level cache for profile_tags keyed by "profileid". Maps tagid => record. */
    private array $profiletagscache = [];

    /** @var array Request-level cache for resolved image URLs keyed by "tagid_profilename_filearea". */
    private array $imageurlcache = [];

    /**
     * Constructor with DI-injected dependencies.
     *
     * Obtain the shared instance via \core\di::get(profile_manager::class).
     *
     * @param \core\clock $clock Clock instance.
     */
    public function __construct(
        /** @var \core\clock Clock instance. */
        private readonly \core\clock $clock,
    ) {
    }

    /**
     * Clear all request-level caches.
     *
     * Called by write methods to prevent stale data within the same request.
     */
    public function clear_request_caches(): void {
        $this->profilebynamecache = [];
        $this->profiletagscache = [];
        $this->imageurlcache = [];
    }

    /** Filemanager options for image uploads. */
    private const FILEMANAGER_OPTIONS = [
        'maxbytes' => 1048576, // 1 MB.
        'maxfiles' => 1,
        'accepted_types' => ['.svg', '.png', '.jpg', '.jpeg', '.gif'],
        'subdirs' => 0,
    ];

    /* =============== *
     * Profile CRUD.  *
     * =============== */

    /**
     * Get all profiles ordered by sortorder.
     *
     * @return array Array of profile objects keyed by id
     */
    public function get_all_profiles(): array {
        global $DB;

        return $DB->get_records('format_mimo_profiles', null, 'sortorder ASC, id ASC');
    }

    /**
     * Get a single profile by ID.
     *
     * @param int $id Profile ID
     * @return stdClass|null
     */
    public function get_profile(int $id): ?stdClass {
        global $DB;

        $record = $DB->get_record('format_mimo_profiles', ['id' => $id]);
        return $record ?: null;
    }

    /**
     * Get a profile by its internal name.
     *
     * @param string $name Profile name (e.g., 'explore', 'develop', 'master')
     * @return stdClass|null
     */
    public function get_profile_by_name(string $name): ?stdClass {
        global $DB;

        if (isset($this->profilebynamecache[$name])) {
            return $this->profilebynamecache[$name];
        }

        $record = $DB->get_record('format_mimo_profiles', ['name' => $name]);
        $result = $record ?: null;
        $this->profilebynamecache[$name] = $result;
        return $result;
    }

    /**
     * Create a new profile.
     *
     * @param string $name Internal identifier
     * @param string $displayname Human-readable name
     * @param int|null $sortorder Sort order (auto-calculated if null)
     * @param string $scope Profile scope: 'global' or 'imported'
     * @return int The new profile ID
     */
    public function create_profile(
        string $name,
        string $displayname,
        ?int $sortorder = null,
        string $scope = 'global',
    ): int {
        global $DB;

        if ($sortorder === null) {
            $maxorder = $DB->get_field_sql(
                "SELECT MAX(sortorder) FROM {format_mimo_profiles}"
            );
            $sortorder = ($maxorder ?? 0) + 1;
        }

        $now = $this->clock->time();
        $record = new stdClass();
        $record->name = $name;
        $record->displayname = $displayname;
        $record->scope = $scope;
        $record->sortorder = $sortorder;
        $record->timecreated = $now;
        $record->timemodified = $now;

        $id = $DB->insert_record('format_mimo_profiles', $record);
        $this->profilebynamecache = [];
        return $id;
    }

    /**
     * Update an existing profile.
     *
     * @param int $id Profile ID
     * @param array $data Fields to update
     * @return bool
     */
    public function update_profile(int $id, array $data): bool {
        global $DB;

        // If the internal name is being changed, cascade to course_format_options.
        if (isset($data['name'])) {
            $oldprofile = $this->get_profile($id);
            if ($oldprofile && $oldprofile->name !== $data['name']) {
                // The value column is TEXT; direct equality breaks on Oracle/MSSQL.
                $valcompare = $DB->sql_compare_text('value', 255);
                $DB->set_field_select(
                    'course_format_options',
                    'value',
                    $data['name'],
                    "format = 'mimo' AND name = 'activityprofile' AND $valcompare = :oldname",
                    ['oldname' => $oldprofile->name]
                );
                // Clear tag cache for all affected courses.
                \core\di::get(tag_manager::class)->clear_tag_cache();
            }
        }

        $record = new stdClass();
        $record->id = $id;
        $record->timemodified = $this->clock->time();

        foreach ($data as $field => $value) {
            if (in_array($field, ['name', 'displayname', 'sortorder'])) {
                $record->$field = $value;
            }
        }

        $result = $DB->update_record('format_mimo_profiles', $record);
        $this->profilebynamecache = [];
        return $result;
    }

    /**
     * Delete a profile and all associated profile tag records + files.
     *
     * @param int $id Profile ID
     * @return bool
     */
    public function delete_profile(int $id): bool {
        global $DB;

        // Delete associated profile tag files.
        $profiletags = $DB->get_records('format_mimo_profile_tags', ['profileid' => $id]);
        foreach ($profiletags as $pt) {
            $this->delete_profile_tag_files($pt->id);
        }

        // Delete profile tag records.
        $DB->delete_records('format_mimo_profile_tags', ['profileid' => $id]);

        // Delete the profile.
        $result = $DB->delete_records('format_mimo_profiles', ['id' => $id]);

        // Courses referencing this profile have stale course_tags_* cache entries.
        \core\di::get(tag_manager::class)->clear_tag_cache();
        $this->clear_request_caches();

        return $result;
    }

    /**
     * Get profiles as options array for select elements.
     *
     * @return array name => displayname
     */
    public function get_profile_options(): array {
        $profiles = $this->get_all_profiles();
        $options = [];
        foreach ($profiles as $profile) {
            $options[$profile->name] = $profile->displayname;
        }
        return $options;
    }

    /* ================================== *
     * Profile-tag override management.  *
     * ================================== */

    /**
     * Get or create a profile_tags record for a tag/profile combination.
     *
     * New rows are fully materialized from anchor values with enabled=0.
     *
     * @param int $tagid Tag ID
     * @param int $profileid Profile ID
     * @return stdClass
     */
    public function get_or_create_profile_tag(int $tagid, int $profileid): stdClass {
        global $DB;

        $record = $DB->get_record('format_mimo_profile_tags', [
            'tagid' => $tagid,
            'profileid' => $profileid,
        ]);
        if ($record) {
            return $record;
        }
        return $this->materialize_profile_tag($tagid, $profileid);
    }

    /**
     * Upsert a fully materialized profile_tags row for a tag/profile pair.
     *
     * Field priority per override field: $values → existing non-NULL row value
     * → anchor tag value. Enabled: explicit $enabled when non-null, otherwise
     * the existing row's flag (0 for brand-new rows).
     *
     * @param int $tagid Tag ID
     * @param int $profileid Profile ID
     * @param array $values Optional explicit field values
     * @param bool|null $enabled Explicit enabled flag, or null to keep/default
     * @return stdClass The materialized profile_tags record
     */
    public function materialize_profile_tag(int $tagid, int $profileid, array $values = [], ?bool $enabled = null): stdClass {
        global $DB;

        $anchor = \core\di::get(tag_manager::class)->get_tag($tagid);
        if (!$anchor) {
            throw new \coding_exception('Cannot materialize profile tag for unknown tag ' . $tagid);
        }

        $existing = $DB->get_record('format_mimo_profile_tags', [
            'tagid' => $tagid,
            'profileid' => $profileid,
        ]);

        $fields = ['name', 'bgcolor', 'activitytype1', 'activitytype2', 'activitytype3', 'imgplacement', 'imgsize'];
        $now = $this->clock->time();

        $record = $existing ?: new stdClass();
        foreach ($fields as $field) {
            if (array_key_exists($field, $values)) {
                $record->$field = $values[$field];
            } else if (!isset($existing->$field)) {
                $record->$field = $anchor->$field ?? null;
            }
        }
        if ($record->bgcolor !== null) {
            $record->bgcolor = \core\di::get(tag_manager::class)->normalize_hex_color($record->bgcolor);
        }
        if ($enabled !== null) {
            $record->enabled = (int) $enabled;
        } else if (!$existing) {
            $record->enabled = 0;
        }
        $record->timemodified = $now;

        if ($existing) {
            $DB->update_record('format_mimo_profile_tags', $record);
        } else {
            $record->tagid = $tagid;
            $record->profileid = $profileid;
            $record->cardimage = null;
            $record->filterimage = null;
            $record->timecreated = $now;
            $record->id = $DB->insert_record('format_mimo_profile_tags', $record);
        }

        unset($this->profiletagscache[$profileid]);
        \core\di::get(tag_manager::class)->clear_tag_cache();

        return $DB->get_record('format_mimo_profile_tags', ['id' => $record->id], '*', MUST_EXIST);
    }

    /**
     * Get profile_tags record by ID.
     *
     * @param int $id Profile tags record ID
     * @return stdClass|null
     */
    public function get_profile_tag(int $id): ?stdClass {
        global $DB;

        $record = $DB->get_record('format_mimo_profile_tags', ['id' => $id]);
        return $record ?: null;
    }

    /**
     * Get all profile_tags records for a tag.
     *
     * @param int $tagid Tag ID
     * @return array Array of profile_tags objects keyed by id
     */
    public function get_profile_tags_for_tag(int $tagid): array {
        global $DB;

        return $DB->get_records('format_mimo_profile_tags', ['tagid' => $tagid]);
    }

    /**
     * Get profile_tags record for a specific tag and profile.
     *
     * @param int $tagid Tag ID
     * @param int $profileid Profile ID
     * @return stdClass|null
     */
    public function get_profile_tag_for_profile(int $tagid, int $profileid): ?stdClass {
        // Ensure the profile's tags are prefetched into the request-level cache.
        $this->prefetch_profile_tags($profileid);

        return $this->profiletagscache[$profileid][$tagid] ?? null;
    }

    /**
     * Prefetch all profile_tags for a profile into the request-level cache.
     *
     * Replaces per-tag DB queries with a single batch query per profile per request.
     *
     * @param int $profileid Profile ID
     */
    private function prefetch_profile_tags(int $profileid): void {
        global $DB;

        if (isset($this->profiletagscache[$profileid])) {
            return;
        }

        $records = $DB->get_records('format_mimo_profile_tags', ['profileid' => $profileid]);
        $this->profiletagscache[$profileid] = [];
        foreach ($records as $record) {
            $this->profiletagscache[$profileid][$record->tagid] = $record;
        }
    }

    /**
     * Update override fields on a profile_tags record.
     *
     * Allowed override fields: name, bgcolor, activitytype1, activitytype2,
     * activitytype3, enabled, imgplacement, imgsize.  NULL values mean "inherit from base tag".
     *
     * @param int $id Profile tags record ID
     * @param array $data Associative array of field => value
     * @return bool
     */
    public function update_profile_tag(int $id, array $data): bool {
        global $DB;

        $allowed = ['name', 'bgcolor', 'activitytype1', 'activitytype2', 'activitytype3', 'enabled', 'imgplacement', 'imgsize'];
        $record = new stdClass();
        $record->id = $id;
        $record->timemodified = $this->clock->time();

        foreach ($data as $field => $value) {
            if (in_array($field, $allowed)) {
                if ($field === 'bgcolor' && $value !== null) {
                    $value = \core\di::get(tag_manager::class)->normalize_hex_color($value);
                }
                $record->$field = $value;
            }
        }

        $result = $DB->update_record('format_mimo_profile_tags', $record);

        // Profile overrides (name, bgcolor, activity types, etc.) are baked into the
        // resolved course_tags_* cache entries.  Purge the tag cache so every course
        // picks up the new override values on next request.
        \core\di::get(tag_manager::class)->clear_tag_cache();
        $this->profiletagscache = [];
        $this->imageurlcache = [];

        return $result;
    }

    /**
     * Delete all profile_tags records and files for a given tag.
     *
     * Called when a tag is deleted to clean up associated profile overrides.
     *
     * @param int $tagid Tag ID
     */
    public function delete_profile_tags_for_tag(int $tagid): void {
        global $DB;

        $profiletags = $DB->get_records('format_mimo_profile_tags', ['tagid' => $tagid]);
        foreach ($profiletags as $pt) {
            $this->delete_profile_tag_files($pt->id);
        }

        $DB->delete_records('format_mimo_profile_tags', ['tagid' => $tagid]);
        $this->profiletagscache = [];
        $this->imageurlcache = [];
    }

    /* ======================================== *
     * Tag resolution with profile overrides.  *
     * ======================================== */

    /**
     * Resolve a tag record with profile-specific overrides applied.
     *
     * For each nullable override field (name, bgcolor, activitytype1-3, imgplacement, imgsize),
     * a non-NULL value in the profile_tags record replaces the base tag value.
     * The enabled flag is always taken from the profile_tags record; a missing
     * profile_tags row resolves as disabled (anchor values are still returned
     * for display purposes).
     *
     * @param stdClass $tag Base tag record
     * @param int $profileid Profile ID
     * @return stdClass Merged tag with overrides applied and 'enabled' flag added
     */
    public function resolve_tag_for_profile(stdClass $tag, int $profileid): stdClass {
        $resolved = clone $tag;

        $pt = $this->get_profile_tag_for_profile($tag->id, $profileid);
        if (!$pt) {
            // No materialized row: the tag is not configured for this set.
            $resolved->enabled = 0;
            return $resolved;
        }

        // Apply non-NULL overrides.
        foreach (['name', 'bgcolor', 'activitytype1', 'activitytype2', 'activitytype3', 'imgplacement', 'imgsize'] as $field) {
            if (property_exists($pt, $field) && $pt->$field !== null) {
                $resolved->$field = $pt->$field;
            }
        }

        $resolved->enabled = (int) $pt->enabled;

        return $resolved;
    }

    /**
     * Resolve all tags for a given profile, returning only enabled ones.
     *
     * @param array $tags Array of base tag records
     * @param int $profileid Profile ID
     * @param bool $onlyenabled If true, exclude disabled tags
     * @return array Resolved tag records
     */
    public function resolve_tags_for_profile(array $tags, int $profileid, bool $onlyenabled = true): array {
        $resolved = [];
        foreach ($tags as $tag) {
            $r = $this->resolve_tag_for_profile($tag, $profileid);
            if (!$onlyenabled || $r->enabled) {
                $resolved[$r->id] = $r;
            }
        }
        return $resolved;
    }

    /* =============================================== *
     * Image management (draft areas, saving, URLs).  *
     * =============================================== */

    /**
     * Retrieve the shared filemanager options for profile image uploads.
     *
     * @return array
     */
    public function get_image_filemanager_options(): array {
        return self::FILEMANAGER_OPTIONS;
    }

    /**
     * Prepare a draft area for the card image filemanager field.
     *
     * @param int $tagid Tag ID
     * @param int $profileid Profile ID
     * @return int Draft item id
     */
    public function prepare_cardimage_draft(int $tagid, int $profileid): int {
        $profiletag = $this->get_profile_tag_for_profile($tagid, $profileid);
        $itemid = $profiletag ? $profiletag->id : 0;

        $draftitemid = file_get_submitted_draft_itemid("cardimage_profile_{$profileid}");
        file_prepare_draft_area(
            $draftitemid,
            \core\context\system::instance()->id,
            'format_mimo',
            self::FILEAREA_PROFILE_CARDIMAGE,
            $itemid,
            $this->get_image_filemanager_options()
        );

        return $draftitemid;
    }

    /**
     * Prepare a draft area for the filter image filemanager field.
     *
     * @param int $tagid Tag ID
     * @param int $profileid Profile ID
     * @return int Draft item id
     */
    public function prepare_filterimage_draft(int $tagid, int $profileid): int {
        $profiletag = $this->get_profile_tag_for_profile($tagid, $profileid);
        $itemid = $profiletag ? $profiletag->id : 0;

        $draftitemid = file_get_submitted_draft_itemid("filterimage_profile_{$profileid}");
        file_prepare_draft_area(
            $draftitemid,
            \core\context\system::instance()->id,
            'format_mimo',
            self::FILEAREA_PROFILE_FILTERIMAGE,
            $itemid,
            $this->get_image_filemanager_options()
        );

        return $draftitemid;
    }

    /**
     * Save card image from draft area.
     *
     * @param int $tagid Tag ID
     * @param int $profileid Profile ID
     * @param int $draftitemid Draft area ID
     */
    public function save_cardimage_from_draft(int $tagid, int $profileid, int $draftitemid): void {
        $this->save_image_from_draft($tagid, $profileid, $draftitemid, self::FILEAREA_PROFILE_CARDIMAGE, 'cardimage');
    }

    /**
     * Save filter image from draft area.
     *
     * @param int $tagid Tag ID
     * @param int $profileid Profile ID
     * @param int $draftitemid Draft area ID
     */
    public function save_filterimage_from_draft(int $tagid, int $profileid, int $draftitemid): void {
        $this->save_image_from_draft($tagid, $profileid, $draftitemid, self::FILEAREA_PROFILE_FILTERIMAGE, 'filterimage');
    }

    /**
     * Shared helper to move files from a draft area into storage.
     *
     * @param int $tagid Tag ID
     * @param int $profileid Profile ID
     * @param int $draftitemid Draft area ID
     * @param string $filearea File area
     * @param string $dbfield Database field to update
     */
    private function save_image_from_draft(
        int $tagid,
        int $profileid,
        int $draftitemid,
        string $filearea,
        string $dbfield
    ): void {
        global $DB;

        // Ensure profile_tags record exists.
        $profiletag = $this->get_or_create_profile_tag($tagid, $profileid);

        file_save_draft_area_files(
            $draftitemid,
            \core\context\system::instance()->id,
            'format_mimo',
            $filearea,
            $profiletag->id,
            $this->get_image_filemanager_options()
        );

        // Update filename in database.
        $file = $this->get_image_file($profiletag->id, $filearea);
        $filename = $file ? $file->get_filename() : null;

        $DB->set_field('format_mimo_profile_tags', $dbfield, $filename, ['id' => $profiletag->id]);
        $DB->set_field('format_mimo_profile_tags', 'timemodified', $this->clock->time(), ['id' => $profiletag->id]);

        // Image URLs are baked into the course_tags_* MUC cache.
        \core\di::get(tag_manager::class)->clear_tag_cache();
        $this->imageurlcache = [];
    }

    /**
     * Get card image URL for a tag and profile.
     *
     * @param int $tagid Tag ID
     * @param int $profileid Profile ID
     * @return moodle_url|null
     */
    public function get_cardimage_url(int $tagid, int $profileid): ?moodle_url {
        $profiletag = $this->get_profile_tag_for_profile($tagid, $profileid);
        if (!$profiletag) {
            return null;
        }
        return $this->get_image_url($profiletag->id, self::FILEAREA_PROFILE_CARDIMAGE);
    }

    /**
     * Get filter image URL for a tag and profile.
     *
     * @param int $tagid Tag ID
     * @param int $profileid Profile ID
     * @return moodle_url|null
     */
    public function get_filterimage_url(int $tagid, int $profileid): ?moodle_url {
        $profiletag = $this->get_profile_tag_for_profile($tagid, $profileid);
        if (!$profiletag) {
            return null;
        }
        return $this->get_image_url($profiletag->id, self::FILEAREA_PROFILE_FILTERIMAGE);
    }

    /**
     * Get card image URL for a tag and profile name.
     *
     * @param int $tagid Tag ID
     * @param string $profilename Profile name (e.g., 'explore')
     * @return moodle_url|null
     */
    public function get_cardimage_url_by_name(int $tagid, string $profilename): ?moodle_url {
        $cachekey = $tagid . '_' . $profilename . '_card';
        if (array_key_exists($cachekey, $this->imageurlcache)) {
            return $this->imageurlcache[$cachekey];
        }

        $profile = $this->get_profile_by_name($profilename);
        if (!$profile) {
            $this->imageurlcache[$cachekey] = null;
            return null;
        }
        $url = $this->get_cardimage_url($tagid, $profile->id);
        $this->imageurlcache[$cachekey] = $url;
        return $url;
    }

    /**
     * Get filter image URL for a tag and profile name.
     *
     * @param int $tagid Tag ID
     * @param string $profilename Profile name (e.g., 'explore')
     * @return moodle_url|null
     */
    public function get_filterimage_url_by_name(int $tagid, string $profilename): ?moodle_url {
        $cachekey = $tagid . '_' . $profilename . '_filter';
        if (array_key_exists($cachekey, $this->imageurlcache)) {
            return $this->imageurlcache[$cachekey];
        }

        $profile = $this->get_profile_by_name($profilename);
        if (!$profile) {
            $this->imageurlcache[$cachekey] = null;
            return null;
        }
        $url = $this->get_filterimage_url($tagid, $profile->id);
        $this->imageurlcache[$cachekey] = $url;
        return $url;
    }

    /* ======================= *
     * Private file helpers.  *
     * ======================= */

    /**
     * Resolve the pluginfile URL for a stored file.
     *
     * @param int $profiletagid Profile tags record ID
     * @param string $filearea File area
     * @return moodle_url|null
     */
    private function get_image_url(int $profiletagid, string $filearea): ?moodle_url {
        $file = $this->get_image_file($profiletagid, $filearea);
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
     * Fetch the stored file object.
     *
     * @param int $profiletagid Profile tags record ID (itemid)
     * @param string $filearea File area
     * @return \stored_file|null
     */
    private function get_image_file(int $profiletagid, string $filearea): ?\stored_file {
        $files = get_file_storage()->get_area_files(
            \core\context\system::instance()->id,
            'format_mimo',
            $filearea,
            $profiletagid,
            '',
            false
        );

        if (empty($files)) {
            return null;
        }

        return reset($files);
    }

    /**
     * Delete all image files for a profile_tags record.
     *
     * @param int $profiletagid Profile tags record ID
     */
    private function delete_profile_tag_files(int $profiletagid): void {
        $fs = get_file_storage();
        $contextid = \core\context\system::instance()->id;

        $fs->delete_area_files($contextid, 'format_mimo', self::FILEAREA_PROFILE_CARDIMAGE, $profiletagid);
        $fs->delete_area_files($contextid, 'format_mimo', self::FILEAREA_PROFILE_FILTERIMAGE, $profiletagid);
    }

    /**
     * Copy a bundled pix/tags asset into a profile-specific file area.
     *
     * Used during installation to seed profile tag overrides with default images.
     * Mirrors {@see tag_manager::copy_default_image()} but targets profile file areas.
     *
     * @param int $profiletagid Profile tags record ID (used as itemid)
     * @param string|null $filename Bundled filename inside pix/tags/
     * @param string $filearea Destination file area constant
     */
    private function copy_default_profile_image(int $profiletagid, ?string $filename, string $filearea): void {
        if (empty($filename)) {
            return;
        }

        $context = \core\context\system::instance();
        $fs = get_file_storage();
        if ($fs->file_exists($context->id, 'format_mimo', $filearea, $profiletagid, '/', $filename)) {
            return;
        }

        $componentdir = \core_component::get_component_directory('format_mimo');
        $source = $componentdir . '/pix/tags/' . $filename;
        if (!file_exists($source)) {
            return;
        }

        $filerecord = [
            'contextid' => $context->id,
            'component' => 'format_mimo',
            'filearea' => $filearea,
            'itemid' => $profiletagid,
            'filepath' => '/',
            'filename' => $filename,
        ];
        $fs->create_file_from_pathname($filerecord, $source);
    }

    /* ============================== *
     * Imported profile management.  *
     * ============================== */

    /**
     * Create a new imported profile for a restored course.
     *
     * Generates a unique name slug and a display name containing the course name.
     *
     * @param string $coursename Course full name
     * @return stdClass The created profile record (with id, name, displayname, scope)
     */
    public function create_imported_profile(string $coursename): stdClass {
        global $DB;

        // Sanitize name to create a slug: lowercase, alphanum + underscore, max 40 chars.
        $slug = preg_replace('/[^a-z0-9_]/', '_', strtolower(trim($coursename)));
        $slug = preg_replace('/_+/', '_', $slug);
        $slug = substr($slug, 0, 30);
        $basename = 'imported_' . $slug;

        // Ensure uniqueness by appending a counter if needed.
        $name = $basename;
        $counter = 1;
        while ($DB->record_exists('format_mimo_profiles', ['name' => $name])) {
            $name = $basename . '_' . $counter;
            $counter++;
        }

        $displayname = get_string('imported_profile_name', 'format_mimo', $coursename);

        $maxorder = $DB->get_field_sql(
            "SELECT MAX(sortorder) FROM {format_mimo_profiles}"
        );
        $sortorder = ($maxorder ?? 0) + 1;

        $now = $this->clock->time();
        $record = new stdClass();
        $record->name = $name;
        $record->displayname = $displayname;
        $record->scope = 'imported';
        $record->sortorder = $sortorder;
        $record->timecreated = $now;
        $record->timemodified = $now;
        $record->id = $DB->insert_record('format_mimo_profiles', $record);

        return $record;
    }

    /**
     * Promote an imported profile to global scope.
     *
     * Also promotes any imported tags referenced by this profile's profile_tags records.
     *
     * @param int $profileid Profile ID
     */
    public function promote_profile_to_global(int $profileid): void {
        global $DB;

        $now = $this->clock->time();
        $DB->set_field('format_mimo_profiles', 'scope', 'global', ['id' => $profileid]);
        $DB->set_field('format_mimo_profiles', 'timemodified', $now, ['id' => $profileid]);

        // Promote any imported tags that have profile_tags records for this profile.
        $sql = "SELECT DISTINCT pt.tagid
                  FROM {format_mimo_profile_tags} pt
                  JOIN {format_mimo_tags} t ON t.id = pt.tagid
                 WHERE pt.profileid = :profileid AND t.scope = :scope";
        $importedtagids = $DB->get_fieldset_sql($sql, ['profileid' => $profileid, 'scope' => 'imported']);

        $tagmanager = \core\di::get(tag_manager::class);
        foreach ($importedtagids as $tagid) {
            $tagmanager->promote_tag_to_global((int) $tagid);
        }

        $tagmanager->clear_tag_cache();
    }

    /**
     * Clean up orphaned imported profiles not referenced by any course.
     *
     * An imported profile is orphaned when no course has it as their activityprofile.
     */
    public function cleanup_orphaned_imported_profiles(): void {
        global $DB;

        // The course_format_options.value column is a text column on most DB engines
        // and cannot be compared directly; use sql_compare_text() for portability.
        // Explicit length: default (32) is shorter than possible profile names.
        $valcompare = $DB->sql_compare_text('cfo.value', 255);
        $namecompare = $DB->sql_compare_text('p.name', 255);
        $sql = "SELECT p.id
                  FROM {format_mimo_profiles} p
                 WHERE p.scope = :scope
                   AND NOT EXISTS (
                       SELECT 1 FROM {course_format_options} cfo
                        WHERE cfo.format = 'mimo'
                          AND cfo.name = 'activityprofile'
                          AND $valcompare = $namecompare
                   )";
        $orphanids = $DB->get_fieldset_sql($sql, ['scope' => 'imported']);

        foreach ($orphanids as $profileid) {
            $this->delete_profile((int) $profileid);
        }
    }

    /**
     * Get all global profiles (scope='global') ordered by sortorder.
     *
     * @return array Array of profile objects keyed by id
     */
    public function get_global_profiles(): array {
        global $DB;

        return $DB->get_records('format_mimo_profiles', ['scope' => 'global'], 'sortorder ASC, id ASC');
    }

    /**
     * Name of the site's default activity profile.
     *
     * @return string First global profile by sortorder, or '' when none exist.
     */
    public function get_default_profile_name(): string {
        $profiles = $this->get_global_profiles();
        $first = reset($profiles);
        return $first ? $first->name : '';
    }

    /* ================= *
     * Initialization.  *
     * ================= */

    /**
     * Initialize default profiles if they don't exist.
     * Called during plugin installation.
     */
    public function initialize_default_profiles(): void {
        global $DB;

        // The base set is always the first profile. Collision rule: an
        // existing profile literally named 'base' is adopted as-is.
        if (!$this->get_profile_by_name('base')) {
            $DB->execute('UPDATE {format_mimo_profiles} SET sortorder = sortorder + 1');
            $this->create_profile('base', get_string('profile_base', 'format_mimo'), 0);
        }

        $defaults = [
            ['name' => 'primary_horst',
                'displayname' => get_string('profile_primary_horst', 'format_mimo'), 'sortorder' => 1],
            ['name' => 'base_symbols',
                'displayname' => get_string('profile_base_symbols', 'format_mimo'), 'sortorder' => 2],
            ['name' => 'foreignlanguage',
                'displayname' => get_string('profile_foreignlanguage', 'format_mimo'), 'sortorder' => 3],
        ];

        foreach ($defaults as $profile) {
            if (!$this->get_profile_by_name($profile['name'])) {
                $profileid = $this->create_profile(
                    $profile['name'],
                    $profile['displayname'],
                    $profile['sortorder']
                );
                $this->apply_default_profile_tag_overrides($profile['name'], $profileid);
            }
        }

        // Equal-tagset invariant: every tag × profile pair gets a fully
        // materialized row; fresh pairs are enabled (default tags are active
        // in default sets).
        $this->materialize_all_profile_tags(true);
    }

    /**
     * Materialize profile_tags rows for every tag × profile combination.
     *
     * Existing rows keep their enabled flag and get NULL fields filled from
     * the anchor; missing rows are created with enabled = $enablemissing
     * (pre-materialization semantics treated missing rows as enabled).
     *
     * @param bool $enablemissing Enabled flag for rows that do not exist yet
     */
    public function materialize_all_profile_tags(bool $enablemissing = true): void {
        $tags = \core\di::get(tag_manager::class)->get_all_tags();
        foreach ($this->get_all_profiles() as $profile) {
            foreach ($tags as $tag) {
                $existing = $this->get_profile_tag_for_profile((int) $tag->id, (int) $profile->id);
                $this->materialize_profile_tag(
                    (int) $tag->id,
                    (int) $profile->id,
                    [],
                    $existing ? null : $enablemissing
                );
            }
        }
    }

    /**
     * Copy anchor-area tag images into profile file areas that have none.
     *
     * One-time migration companion for strict per-set images: sets that
     * previously displayed the anchor image via fallback keep their current
     * appearance.
     */
    public function copy_base_images_to_profile_tags(): void {
        global $DB;

        $fs = get_file_storage();
        $ctxid = \core\context\system::instance()->id;
        $map = [
            ['anchor' => tag_manager::FILEAREA_CARDIMAGE,
                'profile' => self::FILEAREA_PROFILE_CARDIMAGE, 'field' => 'cardimage'],
            ['anchor' => tag_manager::FILEAREA_FILTERIMAGE,
                'profile' => self::FILEAREA_PROFILE_FILTERIMAGE, 'field' => 'filterimage'],
        ];

        $profiletags = $DB->get_records('format_mimo_profile_tags');
        foreach ($profiletags as $pt) {
            foreach ($map as $area) {
                $profilefiles = $fs->get_area_files($ctxid, 'format_mimo', $area['profile'], $pt->id, 'itemid', false);
                if (!empty($profilefiles)) {
                    continue;
                }
                $anchorfiles = $fs->get_area_files($ctxid, 'format_mimo', $area['anchor'], $pt->tagid, 'itemid', false);
                $anchorfile = reset($anchorfiles);
                if (!$anchorfile) {
                    continue;
                }
                $fs->create_file_from_storedfile([
                    'filearea' => $area['profile'],
                    'itemid' => $pt->id,
                ], $anchorfile);
                $DB->set_field(
                    'format_mimo_profile_tags',
                    $area['field'],
                    $anchorfile->get_filename(),
                    ['id' => $pt->id]
                );
            }
        }

        $this->imageurlcache = [];
        $this->profiletagscache = [];
        \core\di::get(tag_manager::class)->clear_tag_cache();
    }

    /**
     * Get the default per-profile tag overrides.
     *
     * Keyed by profile name → default tag definition index (see
     * {@see tag_manager::get_default_tag_definitions()}) → override fields.
     * Supports: name, bgcolor, activitytype1-3, enabled, imgplacement, imgsize,
     * cardimage, filterimage.  Image fields trigger a file copy from pix/tags/
     * into the profile file area.
     *
     * @return array
     */
    private function get_default_profile_tag_overrides(): array {
        return [
            'primary_horst' => [
                0 => ['name' => get_string('tag_reading', 'format_mimo'),
                    'cardimage' => 'horst_reading.png', 'filterimage' => 'horst_reading.png'],
                1 => ['name' => get_string('tag_writing', 'format_mimo'),
                    'cardimage' => 'horst_writing.png', 'filterimage' => 'horst_writing.png'],
                2 => ['name' => get_string('tag_calculate', 'format_mimo'),
                    'cardimage' => 'horst_calculate.png', 'filterimage' => 'horst_calculate.png'],
                3 => ['name' => get_string('tag_play', 'format_mimo'),
                    'cardimage' => 'horst_play.png', 'filterimage' => 'horst_play.png'],
                4 => ['enabled' => 0],
                5 => ['name' => get_string('tag_show', 'format_mimo'), 'activitytype3' => 'hvp',
                    'cardimage' => 'horst_show.png', 'filterimage' => 'horst_show.png'],
                6 => ['name' => get_string('tag_design', 'format_mimo'),
                    'cardimage' => 'horst_design.png', 'filterimage' => 'horst_design.png'],
                7 => ['name' => get_string('tag_investigate', 'format_mimo'),
                    'cardimage' => 'horst_investigate.png', 'filterimage' => 'horst_investigate.png'],
                8 => ['name' => get_string('tag_listen', 'format_mimo'),
                    'cardimage' => 'horst_listen.png', 'filterimage' => 'horst_listen.png'],
                9 => ['name' => get_string('tag_partnerwork', 'format_mimo'),
                    'cardimage' => 'horst_partnerwork.png', 'filterimage' => 'horst_partnerwork.png'],
                10 => ['name' => get_string('tag_groupproject', 'format_mimo'),
                    'cardimage' => 'horst_groupproject.png', 'filterimage' => 'horst_groupproject.png'],
                11 => ['name' => get_string('tag_testyourself', 'format_mimo'),
                    'cardimage' => 'horst_testyourself.png', 'filterimage' => 'horst_testyourself.png'],
                12 => ['name' => get_string('tag_discuss', 'format_mimo'),
                    'cardimage' => 'horst_discuss.png', 'filterimage' => 'horst_discuss.png'],
                13 => ['enabled' => 0],
            ],
            // Same names, colours and activity types as the base set; only the artwork differs.
            'base_symbols' => [
                0 => ['cardimage' => 'symbols_inform.png', 'filterimage' => 'symbols_inform.png'],
                1 => ['cardimage' => 'symbols_compose.png', 'filterimage' => 'symbols_compose.png'],
                2 => ['cardimage' => 'symbols_apply.png', 'filterimage' => 'symbols_apply.png'],
                3 => ['cardimage' => 'symbols_practise.png', 'filterimage' => 'symbols_practise.png'],
                4 => ['cardimage' => 'symbols_receive.png', 'filterimage' => 'symbols_receive.png'],
                5 => ['cardimage' => 'symbols_present.png', 'filterimage' => 'symbols_present.png'],
                6 => ['cardimage' => 'symbols_produce.png', 'filterimage' => 'symbols_produce.png'],
                7 => ['cardimage' => 'symbols_research.png', 'filterimage' => 'symbols_research.png'],
                8 => ['cardimage' => 'symbols_listen.png', 'filterimage' => 'symbols_listen.png'],
                9 => ['cardimage' => 'symbols_cooperate.png', 'filterimage' => 'symbols_cooperate.png'],
                10 => ['cardimage' => 'symbols_project.png', 'filterimage' => 'symbols_project.png'],
                11 => ['cardimage' => 'symbols_test.png', 'filterimage' => 'symbols_test.png'],
                12 => ['cardimage' => 'symbols_discuss.png', 'filterimage' => 'symbols_discuss.png'],
                13 => ['cardimage' => 'symbols_reflect.png', 'filterimage' => 'symbols_reflect.png'],
            ],
            'foreignlanguage' => [
                0 => ['name' => get_string('tag_reading', 'format_mimo')],
                1 => ['name' => get_string('tag_compose', 'format_mimo')],
                2 => ['name' => get_string('tag_speak', 'format_mimo'),
                    'activitytype1' => 'assign', 'activitytype2' => 'forum'],
                3 => ['name' => get_string('tag_translate', 'format_mimo'),
                    'activitytype1' => 'assign', 'activitytype2' => 'page', 'activitytype3' => 'quiz'],
                4 => ['name' => get_string('tag_vocabulary', 'format_mimo'),
                    'activitytype1' => 'hvp', 'activitytype2' => 'wiki'],
                5 => ['name' => get_string('tag_grammar', 'format_mimo'),
                    'activitytype1' => 'page', 'activitytype2' => 'glossary'],
                6 => ['name' => get_string('tag_produce', 'format_mimo')],
                7 => ['name' => get_string('tag_research', 'format_mimo')],
                8 => ['name' => get_string('tag_hearing', 'format_mimo')],
                9 => ['name' => get_string('tag_cooperate', 'format_mimo')],
                10 => ['name' => get_string('tag_projectwork', 'format_mimo')],
                11 => ['name' => get_string('tag_testyourself', 'format_mimo')],
                12 => ['enabled' => 0],
                13 => ['enabled' => 0],
            ],
        ];
    }

    /**
     * Apply the default tag overrides for one profile.
     *
     * Tags are resolved by the names of the default tag definitions, so this
     * works during installation as well as during upgrades.  Missing tags are
     * skipped silently.
     *
     * @param string $profilename Profile name (e.g., 'primary_horst')
     * @param int $profileid Profile ID
     */
    private function apply_default_profile_tag_overrides(string $profilename, int $profileid): void {
        $tagoverrides = $this->get_default_profile_tag_overrides()[$profilename] ?? [];
        if (empty($tagoverrides)) {
            return;
        }

        $tagmanager = \core\di::get(tag_manager::class);
        $definitions = $tagmanager->get_default_tag_definitions();

        foreach ($tagoverrides as $tagindex => $overrides) {
            if (!isset($definitions[$tagindex])) {
                continue;
            }
            $tag = $tagmanager->find_tag_by_name($definitions[$tagindex]['name']);
            if (!$tag) {
                continue;
            }
            $pt = $this->get_or_create_profile_tag($tag->id, $profileid);

            // Separate image fields from non-image fields.
            $imagefields = [];
            $datafields = [];
            foreach ($overrides as $field => $value) {
                if ($field === 'cardimage' || $field === 'filterimage') {
                    $imagefields[$field] = $value;
                } else {
                    $datafields[$field] = $value;
                }
            }

            // Apply non-image overrides (name, bgcolor, activity types, etc.).
            if (!empty($datafields)) {
                $this->update_profile_tag($pt->id, $datafields);
            }

            // Copy image files from pix/tags/ and update DB fields.
            if (!empty($imagefields)) {
                $this->apply_default_profile_images($pt->id, $imagefields);
            }
        }
    }

    /**
     * Copy default image files for a profile_tag record and update the DB fields.
     *
     * @param int $profiletagid Profile tags record ID
     * @param array $imagefields Associative array of 'cardimage' and/or 'filterimage' => filename
     */
    private function apply_default_profile_images(int $profiletagid, array $imagefields): void {
        global $DB;

        $fileareamap = [
            'cardimage' => self::FILEAREA_PROFILE_CARDIMAGE,
            'filterimage' => self::FILEAREA_PROFILE_FILTERIMAGE,
        ];

        foreach ($imagefields as $field => $filename) {
            if (!isset($fileareamap[$field]) || empty($filename)) {
                continue;
            }
            $this->copy_default_profile_image($profiletagid, $filename, $fileareamap[$field]);
            $DB->set_field('format_mimo_profile_tags', $field, $filename, ['id' => $profiletagid]);
        }
        $DB->set_field('format_mimo_profile_tags', 'timemodified', $this->clock->time(), ['id' => $profiletagid]);
    }
}
