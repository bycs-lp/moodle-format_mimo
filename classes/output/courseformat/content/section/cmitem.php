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
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo\output\courseformat\content\section;

use core_courseformat\output\local\content\section\cmitem as cmitem_base;
use format_mimo\completion_helper;
use format_mimo\done_manager;
use format_mimo\tag_manager;

/**
 * Base class to render a course module item.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
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
        return 'format_mimo/local/content/section/cmitem';
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
        $basetag = tag_manager::get_cm_tag($cmid);

        if ($basetag) {
            // Use the fully resolved tag from the course cache (profile overrides + image URLs
            // already applied and MUC-cached), falling back to the base tag.
            $coursetags = tag_manager::get_tags_for_course($mod->course);
            $tag = $coursetags[$basetag->id] ?? $basetag;

            $data->cmformat->tagname = format_string($tag->name, true, ['context' => \core\context\course::instance($cm->course)]);
            $data->cmformat->tagid = $tag->id;
            $data->cmformat->tagcolor = tag_manager::get_tag_accent_color($tag);
            $data->cmformat->imgplacement = $tag->imgplacement ?? 'center';
            $data->cmformat->imgsize = $tag->imgsize ?? 'normal';

            if (!empty($tag->cached_cardimage_url)) {
                $data->cmformat->tagimage = $tag->cached_cardimage_url;
            }

            if (!empty($tag->cached_filterimage_url)) {
                $data->cmformat->filterimage = $tag->cached_filterimage_url;
            }
        }

        // Check if this activity is flagged as done.
        $isdone = done_manager::is_done($cmid);
        $data->cmformat->isdone = $isdone;

        // Add completion status - get from cm_info object.
        // For done activities in student view, we still compute completion so the
        // hourglass/overdue icon can be shown greyed out.
        $completioninfo = new \completion_info($cm->get_course());
        if ($completioninfo->is_enabled($cm)) {
            if (!isset($data->cmformat->completion)) {
                $data->cmformat->completion = new \stdClass();
            }
            $data->cmformat->completion->hascompletion = true;

            $coursecontext = \core\context\course::instance($cm->course);
            if (has_capability('report/progress:view', $coursecontext)) {
                if (!$isdone) {
                    // Teacher view: show aggregated completion count (only for non-done activities).
                    $counts = completion_helper::get_teacher_completion_counts($cm->course);
                    $totalusers = completion_helper::get_tracked_user_count($cm->course);
                    $data->cmformat->completion->isteacherview = true;
                    $data->cmformat->completion->completedcount = $counts[$cmid] ?? 0;
                    $data->cmformat->completion->trackedtotal = $totalusers;
                    $data->cmformat->completion->reporturl = (new \moodle_url(
                        '/report/progress/index.php',
                        ['course' => $cm->course]
                    ))->out(false);
                } else {
                    // Teachers on done activities don't need the completion badge
                    // (they see the done state via the visibility dropdown).
                    $data->cmformat->completion->hascompletion = false;
                }
            } else {
                // Student view: show personal completion state.
                $completiondata = $completioninfo->get_data($cm, false);
                if ($completiondata) {
                    $iscomplete = (
                        $completiondata->completionstate == COMPLETION_COMPLETE ||
                        $completiondata->completionstate == COMPLETION_COMPLETE_PASS
                    );
                    $data->cmformat->completion->iscomplete = $iscomplete;

                    // Check if overdue: not complete and a deadline has passed.
                    // Check completionexpected first, then module-specific due dates from customdata.
                    if (!$iscomplete) {
                        $now = time();
                        $deadline = 0;
                        if (!empty($cm->completionexpected)) {
                            $deadline = $cm->completionexpected;
                        } else {
                            $customdata = $cm->customdata;
                            if (is_array($customdata)) {
                                // Assign: duedate, quiz: timeclose.
                                $deadline = (int) ($customdata['duedate'] ?? $customdata['timeclose'] ?? 0);
                            }
                        }
                        if ($deadline > 0 && $deadline < $now) {
                            $data->cmformat->completion->isoverdue = true;
                        }
                    }
                }
            }
        }

        return $data;
    }
}
