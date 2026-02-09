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

            // Validate that the tag exists and is selected for this course.
            $coursetags = tag_manager::get_tags_for_course($courseid);
            $tag = tag_manager::get_tag($tagid);

            if ($tag && isset($coursetags[$tag->id])) {
                // Assign the tag to the newly created course module.
                tag_manager::assign_tag_to_cm($cmid, $tagid);
            }

            // Clear the pending tag from session.
            unset($SESSION->format_minimoodlewall_pending_tag);
        }
    }

    /**
     * Handle course_module_deleted event to clean up orphaned cmtag records.
     *
     * @param \core\event\course_module_deleted $event The event object
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event) {
        global $DB;

        $cmid = $event->objectid;
        $DB->delete_records('format_minimoodlewall_cmtags', ['cmid' => $cmid]);
        tag_manager::clear_mapping_cache();
    }

    /**
     * Handle course_deleted event to clean up all cmtag records for the course.
     *
     * @param \core\event\course_deleted $event The event object
     */
    public static function course_deleted(\core\event\course_deleted $event) {
        global $DB;

        // Delete all cmtag records for course modules that belonged to this course.
        // By the time the course is deleted, course_modules may already be gone,
        // so we rely on the cmids stored in the cmtags table.
        $sql = "DELETE FROM {format_minimoodlewall_cmtags}
                 WHERE cmid NOT IN (SELECT id FROM {course_modules})";
        $DB->execute($sql);
        tag_manager::clear_mapping_cache();
    }
}
