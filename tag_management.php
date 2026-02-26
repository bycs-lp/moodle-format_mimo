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
use format_minimoodlewall\form\tag_form;

admin_externalpage_setup('format_minimoodlewall_tags');

$action = optional_param('action', '', PARAM_ALPHA);
$tagid = optional_param('tagid', 0, PARAM_INT);

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url('/course/format/minimoodlewall/tag_management.php');
$PAGE->set_title(get_string('tagmanagement', 'format_minimoodlewall'));
$PAGE->set_heading(get_string('tagmanagement', 'format_minimoodlewall'));

// Handle create/edit tag.
if ($action === 'createtag' || $action === 'edittag') {
    $tag = null;
    if ($action === 'edittag' && $tagid) {
        $tag = tag_manager::get_tag($tagid);
        if ($tag) {
            $tag->tagid = $tag->id;
        }
    }

    // Pass the current URL with action parameter to the form.
    $formurl = new moodle_url($PAGE->url, ['action' => $action, 'tagid' => $tagid]);
    $mform = new tag_form($formurl, ['context' => $context, 'tagid' => $tag->id ?? 0]);

    // Prepare form data with profile-specific image drafts.
    $formdata = [];
    $profiles = profile_manager::get_all_profiles();
    $profileids = array_keys($profiles);

    if ($tag) {
        $formdata = (array) $tag;
        // Prepare draft areas for each profile's images.
        foreach ($profiles as $profile) {
            $formdata['cardimage_profile_' . $profile->id] =
                profile_manager::prepare_cardimage_draft($tag->id, $profile->id);
            $formdata['filterimage_profile_' . $profile->id] =
                profile_manager::prepare_filterimage_draft($tag->id, $profile->id);
        }
        $formdata['profileids'] = implode(',', $profileids);
    } else {
        // New tag - prepare empty draft areas.
        foreach ($profiles as $profile) {
            $formdata['cardimage_profile_' . $profile->id] = 0;
            $formdata['filterimage_profile_' . $profile->id] = 0;
        }
        $formdata['profileids'] = implode(',', $profileids);
    }
    $mform->set_data($formdata);

    if ($mform->is_cancelled()) {
        redirect($PAGE->url);
    } else if ($data = $mform->get_data()) {
        if (!empty($data->tagid)) {
            // Update existing tag.
            tag_manager::update_tag(
                $data->tagid,
                [
                    'name' => $data->name,
                    'activitytype1' => $data->activitytype1,
                    'activitytype2' => $data->activitytype2,
                    'activitytype3' => $data->activitytype3,
                    'bgcolor' => $data->bgcolor,
                    'imgplacement' => $data->imgplacement,
                ]
            );
            $currenttagid = $data->tagid;
            $message = get_string('edittag', 'format_minimoodlewall');
        } else {
            // Create new tag.
            $currenttagid = tag_manager::create_tag(
                $data->name,
                null,
                null,
                $data->activitytype1,
                $data->activitytype2,
                $data->activitytype3,
                $data->bgcolor,
                $data->imgplacement
            );
            $message = get_string('createtag', 'format_minimoodlewall');
        }

        // Save profile-specific images.
        $savedprofileids = !empty($data->profileids) ? explode(',', $data->profileids) : [];
        foreach ($savedprofileids as $profileid) {
            $cardfield = 'cardimage_profile_' . $profileid;
            $filterfield = 'filterimage_profile_' . $profileid;

            if (isset($data->$cardfield)) {
                profile_manager::save_cardimage_from_draft($currenttagid, (int)$profileid, (int)$data->$cardfield);
            }
            if (isset($data->$filterfield)) {
                profile_manager::save_filterimage_from_draft($currenttagid, (int)$profileid, (int)$data->$filterfield);
            }
        }

        // Save profile-specific override fields.
        foreach ($savedprofileids as $profileid) {
            $overrides = [];
            $namefield = 'profile_name_' . $profileid;
            $bgcolorfield = 'profile_bgcolor_' . $profileid;
            $at1field = 'profile_activitytype1_' . $profileid;
            $at2field = 'profile_activitytype2_' . $profileid;
            $at3field = 'profile_activitytype3_' . $profileid;
            $enabledfield = 'profile_enabled_' . $profileid;

            if (isset($data->$namefield)) {
                $overrides['name'] = $data->$namefield !== '' ? $data->$namefield : null;
            }
            if (isset($data->$bgcolorfield)) {
                $overrides['bgcolor'] = $data->$bgcolorfield !== '' ? $data->$bgcolorfield : null;
            }
            if (isset($data->$at1field)) {
                $overrides['activitytype1'] = $data->$at1field !== '' ? $data->$at1field : null;
            }
            if (isset($data->$at2field)) {
                $overrides['activitytype2'] = $data->$at2field !== '' ? $data->$at2field : null;
            }
            if (isset($data->$at3field)) {
                $overrides['activitytype3'] = $data->$at3field !== '' ? $data->$at3field : null;
            }
            if (isset($data->$enabledfield)) {
                $overrides['enabled'] = (int)$data->$enabledfield;
            }

            if (!empty($overrides)) {
                $pt = profile_manager::get_or_create_profile_tag($currenttagid, (int)$profileid);
                profile_manager::update_profile_tag($pt->id, $overrides);
            }
        }

        redirect($PAGE->url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading($action === 'createtag' ?
        get_string('createtag', 'format_minimoodlewall') :
        get_string('edittag', 'format_minimoodlewall'));
    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

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
echo $OUTPUT->heading(get_string('tagmanagement', 'format_minimoodlewall'));

// Initialize delete confirmation modal.
$PAGE->requires->js_call_amd('format_minimoodlewall/tag_delete_confirm', 'init');

// Build template context with flat tag list.
$tags = tag_manager::get_all_tags();

$templatecontext = [
    'createtagurl' => (new moodle_url($PAGE->url, ['action' => 'createtag']))->out(false),
    'createtagtext' => get_string('createtag', 'format_minimoodlewall'),
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
    'tags' => [],
];

foreach ($tags as $tag) {
    $cardimgurl = tag_manager::get_cardimage_url($tag);
    $accentcolor = tag_manager::get_tag_accent_color($tag);

    $templatecontext['tags'][] = [
        'id' => $tag->id,
        'name' => format_string($tag->name),
        'cardimageurl' => $cardimgurl ? $cardimgurl->out(false) : null,
        'bgcolor' => $accentcolor,
        'activitytype1' => $tag->activitytype1,
        'activitytype2' => $tag->activitytype2 ?: '-',
        'activitytype3' => $tag->activitytype3 ?: '-',
        'editurl' => (new moodle_url($PAGE->url, [
            'action' => 'edittag',
            'tagid' => $tag->id,
        ]))->out(false),
        'deleteurl' => (new moodle_url($PAGE->url, [
            'action' => 'deletetag',
            'tagid' => $tag->id,
            'sesskey' => sesskey(),
        ]))->out(false),
        'edittitle' => get_string('edittag', 'format_minimoodlewall'),
        'deletetitle' => get_string('deletetag', 'format_minimoodlewall'),
    ];
}

echo $OUTPUT->render_from_template('format_minimoodlewall/tag_management', $templatecontext);

echo $OUTPUT->footer();
