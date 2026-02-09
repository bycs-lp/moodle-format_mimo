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
 * Backup handler for format_minimoodlewall.
 *
 * @package    format_minimoodlewall
 * @category   backup
 * @copyright  2025 MBS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Include minimoodlewall specific information in backups.
 */
class backup_format_minimoodlewall_plugin extends backup_format_plugin {
    /**
     * Include tagset and tag information used by the course.
     *
     * Structure: mmw_tagsets / mmw_tagset / mmw_tags / mmw_tag
     *
     * @return backup_plugin_element
     * @throws base_element_struct_exception
     */
    protected function define_course_plugin_structure() {
        // Always branch through the format plugin optigroup so restore can detect the format.
        $plugin = $this->get_plugin_element(null, $this->get_format_condition(), 'minimoodlewall');
        // Ensure predictable XML like plugin_format_minimoodlewall_course so get_pathfor() works on restore.
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        $tagsets = new backup_nested_element('mmw_tagsets');
        $tagset = new backup_nested_element(
            'mmw_tagset',
            ['id'],
            [
                'name',
                'description',
                'sortorder',
                'timecreated',
                'timemodified',
            ]
        );

        $tags = new backup_nested_element('mmw_tags');
        $tag = new backup_nested_element(
            'mmw_tag',
            ['id'],
            [
                'tagsetid',
                'name',
                'description',
                'cardimage',
                'filterimage',
                'activitytype1',
                'activitytype2',
                'sortorder',
                'timecreated',
                'timemodified',
            ]
        );

        $plugin->add_child($pluginwrapper);
        $pluginwrapper->add_child($tagsets);
        $tagsets->add_child($tagset);
        $tagset->add_child($tags);
        $tags->add_child($tag);

        // Tagset IDs are annotated for mapping on restore.
        $tagset->annotate_ids('format_minimoodlewall_tagset', 'id');

        // Tag IDs are annotated so other plugins (modules) can map references later.
        $tag->annotate_ids('format_minimoodlewall_tag', 'id');
        $tag->annotate_files('format_minimoodlewall', \format_minimoodlewall\tag_manager::FILEAREA_CARDIMAGE, 'id');
        $tag->annotate_files('format_minimoodlewall', \format_minimoodlewall\tag_manager::FILEAREA_FILTERIMAGE, 'id');

        // Export all tagsets that have tags used by course modules in this course.
        $tagset->set_source_sql(
            "SELECT DISTINCT ts.*
               FROM {format_minimoodlewall_tagsets} ts
               JOIN {format_minimoodlewall_tags} t ON t.tagsetid = ts.id
               JOIN {format_minimoodlewall_cmtags} cmt ON cmt.tagid = t.id
               JOIN {course_modules} cm ON cm.id = cmt.cmid
              WHERE cm.course = :courseid
           ORDER BY ts.sortorder",
            ['courseid' => backup::VAR_COURSEID]
        );

        // Export all tags within each tagset that are used by course modules.
        $tag->set_source_sql(
            "SELECT DISTINCT t.*
               FROM {format_minimoodlewall_tags} t
               JOIN {format_minimoodlewall_cmtags} cmt ON cmt.tagid = t.id
               JOIN {course_modules} cm ON cm.id = cmt.cmid
              WHERE t.tagsetid = :tagsetid
                AND cm.course = :courseid
           ORDER BY t.sortorder",
            [
                'tagsetid' => backup::VAR_PARENTID,
                'courseid' => backup::VAR_COURSEID,
            ]
        );

        return $plugin;
    }

    /**
     * Include cm tag mapping for individual activities.
     *
     * @return backup_plugin_element
     * @throws base_element_struct_exception
     */
    protected function define_module_plugin_structure() {
        // Same idea for per-module data so restore finds plugin_format_minimoodlewall_module.
        $plugin = $this->get_plugin_element(null, $this->get_format_condition(), 'minimoodlewall');
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        $cmtag = new backup_nested_element('mmw_cmtag', ['cmid'], ['tagid', 'timecreated']);
        // Per-CM mapping is stored in its own plugin blob for fast lookup during restore.
        $cmtag->set_source_table('format_minimoodlewall_cmtags', ['cmid' => backup::VAR_MODID]);

        $plugin->add_child($pluginwrapper);
        $pluginwrapper->add_child($cmtag);

        return $plugin;
    }
}
