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
 * Main class for the Minimal Moodle Wall course format.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/format/lib.php');

/**
 * Main class for the Minimal Moodle Wall course format.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_minimoodlewall extends core_courseformat\base {
    /**
     * Returns true if this course format uses sections.
     *
     * @return bool
     */
    public function uses_sections() {
        return true;
    }

    /**
     * Returns whether this course format supports course index.
     *
     * @return bool
     */
    public function uses_course_index() {
        return false;
    }

    /**
     * Returns whether this course format uses indentation.
     *
     * @return bool
     */
    public function uses_indentation(): bool {
        return false;
    }

    /**
     * Returns the information about the ajax support.
     *
     * @return stdClass
     */
    public function supports_ajax() {
        $ajaxsupport = new stdClass();
        $ajaxsupport->capable = true;
        return $ajaxsupport;
    }

    /**
     * Enable the component based content for Moodle 4.0+.
     *
     * @return bool
     */
    public function supports_components() {
        return true;
    }

    /**
     * Returns the display name of the given section that the course prefers.
     *
     * @param int|stdClass $section Section object from database or just field section.section
     * @return string Display name that the course format prefers
     */
    public function get_section_name($section) {
        $section = $this->get_section($section);
        if ((string)$section->name !== '') {
            return format_string(
                $section->name,
                true,
                ['context' => \context_course::instance($this->courseid)]
            );
        } else {
            return $this->get_default_section_name($section);
        }
    }

    /**
     * Returns the default section name for the minimoodlewall course format.
     *
     * @param stdClass $section Section object from database
     * @return string Display name
     */
    public function get_default_section_name($section) {
        if ($section->section == 0) {
            // Return the general section.
            return get_string('section0name', 'format_minimoodlewall');
        } else {
            return get_string('sectionname', 'format_minimoodlewall') . ' ' . $section->section;
        }
    }

    /**
     * Returns the URL to view a section. Always returns the main course URL.
     *
     * Since this format displays all activities in a single wall view,
     * section-specific URLs don't make sense - always redirect to the main course page.
     *
     * @param int|stdClass $section Section object from database or section number
     * @return null|moodle_url
     */
    public function get_view_url($section, $options = []) {
        $course = $this->get_course();
        $url = new moodle_url('/course/view.php', ['id' => $course->id]);
        return $url;
    }

    /**
     * Definitions of the additional options that this course format uses for course.
     *
     * This format only uses one section.
     *
     * @param bool $forupdate Whether the form is for updating the course.
     * @return array of options
     */
    public function course_format_options($forupdate = false) {
        $courseformatoptions = [
            'enablefiltering' => [
                'default' => 1,
                'type' => PARAM_BOOL,
            ],
            'distractionfree' => [
                'default' => 0,
                'type' => PARAM_BOOL,
            ],
            'enablecompletionstars' => [
                'default' => 1,
                'type' => PARAM_BOOL,
            ],
            'activityprofile' => [
                'default' => 'classic',
                'type' => PARAM_ALPHANUMEXT,
            ],
            'wallcolor' => [
                'default' => 'default',
                'type' => PARAM_ALPHANUMEXT,
            ],
        ];

        if ($forupdate) {
            // Add form elements for course settings.
            $courseformatoptions['enablefiltering'] += [
                'label' => get_string('setting_enablefiltering', 'format_minimoodlewall'),
                'help' => 'setting_enablefiltering',
                'help_component' => 'format_minimoodlewall',
                'element_type' => 'advcheckbox',
            ];
            $courseformatoptions['distractionfree'] += [
                'label' => get_string('setting_distractionfree', 'format_minimoodlewall'),
                'help' => 'setting_distractionfree',
                'help_component' => 'format_minimoodlewall',
                'element_type' => 'advcheckbox',
            ];
            $courseformatoptions['enablecompletionstars'] += [
                'label' => get_string('setting_enablecompletionstars', 'format_minimoodlewall'),
                'help' => 'setting_enablecompletionstars',
                'help_component' => 'format_minimoodlewall',
                'element_type' => 'advcheckbox',
            ];
            // Load activity profiles dynamically from database.
            $profileoptions = [];
            $profiles = \format_minimoodlewall\profile_manager::get_all_profiles();
            foreach ($profiles as $profile) {
                $profileoptions[$profile->name] = $profile->displayname;
            }
            // Fallback to default if no profiles exist.
            if (empty($profileoptions)) {
                $profileoptions['classic'] = get_string('profile_classic', 'format_minimoodlewall');
            }
            $courseformatoptions['activityprofile'] += [
                'label' => get_string('setting_activityprofile', 'format_minimoodlewall'),
                'help' => 'setting_activityprofile',
                'help_component' => 'format_minimoodlewall',
                'element_type' => 'select',
                'element_attributes' => [$profileoptions],
            ];
            $wallcoloroptions = [
                'default' => get_string('wallcolor_default', 'format_minimoodlewall'),
                'green'   => get_string('wallcolor_green', 'format_minimoodlewall'),
                'white'   => get_string('wallcolor_white', 'format_minimoodlewall'),
                'dark'    => get_string('wallcolor_dark', 'format_minimoodlewall'),
            ];
            $courseformatoptions['wallcolor'] += [
                'label' => get_string('setting_wallcolor', 'format_minimoodlewall'),
                'help' => 'setting_wallcolor',
                'help_component' => 'format_minimoodlewall',
                'element_type' => 'select',
                'element_attributes' => [$wallcoloroptions],
            ];
        }

        return $courseformatoptions;
    }

    /**
     * Adds format options elements to the course/section edit form.
     *
     * Overrides parent to add a read-only tag preview below the activity profile
     * dropdown, showing which tags are enabled for the selected profile.
     *
     * @param MoodleQuickForm $mform form the elements are added to
     * @param bool $forsection 'true' if this is a section edit form, 'false' if this is course edit form
     * @return array array of references to the added form elements
     */
    public function create_edit_form_elements(&$mform, $forsection = false) {
        global $PAGE;

        // Let parent handle all standard elements first.
        $elements = parent::create_edit_form_elements($mform, $forsection);

        // Only add tag preview for course edit form (not sections).
        if ($forsection) {
            return $elements;
        }

        // Get all tags (flat list).
        $alltags = \format_minimoodlewall\tag_manager::get_all_tags();
        if (empty($alltags)) {
            return $elements;
        }

        // Get current course data.
        $course = $this->get_course();

        // Prepare renderer for mustache templates.
        $output = $PAGE->get_renderer('format_minimoodlewall');

        // Get current activity profile for displaying correct images.
        $currentprofile = $course->activityprofile ?? 'classic';

        // Get all profiles for passing image URLs to template data attributes.
        $profiles = \format_minimoodlewall\profile_manager::get_all_profiles();

        // Build tag preview items with profile data attributes.
        $tagpreviews = [];
        foreach ($alltags as $tag) {
            // Get the image URL for the current profile.
            $imageurl = \format_minimoodlewall\tag_manager::get_cardimage_url($tag, $currentprofile);

            // Collect per-profile image URLs, name overrides, and enabled flags.
            $profileimages = [];
            $profilenames = [];
            $profileenabled = [];
            foreach ($profiles as $profile) {
                $profileimageurl = \format_minimoodlewall\tag_manager::get_cardimage_url($tag, $profile->name);
                $profileimages[$profile->name] = $profileimageurl ? $profileimageurl->out(false) : null;

                $pt = \format_minimoodlewall\profile_manager::get_profile_tag_for_profile($tag->id, $profile->id);
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
        $previewhtml = $output->render_from_template('format_minimoodlewall/form_tag_preview', [
            'tags' => $tagpreviews,
            'label' => get_string('tag_preview_label', 'format_minimoodlewall'),
        ]);

        $elements[] = $mform->addElement(
            'static',
            'tag_preview',
            get_string('tag_preview_label', 'format_minimoodlewall'),
            $previewhtml
        );
        $mform->addHelpButton('tag_preview', 'tag_preview', 'format_minimoodlewall');

        // Initialize JS module for profile-reactive preview.
        $PAGE->requires->js_call_amd('format_minimoodlewall/profile_image_switcher', 'init');

        return $elements;
    }



    /**
     * Updates course format options.
     *
     * Overrides parent to clear the course tags cache when the activity profile
     * changes, since the profile determines which tags are active.
     *
     * @param stdClass|array $data Data to update
     * @param stdClass $oldcourse Old course object
     * @return bool whether there were any changes to the options values
     */
    public function update_course_format_options($data, $oldcourse = null) {
        $data = (array)$data;

        $oldprofile = null;
        if ($oldcourse) {
            $oldcourse = (object)$oldcourse;
            $oldprofile = $oldcourse->activityprofile ?? null;
        }

        $result = parent::update_course_format_options($data, $oldcourse);

        // Clear course tags cache if the activity profile changed.
        $newprofile = $data['activityprofile'] ?? null;
        if ($result && $newprofile !== null && $newprofile !== $oldprofile) {
            \format_minimoodlewall\tag_manager::clear_course_tags_cache($this->courseid);
        }

        return $result;
    }

    /**
     * Returns the list of blocks to be automatically added for the newly created course.
     *
     * @return array of default blocks, must contain two keys BLOCK_POS_LEFT and BLOCK_POS_RIGHT
     *     each of values is an array of block names (for left and right side columns)
     */
    public function get_default_blocks() {
        return [
            BLOCK_POS_LEFT => [],
            BLOCK_POS_RIGHT => [],
        ];
    }

    /**
     * Updates the course format page with course-specific configuration.
     * Called during page setup, before output starts.
     *
     * @param moodle_page $page The page object
     */
    public function page_set_course(moodle_page $page) {
        parent::page_set_course($page);

        // Apply distraction-free mode if enabled for this course.
        $distractionfree = $this->get_course()->distractionfree ?? false;
        if ($distractionfree) {
            // Check if user has overridden the default via cookie.
            $dfactive = true;
            if (isset($_COOKIE['format_minimoodlewall_df'])) {
                $dfactive = $_COOKIE['format_minimoodlewall_df'] === 'true';
            }

            if ($dfactive) {
                // Add body class for distraction-free mode.
                // CSS is injected via hook callback in classes/hook_callbacks.php.
                $page->add_body_class('format-minimoodlewall-distraction-free');
            }

            // Initialize JavaScript module for toggle functionality.
            $page->requires->js_call_amd('format_minimoodlewall/distraction_free', 'init');
        }
    }

    /**
     * Minimal Moodle Wall uses only section 0.
     *
     * @return int
     */
    #[\Override]
    public function get_sectionnum(): int {
        return 0;
    }

    /**
     * Returns if a specific section is visible to the current user.
     *
     * Minimal Moodle Wall only uses section 0. Any other included section
     * should be a delegated one (subsections).
     *
     * @param \section_info $section the section modinfo
     * @return bool
     */
    #[\Override]
    public function is_section_visible(\section_info $section): bool {
        $visible = parent::is_section_visible($section);
        return $visible && ($section->sectionnum == 0 || $section->is_delegated());
    }

    /**
     * Extend course navigation to hide section nodes from breadcrumb on activity pages.
     *
     * @param global_navigation $navigation
     * @param navigation_node $node The course node within the navigation
     */
    public function extend_course_navigation($navigation, navigation_node $node) {
        global $PAGE;

        // First, call parent to load sections normally.
        parent::extend_course_navigation($navigation, $node);

        // If we're viewing an activity (module context), hide section nodes.
        // This prevents them from appearing in the breadcrumb.
        if ($PAGE->context->contextlevel == CONTEXT_MODULE && $PAGE->cm) {
            // Find the active section node and mark it to not show in breadcrumb.
            $sectionnode = $node->find($PAGE->cm->sectionnum, navigation_node::TYPE_SECTION);
            if ($sectionnode) {
                $sectionnode->mainnavonly = true; // This prevents it from showing in breadcrumb.
            } else {
                // Try to find it by searching all children.
                foreach ($node->children as $child) {
                    if ($child->type == navigation_node::TYPE_SECTION) {
                        $child->mainnavonly = true;
                    }
                }
            }
        }
    }
}

/**
 * Serve files from the minimoodlewall course format.
 *
 * Supports the tag card/filter image file areas stored in the system context.
 *
 * @param stdClass $course Course object (unused for system-context files)
 * @param stdClass $cm Course module (unused)
 * @param context $context Context the file belongs to
 * @param string $filearea File area name
 * @param array $args Remaining file path arguments
 * @param bool $forcedownload Whether the user must download the file
 * @param array $options Additional options passed to send_stored_file
 * @return void|false
 */
function format_minimoodlewall_pluginfile(
    $course,
    $cm,
    $context,
    $filearea,
    $args,
    $forcedownload,
    array $options = []
) {
    require_login();

    if ($context->contextlevel !== CONTEXT_SYSTEM) {
        return false;
    }

    $allowedareas = [
        \format_minimoodlewall\tag_manager::FILEAREA_CARDIMAGE,
        \format_minimoodlewall\tag_manager::FILEAREA_FILTERIMAGE,
        \format_minimoodlewall\profile_manager::FILEAREA_PROFILE_CARDIMAGE,
        \format_minimoodlewall\profile_manager::FILEAREA_PROFILE_FILTERIMAGE,
    ];
    if (!in_array($filearea, $allowedareas, true)) {
        return false;
    }

    if (count($args) < 2) {
        return false;
    }

    $itemid = (int)array_shift($args);
    $filename = array_pop($args);
    $filepath = '/';
    if (!empty($args)) {
        $filepath .= implode('/', $args) . '/';
    }

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'format_minimoodlewall', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Adds a tag selector to the module edit form for courses using the minimoodlewall format.
 *
 * This callback is invoked by Moodle core for every module form. It adds a dropdown
 * that lets teachers assign or change the tag on an activity.
 *
 * @param \moodleform_mod $formwrapper The module form wrapper
 * @param \MoodleQuickForm $mform The form object
 * @return void
 */
function format_minimoodlewall_coursemodule_standard_elements($formwrapper, $mform): void {
    global $SESSION;

    $course = $formwrapper->get_course();
    if ($course->format !== 'minimoodlewall') {
        return;
    }

    $tags = \format_minimoodlewall\tag_manager::get_tags_for_course($course->id);
    if (empty($tags)) {
        return;
    }

    // Build options: 0 = no tag, then each available tag.
    $options = [0 => get_string('notag', 'format_minimoodlewall')];
    foreach ($tags as $tag) {
        $options[$tag->id] = $tag->name;
    }

    $mform->addElement('header', 'mmw_tagsection', get_string('activitytag', 'format_minimoodlewall'));
    $mform->addElement('select', 'mmw_cmtag', get_string('selecttag', 'format_minimoodlewall'), $options);
    $mform->addHelpButton('mmw_cmtag', 'selecttaghelp', 'format_minimoodlewall');

    // Determine default value.
    $defaulttagid = 0;
    $cm = $formwrapper->get_coursemodule();
    if ($cm) {
        // Editing existing module — load current tag assignment.
        $currenttag = \format_minimoodlewall\tag_manager::get_cm_tag($cm->id);
        if ($currenttag) {
            $defaulttagid = $currenttag->id;
        }
    } else if (!empty($SESSION->format_minimoodlewall_pending_tag)) {
        // Creating new module — pre-select tag from chooser flow.
        $defaulttagid = (int)$SESSION->format_minimoodlewall_pending_tag;
    }

    $mform->setDefault('mmw_cmtag', $defaulttagid);
}

/**
 * Saves/updates the tag assignment after a module form is submitted.
 *
 * This callback is invoked by Moodle core after a module is created or updated.
 * It reads the tag selection from the form and assigns or removes the tag.
 *
 * @param \stdClass $data The form submission data (includes $data->coursemodule)
 * @param \stdClass $course The course object
 * @return \stdClass The (possibly modified) data object — must be returned for chaining
 */
function format_minimoodlewall_coursemodule_edit_post_actions($data, $course) {
    if ($course->format !== 'minimoodlewall') {
        return $data;
    }

    if (!isset($data->mmw_cmtag)) {
        return $data;
    }

    $cmid = $data->coursemodule;
    $tagid = (int)$data->mmw_cmtag;

    if ($tagid > 0) {
        \format_minimoodlewall\tag_manager::assign_tag_to_cm($cmid, $tagid);
    } else {
        \format_minimoodlewall\tag_manager::remove_cm_tag($cmid);
    }

    return $data;
}
