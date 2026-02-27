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
 * Restore handler for format_minimoodlewall.
 *
 * @package    format_minimoodlewall
 * @category   backup
 * @copyright  2025 MBS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Recreate minimoodlewall profile and tag data during course restores.
 */
class restore_format_minimoodlewall_plugin extends restore_format_plugin {

    /**
     * Declare structures to be processed by the restore task.
     *
     * @return restore_path_element[]
     */
    protected function define_course_plugin_structure() {
        $paths = [];

        // Profiles (global, restored before tags so profile IDs can be mapped).
        $paths[] = new restore_path_element(
            'format_minimoodlewall_profile',
            $this->get_pathfor('/mmw_profiles/mmw_profile')
        );

        // Tags (flat list).
        $paths[] = new restore_path_element(
            'format_minimoodlewall_tag',
            $this->get_pathfor('/mmw_tags/mmw_tag')
        );

        // Per-profile tag overrides (nested under tags).
        $paths[] = new restore_path_element(
            'format_minimoodlewall_profile_tag',
            $this->get_pathfor('/mmw_tags/mmw_tag/mmw_profile_tags/mmw_profile_tag')
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
        $paths[] = new restore_path_element('format_minimoodlewall_cmtag', $this->get_pathfor('/mmw_cmtag'));
        return $paths;
    }

    /**
     * Restore profile definitions. Reuse existing profile if name matches.
     *
     * @param array $data raw backup data
     */
    public function process_format_minimoodlewall_profile($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Profiles are unique by name; reuse existing ones.
        if ($existing = $DB->get_record('format_minimoodlewall_profiles', ['name' => $data->name])) {
            $this->set_mapping('format_minimoodlewall_profile', $oldid, $existing->id);
            return;
        }

        unset($data->id);
        $data->timecreated = time();
        $data->timemodified = time();
        $newid = $DB->insert_record('format_minimoodlewall_profiles', $data);
        $this->set_mapping('format_minimoodlewall_profile', $oldid, $newid);
    }

    /**
     * Restore tag definitions. Tags are now flat (no tagset parent).
     *
     * @param array $data raw backup data
     */
    public function process_format_minimoodlewall_tag($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Remove legacy tagsetid if present in backup data.
        unset($data->tagsetid);

        // Tags are unique by name; reuse existing ones when merging courses.
        if ($existing = $DB->get_record('format_minimoodlewall_tags', ['name' => $data->name])) {
            $this->set_mapping('format_minimoodlewall_tag', $oldid, $existing->id);
            return;
        }

        unset($data->id);
        $newid = $DB->insert_record('format_minimoodlewall_tags', $data);
        $this->set_mapping('format_minimoodlewall_tag', $oldid, $newid);
    }

    /**
     * Restore per-profile tag overrides. Reuse existing record if tagid/profileid combo exists.
     *
     * @param array $data raw backup data
     */
    public function process_format_minimoodlewall_profile_tag($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Map tagid and profileid to restored IDs.
        $newtagid = $this->get_mappingid('format_minimoodlewall_tag', $data->tagid);
        $newprofileid = $this->get_mappingid('format_minimoodlewall_profile', $data->profileid);

        if (!$newtagid || !$newprofileid) {
            return;
        }

        $data->tagid = $newtagid;
        $data->profileid = $newprofileid;

        // Reuse existing profile_tag record if the combination already exists.
        $existing = $DB->get_record('format_minimoodlewall_profile_tags', [
            'tagid' => $newtagid,
            'profileid' => $newprofileid,
        ]);
        if ($existing) {
            $this->set_mapping('format_minimoodlewall_profile_tag', $oldid, $existing->id);
            return;
        }

        unset($data->id);
        $data->timecreated = time();
        $data->timemodified = time();
        $newid = $DB->insert_record('format_minimoodlewall_profile_tags', $data);
        $this->set_mapping('format_minimoodlewall_profile_tag', $oldid, $newid);
    }

    /**
     * Restore cm/tag mapping for each activity.
     *
     * @param array $data raw backup data
     */
    public function process_format_minimoodlewall_cmtag($data) {
        $data = (object)$data;
        $newcmid = $this->get_mappingid('course_module', $data->cmid);
        $newtagid = $this->get_mappingid('format_minimoodlewall_tag', $data->tagid);

        if (!$newcmid || !$newtagid) {
            return;
        }

        \format_minimoodlewall\tag_manager::assign_tag_to_cm($newcmid, $newtagid);
    }

    /**
     * Reattach tag card/filter files and profile-specific files after restore.
     */
    public function after_execute_course() {
        $this->add_related_files(
            'format_minimoodlewall',
            \format_minimoodlewall\tag_manager::FILEAREA_CARDIMAGE,
            'format_minimoodlewall_tag'
        );
        $this->add_related_files(
            'format_minimoodlewall',
            \format_minimoodlewall\tag_manager::FILEAREA_FILTERIMAGE,
            'format_minimoodlewall_tag'
        );
        $this->add_related_files(
            'format_minimoodlewall',
            \format_minimoodlewall\profile_manager::FILEAREA_PROFILE_CARDIMAGE,
            'format_minimoodlewall_profile_tag'
        );
        $this->add_related_files(
            'format_minimoodlewall',
            \format_minimoodlewall\profile_manager::FILEAREA_PROFILE_FILTERIMAGE,
            'format_minimoodlewall_profile_tag'
        );
    }

    /**
     * Clear caches after full restore.
     */
    public function after_restore_course() {
        \format_minimoodlewall\tag_manager::clear_tag_cache();
        \format_minimoodlewall\tag_manager::clear_mapping_cache();
    }
}
