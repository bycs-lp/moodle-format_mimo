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
 * Main class for the mimo wall course format.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/format/lib.php');

/**
 * Main class for the mimo wall course format.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_mimo extends core_courseformat\base {
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
     * When multi-section mode is enabled, the course index is needed for
     * section-to-section navigation.
     *
     * @return bool
     */
    public function uses_course_index() {
        return $this->is_multisection_enabled();
    }

    /**
     * Allow the stealth module visibility state inside visible sections.
     *
     * @param stdClass|cm_info $cm course module (may be null if we are displaying a form for adding a module)
     * @param stdClass|section_info $section section where this module is located or will be added to
     * @return bool
     */
    public function allow_stealth_module_visibility($cm, $section) {
        return !$section->section || $section->visible;
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
                ['context' => \core\context\course::instance($this->courseid)]
            );
        } else {
            return $this->get_default_section_name($section);
        }
    }

    /**
     * Returns the default section name for the mimo course format.
     *
     * Section 0 is hidden in this format. In single-section mode, section 1 is
     * the sole wall and gets the primary wall name. In multi-section mode,
     * sections 1+ are numbered.
     *
     * @param stdClass $section Section object from database
     * @return string Display name
     */
    public function get_default_section_name($section) {
        if ($section->section == 0) {
            // Section 0 is hidden — return a generic label (never shown to users).
            return get_string('section0name', 'format_mimo');
        }
        if (!$this->is_multisection_enabled() && $section->section == 1) {
            // Single-section mode: section 1 is the sole wall.
            return get_string('section0name', 'format_mimo');
        }
        return get_string('sectionname', 'format_mimo') . ' ' . $section->section;
    }

    /**
     * Returns the URL to view a section.
     *
     * In single-section mode, always returns the main course URL.
     * In multi-section mode, returns a section-specific URL for non-zero sections.
     *
     * @param int|stdClass $section Section object from database or section number
     * @param array $options Options for view URL
     * @return null|moodle_url
     */
    public function get_view_url($section, $options = []) {
        $course = $this->get_course();

        if ($this->is_multisection_enabled()) {
            if (array_key_exists('sr', $options) && !is_null($options['sr'])) {
                $sectionno = $options['sr'];
            } else if (is_object($section)) {
                $sectionno = $section->section;
            } else {
                $sectionno = $section;
            }
            if ((!empty($options['navigation']) || array_key_exists('sr', $options)) && $sectionno !== null) {
                // Use course/view.php with section parameter instead of course/section.php
                // to preserve secondary navigation and keep users on the same page.
                return new moodle_url('/course/view.php', ['id' => $course->id, 'section' => $sectionno]);
            }
        }

        return new moodle_url('/course/view.php', ['id' => $course->id]);
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
            'enablemultisection' => [
                'default' => 0,
                'type' => PARAM_BOOL,
            ],
            'enablefiltering' => [
                'default' => 1,
                'type' => PARAM_BOOL,
            ],
            'distractionfree' => [
                'default' => 0,
                'type' => PARAM_BOOL,
            ],
            'backgrounddesign' => [
                'default' => 'primary-school',
                'type' => PARAM_ALPHANUMEXT,
            ],
            'activityprofile' => [
                'default' => 'explore',
                'type' => PARAM_ALPHANUMEXT,
            ],
        ];

        if ($forupdate) {
            // Add form elements for course settings.
            $courseformatoptions['enablemultisection'] += [
                'label' => get_string('setting_enablemultisection', 'format_mimo'),
                'help' => 'setting_enablemultisection',
                'help_component' => 'format_mimo',
                'element_type' => 'advcheckbox',
            ];
            $courseformatoptions['enablefiltering'] += [
                'label' => get_string('setting_enablefiltering', 'format_mimo'),
                'help' => 'setting_enablefiltering',
                'help_component' => 'format_mimo',
                'element_type' => 'advcheckbox',
            ];
            $courseformatoptions['distractionfree'] += [
                'label' => get_string('setting_distractionfree', 'format_mimo'),
                'help' => 'setting_distractionfree',
                'help_component' => 'format_mimo',
                'element_type' => 'advcheckbox',
            ];
            // Load activity profiles dynamically from database.
            // Show all global profiles + the current course's imported profile (if assigned).
            $profileoptions = [];
            $profiles = \format_mimo\profile_manager::get_global_profiles();
            foreach ($profiles as $profile) {
                $profileoptions[$profile->name] = $profile->displayname;
            }
            // Include the current course's imported profile if it's already assigned.
            $course = $this->get_course();
            if ($course) {
                $currentprofilename = $course->activityprofile ?? '';
                if ($currentprofilename !== '' && !isset($profileoptions[$currentprofilename])) {
                    $currentprofile = \format_mimo\profile_manager::get_profile_by_name($currentprofilename);
                    if ($currentprofile) {
                        $profileoptions[$currentprofile->name] = $currentprofile->displayname;
                    }
                }
            }
            // Fallback to default if no profiles exist.
            if (empty($profileoptions)) {
                $profileoptions['explore'] = get_string('profile_explore', 'format_mimo');
            }
            $bgdesignoptions = [
                'primary-school' => get_string('backgrounddesign_primaryschool', 'format_mimo'),
                'darkmode'       => get_string('backgrounddesign_darkmode', 'format_mimo'),
                'whiteboard'     => get_string('backgrounddesign_whiteboard', 'format_mimo'),
                'pinnwand'       => get_string('backgrounddesign_pinnwand', 'format_mimo'),
                'paper'          => get_string('backgrounddesign_paper', 'format_mimo'),
            ];
            $courseformatoptions['backgrounddesign'] += [
                'label' => get_string('setting_backgrounddesign', 'format_mimo'),
                'help' => 'setting_backgrounddesign',
                'help_component' => 'format_mimo',
                'element_type' => 'select',
                'element_attributes' => [$bgdesignoptions],
            ];
            $courseformatoptions['activityprofile'] += [
                'label' => get_string('setting_activityprofile', 'format_mimo'),
                'help' => 'setting_activityprofile',
                'help_component' => 'format_mimo',
                'element_type' => 'select',
                'element_attributes' => [$profileoptions],
            ];
        }

        return $courseformatoptions;
    }

    /**
     * Definitions of the additional options that this course format uses for sections.
     *
     * @param bool $foreditform Whether the form is for editing.
     * @return array of options
     */
    public function section_format_options($foreditform = false) {
        return [
            'sectionimagefit' => [
                'default' => 'contain',
                'type' => PARAM_ALPHA,
            ],
        ];
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
        // Let parent handle all standard elements first.
        $elements = parent::create_edit_form_elements($mform, $forsection);

        // Only add tag preview for course edit form (not sections).
        if ($forsection) {
            return $elements;
        }

        $previewelements = \format_mimo\form\tag_preview_helper::add_form_elements($mform, $this);
        return array_merge($elements, $previewelements);
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
            \format_mimo\tag_manager::clear_course_tags_cache($this->courseid);
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

        $pagesetup = new \format_mimo\page_setup($this);

        // These must run before the has_set_url() guard because activity pages
        // (mod/*/view.php) often call require_course_login() before set_url().
        $pagesetup->apply_distraction_free($page);
        $pagesetup->apply_compact_nav($page);

        // During course creation, $PAGE->set_url() has not been called yet.
        if (!$page->has_set_url()) {
            return;
        }

        $pagesetup->handle_redirects($page);
        $pagesetup->apply_multisection_classes($page);
        $pagesetup->apply_multisection_overview_button($page);
    }

    /**
     * Allows course format to execute code on moodle_page::set_cm().
     *
     * Adds navigation buttons to the page header when viewing an activity page.
     *
     * @param moodle_page $page instance of page calling set_cm
     */
    public function page_set_cm(moodle_page $page) {
        $pagesetup = new \format_mimo\page_setup($this);
        $pagesetup->apply_activity_navigation($page);
    }

    /**
     * Get the remembered section number for the current user, if valid.
     *
     * Reads the user preference, validates the section exists and is visible,
     * and returns the section number. Returns null if no preference is stored
     * or the stored section is no longer valid.
     *
     * @return int|null The remembered section number, or null.
     */
    public function get_remembered_section(): ?int {
        $course = $this->get_course();
        $pref = get_user_preferences('format_mimo_lastsection_' . $course->id);
        if ($pref === null) {
            return null;
        }
        $sectionnum = (int) $pref;
        $modinfo = get_fast_modinfo($course);
        $sectioninfos = $modinfo->get_section_info_all();
        foreach ($sectioninfos as $sectioninfo) {
            if ($sectioninfo->section === $sectionnum && $this->is_section_visible($sectioninfo)) {
                return $sectionnum;
            }
        }
        // Stored section no longer exists or is not visible — clear stale preference.
        unset_user_preference('format_mimo_lastsection_' . $course->id);
        return null;
    }

    /**
     * Whether the format allows deleting sections.
     *
     * In multi-section mode, any section > 0 can be deleted.
     * In single-section mode, deletion is not allowed (section 1 is the wall).
     *
     * @param int|\stdClass|\section_info $section the section to check
     * @return bool
     */
    #[\Override]
    public function can_delete_section($section): bool {
        if (!$this->is_multisection_enabled()) {
            return false;
        }
        $sectionnum = is_object($section) ? ($section->section ?? $section->sectionnum ?? 0) : (int) $section;
        return $sectionnum > 0;
    }

    /**
     * Check whether multi-section mode is enabled for this course.
     *
     * @return bool
     */
    public function is_multisection_enabled(): bool {
        $options = $this->get_format_options();
        return !empty($options['enablemultisection']);
    }

    /**
     * Get the current section to display.
     *
     * In single-section mode, always returns 1 (section 0 is hidden).
     * In multi-section mode, returns the currently selected section or null for all.
     *
     * @return int|null
     */
    #[\Override]
    public function get_sectionnum(): ?int {
        if (!$this->is_multisection_enabled()) {
            return 1;
        }
        return $this->singlesection;
    }

    /**
     * Returns if a specific section is visible to the current user.
     *
     * Section 0 is always hidden in this format (exists in DB but never rendered).
     * In single-section mode, only section 1 and delegated sections are visible.
     * In multi-section mode, all non-orphan sections except section 0 are visible.
     *
     * @param \section_info $section the section modinfo
     * @return bool
     */
    #[\Override]
    public function is_section_visible(\section_info $section): bool {
        // Section 0 is always hidden in mimo.
        if ($section->sectionnum == 0) {
            return false;
        }
        $visible = parent::is_section_visible($section);
        if ($this->is_multisection_enabled()) {
            return $visible;
        }
        return $visible && ($section->sectionnum == 1 || $section->is_delegated());
    }

    /**
     * Extend course navigation to hide section nodes from breadcrumb on activity pages.
     *
     * In single-section mode, hides section nodes from breadcrumbs entirely.
     * In multi-section mode, lets section breadcrumbs show normally so users
     * can navigate back to the section they came from.
     *
     * @param global_navigation $navigation
     * @param navigation_node $node The course node within the navigation
     */
    public function extend_course_navigation($navigation, navigation_node $node) {
        global $PAGE;

        // In multi-section mode, expand the selected section in navigation.
        if ($this->is_multisection_enabled()) {
            if ($navigation->includesectionnum === false) {
                $selectedsection = optional_param('section', null, PARAM_INT);
                if (
                    $selectedsection !== null && (!defined('AJAX_SCRIPT') || AJAX_SCRIPT == '0') &&
                        $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)
                ) {
                    $navigation->includesectionnum = $selectedsection;
                }
            }
        }

        // Call parent to load sections normally.
        parent::extend_course_navigation($navigation, $node);

        // In multi-section mode, remember the section when viewing an activity page.
        // This ensures deep links and course index activity clicks set the correct wall.
        if (
            $this->is_multisection_enabled()
                && $PAGE->context->contextlevel == CONTEXT_MODULE
                && $PAGE->cm
                && (!defined('AJAX_SCRIPT') || AJAX_SCRIPT == '0')
        ) {
            $sectionnum = $PAGE->cm->sectionnum;
            if ($sectionnum > 0) {
                $course = $this->get_course();
                set_user_preference('format_mimo_lastsection_' . $course->id, $sectionnum);
            }
        }

        // In single-section mode, hide section breadcrumbs on activity pages.
        if (!$this->is_multisection_enabled()) {
            if ($PAGE->context->contextlevel == CONTEXT_MODULE && $PAGE->cm) {
                $sectionnode = $node->find($PAGE->cm->sectionnum, navigation_node::TYPE_SECTION);
                if ($sectionnode) {
                    $sectionnode->mainnavonly = true;
                } else {
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
     * Clean up format-specific data when a course is deleted.
     *
     * Called by Moodle core during course deletion after course_modules are removed,
     * so we clean up orphaned cmtag records that no longer reference valid modules.
     */
    public function delete_format_data() {
        global $DB;
        parent::delete_format_data();

        $courseid = $this->get_courseid();

        // Delete orphaned cmtag records (course_modules are already removed by this point).
        // Uses NOT EXISTS for better performance than NOT IN on large instances.
        $DB->delete_records_select(
            'format_mimo_cmtags',
            "NOT EXISTS (SELECT 1 FROM {course_modules} cm WHERE cm.id = {format_mimo_cmtags}.cmid)"
        );

        // Delete orphaned done records (same pattern as cmtags above).
        $DB->delete_records_select(
            'format_mimo_cmdone',
            "NOT EXISTS (SELECT 1 FROM {course_modules} cm WHERE cm.id = {format_mimo_cmdone}.cmid)"
        );

        \format_mimo\tag_manager::clear_mapping_cache();
        \format_mimo\tag_manager::clear_course_tags_cache($courseid);

        // Clean up remembered-section preferences for all users.
        $DB->delete_records_select(
            'user_preferences',
            'name = :prefname',
            ['prefname' => 'format_mimo_lastsection_' . $courseid]
        );
    }
}

/**
 * Serve files from the mimo course format.
 *
 * @param stdClass $course Course object
 * @param stdClass $cm Course module
 * @param context $context Context the file belongs to
 * @param string $filearea File area name
 * @param array $args Remaining file path arguments
 * @param bool $forcedownload Whether the user must download the file
 * @param array $options Additional options passed to send_stored_file
 * @return void|false
 */
function format_mimo_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    return \format_mimo\callbacks::pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options);
}

/**
 * Adds a tag selector to the module edit form for courses using the mimo format.
 *
 * @param \moodleform_mod $formwrapper The module form wrapper
 * @param \MoodleQuickForm $mform The form object
 * @return void
 */
function format_mimo_coursemodule_standard_elements($formwrapper, $mform): void {
    \format_mimo\callbacks::coursemodule_standard_elements($formwrapper, $mform);
}

/**
 * Saves/updates the tag assignment after a module form is submitted.
 *
 * @param \stdClass $data The form submission data (includes $data->coursemodule)
 * @param \stdClass $course The course object
 * @return \stdClass The (possibly modified) data object
 */
function format_mimo_coursemodule_edit_post_actions($data, $course) {
    return \format_mimo\callbacks::coursemodule_edit_post_actions($data, $course);
}

/**
 * Pre-populate mimo completion defaults in the module creation form.
 *
 * @param \moodleform_mod $formwrapper The form wrapper object
 * @param \MoodleQuickForm $mform The form object
 * @return void
 */
function format_mimo_coursemodule_definition_after_data($formwrapper, $mform): void {
    \format_mimo\callbacks::coursemodule_definition_after_data($formwrapper, $mform);
}

/**
 * Implements callback for inplace editable (AJAX section name editing).
 *
 * @param string $itemtype The type of item being edited (sectionname or sectionnamenl)
 * @param int $itemid The section id
 * @param string $newvalue The new value
 * @return \core\output\inplace_editable
 */
function format_mimo_inplace_editable($itemtype, $itemid, $newvalue) {
    return \format_mimo\callbacks::inplace_editable($itemtype, $itemid, $newvalue);
}

/**
 * Register user preferences that can be set via the core_user_set_user_preferences webservice.
 *
 * @return array Array of preference definitions keyed by preference name.
 */
function format_mimo_user_preferences(): array {
    return [
        'format_mimo_df_active' => [
            'type' => PARAM_ALPHA,
            'null' => NULL_NOT_ALLOWED,
            'default' => 'true',
            'choices' => ['true', 'false'],
        ],
    ];
}
