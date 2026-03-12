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
 * Tag management interface for minimoodlewall course format.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use format_minimoodlewall\tag_manager;
use format_minimoodlewall\profile_manager;

admin_externalpage_setup('format_minimoodlewall_tags');

$action = optional_param('action', '', PARAM_ALPHA);
$tagid = optional_param('tagid', 0, PARAM_INT);
$profilename = optional_param('profile', '', PARAM_ALPHANUMEXT);

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$urlparams = [];
if ($profilename !== '') {
    $urlparams['profile'] = $profilename;
}
$PAGE->set_url('/course/format/minimoodlewall/tag_management.php', $urlparams);
$PAGE->set_title(get_string('tagmanagement', 'format_minimoodlewall'));
$PAGE->set_heading(get_string('tagmanagement', 'format_minimoodlewall'));

// Handle delete tag.
if ($action === 'deletetag' && confirm_sesskey()) {
    if ($tagid) {
        tag_manager::delete_tag($tagid);
        redirect(
            $PAGE->url,
            get_string('deletetag', 'format_minimoodlewall'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

echo $OUTPUT->header();
echo \format_minimoodlewall\admin_page_tabs::render('tags');
echo $OUTPUT->heading(get_string('tagmanagement', 'format_minimoodlewall'));

// Initialize delete confirmation modal.
$PAGE->requires->js_call_amd('format_minimoodlewall/tag_delete_confirm', 'init');

// Build template context with flat tag list.
$tags = tag_manager::get_all_tags();
$allprofiles = profile_manager::get_all_profiles();

// Determine the active profile for initial render.
$activeprofileid = 0;
if ($profilename !== '') {
    $activeprofileobj = profile_manager::get_profile_by_name($profilename);
    if ($activeprofileobj) {
        $activeprofileid = (int) $activeprofileobj->id;
    }
}

// Build profile buttons.
$profilebuttons = [];
$profilebuttons[] = [
    'name' => '',
    'displayname' => get_string('basetagfields', 'format_minimoodlewall'),
    'active' => ($profilename === ''),
];
foreach ($allprofiles as $profile) {
    $profilebuttons[] = [
        'name' => $profile->name,
        'displayname' => $profile->displayname,
        'active' => ($profilename === $profile->name),
    ];
}

// Build profile name → ID map for JS.
$profileidmap = ['' => 0];
foreach ($allprofiles as $profile) {
    $profileidmap[$profile->name] = (int) $profile->id;
}

// Build per-profile tag data for JS switching.
$tagprofiledata = [];
foreach ($tags as $tag) {
    $tagdata = [];

    // Default view = base values.
    $cardimgurl = tag_manager::get_cardimage_url($tag);
    $tagdata[''] = [
        'name' => format_string($tag->name),
        'cardimageurl' => $cardimgurl ? $cardimgurl->out(false) : '',
        'bgcolor' => tag_manager::get_tag_accent_color($tag),
        'activitytype1' => $tag->activitytype1 ?: '-',
        'activitytype2' => $tag->activitytype2 ?: '-',
        'activitytype3' => $tag->activitytype3 ?: '-',
        'enabled' => true,
    ];

    // Per-profile resolved views.
    foreach ($allprofiles as $profile) {
        $resolved = profile_manager::resolve_tag_for_profile($tag, $profile->id);
        $profileimgurl = tag_manager::get_cardimage_url($tag, $profile->name);
        $tagdata[$profile->name] = [
            'name' => format_string($resolved->name),
            'cardimageurl' => $profileimgurl ? $profileimgurl->out(false) : '',
            'bgcolor' => tag_manager::get_tag_accent_color($resolved),
            'activitytype1' => $resolved->activitytype1 ?: '-',
            'activitytype2' => $resolved->activitytype2 ?: '-',
            'activitytype3' => $resolved->activitytype3 ?: '-',
            'enabled' => (bool) $resolved->enabled,
        ];
    }
    $tagprofiledata[$tag->id] = $tagdata;
}

// Build initial tag list for the selected profile.
$templatetags = [];
foreach ($tags as $tag) {
    $data = $tagprofiledata[$tag->id][$profilename] ?? $tagprofiledata[$tag->id][''];

    $templatetags[] = [
        'id' => $tag->id,
        'name' => $data['name'],
        'cardimageurl' => $data['cardimageurl'] ?: null,
        'bgcolor' => $data['bgcolor'],
        'activitytype1' => $data['activitytype1'],
        'activitytype2' => $data['activitytype2'],
        'activitytype3' => $data['activitytype3'],
        'enabled' => $data['enabled'],
        'disabled' => !$data['enabled'],
        'deleteurl' => (new moodle_url($PAGE->url, [
            'action' => 'deletetag',
            'tagid' => $tag->id,
            'sesskey' => sesskey(),
        ]))->out(false),
        'edittitle' => get_string('edittag', 'format_minimoodlewall'),
        'deletetitle' => get_string('deletetag', 'format_minimoodlewall'),
    ];
}

$templatecontext = [
    'createtagtext' => get_string('createtag', 'format_minimoodlewall'),
    'activeprofileid' => $activeprofileid,
    'notagstext' => get_string('notags', 'format_minimoodlewall'),
    'hastags' => !empty($tags),
    'tableheaders' => [
        'cardimage' => get_string('cardimage', 'format_minimoodlewall'),
        'name' => get_string('tagname', 'format_minimoodlewall'),
        'bgcolor' => get_string('tagbgcolor', 'format_minimoodlewall'),
        'activitytype1' => get_string('activitytype1', 'format_minimoodlewall'),
        'activitytype2' => get_string('activitytype2', 'format_minimoodlewall'),
        'activitytype3' => get_string('activitytype3', 'format_minimoodlewall'),
        'actions' => get_string('actions'),
    ],
    'profilebuttons' => $profilebuttons,
    'tags' => $templatetags,
    'disabledtext' => get_string('profiletag_disabled', 'format_minimoodlewall'),
    'tagprofiledatajson' => json_encode($tagprofiledata),
    'profileidmapjson' => json_encode($profileidmap),
    'currentprofile' => $profilename,
    'managementurl' => (new moodle_url('/course/format/minimoodlewall/tag_management.php'))->out(false),
];

// Initialize profile switcher JS (data is passed via data attributes in template).
$PAGE->requires->js_call_amd('format_minimoodlewall/tag_profile_switcher', 'init');

// Initialize modal form JS for create/edit tag.
$PAGE->requires->js_call_amd('format_minimoodlewall/tag_management_modal', 'init');

echo $OUTPUT->render_from_template('format_minimoodlewall/tag_management', $templatecontext);

echo $OUTPUT->footer();
