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

namespace format_mimo\form;

/**
 * Helper for rendering the tag preview in the course edit form.
 *
 * Encapsulates the logic for building tag preview data and rendering
 * the mustache template with profile-aware image URLs.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tag_preview_helper {
    /**
     * Add tag preview elements to the course edit form.
     *
     * Renders a read-only tag preview section below the activity profile dropdown,
     * showing which tags are enabled for the selected profile with their images.
     *
     * @param \MoodleQuickForm $mform The form object.
     * @param \format_mimo $format The course format instance.
     * @return array Array of added form elements.
     */
    public static function add_form_elements(\MoodleQuickForm $mform, \format_mimo $format): array {
        global $PAGE;

        $elements = [];

        // Get all tags (flat list).
        $alltags = \format_mimo\tag_manager::get_all_tags();
        if (empty($alltags)) {
            return $elements;
        }

        // Get current course data.
        $course = $format->get_course();

        // Prepare renderer for mustache templates.
        $output = $PAGE->get_renderer('format_mimo');

        // Get current activity profile for displaying correct images.
        $currentprofile = $course->activityprofile ?? 'primaryschool';

        // Get all profiles for passing image URLs to template data attributes.
        $profiles = \format_mimo\profile_manager::get_all_profiles();

        // Build tag preview items with profile data attributes.
        $tagpreviews = [];
        foreach ($alltags as $tag) {
            $imageurl = \format_mimo\tag_manager::get_cardimage_url($tag, $currentprofile);

            // Collect per-profile image URLs, name overrides, and enabled flags.
            $profileimages = [];
            $profilenames = [];
            $profileenabled = [];
            foreach ($profiles as $profile) {
                $profileimageurl = \format_mimo\tag_manager::get_cardimage_url($tag, $profile->name);
                $profileimages[$profile->name] = $profileimageurl ? $profileimageurl->out(false) : null;

                $pt = \format_mimo\profile_manager::get_profile_tag_for_profile($tag->id, $profile->id);
                $profilenames[$profile->name] = ($pt && $pt->name !== null) ? $pt->name : $tag->name;
                $profileenabled[$profile->name] = $pt ? (int) $pt->enabled : 1;
            }

            $tagpreviews[] = [
                'name' => $tag->name,
                'imageurl' => $imageurl ? $imageurl->out(false) : null,
                'tagid' => $tag->id,
                'profileimages' => json_encode($profileimages),
                'profilenames' => json_encode($profilenames),
                'profileenabled' => json_encode($profileenabled),
            ];
        }

        // Render the complete tag preview section.
        $previewhtml = $output->render_from_template('format_mimo/form_tag_preview', [
            'tags' => $tagpreviews,
            'label' => get_string('tag_preview_label', 'format_mimo'),
        ]);

        $elements[] = $mform->addElement(
            'static',
            'tag_preview',
            '',
            $previewhtml
        );

        // Initialize JS module for profile-reactive preview.
        $PAGE->requires->js_call_amd('format_mimo/profile_image_switcher', 'init');

        return $elements;
    }
}
