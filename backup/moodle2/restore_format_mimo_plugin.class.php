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
 * Restore handler for format_mimo.
 *
 * @package    format_mimo
 * @category   backup
 * @copyright  2025 MBS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Recreate mimo profile and tag data during course restores.
 *
 * Uses a two-phase approach:
 * 1. process_*() — match/create tags and profiles, collect override data.
 * 2. after_execute_course() — create imported profile with full overrides,
 *    disable surplus tags, bind imported tags, set course profile.
 */
class restore_format_mimo_plugin extends restore_format_plugin {

    /** @var array|null Existing tags on target, keyed by id, loaded once. */
    private $existingtags = null;

    /** @var array Tag IDs that have been matched (excluded from future matches). */
    private $matchedtagids = [];

    /** @var array Positional pool: existing tags not yet fingerprint-matched, in sortorder. */
    private $positionalpool = [];

    /** @var array Override data per backup tag: oldid => ['match' => 'fingerprint'|'name'|'positional'|'new', 'targetid' => int, 'backupdata' => object]. */
    private $overridedata = [];

    /** @var int Count of backup tags processed. */
    private $backuptagcount = 0;

    /** @var bool Whether all tags were recognized (fingerprint or name match). */
    private $allrecognized = true;

    /**
     * Declare structures to be processed by the restore task.
     *
     * @return restore_path_element[]
     */
    protected function define_course_plugin_structure() {
        $paths = [];

        // Profiles (global, restored before tags so profile IDs can be mapped).
        $paths[] = new restore_path_element(
            'format_mimo_profile',
            $this->get_pathfor('/mimo_profiles/mimo_profile')
        );

        // Tags (flat list).
        $paths[] = new restore_path_element(
            'format_mimo_tag',
            $this->get_pathfor('/mimo_tags/mimo_tag')
        );

        // Per-profile tag overrides (nested under tags).
        $paths[] = new restore_path_element(
            'format_mimo_profile_tag',
            $this->get_pathfor('/mimo_tags/mimo_tag/mimo_profile_tags/mimo_profile_tag')
        );

        return $paths;
    }

    /**
     * Declare module-level structures.
     *
     * @return restore_path_element[]
     */
    protected function define_module_plugin_structure() {
        $paths = [];
        $paths[] = new restore_path_element('format_mimo_cmtag', $this->get_pathfor('/mimo_cmtag'));
        return $paths;
    }

    /**
     * Initialize the existing tags pool on first call.
     */
    private function init_existing_tags(): void {
        if ($this->existingtags !== null) {
            return;
        }
        $this->existingtags = \format_mimo\tag_manager::get_all_tags();
        // Build positional pool: all existing tags in sortorder, as array values.
        $this->positionalpool = array_values($this->existingtags);
    }

    /**
     * Restore profile definitions. Reuse existing profile if name matches.
     *
     * @param array $data raw backup data
     */
    public function process_format_mimo_profile($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Profiles are unique by name; reuse existing ones.
        if ($existing = $DB->get_record('format_mimo_profiles', ['name' => $data->name])) {
            $this->set_mapping('format_mimo_profile', $oldid, $existing->id);
            return;
        }

        unset($data->id);
        // Preserve scope from backup if present, else default to 'global'.
        if (!isset($data->scope)) {
            $data->scope = 'global';
        }
        $data->timecreated = time();
        $data->timemodified = time();
        $newid = $DB->insert_record('format_mimo_profiles', $data);
        $this->set_mapping('format_mimo_profile', $oldid, $newid);
    }

    /**
     * Restore tag definitions using fingerprint → name → positional → create matching.
     *
     * @param array $data raw backup data
     */
    public function process_format_mimo_tag($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $this->backuptagcount++;

        // Remove legacy fields if present.
        unset($data->tagsetid);

        $this->init_existing_tags();

        // Step 1: Fingerprint match (name + bgcolor + activitytype1-3).
        $match = \format_mimo\tag_manager::find_tag_by_fingerprint($data, $this->matchedtagids);
        if ($match) {
            $this->matchedtagids[] = $match->id;
            $this->set_mapping('format_mimo_tag', $oldid, $match->id);
            $this->overridedata[$oldid] = [
                'match' => 'fingerprint',
                'targetid' => (int) $match->id,
                'backupdata' => clone $data,
            ];
            // Remove from positional pool.
            $this->positionalpool = array_filter(
                $this->positionalpool,
                fn($t) => (int) $t->id !== (int) $match->id
            );
            return;
        }

        // Step 2: Name match — same conceptual tag but properties changed by admin.
        // Trusts the instance's current values (admin's edits take precedence).
        $namematch = \format_mimo\tag_manager::find_tag_by_name($data->name, $this->matchedtagids);
        if ($namematch) {
            $this->matchedtagids[] = $namematch->id;
            $this->set_mapping('format_mimo_tag', $oldid, $namematch->id);
            $this->overridedata[$oldid] = [
                'match' => 'name',
                'targetid' => (int) $namematch->id,
                'backupdata' => clone $data,
            ];
            // Remove from positional pool.
            $this->positionalpool = array_filter(
                $this->positionalpool,
                fn($t) => (int) $t->id !== (int) $namematch->id
            );
            return;
        }

        // Step 3: Positional match (next unmatched existing tag by sortorder).
        $posmatch = null;
        foreach ($this->positionalpool as $idx => $candidate) {
            if (!in_array((int) $candidate->id, $this->matchedtagids, true)) {
                $posmatch = $candidate;
                unset($this->positionalpool[$idx]);
                break;
            }
        }

        if ($posmatch) {
            $this->matchedtagids[] = $posmatch->id;
            $this->set_mapping('format_mimo_tag', $oldid, $posmatch->id);
            $this->overridedata[$oldid] = [
                'match' => 'positional',
                'targetid' => (int) $posmatch->id,
                'backupdata' => clone $data,
            ];
            $this->allrecognized = false;
            return;
        }

        // Step 4: Create new imported tag (backup has more tags than instance).
        unset($data->id);
        $data->scope = 'imported';
        $newid = $DB->insert_record('format_mimo_tags', $data);
        $this->set_mapping('format_mimo_tag', $oldid, $newid);
        $this->overridedata[$oldid] = [
            'match' => 'new',
            'targetid' => (int) $newid,
            'backupdata' => clone $data,
        ];
        $this->allrecognized = false;
    }

    /**
     * Restore per-profile tag overrides.
     *
     * Only applied for fingerprint/name-matched tags (safe — same conceptual tag).
     * Skipped for positional/new matches to avoid contaminating target profiles.
     *
     * @param array $data raw backup data
     */
    public function process_format_mimo_profile_tag($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Check the match type for the parent tag.
        $oldtagid = $data->tagid;
        if (isset($this->overridedata[$oldtagid])
                && !in_array($this->overridedata[$oldtagid]['match'], ['fingerprint', 'name'])) {
            // Skip profile_tag records for positional/new matches.
            return;
        }

        // Map tagid and profileid to restored IDs.
        $newtagid = $this->get_mappingid('format_mimo_tag', $data->tagid);
        $newprofileid = $this->get_mappingid('format_mimo_profile', $data->profileid);

        if (!$newtagid || !$newprofileid) {
            return;
        }

        $data->tagid = $newtagid;
        $data->profileid = $newprofileid;

        // Reuse existing profile_tag record if the combination already exists.
        $existing = $DB->get_record('format_mimo_profile_tags', [
            'tagid' => $newtagid,
            'profileid' => $newprofileid,
        ]);
        if ($existing) {
            $this->set_mapping('format_mimo_profile_tag', $oldid, $existing->id);
            return;
        }

        unset($data->id);
        $data->timecreated = time();
        $data->timemodified = time();
        $newid = $DB->insert_record('format_mimo_profile_tags', $data);
        $this->set_mapping('format_mimo_profile_tag', $oldid, $newid);
    }

    /**
     * Restore cm/tag mapping for each activity.
     *
     * @param array $data raw backup data
     */
    public function process_format_mimo_cmtag($data) {
        $data = (object)$data;
        $newcmid = $this->get_mappingid('course_module', $data->cmid);
        $newtagid = $this->get_mappingid('format_mimo_tag', $data->tagid);

        if (!$newcmid || !$newtagid) {
            return;
        }

        \format_mimo\tag_manager::assign_tag_to_cm($newcmid, $newtagid);
    }

    /**
     * Create imported profile with full overrides, restore files, and
     * set the course's activity profile.
     */
    public function after_execute_course() {
        global $DB;

        // Restore base tag files (for new and fingerprint-matched tags).
        $this->add_related_files(
            'format_mimo',
            \format_mimo\tag_manager::FILEAREA_CARDIMAGE,
            'format_mimo_tag'
        );
        $this->add_related_files(
            'format_mimo',
            \format_mimo\tag_manager::FILEAREA_FILTERIMAGE,
            'format_mimo_tag'
        );
        // Restore profile-specific files for fingerprint-matched tags.
        $this->add_related_files(
            'format_mimo',
            \format_mimo\profile_manager::FILEAREA_PROFILE_CARDIMAGE,
            'format_mimo_profile_tag'
        );
        $this->add_related_files(
            'format_mimo',
            \format_mimo\profile_manager::FILEAREA_PROFILE_FILTERIMAGE,
            'format_mimo_profile_tag'
        );
        // Section overview card images.
        $this->add_related_files(
            'format_mimo',
            \format_mimo\section_image_manager::FILEAREA,
            'course_section'
        );

        // If all backup tags were recognized (fingerprint or name match), no imported
        // profile needed. The instance already has the right tags — either identical
        // (fingerprint) or intentionally updated by admin (name match).
        if ($this->allrecognized) {
            return;
        }

        // Create the imported profile.
        $courseid = $this->task->get_courseid();
        $course = get_course($courseid);
        $profile = \format_mimo\profile_manager::create_imported_profile($course->fullname);

        // Get all global profiles for disabling imported tags in them.
        $globalprofiles = \format_mimo\profile_manager::get_global_profiles();

        // Build full profile_tag overrides for every matched tag.
        foreach ($this->overridedata as $info) {
            $targetid = $info['targetid'];
            $backupdata = $info['backupdata'];
            $matchtype = $info['match'];

            // Create profile_tag with full override values from backup.
            $pt = \format_mimo\profile_manager::get_or_create_profile_tag($targetid, $profile->id);
            \format_mimo\profile_manager::update_profile_tag($pt->id, [
                'name' => $backupdata->name,
                'bgcolor' => $backupdata->bgcolor ?? null,
                'activitytype1' => $backupdata->activitytype1 ?? null,
                'activitytype2' => $backupdata->activitytype2 ?? null,
                'activitytype3' => $backupdata->activitytype3 ?? null,
                'enabled' => 1,
            ]);

            // For newly created imported tags: disable in all global profiles.
            if ($matchtype === 'new') {
                \format_mimo\tag_manager::bind_tag_to_course($targetid, $courseid);
                foreach ($globalprofiles as $gp) {
                    $gpt = \format_mimo\profile_manager::get_or_create_profile_tag($targetid, $gp->id);
                    \format_mimo\profile_manager::update_profile_tag($gpt->id, [
                        'enabled' => 0,
                    ]);
                }
            }
        }

        // Disable surplus existing tags in the imported profile.
        $allexisting = $this->existingtags ?? [];
        foreach ($allexisting as $etag) {
            if (!in_array((int) $etag->id, $this->matchedtagids, true)) {
                $pt = \format_mimo\profile_manager::get_or_create_profile_tag($etag->id, $profile->id);
                \format_mimo\profile_manager::update_profile_tag($pt->id, [
                    'enabled' => 0,
                ]);
            }
        }

        // Set the course's activity profile to the new imported profile.
        $DB->set_field_select(
            'course_format_options',
            'value',
            $profile->name,
            "courseid = :courseid AND format = 'mimo' AND name = 'activityprofile'",
            ['courseid' => $courseid]
        );
        // If the option doesn't exist yet, create it.
        if (!$DB->record_exists('course_format_options', [
            'courseid' => $courseid,
            'format' => 'mimo',
            'name' => 'activityprofile',
        ])) {
            $DB->insert_record('course_format_options', (object) [
                'courseid' => $courseid,
                'format' => 'mimo',
                'sectionid' => 0,
                'name' => 'activityprofile',
                'value' => $profile->name,
            ]);
        }
    }

    /**
     * Clear caches after full restore.
     */
    public function after_restore_course() {
        \format_mimo\tag_manager::clear_tag_cache();
        \format_mimo\tag_manager::clear_mapping_cache();
    }
}
