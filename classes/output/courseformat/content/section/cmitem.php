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
 * Contains the course module item output class.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall\output\courseformat\content\section;

use core_courseformat\output\local\content\section\cmitem as cmitem_base;
use format_minimoodlewall\tag_manager;

/**
 * Base class to render a course module item.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cmitem extends cmitem_base {
    /**
     * Returns the output class template path.
     *
     * @param \renderer_base $renderer typically, the renderer that's calling this function
     * @return string
     */
    public function get_template_name(\renderer_base $renderer): string {
        return 'format_minimoodlewall/local/content/section/cmitem';
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output typically, the renderer that's calling this function
     * @return \stdClass data context for a mustache template
     */
    public function export_for_template(\renderer_base $output): \stdClass {
        $data = parent::export_for_template($output);
        
        // Get the course module.
        $mod = $this->mod;
        $cmid = $mod->id;
        
        // Get tag information for this activity.
        $tag = tag_manager::get_cm_tag($cmid);
        
        if ($tag) {
            // Add tag data to cmformat.
            if (!isset($data->cmformat)) {
                $data->cmformat = new \stdClass();
            }
            
            $data->cmformat->tagname = $tag->name;
            $data->cmformat->tagid = $tag->id;
            
            // Use output API to construct image URLs.
            $data->cmformat->tagimage = $output->image_url('tags/' . $tag->cardimage, 'format_minimoodlewall')->out(false);
            $data->cmformat->filterimage = $output->image_url('tags/' . $tag->filterimage, 'format_minimoodlewall')->out(false);
        }
        
        // Add activity description (truncated to 3 lines).
        $description = '';
        if (!empty($mod->intro)) {
            // Strip HTML tags and get plain text.
            $description = strip_tags($mod->intro);
            // Truncate to approximately 150 characters (about 3 lines).
            if (strlen($description) > 150) {
                $description = substr($description, 0, 147) . '...';
            }
        }
        
        if (!isset($data->cmformat)) {
            $data->cmformat = new \stdClass();
        }
        $data->cmformat->description = $description;
        
        // Add completion status.
        if (isset($data->cmformat->completion)) {
            $completiondata = $data->cmformat->completion;
            // Check if activity is completed.
            if (isset($completiondata->completionstate)) {
                $data->cmformat->completion->iscomplete = (
                    $completiondata->completionstate == COMPLETION_COMPLETE ||
                    $completiondata->completionstate == COMPLETION_COMPLETE_PASS
                );
            }
        }
        
        return $data;
    }
}
