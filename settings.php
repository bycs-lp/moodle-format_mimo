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
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Single visible entry in admin nav – points to tag management page.
// Users navigate to other admin pages via tabs on each page.
$settings = new admin_externalpage(
    'format_minimoodlewall_tags',
    new lang_string('pluginname', 'format_minimoodlewall'),
    new moodle_url('/course/format/minimoodlewall/tag_management.php'),
    'moodle/site:config'
);

// Hidden settings page for distraction-free mode (still functional via tab navigation).
$settingspage = new admin_settingpage(
    'format_minimoodlewall',
    new lang_string('distractionfreemode', 'format_minimoodlewall'),
    'moodle/site:config',
    true
);

if ($ADMIN->fulltree) {
    // Tab navigation across all admin pages.
    require_once(__DIR__ . '/classes/admin_setting_tabs.php');
    $settingspage->add(new format_minimoodlewall_admin_setting_tabs());

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
}

$ADMIN->add('formatsettings', $settingspage);

// Hidden external pages – accessible via tab navigation, not shown in admin tree.
$ADMIN->add('formatsettings', new admin_externalpage(
    'format_minimoodlewall_descriptiontags',
    get_string('desctagmanagement', 'format_minimoodlewall'),
    new moodle_url('/course/format/minimoodlewall/description_tags.php'),
    'moodle/site:config',
    true
));

$ADMIN->add('formatsettings', new admin_externalpage(
    'format_minimoodlewall_activitydescriptions',
    get_string('activitydescriptions', 'format_minimoodlewall'),
    new moodle_url('/course/format/minimoodlewall/activity_descriptions.php'),
    'moodle/site:config',
    true
));

$ADMIN->add('formatsettings', new admin_externalpage(
    'format_minimoodlewall_profiles',
    get_string('profilemanagement', 'format_minimoodlewall'),
    new moodle_url('/course/format/minimoodlewall/profile_management.php'),
    'moodle/site:config',
    true
));

$ADMIN->add('formatsettings', new admin_externalpage(
    'format_minimoodlewall_completiondefaults',
    get_string('completiondefaults', 'format_minimoodlewall'),
    new moodle_url('/course/format/minimoodlewall/completion_defaults.php'),
    'moodle/site:config',
    true
));
