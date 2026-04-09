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
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo\output\courseformat;

use core_courseformat\output\local\content as content_base;

/**
 * Base class to render the course content.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
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
        $profile = \format_mimo\profile_manager::get_profile_by_name($activityprofile);
        if (!$profile) {
            $activityprofile = 'explore';
        }

        $data->stylevariant = $activityprofile;
        $data->styleclass = 'mimo-style-' . $activityprofile;

        // Resolve background design class.
        $bgdesign = $course->backgrounddesign ?? 'primary-school';
        $data->bgdesignclass = 'mimo-bgdesign-' . $bgdesign;

        // Initialize the tag chooser button JavaScript if editing is on and course has selected tags.
        $tags = \format_mimo\tag_manager::get_tags_for_course($course->id);
        if ($PAGE->user_is_editing() && !empty($tags)) {
            $PAGE->requires->js_call_amd('format_mimo/tagchooserbutton', 'init');

            // Pass tag data to the template.
            $data->tags = array_values($tags);
            $data->hastags = true;
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
        $profile = \format_mimo\profile_manager::get_profile_by_name($activityprofile);
        if (!$profile) {
            $activityprofile = 'explore';
        }

        $modinfo = get_fast_modinfo($course);
        $completioninfo = new \completion_info($course);
        $completionenabled = $completioninfo->is_enabled();
        $context = \core\context\course::instance($course->id);
        $isediting = $PAGE->user_is_editing();

        // Pre-fetch course tags for mini-wall tile colours and images.
        $coursetags = \format_mimo\tag_manager::get_tags_for_course($course->id);
        // Build tagid → accent colour and card image URL maps for quick lookup.
        // Image URLs are pre-computed and MUC-cached inside get_tags_for_course().
        $tagcolours = [];
        $tagimages = [];
        foreach ($coursetags as $tag) {
            $tagcolours[$tag->id] = \format_mimo\tag_manager::get_tag_accent_color($tag);
            if (!empty($tag->cached_cardimage_url)) {
                $tagimages[$tag->id] = $tag->cached_cardimage_url;
            }
        }
        // Default colour for activities without a tag.
        $defaultcolour = '#d0d0d0';

        $sections = [];
        foreach ($modinfo->get_section_info_all() as $sectioninfo) {
            // Skip orphaned or delegated sections.
            if (!$format->is_section_visible($sectioninfo)) {
                continue;
            }

            $sectionnum = $sectioninfo->section;
            $sectionname = $format->get_section_name($sectioninfo);
            $url = new \moodle_url('/course/view.php', ['id' => $course->id, 'section' => $sectionnum]);

            // Count activities and completion in this section, and collect mini-wall tiles.
            $activitycount = 0;
            $completedcount = 0;
            $totaltracked = 0;
            $minitiles = [];
            if (!empty($modinfo->sections[$sectionnum])) {
                foreach ($modinfo->sections[$sectionnum] as $cmid) {
                    $cm = $modinfo->cms[$cmid];
                    if (!$cm->uservisible) {
                        continue;
                    }
                    $activitycount++;

                    // Determine tile colour and image from tag assignment.
                    $cmtag = \format_mimo\tag_manager::get_cm_tag($cmid);
                    $colour = $defaultcolour;
                    $tile = ['color' => $colour];
                    if ($cmtag && isset($tagcolours[$cmtag->id])) {
                        $tile['color'] = $tagcolours[$cmtag->id];
                        if (isset($tagimages[$cmtag->id])) {
                            $tile['image'] = $tagimages[$cmtag->id];
                            $tile['hasimage'] = true;
                        }
                    }
                    $minitiles[] = $tile;

                    if ($completionenabled && $completioninfo->is_enabled($cm)) {
                        $totaltracked++;
                        $completiondata = $completioninfo->get_data($cm, false);
                        if (
                            $completiondata->completionstate == COMPLETION_COMPLETE ||
                                $completiondata->completionstate == COMPLETION_COMPLETE_PASS
                        ) {
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
                    ['context' => $context]
                );
            }

            $sectioncard = (object) [
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
                'isediting' => $isediting,
                'minitiles' => $minitiles,
                'hasminitiles' => !empty($minitiles),
            ];

            // Provide default placeholder tiles for empty sections using course tag colours.
            if (empty($minitiles) && !empty($tagcolours)) {
                $taglist = array_keys($tagcolours);
                $tagcount = count($taglist);
                $placeholdertiles = [];
                for ($i = 0; $i < 8; $i++) {
                    $tid = $taglist[$i % $tagcount];
                    $tile = ['color' => $tagcolours[$tid]];
                    if (isset($tagimages[$tid])) {
                        $tile['image'] = $tagimages[$tid];
                        $tile['hasimage'] = true;
                    }
                    $placeholdertiles[] = $tile;
                }
                $sectioncard->defaultminitiles = $placeholdertiles;
                $sectioncard->hasdefaultminitiles = true;
            }

            // Check for a section overview card image.
            $sectionimageurl = \format_mimo\section_image_manager::get_image_url(
                $course->id,
                $sectioninfo->id
            );
            if ($sectionimageurl) {
                $sectioncard->sectionimageurl = $sectionimageurl->out(false);
                $sectioncard->hassectionimage = true;
                // Read object-fit preference for this section.
                $opts = $format->get_format_options($sectioninfo);
                $fit = $opts['sectionimagefit'] ?? 'cover';
                $sectioncard->sectionimagefitclass = 'mimo-overview-card__sectionimage--' . $fit;
            } else {
                $sectioncard->hassectionimage = false;
            }

            // Add editing-mode properties.
            if ($isediting) {
                $sectioncard->courseid = $course->id;
                $sectioncard->candeletesection = course_can_delete_section($course, $sectioninfo);
                $sectioncard->activitycount = $activitycount;
            }

            // Render inplace editable for section name in editing mode.
            if ($isediting) {
                $inplaceeditable = $format->inplace_editable_render_section_name(
                    $sectioninfo,
                    false
                );
                $sectioncard->inplaceeditable = $output->render($inplaceeditable);
            }

            $sections[] = $sectioncard;
        }

        // Get section 0 DB id for drag-and-drop (needed for "move to first position").
        $section0id = 0;
        $section0info = $modinfo->get_section_info(0);
        if ($section0info) {
            $section0id = $section0info->id;
        }

        $data = (object) [
            'isoverview' => true,
            'overviewsections' => $sections,
            'hassections' => !empty($sections),
            'isediting' => $isediting,
            'section0id' => $section0id,
            'bgdesignclass' => 'mimo-bgdesign-' . $bgdesign,
            'stylevariant' => $activityprofile,
            'styleclass' => 'mimo-style-' . $activityprofile,
            'format' => $format->get_format(),
            'title' => $format->page_title(),
            'courseid' => $course->id,
        ];

        // Add section editing support.
        if ($this->hasaddsection && $PAGE->user_is_editing()) {
            $addsectionclass = $format->get_output_classname('content\\addsection');
            $addsection = new $addsectionclass($format);
            $data->numsections = $addsection->export_for_template($output);
            $data->mimoaddsection = $data->numsections;
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
            return 'format_mimo/local/overview';
        }
        return 'format_mimo/local/content';
    }
}
