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
     * Include profile and tag information used by the course.
     *
     * Structure: mmw_profiles / mmw_profile
     *            mmw_tags / mmw_tag / mmw_profile_tags / mmw_profile_tag
     *
     * @return backup_plugin_element
     * @throws base_element_struct_exception
     */
    protected function define_course_plugin_structure() {
        $plugin = $this->get_plugin_element(null, $this->get_format_condition(), 'minimoodlewall');
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // Profiles (formerly styles).
        $profiles = new backup_nested_element('mmw_profiles');
        $profile = new backup_nested_element(
            'mmw_profile',
            ['id'],
            [
                'name',
                'displayname',
                'scope',
                'sortorder',
                'timecreated',
                'timemodified',
            ]
        );

        // Tags (flat list, no tagset parent).
        $tags = new backup_nested_element('mmw_tags');
        $tag = new backup_nested_element(
            'mmw_tag',
            ['id'],
            [
                'name',
                'scope',
                'cardimage',
                'imgplacement',
                'imgsize',
                'filterimage',
                'bgcolor',
                'activitytype1',
                'activitytype2',
                'activitytype3',
                'sortorder',
                'timecreated',
                'timemodified',
            ]
        );

        // Per-profile tag overrides (nested under each tag).
        $profiletags = new backup_nested_element('mmw_profile_tags');
        $profiletag = new backup_nested_element(
            'mmw_profile_tag',
            ['id'],
            [
                'tagid',
                'profileid',
                'name',
                'bgcolor',
                'activitytype1',
                'activitytype2',
                'activitytype3',
                'enabled',
                'imgplacement',
                'imgsize',
                'cardimage',
                'filterimage',
                'timecreated',
                'timemodified',
            ]
        );

        $plugin->add_child($pluginwrapper);
        $pluginwrapper->add_child($profiles);
        $profiles->add_child($profile);
        $pluginwrapper->add_child($tags);
        $tags->add_child($tag);
        $tag->add_child($profiletags);
        $profiletags->add_child($profiletag);

        // Tag IDs annotation.
        $tag->annotate_ids('format_minimoodlewall_tag', 'id');
        $tag->annotate_files('format_minimoodlewall', \format_minimoodlewall\tag_manager::FILEAREA_CARDIMAGE, 'id');
        $tag->annotate_files('format_minimoodlewall', \format_minimoodlewall\tag_manager::FILEAREA_FILTERIMAGE, 'id');

        // Profile IDs annotation.
        $profile->annotate_ids('format_minimoodlewall_profile', 'id');

        // Profile tag IDs and file annotations.
        $profiletag->annotate_ids('format_minimoodlewall_profile_tag', 'id');
        $profiletag->annotate_files(
            'format_minimoodlewall',
            \format_minimoodlewall\profile_manager::FILEAREA_PROFILE_CARDIMAGE,
            'id'
        );
        $profiletag->annotate_files(
            'format_minimoodlewall',
            \format_minimoodlewall\profile_manager::FILEAREA_PROFILE_FILTERIMAGE,
            'id'
        );

        // Export profiles that have profile_tag records for tags used by this course.
        $profile->set_source_sql(
            "SELECT DISTINCT p.*
               FROM {format_minimoodlewall_profiles} p
               JOIN {format_minimoodlewall_profile_tags} pt ON pt.profileid = p.id
               JOIN {format_minimoodlewall_tags} t ON t.id = pt.tagid
               JOIN {format_minimoodlewall_cmtags} cmt ON cmt.tagid = t.id
               JOIN {course_modules} cm ON cm.id = cmt.cmid
              WHERE cm.course = :courseid
           ORDER BY p.sortorder",
            ['courseid' => backup::VAR_COURSEID]
        );

        // Export all tags used by course modules in this course.
        $tag->set_source_sql(
            "SELECT DISTINCT t.*
               FROM {format_minimoodlewall_tags} t
               JOIN {format_minimoodlewall_cmtags} cmt ON cmt.tagid = t.id
               JOIN {course_modules} cm ON cm.id = cmt.cmid
              WHERE cm.course = :courseid
           ORDER BY t.sortorder",
            ['courseid' => backup::VAR_COURSEID]
        );

        // Export profile-specific overrides for each tag.
        $profiletag->set_source_table('format_minimoodlewall_profile_tags', ['tagid' => backup::VAR_PARENTID]);

        // Section images: back up file annotations keyed by section ID.
        $sectionimages = new backup_nested_element('mmw_section_images');
        $sectionimage = new backup_nested_element('mmw_section_image', ['id'], []);
        $pluginwrapper->add_child($sectionimages);
        $sectionimages->add_child($sectionimage);

        $sectionimage->set_source_sql(
            "SELECT id FROM {course_sections} WHERE course = :courseid ORDER BY section",
            ['courseid' => backup::VAR_COURSEID]
        );
        $sectionimage->annotate_files(
            'format_minimoodlewall',
            \format_minimoodlewall\section_image_manager::FILEAREA,
            'id'
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
        $plugin = $this->get_plugin_element(null, $this->get_format_condition(), 'minimoodlewall');
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        $cmtag = new backup_nested_element('mmw_cmtag', ['cmid'], ['tagid', 'timecreated']);
        $cmtag->set_source_table('format_minimoodlewall_cmtags', ['cmid' => backup::VAR_MODID]);

        $plugin->add_child($pluginwrapper);
        $pluginwrapper->add_child($cmtag);

        return $plugin;
    }
}
