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
 * Activity profile management interface for minimoodlewall course format.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use format_minimoodlewall\profile_manager;

admin_externalpage_setup('format_minimoodlewall_profiles');

$action = optional_param('action', '', PARAM_ALPHA);
$profileid = optional_param('profileid', 0, PARAM_INT);

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url('/course/format/minimoodlewall/profile_management.php');
$PAGE->set_title(get_string('profilemanagement', 'format_minimoodlewall'));
$PAGE->set_heading(get_string('profilemanagement', 'format_minimoodlewall'));

// Handle delete action.
if ($action === 'deleteprofile' && confirm_sesskey()) {
    if ($profileid) {
        $profile = profile_manager::get_profile($profileid);
        if ($profile) {
            // Prevent deletion if profile is in use by any course.
            $inuse = $DB->record_exists_select(
                'course_format_options',
                "format = 'minimoodlewall' AND name = 'activityprofile' AND value = :name",
                ['name' => $profile->name]
            );
            if ($inuse) {
                redirect(
                    $PAGE->url,
                    get_string('profileinuse', 'format_minimoodlewall'),
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
            }

            profile_manager::delete_profile($profileid);
            redirect(
                $PAGE->url,
                get_string('profiledeleted', 'format_minimoodlewall'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
    }
}

echo $OUTPUT->header();
echo \format_minimoodlewall\admin_page_tabs::render('profiles');
echo $OUTPUT->heading(get_string('profilemanagement', 'format_minimoodlewall'));

// Initialize delete confirmation modal.
$PAGE->requires->js_call_amd('format_minimoodlewall/profile_delete_confirm', 'init');

// Initialize modal form JS for create/edit profile.
$PAGE->requires->js_call_amd('format_minimoodlewall/profile_management_modal', 'init');

// Get all profiles.
$profiles = profile_manager::get_all_profiles();

$templatecontext = [
    'createprofiletext' => get_string('createprofile', 'format_minimoodlewall'),
    'noprofilestext' => get_string('noprofiles', 'format_minimoodlewall'),
    'hasprofiles' => !empty($profiles),
    'tableheaders' => [
        'name' => get_string('profilename', 'format_minimoodlewall'),
        'displayname' => get_string('profiledisplayname', 'format_minimoodlewall'),
        'sortorder' => get_string('sortorder', 'format_minimoodlewall'),
        'actions' => get_string('actions'),
    ],
    'profiles' => [],
];

foreach ($profiles as $profile) {
    $templatecontext['profiles'][] = [
        'id' => $profile->id,
        'name' => format_string($profile->name),
        'displayname' => format_string($profile->displayname),
        'sortorder' => $profile->sortorder,
        'deleteurl' => (new moodle_url($PAGE->url, [
            'action' => 'deleteprofile',
            'profileid' => $profile->id,
            'sesskey' => sesskey(),
        ]))->out(false),
        'edittitle' => get_string('editprofile', 'format_minimoodlewall'),
        'deletetitle' => get_string('deleteprofile', 'format_minimoodlewall'),
    ];
}

echo $OUTPUT->render_from_template('format_minimoodlewall/profile_management', $templatecontext);

echo $OUTPUT->footer();
