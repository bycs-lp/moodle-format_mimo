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
 * Description tag management interface for minimoodlewall course format.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use format_minimoodlewall\description_tag_manager;
use format_minimoodlewall\form\description_tag_form;

admin_externalpage_setup('format_minimoodlewall_descriptiontags');

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$action = optional_param('action', 'list', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$PAGE->set_url('/course/format/minimoodlewall/description_tags.php');
$PAGE->set_title(get_string('desctagmanagement', 'format_minimoodlewall'));
$PAGE->set_heading(get_string('desctagmanagement', 'format_minimoodlewall'));

// Handle delete action.
if ($delete && $confirm && confirm_sesskey()) {
    $tag = description_tag_manager::get_tag($delete);
    if ($tag) {
        $usagecount = description_tag_manager::count_descriptions_with_tag($delete);
        description_tag_manager::delete_tag($delete);
        
        if ($usagecount > 0) {
            $message = get_string('desctagdeletedwithusage', 'format_minimoodlewall', $usagecount);
        } else {
            $message = get_string('desctagdeleted', 'format_minimoodlewall');
        }
        
        redirect($PAGE->url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// Handle delete confirmation.
if ($delete && !$confirm) {
    $tag = description_tag_manager::get_tag($delete);
    if ($tag) {
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('deletetag', 'format_minimoodlewall'));
        
        $usagecount = description_tag_manager::count_descriptions_with_tag($delete);
        
        $message = get_string('confirmdeletedestag', 'format_minimoodlewall', $tag->name);
        if ($usagecount > 0) {
            $message .= '<br><br>' . get_string('desctagusagewarning', 'format_minimoodlewall', $usagecount);
        }
        
        $continueurl = new moodle_url($PAGE->url, [
            'delete' => $delete,
            'confirm' => 1,
            'sesskey' => sesskey(),
        ]);
        $cancelurl = $PAGE->url;
        
        echo $OUTPUT->confirm($message, $continueurl, $cancelurl);
        echo $OUTPUT->footer();
        exit;
    }
}

// Handle create/edit actions.
if ($action === 'edit' || $action === 'create') {
    $formurl = new moodle_url($PAGE->url, ['action' => $action, 'id' => $id]);
    $mform = new description_tag_form($formurl);
    
    if ($action === 'edit' && $id) {
        $tag = description_tag_manager::get_tag($id);
        if ($tag) {
            $mform->set_data($tag);
        }
    }
    
    if ($mform->is_cancelled()) {
        redirect($PAGE->url);
    } else if ($data = $mform->get_data()) {
        if (!empty($data->id)) {
            // Update existing tag.
            description_tag_manager::update_tag($data->id, $data->name, $data->color);
            $message = get_string('desctagsaved', 'format_minimoodlewall');
        } else {
            // Create new tag.
            description_tag_manager::create_tag($data->name, $data->color);
            $message = get_string('desctagcreated', 'format_minimoodlewall');
        }
        
        redirect($PAGE->url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    }
    
    echo $OUTPUT->header();
    
    $heading = $action === 'edit' ?
        get_string('editdesctag', 'format_minimoodlewall') :
        get_string('createdesctag', 'format_minimoodlewall');
    echo $OUTPUT->heading($heading);
    
    $mform->display();
    
    echo $OUTPUT->footer();
    exit;
}

// List all description tags.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('desctagmanagement', 'format_minimoodlewall'));

$tags = description_tag_manager::get_all_tags();

// Prepare template context.
$templatecontext = [
    'createurl' => (new moodle_url($PAGE->url, ['action' => 'create']))->out(false),
    'notags' => empty($tags),
    'tags' => [],
];

if (!empty($tags)) {
    foreach ($tags as $tag) {
        $usagecount = description_tag_manager::count_descriptions_with_tag($tag->id);
        
        $editurl = new moodle_url($PAGE->url, ['action' => 'edit', 'id' => $tag->id]);
        $deleteurl = new moodle_url($PAGE->url, ['delete' => $tag->id, 'sesskey' => sesskey()]);
        
        $templatecontext['tags'][] = [
            'name' => $tag->name,
            'color' => $tag->color,
            'usagecount' => $usagecount,
            'editurl' => $editurl->out(false),
            'deleteurl' => $deleteurl->out(false),
            'editicon' => $OUTPUT->pix_icon('t/edit', get_string('edit')),
            'deleteicon' => $OUTPUT->pix_icon('t/delete', get_string('delete')),
        ];
    }
}

echo $OUTPUT->render_from_template('format_minimoodlewall/description_tags_list', $templatecontext);

echo $OUTPUT->footer();
