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
use html_writer;
use moodle_url;

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
    debugging("Action: {$action}, tagsetid: {$tagsetid}", DEBUG_DEVELOPER);
    
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
        debugging("Form data received: name={$data->name}, description={$data->description}, tagsetid=" . (isset($data->tagsetid) ? $data->tagsetid : 'not set'), DEBUG_DEVELOPER);
        
        if (!empty($data->tagsetid)) {
            debugging("Updating tagset {$data->tagsetid}", DEBUG_DEVELOPER);
            $success = tag_manager::update_tagset($data->tagsetid, $data->name, $data->description);
            $message = get_string('edittagset', 'format_minimoodlewall');
        } else {
            debugging("Creating new tagset with name={$data->name}", DEBUG_DEVELOPER);
            try {
                $id = tag_manager::create_tagset($data->name, $data->description);
                debugging("Tagset created with ID: {$id}", DEBUG_DEVELOPER);
                $message = get_string('createtagset', 'format_minimoodlewall');
                $success = !empty($id);
                if ($success) {
                    // Debug: verify it was created.
                    $check = tag_manager::get_tagset($id);
                    if (!$check) {
                        debugging('Tagset created with ID ' . $id . ' but cannot be retrieved', DEBUG_DEVELOPER);
                        $success = false;
                    }
                }
            } catch (\Exception $e) {
                debugging('Error creating tagset: ' . $e->getMessage(), DEBUG_DEVELOPER);
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
                    'description' => $data->description,
                    'activitytype1' => $data->activitytype1,
                    'activitytype2' => $data->activitytype2,
                ]
            );
            tag_manager::save_cardimage_from_draft($data->tagid, $data->cardimagefile);
            tag_manager::save_filterimage_from_draft($data->tagid, $data->filterimagefile);
            $message = get_string('edittag', 'format_minimoodlewall');
        } else {
            $newtagid = tag_manager::create_tag(
                $data->tagsetid,
                $data->name,
                $data->description,
                null,
                null,
                $data->activitytype1,
                $data->activitytype2
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

// Display tagsets.
$tagsets = tag_manager::get_tagsets();

echo html_writer::start_div('mb-3');
echo html_writer::link(
    new moodle_url($PAGE->url, ['action' => 'createtagset']),
    get_string('createtagset', 'format_minimoodlewall'),
    ['class' => 'btn btn-primary']
);
echo html_writer::end_div();

if (empty($tagsets)) {
    echo $OUTPUT->notification(get_string('notagsets', 'format_minimoodlewall'), \core\output\notification::NOTIFY_INFO);
} else {
    foreach ($tagsets as $tagset) {
        echo html_writer::start_div('card mb-3');
        echo html_writer::start_div('card-header d-flex justify-content-between align-items-center');
        echo html_writer::tag('h3', format_string($tagset->name), ['class' => 'mb-0']);
        echo html_writer::start_div('btn-group');
        echo html_writer::link(
            new moodle_url($PAGE->url, ['action' => 'edittagset', 'tagsetid' => $tagset->id]),
            get_string('edittagset', 'format_minimoodlewall'),
            ['class' => 'btn btn-sm btn-secondary']
        );
        echo html_writer::link(
            new moodle_url($PAGE->url, ['action' => 'deletetagset', 'tagsetid' => $tagset->id, 'sesskey' => sesskey()]),
            get_string('deletetagset', 'format_minimoodlewall'),
            ['class' => 'btn btn-sm btn-danger', 'onclick' => 'return confirm("' . get_string('confirm') . '");']
        );
        echo html_writer::end_div();
        echo html_writer::end_div();

        echo html_writer::start_div('card-body');
        
        if (!empty($tagset->description)) {
            echo html_writer::tag('p', format_text($tagset->description), ['class' => 'text-muted']);
        }

        // Display tags in this tagset.
        $tags = tag_manager::get_tags_by_tagset($tagset->id);
        
        echo html_writer::start_div('mb-2');
        echo html_writer::link(
            new moodle_url($PAGE->url, ['action' => 'createtag', 'tagsetid' => $tagset->id]),
            get_string('createtag', 'format_minimoodlewall'),
            ['class' => 'btn btn-sm btn-success']
        );
        echo html_writer::end_div();

        if (empty($tags)) {
            echo $OUTPUT->notification(get_string('notags', 'format_minimoodlewall'), \core\output\notification::NOTIFY_INFO);
        } else {
            echo html_writer::start_tag('table', ['class' => 'table table-striped']);
            echo html_writer::start_tag('thead');
            echo html_writer::start_tag('tr');
            echo html_writer::tag('th', get_string('cardimage', 'format_minimoodlewall'));
            echo html_writer::tag('th', get_string('tagname', 'format_minimoodlewall'));
            echo html_writer::tag('th', get_string('activitytype1', 'format_minimoodlewall'));
            echo html_writer::tag('th', get_string('activitytype2', 'format_minimoodlewall'));
            echo html_writer::tag('th', get_string('actions'));
            echo html_writer::end_tag('tr');
            echo html_writer::end_tag('thead');
            echo html_writer::start_tag('tbody');

            foreach ($tags as $tag) {
                echo html_writer::start_tag('tr');
                
                // Card image preview.
                $cardimgurl = tag_manager::get_cardimage_url($tag);
                echo html_writer::start_tag('td');
                if ($cardimgurl) {
                    echo html_writer::img($cardimgurl, $tag->name, ['style' => 'width: 80px; height: 50px; object-fit: cover;']);
                } else {
                    echo html_writer::span('-', 'text-muted');
                }
                echo html_writer::end_tag('td');
                
                echo html_writer::tag('td', format_string($tag->name));
                echo html_writer::tag('td', $tag->activitytype1);
                echo html_writer::tag('td', $tag->activitytype2 ?: '-');
                
                // Actions.
                echo html_writer::start_tag('td');
                echo html_writer::start_div('btn-group');
                echo html_writer::link(
                    new moodle_url($PAGE->url, ['action' => 'edittag', 'tagid' => $tag->id]),
                    get_string('edittag', 'format_minimoodlewall'),
                    ['class' => 'btn btn-sm btn-secondary']
                );
                echo html_writer::link(
                    new moodle_url($PAGE->url, ['action' => 'deletetag', 'tagid' => $tag->id, 'sesskey' => sesskey()]),
                    get_string('deletetag', 'format_minimoodlewall'),
                    ['class' => 'btn btn-sm btn-danger', 'onclick' => 'return confirm("' . get_string('confirm') . '");']
                );
                echo html_writer::end_div();
                echo html_writer::end_tag('td');
                
                echo html_writer::end_tag('tr');
            }

            echo html_writer::end_tag('tbody');
            echo html_writer::end_tag('table');
        }

        echo html_writer::end_div();
        echo html_writer::end_div();
    }
}

echo $OUTPUT->footer();
