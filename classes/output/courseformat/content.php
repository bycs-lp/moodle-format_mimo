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
 * Contains the content output class.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall\output\courseformat;

use core_courseformat\output\local\content as content_base;

/**
 * Base class to render the course content.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content extends content_base {
    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output typically, the renderer that's calling this function
     * @return \stdClass data context for a mustache template
     */
    public function export_for_template(\renderer_base $output): \stdClass {
        global $PAGE;

        $data = parent::export_for_template($output);

        // Get the course format options.
        $course = $this->format->get_course();
        $designvariant = $course->designvariant ?? 'classic';

        // Validate design exists in database, fallback to classic if not.
        $design = \format_minimoodlewall\design_manager::get_design_by_name($designvariant);
        if (!$design) {
            $designvariant = 'classic';
        }

        $data->designvariant = $designvariant;
        $data->designclass = 'minimoodlewall-design-' . $designvariant;

        // Initialize the tag chooser button JavaScript if editing is on and course has selected tags.
        $tags = \format_minimoodlewall\tag_manager::get_tags_for_course($course->id);
        if ($PAGE->user_is_editing() && !empty($tags)) {
            $PAGE->requires->js_call_amd('format_minimoodlewall/tagchooserbutton', 'init');

            // Pass tag data to the template.
            $data->tags = array_values($tags);
            $data->hastags = true;
        }

        return $data;
    }

    /**
     * Returns the output class template path.
     *
     * @param \renderer_base $renderer typically, the renderer that's calling this function
     * @return string
     */
    public function get_template_name(\renderer_base $renderer): string {
        return 'format_minimoodlewall/local/content';
    }
}
