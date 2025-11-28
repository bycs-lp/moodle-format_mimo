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
use format_minimoodlewall\form\tagset_form;
use format_minimoodlewall\form\tag_form;

admin_externalpage_setup('format_minimoodlewall_tags');

$action = optional_param('action', '', PARAM_ALPHA);
$tagsetid = optional_param('tagsetid', 0, PARAM_INT);
$tagid = optional_param('tagid', 0, PARAM_INT);

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url('/course/format/minimoodlewall/tag_management.php');
$PAGE->set_title(get_string('tagmanagement', 'format_minimoodlewall'));
$PAGE->set_heading(get_string('tagmanagement', 'format_minimoodlewall'));

// Handle create/edit tagset.
if ($action === 'createtagset' || $action === 'edittagset') {
    $tagset = null;
    if ($action === 'edittagset' && $tagsetid) {
        $tagset = tag_manager::get_tagset($tagsetid);
        if ($tagset) {
            $tagset->tagsetid = $tagset->id;
        }
    }
    
    // Pass the current URL with action parameter to the form.
    $formurl = new moodle_url($PAGE->url, ['action' => $action, 'tagsetid' => $tagsetid]);
    $mform = new tagset_form($formurl);
    
    if ($tagset) {
        $mform->set_data($tagset);
    }
    
    if ($mform->is_cancelled()) {
        redirect($PAGE->url);
    } else if ($data = $mform->get_data()) {
        if (!empty($data->tagsetid)) {
            $success = tag_manager::update_tagset($data->tagsetid, $data->name);
            $message = get_string('edittagset', 'format_minimoodlewall');
        } else {
            try {
                $id = tag_manager::create_tagset($data->name);
                $message = get_string('createtagset', 'format_minimoodlewall');
                $success = !empty($id);
            } catch (\Exception $e) {
                $success = false;
                $message = 'Error: ' . $e->getMessage();
            }
        }
        if ($success) {
            redirect($PAGE->url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            redirect($PAGE->url, $message, null, \core\output\notification::NOTIFY_ERROR);
        }
    }
    
    echo $OUTPUT->header();
    echo $OUTPUT->heading($action === 'createtagset' ?
        get_string('createtagset', 'format_minimoodlewall') :
        get_string('edittagset', 'format_minimoodlewall'));
    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

// Handle create/edit tag.
if ($action === 'createtag' || $action === 'edittag') {
    $tag = null;
    if ($action === 'edittag' && $tagid) {
        $tag = tag_manager::get_tag($tagid);
        if ($tag) {
            $tag->tagid = $tag->id;
            $tagsetid = $tag->tagsetid;
        }
    }
    
    // Pass the current URL with action parameter to the form.
    $formurl = new moodle_url($PAGE->url, ['action' => $action, 'tagsetid' => $tagsetid, 'tagid' => $tagid]);
    $mform = new tag_form($formurl, ['context' => $context, 'tagid' => $tag->id ?? 0]);
    
    if ($tag) {
        $tag->cardimagefile = tag_manager::prepare_cardimage_draft($tag->id);
        $tag->filterimagefile = tag_manager::prepare_filterimage_draft($tag->id);
        $mform->set_data($tag);
    } else if ($tagsetid) {
        $mform->set_data([
            'tagsetid' => $tagsetid,
            'cardimagefile' => tag_manager::prepare_cardimage_draft(null),
            'filterimagefile' => tag_manager::prepare_filterimage_draft(null),
        ]);
    } else {
        $mform->set_data([
            'cardimagefile' => tag_manager::prepare_cardimage_draft(null),
            'filterimagefile' => tag_manager::prepare_filterimage_draft(null),
        ]);
    }
    
    if ($mform->is_cancelled()) {
        redirect($PAGE->url);
    } else if ($data = $mform->get_data()) {
        if (!empty($data->tagid)) {
            tag_manager::update_tag(
                $data->tagid,
                [
                    'name' => $data->name,
                    'activitytype1' => $data->activitytype1,
                    'activitytype2' => $data->activitytype2,
                    'activitytype3' => $data->activitytype3,
                    'bgcolor' => $data->bgcolor,
                ]
            );
            tag_manager::save_cardimage_from_draft($data->tagid, $data->cardimagefile);
            tag_manager::save_filterimage_from_draft($data->tagid, $data->filterimagefile);
            $message = get_string('edittag', 'format_minimoodlewall');
        } else {
            $newtagid = tag_manager::create_tag(
                $data->tagsetid,
                $data->name,
                null,
                null,
                $data->activitytype1,
                $data->activitytype2,
                $data->activitytype3,
                $data->bgcolor
            );
            tag_manager::save_cardimage_from_draft($newtagid, (int)$data->cardimagefile);
            tag_manager::save_filterimage_from_draft($newtagid, (int)$data->filterimagefile);
            $message = get_string('createtag', 'format_minimoodlewall');
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

// Handle actions.
if ($action && confirm_sesskey()) {
    switch ($action) {
        case 'deletetagset':
            if ($tagsetid) {
                tag_manager::delete_tagset($tagsetid);
                redirect(
                    $PAGE->url,
                    get_string('deletetagset', 'format_minimoodlewall'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            }
            break;
        case 'deletetag':
            if ($tagid) {
                tag_manager::delete_tag($tagid);
                redirect(
                    $PAGE->url,
                    get_string('deletetag', 'format_minimoodlewall'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            }
            break;
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('tagmanagement', 'format_minimoodlewall'));

// Initialize delete confirmation modal.
$PAGE->requires->js_call_amd('format_minimoodlewall/tag_delete_confirm', 'init');

// Prepare template context.
$tagsets = tag_manager::get_tagsets();

$templatecontext = [
    'createtagseturl' => (new moodle_url($PAGE->url, ['action' => 'createtagset']))->out(false),
    'createtagsettext' => get_string('createtagset', 'format_minimoodlewall'),
    'notagsetstext' => get_string('notagsets', 'format_minimoodlewall'),
    'hastagsets' => !empty($tagsets),
    'tagsets' => [],
];

foreach ($tagsets as $tagset) {
    $tags = tag_manager::get_tags_by_tagset($tagset->id);
    
    $tagsetdata = [
        'id' => $tagset->id,
        'name' => format_string($tagset->name),
        'editurl' => (new moodle_url($PAGE->url, ['action' => 'edittagset', 'tagsetid' => $tagset->id]))->out(false),
        'deleteurl' => (new moodle_url($PAGE->url, [
            'action' => 'deletetagset',
            'tagsetid' => $tagset->id,
            'sesskey' => sesskey(),
        ]))->out(false),
        'edittext' => get_string('edittagset', 'format_minimoodlewall'),
        'deletetext' => get_string('deletetagset', 'format_minimoodlewall'),
        'createtagurl' => (new moodle_url($PAGE->url, ['action' => 'createtag', 'tagsetid' => $tagset->id]))->out(false),
        'createtagtext' => get_string('createtag', 'format_minimoodlewall'),
        'hastags' => !empty($tags),
        'notagstext' => get_string('notags', 'format_minimoodlewall'),
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
        
        $tagsetdata['tags'][] = [
            'id' => $tag->id,
            'name' => format_string($tag->name),
            'cardimageurl' => $cardimgurl ? $cardimgurl->out(false) : null,
            'bgcolor' => $accentcolor,
            'activitytype1' => $tag->activitytype1,
            'activitytype2' => $tag->activitytype2 ?: '-',
            'activitytype3' => $tag->activitytype3 ?: '-',
            'editurl' => (new moodle_url($PAGE->url, ['action' => 'edittag', 'tagid' => $tag->id]))->out(false),
            'deleteurl' => (new moodle_url($PAGE->url, [
                'action' => 'deletetag',
                'tagid' => $tag->id,
                'sesskey' => sesskey(),
            ]))->out(false),
            'edittitle' => get_string('edittag', 'format_minimoodlewall'),
            'deletetitle' => get_string('deletetag', 'format_minimoodlewall'),
        ];
    }
    
    $templatecontext['tagsets'][] = $tagsetdata;
}

echo $OUTPUT->render_from_template('format_minimoodlewall/tag_management', $templatecontext);

echo $OUTPUT->footer();
