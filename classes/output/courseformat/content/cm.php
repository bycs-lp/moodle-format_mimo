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
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo\output\courseformat\content;

use core_courseformat\output\local\content\cm as cm_base;
use renderer_base;
use stdClass;

/**
 * Base class to render a course module.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cm extends cm_base {
    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * Note: In Moodle 5.1+, tag data is added via the activitychooserbutton class.
     * This method is kept for backward compatibility with Moodle 5.0 and earlier.
     *
     * @param renderer_base $output typically, the renderer that's calling this function
     * @return stdClass data context for a mustache template
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $PAGE, $CFG;

        $data = parent::export_for_template($output);

        // Get tags selected for this course.
        $courseid = $this->mod->course;
        $tags = \format_mimo\tag_manager::get_tags_for_course($courseid);
        $hastags = !empty($tags);

        // In Moodle 5.1+, the activitychooserbutton class handles tag data.
        // This is only needed for backward compatibility with 5.0 and earlier.
        if ($CFG->branch < 501) {
            if ($PAGE->user_is_editing() && $hastags) {
                // Add tag data and section info to the activity chooser button context.
                if (isset($data->activitychooserbutton)) {
                    $data->activitychooserbutton->tags = array_values($tags);
                    $data->activitychooserbutton->hastags = $hastags;
                    $data->activitychooserbutton->courseid = $courseid;
                    $data->activitychooserbutton->sectionnum = $this->mod->sectionnum;
                    $data->activitychooserbutton->uniqid = uniqid();
                }

                // Also add to the top level for the cm template.
                $data->tags = array_values($tags);
                $data->hastags = $hastags;
            }
        } else {
            // In Moodle 5.1+, ensure tags are set at top level for the template.
            if ($PAGE->user_is_editing() && $hastags) {
                $data->tags = array_values($tags);
                $data->hastags = $hastags;
            }
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
        return 'format_mimo/local/content/cm';
    }
}
