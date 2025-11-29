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

use context_course;
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
     * Number of activities we assume fit on the first screen before JS adapts to viewport size.
     */
    private const INITIAL_PAGINATION_THRESHOLD = 8;
    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output typically, the renderer that's calling this function
     * @return \stdClass data context for a mustache template
     */
    public function export_for_template(\renderer_base $output): \stdClass {
        global $PAGE;

        $data = parent::export_for_template($output);

        $course = $this->format->get_course();
        $options = $this->format->get_format_options();
        $tagsetid = $options['tagsetid'] ?? 0;
        $enablefiltering = !empty($options['enablefiltering']);
        $isediting = $PAGE->user_is_editing();

        if (!empty($data->cmlist) && isset($data->cmlist->cms)) {
            $activitycount = 0;
            if (is_countable($data->cmlist->cms)) {
                $activitycount = count($data->cmlist->cms);
            }

            $data->cmlist->activitycount = $activitycount;
            $data->cmlist->initialpaginationthreshold = self::INITIAL_PAGINATION_THRESHOLD;
            $data->cmlist->hasinitialnext = ($activitycount > self::INITIAL_PAGINATION_THRESHOLD);
        }

        if ($tagsetid > 0) {
            $tags = \format_minimoodlewall\tag_manager::get_tags_by_tagset($tagsetid);

            if ($isediting) {
                $data->tags = array_values($tags);
                $data->hastags = !empty($tags);
                $data->tagsetid = $tagsetid;
                $data->sectionnum = $this->section->section;
            }

            if ($enablefiltering) {
                $filtertags = $this->build_filterbar_data($tags, (int)$course->id, $isediting);
                if (!empty($filtertags)) {
                    $data->filterbar = (object) [
                        'tags' => $filtertags,
                        'hasitems' => true,
                        'label' => get_string('filterbarlabel', 'format_minimoodlewall'),
                        'emptylabel' => get_string('filterbarnoactivities', 'format_minimoodlewall'),
                        'isediting' => $isediting,
                    ];
                }
            }
        }

        return $data;
    }

    /**
     * Build template data for the filter bar.
     *
     * @param array $tags Tag records keyed by id
     * @param bool $isediting Whether editing mode is enabled
     * @return array
     */
    private function build_filterbar_data(array $tags, int $courseid, bool $isediting): array {
        if (empty($tags)) {
            return [];
        }

        $tagids = array_map('intval', array_keys($tags));
        $usage = \format_minimoodlewall\tag_manager::get_tag_usage_counts($courseid, $tagids);
        $context = context_course::instance($courseid);

        $filtertags = [];
        foreach ($tags as $tag) {
            $filterurl = \format_minimoodlewall\tag_manager::get_filterimage_url($tag);
            $hasactivities = !empty($usage[$tag->id]);
            if (!$isediting && !$hasactivities) {
                continue;
            }

            $filtertags[] = [
                'id' => $tag->id,
                'name' => format_string($tag->name, true, ['context' => $context]),
                'imageurl' => $filterurl ? $filterurl->out(false) : null,
                'hasactivities' => $hasactivities,
                'bgcolor' => \format_minimoodlewall\tag_manager::get_tag_accent_color($tag),
            ];
        }

        return $filtertags;
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
