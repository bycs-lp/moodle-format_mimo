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
 * Event observer for format_minimoodlewall.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall;

/**
 * Event observer class.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Handle course_module_created event to check for pending tag assignment.
     *
     * When a course module is created, check if there's a pending tag in the session
     * and assign it automatically.
     *
     * @param \core\event\course_module_created $event The event object
     */
    public static function course_module_created(\core\event\course_module_created $event) {
        global $SESSION;

        // Check if this course uses minimoodlewall format.
        $courseid = $event->courseid;
        $course = get_course($courseid);
        
        if ($course->format !== 'minimoodlewall') {
            return;
        }

        // Check if there's a pending tag in the session.
        if (isset($SESSION->format_minimoodlewall_pending_tag)) {
            $tagid = $SESSION->format_minimoodlewall_pending_tag;
            $cmid = $event->objectid;

            // Validate that the tag exists and belongs to this course's tagset.
            $formatoptions = course_get_format($courseid)->get_format_options();
            $tagsetid = $formatoptions['tagsetid'] ?? 0;

            if ($tagsetid > 0) {
                $tag = tag_manager::get_tag($tagid);
                if ($tag && $tag->tagsetid == $tagsetid) {
                    // Assign the tag to the newly created course module.
                    tag_manager::assign_tag_to_cm($cmid, $tagid);
                }
            }

            // Clear the pending tag from session.
            unset($SESSION->format_minimoodlewall_pending_tag);
        }
    }
}
