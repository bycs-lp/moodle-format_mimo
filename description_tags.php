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
 * Description tag management interface for mimo course format.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use format_mimo\description_tag_manager;

admin_externalpage_setup('format_mimo_descriptiontags');

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$PAGE->set_url('/course/format/mimo/description_tags.php');
$PAGE->set_title(get_string('desctagmanagement', 'format_mimo'));
$PAGE->set_heading(get_string('desctagmanagement', 'format_mimo'));

// Handle delete action (from AJAX).
if ($delete && $confirm && confirm_sesskey()) {
    $tag = description_tag_manager::get_tag($delete);
    if ($tag) {
        $usagecount = description_tag_manager::count_descriptions_with_tag($delete);
        description_tag_manager::delete_tag($delete);

        if ($usagecount > 0) {
            $message = get_string('desctagdeletedwithusage', 'format_mimo', $usagecount);
        } else {
            $message = get_string('desctagdeleted', 'format_mimo');
        }

        redirect($PAGE->url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// Output starts here.
echo $OUTPUT->header();
echo \format_mimo\admin_page_tabs::render('descriptiontags');

// List all description tags.
echo $OUTPUT->heading(get_string('desctagmanagement', 'format_mimo'));

echo html_writer::div(
    get_string('desctagmanagement_help', 'format_mimo'),
    'mb-4'
);

// Initialize JavaScript for dynamic forms.
$PAGE->requires->js_call_amd('format_mimo/description_tag_management', 'init');

$tags = description_tag_manager::get_all_tags();

// Prepare template context.
$templatecontext = [
    'notags' => empty($tags),
    'tags' => [],
];

if (!empty($tags)) {
    foreach ($tags as $tag) {
        $usagecount = description_tag_manager::count_descriptions_with_tag($tag->id);

        $templatecontext['tags'][] = [
            'id' => $tag->id,
            'name' => $tag->name,
            'color' => $tag->color,
            'usagecount' => $usagecount,
            'editicon' => $OUTPUT->pix_icon('t/edit', get_string('edit')),
            'deleteicon' => $OUTPUT->pix_icon('t/delete', get_string('delete')),
        ];
    }
}

echo $OUTPUT->render_from_template('format_mimo/description_tags_list', $templatecontext);

echo $OUTPUT->footer();
