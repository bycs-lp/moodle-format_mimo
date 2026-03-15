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
 * @copyright  2025 Tobias Garske
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
    // Multi-section mode: show overview or a single wall.
    // Ensure section 0 (required by Moodle core) and section 1 (first wall) exist.
    course_create_sections_if_missing($course, [0, 1]);

    $renderer = $PAGE->get_renderer('format_minimoodlewall');

    if ($displaysection !== null) {
        // A specific section was requested — show that wall.
        $format->set_sectionnum($displaysection);
        // Remember this wall so the user returns here on their next plain course visit.
        set_user_preference('format_minimoodlewall_lastsection_' . $course->id, $displaysection);
    } else {
        // No section param — check if user explicitly requested the overview.
        $showoverview = optional_param('overview', 0, PARAM_INT);
        if ($showoverview) {
            // Clear stored preference so future plain visits also show the overview.
            unset_user_preference('format_minimoodlewall_lastsection_' . $course->id);
        }
        // If the user had a remembered section, the redirect already happened
        // in page_set_course() (before output started). If we're still here,
        // no redirect was needed — render the overview page.
    }
    // When no redirect happened and $displaysection is null,
    // do NOT call set_sectionnum() so core renders the overview page.
} else {
    // Single-section mode: non-zero section redirects are handled in page_set_course().
    // Ensure section 0 (required by Moodle core) and section 1 (the wall) exist.
    course_create_sections_if_missing($course, [0, 1]);

    $renderer = $PAGE->get_renderer('format_minimoodlewall');

    // Always display section 1 (the sole wall in this format; section 0 is hidden).
    $format->set_sectionnum(1);
}

$outputclass = $format->get_output_classname('content');
$widget = new $outputclass($format);
echo $renderer->render($widget);
