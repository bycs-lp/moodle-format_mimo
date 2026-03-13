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

        $course = $this->format->get_course();
        $ismultisection = $this->format->is_multisection_enabled();

        // Overview mode: multi-section enabled and no specific section selected.
        if ($ismultisection && $this->format->get_sectionid() === null) {
            return $this->export_overview($output);
        }

        $data = parent::export_for_template($output);

        // Get the course format options.
        $activityprofile = $course->activityprofile ?? 'explore';

        // Validate profile exists in database, fallback to explore if not.
        $profile = \format_minimoodlewall\profile_manager::get_profile_by_name($activityprofile);
        if (!$profile) {
            $activityprofile = 'explore';
        }

        $data->stylevariant = $activityprofile;
        $data->styleclass = 'minimoodlewall-style-' . $activityprofile;

        // Resolve background design class.
        $bgdesign = $course->backgrounddesign ?? 'primary-school';
        $data->bgdesignclass = 'mmw-bgdesign-' . $bgdesign;

        // Initialize the tag chooser button JavaScript if editing is on and course has selected tags.
        $tags = \format_minimoodlewall\tag_manager::get_tags_for_course($course->id);
        if ($PAGE->user_is_editing() && !empty($tags)) {
            $PAGE->requires->js_call_amd('format_minimoodlewall/tagchooserbutton', 'init');

            // Pass tag data to the template.
            $data->tags = array_values($tags);
            $data->hastags = true;
        }

        // In multi-section mode the core template hides the "Add section" button
        // when viewing a single section ({{^singlesection}}). We expose the
        // addsection data under a separate key so our template can render it
        // unconditionally while editing.
        if ($ismultisection && !empty($data->numsections) && $PAGE->user_is_editing()) {
            $data->mmwaddsection = $data->numsections;
        }

        // In multi-section single-wall view, provide a back link to the overview.
        if ($ismultisection) {
            $data->overviewurl = (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
            $data->showoverviewlink = true;

            // Provide the current section name for the heading next to the back link.
            $sectionnum = $this->format->get_sectionnum();
            if ($sectionnum !== null) {
                $sectioninfo = $this->format->get_section($sectionnum);
                if ($sectioninfo) {
                    $data->currentsectionname = $this->format->get_section_name($sectioninfo);
                }
            }
        }

        return $data;
    }

    /**
     * Build lightweight overview data for the multi-section landing page.
     *
     * Instead of rendering full walls for every section (which is expensive),
     * this builds a flat list of section cards with metadata.
     *
     * @param \renderer_base $output The renderer
     * @return \stdClass Template data for the overview
     */
    private function export_overview(\renderer_base $output): \stdClass {
        global $PAGE;

        $format = $this->format;
        $course = $format->get_course();

        $bgdesign = $course->backgrounddesign ?? 'primary-school';
        $activityprofile = $course->activityprofile ?? 'explore';

        // Validate profile.
        $profile = \format_minimoodlewall\profile_manager::get_profile_by_name($activityprofile);
        if (!$profile) {
            $activityprofile = 'explore';
        }

        $modinfo = get_fast_modinfo($course);
        $completioninfo = new \completion_info($course);
        $completionenabled = $completioninfo->is_enabled();
        $context = \context_course::instance($course->id);

        $sections = [];
        foreach ($modinfo->get_section_info_all() as $sectioninfo) {
            // Skip orphaned or delegated sections.
            if (!$format->is_section_visible($sectioninfo)) {
                continue;
            }

            $sectionnum = $sectioninfo->section;
            $sectionname = $format->get_section_name($sectioninfo);
            $url = new \moodle_url('/course/view.php', ['id' => $course->id, 'section' => $sectionnum]);

            // Count activities and completion in this section.
            $activitycount = 0;
            $completedcount = 0;
            $totaltracked = 0;
            if (!empty($modinfo->sections[$sectionnum])) {
                foreach ($modinfo->sections[$sectionnum] as $cmid) {
                    $cm = $modinfo->cms[$cmid];
                    if (!$cm->uservisible) {
                        continue;
                    }
                    $activitycount++;
                    if ($completionenabled && $completioninfo->is_enabled($cm)) {
                        $totaltracked++;
                        $completiondata = $completioninfo->get_data($cm, false);
                        if ($completiondata->completionstate == COMPLETION_COMPLETE ||
                                $completiondata->completionstate == COMPLETION_COMPLETE_PASS) {
                            $completedcount++;
                        }
                    }
                }
            }

            // Format summary text.
            $summary = '';
            if (!empty($sectioninfo->summary)) {
                $summary = format_text(
                    $sectioninfo->summary,
                    $sectioninfo->summaryformat,
                    ['context' => $context, 'noclean' => true]
                );
            }

            $sections[] = (object) [
                'id' => $sectioninfo->id,
                'num' => $sectionnum,
                'name' => $sectionname,
                'url' => $url->out(false),
                'activitycount' => $activitycount,
                'completedcount' => $completedcount,
                'totaltracked' => $totaltracked,
                'hastracking' => $totaltracked > 0,
                'allcomplete' => $totaltracked > 0 && $completedcount === $totaltracked,
                'summary' => $summary,
                'hassummary' => !empty($summary),
            ];
        }

        $data = (object) [
            'isoverview' => true,
            'overviewsections' => $sections,
            'hassections' => !empty($sections),
            'bgdesignclass' => 'mmw-bgdesign-' . $bgdesign,
            'stylevariant' => $activityprofile,
            'styleclass' => 'minimoodlewall-style-' . $activityprofile,
            'format' => $format->get_format(),
            'title' => $format->page_title(),
            'courseid' => $course->id,
        ];

        // Add section editing support.
        if ($this->hasaddsection && $PAGE->user_is_editing()) {
            $addsectionclass = $format->get_output_classname('content\\addsection');
            $addsection = new $addsectionclass($format);
            $data->numsections = $addsection->export_for_template($output);
            $data->mmwaddsection = $data->numsections;
        }

        if ($format->show_editor()) {
            $bulkedittoolsclass = $format->get_output_classname('content\\bulkedittools');
            $bulkedittools = new $bulkedittoolsclass($format);
            $data->bulkedittools = $bulkedittools->export_for_template($output);
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
        // In overview mode, use the overview template.
        if ($this->format->is_multisection_enabled() && $this->format->get_sectionid() === null) {
            return 'format_minimoodlewall/local/overview';
        }
        return 'format_minimoodlewall/local/content';
    }
}
