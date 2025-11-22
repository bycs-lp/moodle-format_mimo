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
 * Contains the course module output class.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall\output\courseformat\content;

use core_courseformat\output\local\content\cm as cm_base;
use renderer_base;
use stdClass;

/**
 * Base class to render a course module.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cm extends cm_base {
    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output typically, the renderer that's calling this function
     * @return stdClass data context for a mustache template
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $PAGE;
        
        $data = parent::export_for_template($output);
        
        // Pass tag information if we're editing and have tags configured.
        $options = $this->format->get_format_options();
        $tagsetid = $options['tagsetid'] ?? 0;
        
        if ($PAGE->user_is_editing() && $tagsetid > 0) {
            // Get tags for this tagset.
            $tags = \format_minimoodlewall\tag_manager::get_tags_by_tagset($tagsetid);
            
            // Add tag data and section info to the activity chooser button context.
            if (isset($data->activitychooserbutton)) {
                $data->activitychooserbutton->tags = array_values($tags);
                $data->activitychooserbutton->hastags = !empty($tags);
                $data->activitychooserbutton->tagsetid = $tagsetid;
                $data->activitychooserbutton->sectionnum = $this->mod->sectionnum;
                $data->activitychooserbutton->uniqid = uniqid();
            }
            
            // Also add to the top level for the cm template.
            $data->tags = array_values($tags);
            $data->hastags = !empty($tags);
        }
        
        return $data;
    }
    
    /**
     * Returns the output class template path.
     *
     * @param renderer_base $renderer typically, the renderer that's calling this function
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'format_minimoodlewall/local/content/cm';
    }
}
