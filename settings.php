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
    // Distraction-free mode settings.
    $distractionfreeselectorsdefault = [
        'nav.fixed-top',
        '#nav-drawer',
        '#page-footer',
        '.activity-navigation',
        '#region-main-settings-menu',
        '.drawer-toggles',
        '.secondary-navigation',
    ];

    $nopaddingselectorsdefault = [
        '#page',
        '#topofscroll',
    ];

    $settingspage->add(new admin_setting_configtextarea(
        'format_minimoodlewall/distractionfreeselectors',
        get_string('distractionfreeselectors', 'format_minimoodlewall'),
        get_string('distractionfreeselectors_desc', 'format_minimoodlewall'),
        implode("\n", $distractionfreeselectorsdefault),
        PARAM_TEXT
    ));

    $settingspage->add(new admin_setting_configtextarea(
        'format_minimoodlewall/nopaddingselectors',
        get_string('nopaddingselectors', 'format_minimoodlewall'),
        get_string('nopaddingselectors_desc', 'format_minimoodlewall'),
        implode("\n", $nopaddingselectorsdefault),
        PARAM_TEXT
    ));

    $settingspage->add(new admin_setting_configcheckbox(
        'format_minimoodlewall/closedrawers',
        get_string('closedrawers', 'format_minimoodlewall'),
        get_string('closedrawers_desc', 'format_minimoodlewall'),
        1
    ));

    // Link to tag management page.
    $tagmanageurl = new moodle_url('/course/format/minimoodlewall/tag_management.php');
    $activitydescurl = new moodle_url('/course/format/minimoodlewall/activity_descriptions.php');
    $desctagurl = new moodle_url('/course/format/minimoodlewall/description_tags.php');
    $profilemanageurl = new moodle_url('/course/format/minimoodlewall/profile_management.php');
    $completiondefaultsurl = new moodle_url('/course/format/minimoodlewall/completion_defaults.php');
    $links = html_writer::link($tagmanageurl, get_string('setting_tagmanagement_link', 'format_minimoodlewall')) .
        '<br>' .
        html_writer::link($activitydescurl, get_string('setting_activitydescriptions_link', 'format_minimoodlewall')) .
        '<br>' .
        html_writer::link($desctagurl, get_string('desctagmanagement', 'format_minimoodlewall')) .
        '<br>' .
        html_writer::link($profilemanageurl, get_string('profilemanagement', 'format_minimoodlewall')) .
        '<br>' .
        html_writer::link($completiondefaultsurl, get_string('completiondefaults', 'format_minimoodlewall'));

    $settingspage->add(new admin_setting_heading(
        'format_minimoodlewall/tagmanagement',
        get_string('setting_tagmanagement', 'format_minimoodlewall'),
        $links
    ));
}

$settings->add($categoryname, $settingspage);

$settings->add($categoryname, new admin_externalpage(
    'format_minimoodlewall_tags',
    get_string('setting_tagmanagement', 'format_minimoodlewall'),
    new moodle_url('/course/format/minimoodlewall/tag_management.php'),
    'moodle/site:config'
));

$settings->add($categoryname, new admin_externalpage(
    'format_minimoodlewall_descriptiontags',
    get_string('desctagmanagement', 'format_minimoodlewall'),
    new moodle_url('/course/format/minimoodlewall/description_tags.php'),
    'moodle/site:config'
));

$settings->add($categoryname, new admin_externalpage(
    'format_minimoodlewall_activitydescriptions',
    get_string('activitydescriptions', 'format_minimoodlewall'),
    new moodle_url('/course/format/minimoodlewall/activity_descriptions.php'),
    'moodle/site:config'
));

$settings->add($categoryname, new admin_externalpage(
    'format_minimoodlewall_profiles',
    get_string('profilemanagement', 'format_minimoodlewall'),
    new moodle_url('/course/format/minimoodlewall/profile_management.php'),
    'moodle/site:config'
));

$settings->add($categoryname, new admin_externalpage(
    'format_minimoodlewall_completiondefaults',
    get_string('completiondefaults', 'format_minimoodlewall'),
    new moodle_url('/course/format/minimoodlewall/completion_defaults.php'),
    'moodle/site:config'
));
