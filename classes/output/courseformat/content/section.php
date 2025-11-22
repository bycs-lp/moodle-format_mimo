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
 * Contains the section output class.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall\output\courseformat\content;

use core_courseformat\output\local\content\section as section_base;

/**
 * Base class to render a course section.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section extends section_base {
    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output typically, the renderer that's calling this function
     * @return \stdClass data context for a mustache template
     */
    public function export_for_template(\renderer_base $output): \stdClass {
        global $PAGE;
        
        $data = parent::export_for_template($output);
        
        // Pass tag information to all CMs if we're editing and have tags configured.
        $course = $this->format->get_course();
        
        // Get tagsetid from format options.
        $options = $this->format->get_format_options();
        $tagsetid = $options['tagsetid'] ?? 0;
        
        if ($PAGE->user_is_editing() && $tagsetid > 0) {
            // Get tags for this tagset.
            $tags = \format_minimoodlewall\tag_manager::get_tags_by_tagset($tagsetid);
            
            // Add tag data to the template context.
            $data->tags = array_values($tags);
            $data->hastags = !empty($tags);
            $data->tagsetid = $tagsetid;
            $data->sectionnum = $this->section->section;
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
        return 'format_minimoodlewall/local/content/section';
    }
}
