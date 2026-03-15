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
 * Minimoodlewall completion defaults management page.
 *
 * Allows site administrators to configure per-module-type completion defaults
 * that override core Moodle defaults when activities are created in
 * minimoodlewall courses.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/course/format/minimoodlewall/classes/form/completion_defaults_form.php');

// Parameters.
$modid = optional_param('modid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_INT);

// Authentication and capabilities.
admin_externalpage_setup('format_minimoodlewall_completiondefaults');
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$pageurl = new moodle_url('/course/format/minimoodlewall/completion_defaults.php');
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('completiondefaults', 'format_minimoodlewall'));
$PAGE->set_heading(get_string('completiondefaults', 'format_minimoodlewall'));

// Handle delete action.
if ($action === 'delete' && $modid) {
    if ($confirm) {
        require_sesskey();
        \format_minimoodlewall\completion_defaults_manager::delete_default($modid);
        \core\notification::add(
            get_string('completiondefaultdeleted', 'format_minimoodlewall'),
            \core\notification::SUCCESS
        );
        redirect($pageurl);
    }
    // Show confirmation.
    $module = $DB->get_record('modules', ['id' => $modid], 'id, name', MUST_EXIST);
    echo $OUTPUT->header();
    echo \format_minimoodlewall\admin_page_tabs::render('completiondefaults');
    echo $OUTPUT->confirm(
        get_string('confirmdeletecompletiondefault', 'format_minimoodlewall', get_string('modulename', $module->name)),
        new moodle_url($pageurl, ['modid' => $modid, 'action' => 'delete', 'confirm' => 1]),
        $pageurl
    );
    echo $OUTPUT->footer();
    die;
}

// Handle edit form for a specific module type.
if ($modid) {
    $module = $DB->get_record('modules', ['id' => $modid], '*', MUST_EXIST);

    // We need a course object for the form. Use the site course (SITEID).
    $course = get_course(SITEID);

    // Check if we have existing minimoodlewall defaults for this module type.
    $mmwdefaults = \format_minimoodlewall\completion_defaults_manager::get_default($modid);

    // Build module info for the form (mimics core's structure).
    $moduleinfo = new stdClass();
    $moduleinfo->id = $module->id;
    $moduleinfo->name = $module->name;
    $moduleinfo->formattedname = get_string('modulename', $module->name);
    $moduleinfo->canmanage = true;

    $form = new format_minimoodlewall_completion_defaults_form(
        new moodle_url($pageurl, ['modid' => $modid]),
        [
            'course' => $course,
            'modules' => [$module->id => $moduleinfo],
            'displaycancel' => true,
            'forceuniqueid' => true,
            'mmw_defaults' => $mmwdefaults,
        ]
    );

    if ($form->is_cancelled()) {
        redirect($pageurl);
    }

    if ($data = $form->get_data()) {
        $suffix = $form->get_suffix();
        $packed = \format_minimoodlewall\completion_defaults_manager::pack_form_data($data, $suffix);
        \format_minimoodlewall\completion_defaults_manager::save_default($modid, $packed);
        \core\notification::add(
            get_string('completiondefaultsaved', 'format_minimoodlewall'),
            \core\notification::SUCCESS
        );
        redirect($pageurl);
    }

    // Render the edit form.
    echo $OUTPUT->header();
    echo \format_minimoodlewall\admin_page_tabs::render('completiondefaults');
    echo $OUTPUT->heading(get_string('editcompletiondefault', 'format_minimoodlewall', $moduleinfo->formattedname));
    echo html_writer::tag('p', get_string('completiondefaults_desc', 'format_minimoodlewall'));
    $form->display();
    echo $OUTPUT->footer();
    die;
}

// Main listing page: show all module types with their override status.
$manager = new \core_completion\manager(SITEID);
$allmodules = $manager->get_activities_and_resources(false);
$mmwdefaults = \format_minimoodlewall\completion_defaults_manager::get_all_defaults_by_module();

echo $OUTPUT->header();
echo \format_minimoodlewall\admin_page_tabs::render('completiondefaults');
echo $OUTPUT->heading(get_string('completiondefaults', 'format_minimoodlewall'));
echo html_writer::tag('p', get_string('completiondefaults_desc', 'format_minimoodlewall'));

// Build table of module types.
$table = new html_table();
$table->head = [
    get_string('activitytype', 'format_minimoodlewall'),
    get_string('completiondefaultstatus', 'format_minimoodlewall'),
    get_string('actions'),
];
$table->attributes['class'] = 'admintable generaltable';
$table->data = [];

foreach ($allmodules->modules as $module) {
    $row = new html_table_row();

    // Module name with icon.
    $modulecell = $OUTPUT->pix_icon('monologo', $module->formattedname, 'mod_' . $module->name, ['class' => 'smallicon']) .
        ' ' . $module->formattedname;
    $row->cells[] = $modulecell;

    // Override status.
    if (isset($mmwdefaults[$module->id])) {
        $def = $mmwdefaults[$module->id];
        $completionlabels = [
            COMPLETION_TRACKING_NONE => get_string('completion_none', 'completion'),
            COMPLETION_TRACKING_MANUAL => get_string('completion_manual', 'completion'),
            COMPLETION_TRACKING_AUTOMATIC => get_string('completion_automatic', 'completion'),
        ];
        $statustext = $completionlabels[(int)$def->completion] ?? get_string('completion_none', 'completion');
        $row->cells[] = html_writer::tag('span', $statustext, ['class' => 'badge badge-info bg-info']);
    } else {
        $row->cells[] = html_writer::tag(
            'span',
            get_string('nocompletiondefault', 'format_minimoodlewall'),
            ['class' => 'text-muted']
        );
    }

    // Actions.
    $actions = [];
    $editurl = new moodle_url($pageurl, ['modid' => $module->id]);
    $actions[] = html_writer::link(
        $editurl,
        $OUTPUT->pix_icon('t/edit', get_string('edit')),
        ['title' => get_string('edit')]
    );
    if (isset($mmwdefaults[$module->id])) {
        $deleteurl = new moodle_url($pageurl, ['modid' => $module->id, 'action' => 'delete']);
        $actions[] = html_writer::link(
            $deleteurl,
            $OUTPUT->pix_icon('t/delete', get_string('delete')),
            ['title' => get_string('clearcompletiondefault', 'format_minimoodlewall')]
        );
    }
    $row->cells[] = implode(' ', $actions);

    $table->data[] = $row;
}

echo html_writer::table($table);
echo $OUTPUT->footer();
