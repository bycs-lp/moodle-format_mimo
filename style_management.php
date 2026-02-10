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
 * Style management interface for minimoodlewall course format.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use format_minimoodlewall\style_manager;
use format_minimoodlewall\form\style_form;

admin_externalpage_setup('format_minimoodlewall_styles');

$action = optional_param('action', '', PARAM_ALPHA);
$styleid = optional_param('styleid', 0, PARAM_INT);

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url('/course/format/minimoodlewall/style_management.php');
$PAGE->set_title(get_string('stylemanagement', 'format_minimoodlewall'));
$PAGE->set_heading(get_string('stylemanagement', 'format_minimoodlewall'));

// Handle create/edit style.
if ($action === 'createstyle' || $action === 'editstyle') {
    $style = null;
    if ($action === 'editstyle' && $styleid) {
        $style = style_manager::get_style($styleid);
        if ($style) {
            $style->styleid = $style->id;
        }
    }

    $formurl = new moodle_url($PAGE->url, ['action' => $action, 'styleid' => $styleid]);
    $mform = new style_form($formurl, ['context' => $context]);

    if ($style) {
        $mform->set_data($style);
    }

    if ($mform->is_cancelled()) {
        redirect($PAGE->url);
    } else if ($data = $mform->get_data()) {
        if (!empty($data->styleid)) {
            style_manager::update_style(
                $data->styleid,
                [
                    'name' => $data->name,
                    'displayname' => $data->displayname,
                    'sortorder' => $data->sortorder,
                ]
            );
            $message = get_string('styleupdated', 'format_minimoodlewall');
        } else {
            style_manager::create_style(
                $data->name,
                $data->displayname,
                $data->sortorder
            );
            $message = get_string('stylecreated', 'format_minimoodlewall');
        }
        redirect($PAGE->url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading($action === 'createstyle' ?
        get_string('createstyle', 'format_minimoodlewall') :
        get_string('editstyle', 'format_minimoodlewall'));
    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

// Handle delete action.
if ($action === 'deletestyle' && confirm_sesskey()) {
    if ($styleid) {
        $style = style_manager::get_style($styleid);
        if ($style) {
            // Prevent deletion if style is in use by any course.
            $inuse = $DB->record_exists_select(
                'course_format_options',
                "format = 'minimoodlewall' AND name = 'stylevariant' AND value = :name",
                ['name' => $style->name]
            );
            if ($inuse) {
                redirect(
                    $PAGE->url,
                    get_string('styleinuse', 'format_minimoodlewall'),
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
            }

            style_manager::delete_style($styleid);
            redirect(
                $PAGE->url,
                get_string('styledeleted', 'format_minimoodlewall'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('stylemanagement', 'format_minimoodlewall'));

// Initialize delete confirmation modal.
$PAGE->requires->js_call_amd('format_minimoodlewall/style_delete_confirm', 'init');

// Get all styles.
$styles = style_manager::get_all_styles();

$templatecontext = [
    'createstyleurl' => (new moodle_url($PAGE->url, ['action' => 'createstyle']))->out(false),
    'createstyletext' => get_string('createstyle', 'format_minimoodlewall'),
    'nostylestext' => get_string('nostyles', 'format_minimoodlewall'),
    'hasstyles' => !empty($styles),
    'tableheaders' => [
        'name' => get_string('stylename', 'format_minimoodlewall'),
        'displayname' => get_string('styledisplayname', 'format_minimoodlewall'),
        'sortorder' => get_string('sortorder', 'format_minimoodlewall'),
        'actions' => get_string('actions'),
    ],
    'styles' => [],
];

foreach ($styles as $style) {
    $templatecontext['styles'][] = [
        'id' => $style->id,
        'name' => format_string($style->name),
        'displayname' => format_string($style->displayname),
        'sortorder' => $style->sortorder,
        'editurl' => (new moodle_url($PAGE->url, ['action' => 'editstyle', 'styleid' => $style->id]))->out(false),
        'deleteurl' => (new moodle_url($PAGE->url, [
            'action' => 'deletestyle',
            'styleid' => $style->id,
            'sesskey' => sesskey(),
        ]))->out(false),
        'edittitle' => get_string('editstyle', 'format_minimoodlewall'),
        'deletetitle' => get_string('deletestyle', 'format_minimoodlewall'),
    ];
}

echo $OUTPUT->render_from_template('format_minimoodlewall/style_management', $templatecontext);

echo $OUTPUT->footer();
