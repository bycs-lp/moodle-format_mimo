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
        global $DB;

        $data = parent::export_for_template($output);

        // Get the course module.
        $mod = $this->mod;
        $cmid = $mod->id;

        // Get the full cm_info object for more details.
        $modinfo = get_fast_modinfo($mod->course);
        $cm = $modinfo->get_cm($cmid);

        // Initialize cmformat if not set.
        if (!isset($data->cmformat)) {
            $data->cmformat = new \stdClass();
        }

        // Add activity name and URL from cm_info.
        $data->cmformat->activityname = $cm->get_formatted_name();
        $data->cmformat->url = $cm->url ? $cm->url->out(false) : null;
        $data->cmformat->sectionid = $cm->sectionid;

        // Get tag information for this activity.
        $tag = tag_manager::get_cm_tag($cmid);

        if ($tag) {
            $data->cmformat->tagname = $tag->name;
            $data->cmformat->tagid = $tag->id;
            $data->cmformat->tagcolor = tag_manager::get_tag_accent_color($tag);
            $data->cmformat->imgplacement = $tag->imgplacement ?? 'center';

            $cardurl = tag_manager::get_cardimage_url($tag);
            if ($cardurl) {
                $data->cmformat->tagimage = $cardurl->out(false);
            }

            $filterurl = tag_manager::get_filterimage_url($tag);
            if ($filterurl) {
                $data->cmformat->filterimage = $filterurl->out(false);
            }
        }

        // Add activity description (truncated to 3 lines).
        $intro = $cm->get_formatted_content(['overflowdiv' => false, 'noclean' => false]);
        if (empty($intro)) {
            // Try getting intro from the module table directly.
            $modulename = $cm->modname;
            $instance = $DB->get_record($modulename, ['id' => $cm->instance], 'intro', IGNORE_MISSING);
            if ($instance && !empty($instance->intro)) {
                $intro = format_text($instance->intro, FORMAT_HTML, ['noclean' => false]);
            }
        }

        if (!empty($intro)) {
            // Strip HTML tags and get plain text.
            $description = trim(strip_tags($intro));
            // Truncate to approximately 150 characters (about 3 lines).
            if (strlen($description) > 150) {
                $description = substr($description, 0, 147) . '...';
            }
            // Only set description if it's not empty (for Mustache conditionals).
            if (!empty($description)) {
                $data->cmformat->description = $description;
            }
        }

        // Add completion status - get from cm_info object.
        $completioninfo = new \completion_info($cm->get_course());
        if ($completioninfo->is_enabled($cm)) {
            if (!isset($data->cmformat->completion)) {
                $data->cmformat->completion = new \stdClass();
            }
            $data->cmformat->completion->hascompletion = true;

            // Check if activity is completed.
            $completiondata = $completioninfo->get_data($cm, false);
            if ($completiondata) {
                $data->cmformat->completion->iscomplete = (
                    $completiondata->completionstate == COMPLETION_COMPLETE ||
                    $completiondata->completionstate == COMPLETION_COMPLETE_PASS
                );
            }
        }

        return $data;
    }
}
