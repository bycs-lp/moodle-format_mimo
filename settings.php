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

$categoryname = 'format_minimoodlewall_settings';

// We overwrite $settings here that is defined in format\plugininfo\format::load_settings().
$settings = new admin_category($categoryname, new lang_string('pluginname', 'format_minimoodlewall'));

$settingspage = new admin_settingpage('format_minimoodlewall', new lang_string('pluginname', 'format_minimoodlewall'));

if ($ADMIN->fulltree) {
    // Theme selection.
    $settingspage->add(new admin_setting_configselect(
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

    // Link to tag management page.
    $tagmanageurl = new moodle_url('/course/format/minimoodlewall/tag_management.php');
    $activitydescurl = new moodle_url('/course/format/minimoodlewall/activity_descriptions.php');
    $links = html_writer::link($tagmanageurl, get_string('setting_tagmanagement_link', 'format_minimoodlewall')) .
        '<br>' .
        html_writer::link($activitydescurl, get_string('setting_activitydescriptions_link', 'format_minimoodlewall'));
    
    $settingspage->add(new admin_setting_heading(
        'format_minimoodlewall/tagmanagement',
        get_string('setting_tagmanagement', 'format_minimoodlewall'),
        $links
    ));
}

$settings->add($categoryname, $settingspage);

$settings->add($categoryname, new admin_externalpage(
    'format_minimoodlewall_tags',
    get_string('tagmanagement', 'format_minimoodlewall'),
    new moodle_url('/course/format/minimoodlewall/tag_management.php'),
    'moodle/site:config'
));

$settings->add($categoryname, new admin_externalpage(
    'format_minimoodlewall_activitydescriptions',
    get_string('activitydescriptions', 'format_minimoodlewall'),
    new moodle_url('/course/format/minimoodlewall/activity_descriptions.php'),
    'moodle/site:config'
));
