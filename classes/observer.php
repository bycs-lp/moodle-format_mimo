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

        // Apply minimoodlewall completion defaults if the module was created with core defaults.
        self::apply_completion_override($event, $course);
    }

    /**
     * Apply minimoodlewall completion default overrides to a newly created course module.
     *
     * If the module's completion settings match the core Moodle defaults (meaning the
     * teacher did not customize them), and a minimoodlewall-specific override exists
     * for this module type, silently replace the completion settings.
     *
     * @param \core\event\course_module_created $event The event object.
     * @param \stdClass $course The course record.
     */
    protected static function apply_completion_override(\core\event\course_module_created $event, \stdClass $course): void {
        global $DB;

        $cmid = $event->objectid;
        $modname = $event->other['modulename'];

        // Get the module type id from modules table.
        $module = $DB->get_record('modules', ['name' => $modname], 'id', IGNORE_MISSING);
        if (!$module) {
            return;
        }

        // Check if we have a minimoodlewall completion override for this module type.
        $mmwdefaults = completion_defaults_manager::get_default($module->id);
        if (!$mmwdefaults) {
            return;
        }

        // Get the core defaults for comparison.
        $coredefaults = \core_completion\manager::get_default_completion($course, $module, true);

        // If the core default is "no tracking" and nothing was set, there's nothing meaningful to compare.
        // But we should still apply the override if we have one — the teacher got "none" by default
        // and we want to change it to our settings.
        if (
            (int)($coredefaults->completion ?? COMPLETION_TRACKING_NONE) === COMPLETION_TRACKING_NONE
            && (int)$mmwdefaults->completion === COMPLETION_TRACKING_NONE
        ) {
            // Both are "none", nothing to override.
            return;
        }

        // Get the current course_modules record.
        $cmrecord = $DB->get_record('course_modules', ['id' => $cmid]);
        if (!$cmrecord) {
            return;
        }

        // Check if the module's completion matches core defaults.
        // If the teacher customized completion during creation, we leave it alone.
        if (!completion_defaults_manager::matches_core_defaults($cmrecord, $coredefaults, $modname)) {
            return;
        }

        // Apply the minimoodlewall override.
        completion_defaults_manager::apply_defaults($cmrecord, $mmwdefaults, $modname);
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


}
