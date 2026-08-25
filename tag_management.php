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
 * Tag management interface for mimo course format.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use format_mimo\tag_manager;
use format_mimo\profile_manager;

admin_externalpage_setup('format_mimo_tags');

$action = optional_param('action', '', PARAM_ALPHA);
$tagid = optional_param('tagid', 0, PARAM_INT);
$profilename = optional_param('profile', '', PARAM_ALPHANUMEXT);

$context = \core\context\system::instance();
require_capability('moodle/site:config', $context);

$tagmanager = \core\di::get(tag_manager::class);
$profilemanager = \core\di::get(profile_manager::class);

// Default view: the site's default tagset. Unknown profile names fall back too.
if ($profilename === '' || !$profilemanager->get_profile_by_name($profilename)) {
    $profilename = $profilemanager->get_default_profile_name();
}

$urlparams = [];
if ($profilename !== '') {
    $urlparams['profile'] = $profilename;
}
$PAGE->set_url('/course/format/mimo/tag_management.php', $urlparams);
$PAGE->set_title(get_string('tagmanagement', 'format_mimo'));
$PAGE->set_heading(get_string('tagmanagement', 'format_mimo'));

// Handle delete tag (only allowed once the tag is disabled in every set).
if ($action === 'deletetag' && confirm_sesskey()) {
    if ($tagid && $tagmanager->is_tag_disabled_everywhere($tagid)) {
        $tagmanager->delete_tag($tagid);
        redirect(
            $PAGE->url,
            get_string('deletetag', 'format_mimo'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    redirect(
        $PAGE->url,
        get_string('tagdelete_stillenabled', 'format_mimo'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// Handle per-set enable/disable toggle.
if ($action === 'toggletag' && confirm_sesskey()) {
    $toggleprofile = $profilemanager->get_profile_by_name($profilename);
    if ($tagid && $toggleprofile) {
        $current = $profilemanager->get_profile_tag_for_profile($tagid, (int) $toggleprofile->id);
        $newstate = !($current && (int) $current->enabled === 1);
        $profilemanager->materialize_profile_tag($tagid, (int) $toggleprofile->id, [], $newstate);
    }
    redirect($PAGE->url);
}

// Handle promote tag to global.
if ($action === 'promotetag' && confirm_sesskey()) {
    if ($tagid) {
        $tagmanager->promote_tag_to_global($tagid);
        redirect(
            $PAGE->url,
            get_string('promotetoglobal_success', 'format_mimo'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Handle promote profile to global.
$promoteid = optional_param('promoteid', 0, PARAM_INT);
if ($action === 'promoteprofile' && confirm_sesskey()) {
    if ($promoteid) {
        $profilemanager->promote_profile_to_global($promoteid);
        redirect(
            $PAGE->url,
            get_string('promotetoglobal_success', 'format_mimo'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

echo $OUTPUT->header();
echo \format_mimo\admin_page_tabs::render('tags');
echo $OUTPUT->heading(get_string('tagmanagement', 'format_mimo'));

// Initialize delete confirmation modal.
$PAGE->requires->js_call_amd('format_mimo/tag_delete_confirm', 'init');

// Build template context with flat tag list.
$tags = $tagmanager->get_all_tags();
$allprofiles = $profilemanager->get_all_profiles();

// Determine the active profile for initial render.
$activeprofileid = 0;
if ($profilename !== '') {
    $activeprofileobj = $profilemanager->get_profile_by_name($profilename);
    if ($activeprofileobj) {
        $activeprofileid = (int) $activeprofileobj->id;
    }
}

// Build profile buttons.
$profilebuttons = [];
foreach ($allprofiles as $profile) {
    $isimported = ($profile->scope ?? 'global') === 'imported';
    $profilebuttons[] = [
        'name' => $profile->name,
        'displayname' => $profile->displayname,
        'active' => ($profilename === $profile->name),
        'isimported' => $isimported,
        'promoteurl' => $isimported ? (new moodle_url($PAGE->url, [
            'action' => 'promoteprofile',
            'promoteid' => $profile->id,
            'sesskey' => sesskey(),
        ]))->out(false) : null,
    ];
}

// Build profile name → ID map for JS.
$profileidmap = [];
foreach ($allprofiles as $profile) {
    $profileidmap[$profile->name] = (int) $profile->id;
}

// Build per-profile tag data for JS switching.
$tagprofiledata = [];
foreach ($tags as $tag) {
    $tagdata = [];

    foreach ($allprofiles as $profile) {
        $resolved = $profilemanager->resolve_tag_for_profile($tag, $profile->id);
        $profileimgurl = $tagmanager->get_cardimage_url($tag, $profile->name);
        $tagdata[$profile->name] = [
            'name' => format_string($resolved->name),
            'cardimageurl' => $profileimgurl ? $profileimgurl->out(false) : '',
            'bgcolor' => $tagmanager->get_tag_accent_color($resolved),
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
    $data = $tagprofiledata[$tag->id][$profilename] ?? null;
    if ($data === null) {
        continue;
    }

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
        'isimported' => ($tag->scope ?? 'global') === 'imported',
        'candelete' => $tagmanager->is_tag_disabled_everywhere((int) $tag->id),
        'deleteurl' => (new moodle_url($PAGE->url, [
            'action' => 'deletetag',
            'tagid' => $tag->id,
            'sesskey' => sesskey(),
        ]))->out(false),
        'toggleurl' => (new moodle_url($PAGE->url, [
            'action' => 'toggletag',
            'tagid' => $tag->id,
            'sesskey' => sesskey(),
        ]))->out(false),
        'promoteurl' => (new moodle_url($PAGE->url, [
            'action' => 'promotetag',
            'tagid' => $tag->id,
            'sesskey' => sesskey(),
        ]))->out(false),
        'edittitle' => get_string('edittag', 'format_mimo'),
        'deletetitle' => get_string('deletetag', 'format_mimo'),
        'toggletitle' => get_string('toggletaginset', 'format_mimo'),
    ];
}

$templatecontext = [
    'createtagtext' => get_string('createtag', 'format_mimo'),
    'activeprofileid' => $activeprofileid,
    'notagstext' => get_string('notags', 'format_mimo'),
    'hastags' => !empty($tags),
    'tableheaders' => [
        'cardimage' => get_string('cardimage', 'format_mimo'),
        'name' => get_string('tagname', 'format_mimo'),
        'bgcolor' => get_string('tagbgcolor', 'format_mimo'),
        'activitytype1' => get_string('activitytype1', 'format_mimo'),
        'activitytype2' => get_string('activitytype2', 'format_mimo'),
        'activitytype3' => get_string('activitytype3', 'format_mimo'),
        'actions' => get_string('actions'),
    ],
    'profilebuttons' => $profilebuttons,
    'tags' => $templatetags,
    'disabledtext' => get_string('profiletag_disabled', 'format_mimo'),
    'importedtext' => get_string('tag_imported', 'format_mimo'),
    'promotetoglobaltext' => get_string('promotetoglobal', 'format_mimo'),
    'tagprofiledatajson' => json_encode($tagprofiledata),
    'profileidmapjson' => json_encode($profileidmap),
    'currentprofile' => $profilename,
    'managementurl' => (new moodle_url('/course/format/mimo/tag_management.php'))->out(false),
];

// Initialize profile switcher JS (data is passed via data attributes in template).
$PAGE->requires->js_call_amd('format_mimo/tag_profile_switcher', 'init');

// Initialize modal form JS for create/edit tag.
$PAGE->requires->js_call_amd('format_mimo/tag_management_modal', 'init');

echo $OUTPUT->render_from_template('format_mimo/tag_management', $templatecontext);

echo $OUTPUT->footer();
