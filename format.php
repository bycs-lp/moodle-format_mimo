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
 * Minimal Moodle Wall course format - display a single section with all activities.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/completionlib.php');

// Retrieve course format option fields and add them to the $course object.
$format = course_get_format($course);
$course = $format->get_course();
$ismultisection = $format->is_multisection_enabled();

if ($ismultisection) {
    // Multi-section mode: allow section navigation, ensure section 0 exists.
    course_create_sections_if_missing($course, 0);

    $renderer = $PAGE->get_renderer('format_minimoodlewall');

    // Use $displaysection from course/view.php to show the requested section.
    if (!is_null($displaysection)) {
        $format->set_sectionnum($displaysection);
    }
} else {
    // Single-section mode: redirect non-zero section URLs and lock to section 0.
    $sectionnum = optional_param('section', null, PARAM_INT);
    if ($sectionnum !== null && $sectionnum != 0) {
        redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
    }

    // Make sure section 0 exists.
    course_create_sections_if_missing($course, [0]);

    $renderer = $PAGE->get_renderer('format_minimoodlewall');

    // Always display section 0 (the only section in this format).
    $format->set_sectionnum(0);
}

$outputclass = $format->get_output_classname('content');
$widget = new $outputclass($format);
echo $renderer->render($widget);
