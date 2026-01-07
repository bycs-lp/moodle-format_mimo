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
 * Recreate minimoodlewall tag data during course restores.
 */
class restore_format_minimoodlewall_plugin extends restore_format_plugin {
    /** @var array Tag IDs restored for this course, used to update selectedtags format option */
    protected $restoredtagids = [];

    /**
     * Declare structures to be processed by the restore task.
     *
     * @return restore_path_element[]
     */
    protected function define_course_plugin_structure() {
        $paths = [];

        // Current backup format: tags directly under plugin wrapper.
        $paths[] = new restore_path_element('format_minimoodlewall_tag', $this->get_pathfor('/mmw_tags/mmw_tag'));

        // Legacy backup format: tags nested under tagsets (for compatibility with old backups).
        $paths[] = new restore_path_element(
            'format_minimoodlewall_tagset_legacy',
            $this->get_pathfor('/mmw_tagsets/mmw_tagset')
        );
        $paths[] = new restore_path_element(
            'format_minimoodlewall_tag_legacy',
            $this->get_pathfor('/mmw_tagsets/mmw_tagset/mmw_tags/mmw_tag')
        );

        // Very old backups without plugin wrapper.
        $paths[] = new restore_path_element('format_minimoodlewall_tagset_very_legacy', '/course/mmw_tagsets/mmw_tagset');
        $paths[] = new restore_path_element(
            'format_minimoodlewall_tag_very_legacy',
            '/course/mmw_tagsets/mmw_tagset/mmw_tags/mmw_tag'
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
        // Standard module-level plugin payload.
        $paths[] = new restore_path_element('format_minimoodlewall_cmtag', $this->get_pathfor('/mmw_cmtag'));
        // Old backups lacked the plugin prefix for module-level data.
        $paths[] = new restore_path_element('format_minimoodlewall_cmtag_legacy', '/module/mmw_cmtag');
        return $paths;
    }

    /**
     * Restore tag definitions.
     *
     * @param array $data raw backup data
     */
    public function process_format_minimoodlewall_tag($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Remove tagsetid if present (from legacy backups).
        unset($data->tagsetid);

        // Tags are unique by name; reuse existing ones when merging courses.
        if ($existing = $DB->get_record('format_minimoodlewall_tags', ['name' => $data->name])) {
            $this->set_mapping('format_minimoodlewall_tag', $oldid, $existing->id);
            $this->restoredtagids[$existing->id] = $existing->id;
            return;
        }

        unset($data->id);
        $newid = $DB->insert_record('format_minimoodlewall_tags', $data);
        $this->set_mapping('format_minimoodlewall_tag', $oldid, $newid);
        $this->restoredtagids[$newid] = $newid;
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

        // Incomplete mapping is expected when the source course had filtered activities.
        if (!$newcmid || !$newtagid) {
            return;
        }

        \format_minimoodlewall\tag_manager::assign_tag_to_cm($newcmid, $newtagid);
    }

    /**
     * Reattach tag card/filter files after the course structure has been recreated.
     * Also update the course's selectedtags format option with restored tag IDs.
     */
    public function after_execute_course() {
        global $DB;

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

        // Update course format option with restored tag IDs.
        if (!empty($this->restoredtagids)) {
            $courseid = $this->task->get_courseid();
            $selectedtags = implode(',', array_keys($this->restoredtagids));

            // Check if format option already exists.
            $existing = $DB->get_record('course_format_options', [
                'courseid' => $courseid,
                'format' => 'minimoodlewall',
                'name' => 'selectedtags',
            ]);

            if ($existing) {
                // Merge with existing tags.
                $existingtags = !empty($existing->value) ? explode(',', $existing->value) : [];
                $mergedtags = array_unique(array_merge($existingtags, array_keys($this->restoredtagids)));
                $existing->value = implode(',', $mergedtags);
                $DB->update_record('course_format_options', $existing);
            } else {
                // Insert new format option.
                $DB->insert_record('course_format_options', (object)[
                    'courseid' => $courseid,
                    'format' => 'minimoodlewall',
                    'sectionid' => 0,
                    'name' => 'selectedtags',
                    'value' => $selectedtags,
                ]);
            }
        }
    }

    /**
     * Clear caches when the restore finishes to expose the imported data immediately.
     */
    public function after_restore_course() {
        \format_minimoodlewall\tag_manager::clear_mapping_cache();
        \format_minimoodlewall\tag_manager::clear_tag_cache();
    }

    /**
     * Legacy handler for tagsets - we skip them now since tagsets no longer exist.
     *
     * @param array $data
     */
    public function process_format_minimoodlewall_tagset_legacy($data) {
        // Tagsets are no longer used, so we just skip this.
        // Tags will be processed separately and added without tagset reference.
    }

    /**
     * Very old legacy handler for tagsets.
     *
     * @param array $data
     */
    public function process_format_minimoodlewall_tagset_very_legacy($data) {
        // Tagsets are no longer used.
    }

    /**
     * Legacy handler that processes tags from old backup format (nested under tagsets).
     *
     * @param array $data
     */
    public function process_format_minimoodlewall_tag_legacy($data) {
        $this->process_format_minimoodlewall_tag($data);
    }

    /**
     * Very old legacy handler for tags.
     *
     * @param array $data
     */
    public function process_format_minimoodlewall_tag_very_legacy($data) {
        $this->process_format_minimoodlewall_tag($data);
    }

    /**
     * Legacy handler that reuses the regular cm/tag mapping logic.
     *
     * @param array $data
     */
    public function process_format_minimoodlewall_cmtag_legacy($data) {
        $this->process_format_minimoodlewall_cmtag($data);
    }
}
