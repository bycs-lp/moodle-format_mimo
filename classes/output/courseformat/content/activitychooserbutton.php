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
 * Activity chooser button output for mimo wall format.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo\output\courseformat\content;

use core_courseformat\output\local\content\activitychooserbutton as activitychooserbutton_base;
use renderer_base;
use stdClass;

/**
 * Activity chooser button with tag support.
 *
 * Extends the core activitychooserbutton (introduced in MDL-86337).
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activitychooserbutton extends activitychooserbutton_base {
    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output typically, the renderer that's calling this function
     * @return stdClass data context for a mustache template
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $PAGE;

        // Get the base data from parent.
        $data = parent::export_for_template($output);

        // Add tag information if tags are configured for this course.
        $courseid = $this->section->course;

        if ($PAGE->user_is_editing()) {
            // Get tags selected for this course.
            $tags = \format_mimo\tag_manager::get_tags_for_course($courseid);

            // Add tag data to context.
            $data->tags = array_values($tags);
            $data->hastags = !empty($tags);
            $data->courseid = $courseid;
            $data->uniqid = uniqid();
        } else {
            $data->hastags = false;
        }

        return $data;
    }

    /**
     * Get the template name for this output.
     *
     * @param renderer_base $renderer typically, the renderer that's calling this function
     * @return string The template name
     */
    public function get_template_name(renderer_base $renderer): string {
        global $PAGE;

        // Check if we have tags configured for this course.
        $courseid = $this->section->course;

        if ($PAGE->user_is_editing()) {
            $tags = \format_mimo\tag_manager::get_tags_for_course($courseid);

            if (!empty($tags)) {
                // Use our custom template with tag chooser.
                return 'format_mimo/local/content/activitychooserbutton';
            }
        }

        // Fall back to core template.
        return 'core_courseformat/local/content/activitychooserbutton';
    }
}
