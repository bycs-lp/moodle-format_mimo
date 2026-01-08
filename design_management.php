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
 * Design management interface for minimoodlewall course format.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use format_minimoodlewall\design_manager;
use format_minimoodlewall\form\design_form;

admin_externalpage_setup('format_minimoodlewall_designs');

$action = optional_param('action', '', PARAM_ALPHA);
$designid = optional_param('designid', 0, PARAM_INT);

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url('/course/format/minimoodlewall/design_management.php');
$PAGE->set_title(get_string('designmanagement', 'format_minimoodlewall'));
$PAGE->set_heading(get_string('designmanagement', 'format_minimoodlewall'));

// Handle create/edit design.
if ($action === 'createdesign' || $action === 'editdesign') {
    $design = null;
    if ($action === 'editdesign' && $designid) {
        $design = design_manager::get_design($designid);
        if ($design) {
            $design->designid = $design->id;
        }
    }

    $formurl = new moodle_url($PAGE->url, ['action' => $action, 'designid' => $designid]);
    $mform = new design_form($formurl, ['context' => $context]);

    if ($design) {
        $mform->set_data($design);
    }

    if ($mform->is_cancelled()) {
        redirect($PAGE->url);
    } else if ($data = $mform->get_data()) {
        if (!empty($data->designid)) {
            design_manager::update_design(
                $data->designid,
                [
                    'name' => $data->name,
                    'displayname' => $data->displayname,
                    'sortorder' => $data->sortorder,
                ]
            );
            $message = get_string('designupdated', 'format_minimoodlewall');
        } else {
            design_manager::create_design(
                $data->name,
                $data->displayname,
                $data->sortorder
            );
            $message = get_string('designcreated', 'format_minimoodlewall');
        }
        redirect($PAGE->url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading($action === 'createdesign' ?
        get_string('createdesign', 'format_minimoodlewall') :
        get_string('editdesign', 'format_minimoodlewall'));
    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

// Handle delete action.
if ($action === 'deletedesign' && confirm_sesskey()) {
    if ($designid) {
        $design = design_manager::get_design($designid);
        if ($design) {
            // Prevent deletion if design is in use by any course.
            $inuse = $DB->record_exists_select(
                'course_format_options',
                "format = 'minimoodlewall' AND name = 'designvariant' AND value = :name",
                ['name' => $design->name]
            );
            if ($inuse) {
                redirect(
                    $PAGE->url,
                    get_string('designinuse', 'format_minimoodlewall'),
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
            }

            design_manager::delete_design($designid);
            redirect(
                $PAGE->url,
                get_string('designdeleted', 'format_minimoodlewall'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('designmanagement', 'format_minimoodlewall'));

// Initialize delete confirmation modal.
$PAGE->requires->js_call_amd('format_minimoodlewall/design_delete_confirm', 'init');

// Get all designs.
$designs = design_manager::get_all_designs();

$templatecontext = [
    'createdesignurl' => (new moodle_url($PAGE->url, ['action' => 'createdesign']))->out(false),
    'createdesigntext' => get_string('createdesign', 'format_minimoodlewall'),
    'nodesignstext' => get_string('nodesigns', 'format_minimoodlewall'),
    'hasdesigns' => !empty($designs),
    'tableheaders' => [
        'name' => get_string('designname', 'format_minimoodlewall'),
        'displayname' => get_string('designdisplayname', 'format_minimoodlewall'),
        'sortorder' => get_string('sortorder', 'format_minimoodlewall'),
        'actions' => get_string('actions'),
    ],
    'designs' => [],
];

foreach ($designs as $design) {
    $templatecontext['designs'][] = [
        'id' => $design->id,
        'name' => format_string($design->name),
        'displayname' => format_string($design->displayname),
        'sortorder' => $design->sortorder,
        'editurl' => (new moodle_url($PAGE->url, ['action' => 'editdesign', 'designid' => $design->id]))->out(false),
        'deleteurl' => (new moodle_url($PAGE->url, [
            'action' => 'deletedesign',
            'designid' => $design->id,
            'sesskey' => sesskey(),
        ]))->out(false),
        'edittitle' => get_string('editdesign', 'format_minimoodlewall'),
        'deletetitle' => get_string('deletedesign', 'format_minimoodlewall'),
    ];
}

echo $OUTPUT->render_from_template('format_minimoodlewall/design_management', $templatecontext);

echo $OUTPUT->footer();
