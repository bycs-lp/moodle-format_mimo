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
 * Renderer for outputting the mimo course format.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo\output;

use core_courseformat\output\section_renderer;

/**
 * Basic renderer for mimo format.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends section_renderer {
    /**
     * Renders the add cm control for a section.
     *
     * @param object $course The course object
     * @param int $section The section number
     * @param int|null $sectionreturn The section return number
     * @param array $displayoptions Display options
     * @return string HTML to output
     */
    public function course_section_add_cm_control($course, $section, $sectionreturn = null, $displayoptions = []) {
        // Check to see if user can add menus.
        if (
            !has_capability('moodle/course:manageactivities', \context_course::instance($course->id))
                || !$this->page->user_is_editing()
        ) {
            return '';
        }

        // Get tags selected for this course.
        $tags = \format_mimo\tag_manager::get_tags_for_course($course->id);

        // If we have tags selected, use our tag chooser button.
        if (!empty($tags)) {
            $data = [
                'tags' => array_values($tags),
                'sectionnum' => $section,
                'sectionreturn' => $sectionreturn,
                'uniqid' => uniqid(),
            ];

            // Load the JS for our tag chooser.
            $this->page->requires->js_call_amd('format_mimo/tagchooserbutton', 'init');

            return $this->render_from_template(
                'core_courseformat/local/content/divider',
                [
                    'content' => $this->render_from_template('format_mimo/tagchooserbutton', $data),
                    'extraclasses' => 'always-visible my-3',
                ]
            );
        }

        // Fall back to default implementation.
        return parent::course_section_add_cm_control($course, $section, $sectionreturn, $displayoptions);
    }

    /**
     * Generate the section title, wraps it in an inplace editable for editing.
     *
     * @param \section_info|\stdClass $section The course_section entry from DB
     * @param \stdClass $course The course entry from DB
     * @return string HTML to output
     */
    public function section_title($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section));
    }

    /**
     * Generate the section title without a link, wraps it in an inplace editable for editing.
     *
     * @param \section_info|\stdClass $section The course_section entry from DB
     * @param \stdClass $course The course entry from DB
     * @return string HTML to output
     */
    public function section_title_without_link($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section, false));
    }
}
