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
 * Activity profile manager for format_minimoodlewall.
 *
 * Manages activity profiles (formerly called styles) and per-profile
 * tag overrides (name, bgcolor, activity types, enabled flag, images).
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall;

use context_system;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Activity profile manager class for handling activity profiles.
 *
 * An activity profile controls the visual appearance and behaviour of
 * the minimoodlewall course format. Per-profile overrides allow each
 * profile to show different tag names, colours, activity types, images
 * and an enabled/disabled flag for each tag.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_manager {

    /** Database table for profiles. */
    private const TABLE_PROFILES = 'format_minimoodlewall_profiles';

    /** Database table for per-profile tag overrides. */
    private const TABLE_PROFILE_TAGS = 'format_minimoodlewall_profile_tags';

    /** File area for profile-specific card images. */
    public const FILEAREA_PROFILE_CARDIMAGE = 'profiletagcard';

    /** File area for profile-specific filter images. */
    public const FILEAREA_PROFILE_FILTERIMAGE = 'profiletagfilter';

    /** Filemanager options for image uploads. */
    private const FILEMANAGER_OPTIONS = [
        'maxbytes' => 1048576, // 1 MB.
        'maxfiles' => 1,
        'accepted_types' => ['.svg', '.png', '.jpg', '.jpeg', '.gif'],
        'subdirs' => 0,
    ];

    // ---------------------------------------------------------------
    // Profile CRUD.
    // ---------------------------------------------------------------

    /**
     * Get all profiles ordered by sortorder.
     *
     * @return array Array of profile objects keyed by id
     */
    public static function get_all_profiles(): array {
        global $DB;
        return $DB->get_records(self::TABLE_PROFILES, null, 'sortorder ASC, id ASC');
    }

    /**
     * Get a single profile by ID.
     *
     * @param int $id Profile ID
     * @return stdClass|null
     */
    public static function get_profile(int $id): ?stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE_PROFILES, ['id' => $id]);
        return $record ?: null;
    }

    /**
     * Get a profile by its internal name.
     *
     * @param string $name Profile name (e.g., 'classic', 'light', 'dark')
     * @return stdClass|null
     */
    public static function get_profile_by_name(string $name): ?stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE_PROFILES, ['name' => $name]);
        return $record ?: null;
    }

    /**
     * Create a new profile.
     *
     * @param string $name Internal identifier
     * @param string $displayname Human-readable name
     * @param int|null $sortorder Sort order (auto-calculated if null)
     * @return int The new profile ID
     */
    public static function create_profile(string $name, string $displayname, ?int $sortorder = null): int {
        global $DB;

        if ($sortorder === null) {
            $maxorder = $DB->get_field_sql(
                "SELECT MAX(sortorder) FROM {" . self::TABLE_PROFILES . "}"
            );
            $sortorder = ($maxorder ?? 0) + 1;
        }

        $now = time();
        $record = new stdClass();
        $record->name = $name;
        $record->displayname = $displayname;
        $record->sortorder = $sortorder;
        $record->timecreated = $now;
        $record->timemodified = $now;

        return $DB->insert_record(self::TABLE_PROFILES, $record);
    }

    /**
     * Update an existing profile.
     *
     * @param int $id Profile ID
     * @param array $data Fields to update
     * @return bool
     */
    public static function update_profile(int $id, array $data): bool {
        global $DB;

        $record = new stdClass();
        $record->id = $id;
        $record->timemodified = time();

        foreach ($data as $field => $value) {
            if (in_array($field, ['name', 'displayname', 'sortorder'])) {
                $record->$field = $value;
            }
        }

        return $DB->update_record(self::TABLE_PROFILES, $record);
    }

    /**
     * Delete a profile and all associated profile tag records + files.
     *
     * @param int $id Profile ID
     * @return bool
     */
    public static function delete_profile(int $id): bool {
        global $DB;

        // Delete associated profile tag files.
        $profiletags = $DB->get_records(self::TABLE_PROFILE_TAGS, ['profileid' => $id]);
        foreach ($profiletags as $pt) {
            self::delete_profile_tag_files($pt->id);
        }

        // Delete profile tag records.
        $DB->delete_records(self::TABLE_PROFILE_TAGS, ['profileid' => $id]);

        // Delete the profile.
        return $DB->delete_records(self::TABLE_PROFILES, ['id' => $id]);
    }

    /**
     * Get profiles as options array for select elements.
     *
     * @return array name => displayname
     */
    public static function get_profile_options(): array {
        $profiles = self::get_all_profiles();
        $options = [];
        foreach ($profiles as $profile) {
            $options[$profile->name] = $profile->displayname;
        }
        return $options;
    }

    // ---------------------------------------------------------------
    // Profile-tag override management.
    // ---------------------------------------------------------------

    /**
     * Get or create a profile_tags record for a tag/profile combination.
     *
     * @param int $tagid Tag ID
     * @param int $profileid Profile ID
     * @return stdClass
     */
    public static function get_or_create_profile_tag(int $tagid, int $profileid): stdClass {
        global $DB;

        $record = $DB->get_record(self::TABLE_PROFILE_TAGS, [
            'tagid' => $tagid,
            'profileid' => $profileid,
        ]);

        if (!$record) {
            $now = time();
            $record = new stdClass();
            $record->tagid = $tagid;
            $record->profileid = $profileid;
            $record->name = null;
            $record->bgcolor = null;
            $record->activitytype1 = null;
            $record->activitytype2 = null;
            $record->activitytype3 = null;
            $record->enabled = 1;
            $record->cardimage = null;
            $record->filterimage = null;
            $record->timecreated = $now;
            $record->timemodified = $now;
            $record->id = $DB->insert_record(self::TABLE_PROFILE_TAGS, $record);
        }

        return $record;
    }

    /**
     * Get profile_tags record by ID.
     *
     * @param int $id Profile tags record ID
     * @return stdClass|null
     */
    public static function get_profile_tag(int $id): ?stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE_PROFILE_TAGS, ['id' => $id]);
        return $record ?: null;
    }

    /**
     * Get all profile_tags records for a tag.
     *
     * @param int $tagid Tag ID
     * @return array Array of profile_tags objects keyed by id
     */
    public static function get_profile_tags_for_tag(int $tagid): array {
        global $DB;
        return $DB->get_records(self::TABLE_PROFILE_TAGS, ['tagid' => $tagid]);
    }

    /**
     * Get profile_tags record for a specific tag and profile.
     *
     * @param int $tagid Tag ID
     * @param int $profileid Profile ID
     * @return stdClass|null
     */
    public static function get_profile_tag_for_profile(int $tagid, int $profileid): ?stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE_PROFILE_TAGS, [
            'tagid' => $tagid,
            'profileid' => $profileid,
        ]);
        return $record ?: null;
    }

    /**
     * Update override fields on a profile_tags record.
     *
     * Allowed override fields: name, bgcolor, activitytype1, activitytype2,
     * activitytype3, enabled.  NULL values mean "inherit from base tag".
     *
     * @param int $id Profile tags record ID
     * @param array $data Associative array of field => value
     * @return bool
     */
    public static function update_profile_tag(int $id, array $data): bool {
        global $DB;

        $allowed = ['name', 'bgcolor', 'activitytype1', 'activitytype2', 'activitytype3', 'enabled'];
        $record = new stdClass();
        $record->id = $id;
        $record->timemodified = time();

        foreach ($data as $field => $value) {
            if (in_array($field, $allowed)) {
                if ($field === 'bgcolor' && $value !== null) {
                    $value = tag_manager::normalize_hex_color($value);
                }
                $record->$field = $value;
            }
        }

        return $DB->update_record(self::TABLE_PROFILE_TAGS, $record);
    }

    /**
     * Delete all profile_tags records and files for a given tag.
     *
     * Called when a tag is deleted to clean up associated profile overrides.
     *
     * @param int $tagid Tag ID
     */
    public static function delete_profile_tags_for_tag(int $tagid): void {
        global $DB;

        $profiletags = $DB->get_records(self::TABLE_PROFILE_TAGS, ['tagid' => $tagid]);
        foreach ($profiletags as $pt) {
            self::delete_profile_tag_files($pt->id);
        }

        $DB->delete_records(self::TABLE_PROFILE_TAGS, ['tagid' => $tagid]);
    }

    // ---------------------------------------------------------------
    // Tag resolution with profile overrides.
    // ---------------------------------------------------------------

    /**
     * Resolve a tag record with profile-specific overrides applied.
     *
     * For each nullable override field (name, bgcolor, activitytype1-3),
     * a non-NULL value in the profile_tags record replaces the base tag value.
     * The enabled flag is always taken from the profile_tags record.
     *
     * @param stdClass $tag Base tag record
     * @param int $profileid Profile ID
     * @return stdClass Merged tag with overrides applied and 'enabled' flag added
     */
    public static function resolve_tag_for_profile(stdClass $tag, int $profileid): stdClass {
        $resolved = clone $tag;

        $pt = self::get_profile_tag_for_profile($tag->id, $profileid);
        if (!$pt) {
            $resolved->enabled = 1;
            return $resolved;
        }

        // Apply non-NULL overrides.
        foreach (['name', 'bgcolor', 'activitytype1', 'activitytype2', 'activitytype3'] as $field) {
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
    public static function resolve_tags_for_profile(array $tags, int $profileid, bool $onlyenabled = true): array {
        $resolved = [];
        foreach ($tags as $tag) {
            $r = self::resolve_tag_for_profile($tag, $profileid);
            if (!$onlyenabled || $r->enabled) {
                $resolved[$r->id] = $r;
            }
        }
        return $resolved;
    }

    // ---------------------------------------------------------------
    // Image management (draft areas, saving, URLs).
    // ---------------------------------------------------------------

    /**
     * Retrieve the shared filemanager options for profile image uploads.
     *
     * @return array
     */
    public static function get_image_filemanager_options(): array {
        return self::FILEMANAGER_OPTIONS;
    }

    /**
     * Prepare a draft area for the card image filemanager field.
     *
     * @param int $tagid Tag ID
     * @param int $profileid Profile ID
     * @return int Draft item id
     */
    public static function prepare_cardimage_draft(int $tagid, int $profileid): int {
        $profiletag = self::get_profile_tag_for_profile($tagid, $profileid);
        $itemid = $profiletag ? $profiletag->id : 0;

        $draftitemid = file_get_submitted_draft_itemid("cardimage_profile_{$profileid}");
        file_prepare_draft_area(
            $draftitemid,
            context_system::instance()->id,
            'format_minimoodlewall',
            self::FILEAREA_PROFILE_CARDIMAGE,
            $itemid,
            self::get_image_filemanager_options()
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
    public static function prepare_filterimage_draft(int $tagid, int $profileid): int {
        $profiletag = self::get_profile_tag_for_profile($tagid, $profileid);
        $itemid = $profiletag ? $profiletag->id : 0;

        $draftitemid = file_get_submitted_draft_itemid("filterimage_profile_{$profileid}");
        file_prepare_draft_area(
            $draftitemid,
            context_system::instance()->id,
            'format_minimoodlewall',
            self::FILEAREA_PROFILE_FILTERIMAGE,
            $itemid,
            self::get_image_filemanager_options()
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
    public static function save_cardimage_from_draft(int $tagid, int $profileid, int $draftitemid): void {
        self::save_image_from_draft($tagid, $profileid, $draftitemid, self::FILEAREA_PROFILE_CARDIMAGE, 'cardimage');
    }

    /**
     * Save filter image from draft area.
     *
     * @param int $tagid Tag ID
     * @param int $profileid Profile ID
     * @param int $draftitemid Draft area ID
     */
    public static function save_filterimage_from_draft(int $tagid, int $profileid, int $draftitemid): void {
        self::save_image_from_draft($tagid, $profileid, $draftitemid, self::FILEAREA_PROFILE_FILTERIMAGE, 'filterimage');
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
    private static function save_image_from_draft(
        int $tagid,
        int $profileid,
        int $draftitemid,
        string $filearea,
        string $dbfield
    ): void {
        global $DB;

        // Ensure profile_tags record exists.
        $profiletag = self::get_or_create_profile_tag($tagid, $profileid);

        file_save_draft_area_files(
            $draftitemid,
            context_system::instance()->id,
            'format_minimoodlewall',
            $filearea,
            $profiletag->id,
            self::get_image_filemanager_options()
        );

        // Update filename in database.
        $file = self::get_image_file($profiletag->id, $filearea);
        $filename = $file ? $file->get_filename() : null;

        $DB->set_field(self::TABLE_PROFILE_TAGS, $dbfield, $filename, ['id' => $profiletag->id]);
        $DB->set_field(self::TABLE_PROFILE_TAGS, 'timemodified', time(), ['id' => $profiletag->id]);
    }

    /**
     * Get card image URL for a tag and profile.
     *
     * @param int $tagid Tag ID
     * @param int $profileid Profile ID
     * @return moodle_url|null
     */
    public static function get_cardimage_url(int $tagid, int $profileid): ?moodle_url {
        $profiletag = self::get_profile_tag_for_profile($tagid, $profileid);
        if (!$profiletag) {
            return null;
        }
        return self::get_image_url($profiletag->id, self::FILEAREA_PROFILE_CARDIMAGE);
    }

    /**
     * Get filter image URL for a tag and profile.
     *
     * @param int $tagid Tag ID
     * @param int $profileid Profile ID
     * @return moodle_url|null
     */
    public static function get_filterimage_url(int $tagid, int $profileid): ?moodle_url {
        $profiletag = self::get_profile_tag_for_profile($tagid, $profileid);
        if (!$profiletag) {
            return null;
        }
        return self::get_image_url($profiletag->id, self::FILEAREA_PROFILE_FILTERIMAGE);
    }

    /**
     * Get card image URL for a tag and profile name.
     *
     * @param int $tagid Tag ID
     * @param string $profilename Profile name (e.g., 'classic')
     * @return moodle_url|null
     */
    public static function get_cardimage_url_by_name(int $tagid, string $profilename): ?moodle_url {
        $profile = self::get_profile_by_name($profilename);
        if (!$profile) {
            return null;
        }
        return self::get_cardimage_url($tagid, $profile->id);
    }

    /**
     * Get filter image URL for a tag and profile name.
     *
     * @param int $tagid Tag ID
     * @param string $profilename Profile name (e.g., 'classic')
     * @return moodle_url|null
     */
    public static function get_filterimage_url_by_name(int $tagid, string $profilename): ?moodle_url {
        $profile = self::get_profile_by_name($profilename);
        if (!$profile) {
            return null;
        }
        return self::get_filterimage_url($tagid, $profile->id);
    }

    // ---------------------------------------------------------------
    // Private file helpers.
    // ---------------------------------------------------------------

    /**
     * Resolve the pluginfile URL for a stored file.
     *
     * @param int $profiletagid Profile tags record ID
     * @param string $filearea File area
     * @return moodle_url|null
     */
    private static function get_image_url(int $profiletagid, string $filearea): ?moodle_url {
        $file = self::get_image_file($profiletagid, $filearea);
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
    private static function get_image_file(int $profiletagid, string $filearea): ?\stored_file {
        $files = get_file_storage()->get_area_files(
            context_system::instance()->id,
            'format_minimoodlewall',
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
    private static function delete_profile_tag_files(int $profiletagid): void {
        $fs = get_file_storage();
        $contextid = context_system::instance()->id;

        $fs->delete_area_files($contextid, 'format_minimoodlewall', self::FILEAREA_PROFILE_CARDIMAGE, $profiletagid);
        $fs->delete_area_files($contextid, 'format_minimoodlewall', self::FILEAREA_PROFILE_FILTERIMAGE, $profiletagid);
    }

    // ---------------------------------------------------------------
    // Initialization.
    // ---------------------------------------------------------------

    /**
     * Initialize default profiles if they don't exist.
     * Called during plugin installation.
     */
    public static function initialize_default_profiles(): void {
        $defaults = [
            ['name' => 'classic', 'displayname' => 'Classic', 'sortorder' => 1],
            ['name' => 'light', 'displayname' => 'Light', 'sortorder' => 2],
            ['name' => 'dark', 'displayname' => 'Dark', 'sortorder' => 3],
        ];

        foreach ($defaults as $profile) {
            if (!self::get_profile_by_name($profile['name'])) {
                self::create_profile($profile['name'], $profile['displayname'], $profile['sortorder']);
            }
        }
    }
}
