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
 * Settings for minimoodlewall course format.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // Theme selection.
    $settings->add(new admin_setting_configselect(
        'format_minimoodlewall/defaulttheme',
        get_string('setting_defaulttheme', 'format_minimoodlewall'),
        get_string('setting_defaulttheme_desc', 'format_minimoodlewall'),
        'normal',
        [
            'normal' => get_string('theme_normal', 'format_minimoodlewall'),
            'dark' => get_string('theme_dark', 'format_minimoodlewall'),
            'colorful' => get_string('theme_colorful', 'format_minimoodlewall'),
        ]
    ));

    // Items per page for pagination.
    $settings->add(new admin_setting_configtext(
        'format_minimoodlewall/itemsperpage',
        get_string('setting_itemsperpage', 'format_minimoodlewall'),
        get_string('setting_itemsperpage_desc', 'format_minimoodlewall'),
        8,
        PARAM_INT
    ));

    // Enable tag filtering.
    $settings->add(new admin_setting_configcheckbox(
        'format_minimoodlewall/enablefiltering',
        get_string('setting_enablefiltering', 'format_minimoodlewall'),
        get_string('setting_enablefiltering_desc', 'format_minimoodlewall'),
        1
    ));

    // Enable activity reordering via drag-drop.
    $settings->add(new admin_setting_configcheckbox(
        'format_minimoodlewall/enablereordering',
        get_string('setting_enablereordering', 'format_minimoodlewall'),
        get_string('setting_enablereordering_desc', 'format_minimoodlewall'),
        1
    ));

    // Link to tag management page.
    $tagmanageurl = new moodle_url('/course/format/minimoodlewall/tag_management.php');
    $settings->add(new admin_setting_heading(
        'format_minimoodlewall/tagmanagement',
        get_string('setting_tagmanagement', 'format_minimoodlewall'),
        html_writer::link($tagmanageurl, get_string('setting_tagmanagement_link', 'format_minimoodlewall'))
    ));
}
