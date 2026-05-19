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
        global $PAGE;

        // Let parent handle all standard elements first.
        $elements = parent::create_edit_form_elements($mform, $forsection);

        // Only add tag preview for course edit form (not sections).
        if ($forsection) {
            return $elements;
        }

        // Get all tags (flat list).
        $alltags = \format_mimo\tag_manager::get_all_tags();
        if (empty($alltags)) {
            return $elements;
        }

        // Get current course data.
        $course = $this->get_course();

        // Prepare renderer for mustache templates.
        $output = $PAGE->get_renderer('format_mimo');

        // Get current activity profile for displaying correct images.
        $currentprofile = $course->activityprofile ?? 'explore';

        // Get all profiles for passing image URLs to template data attributes.
        $profiles = \format_mimo\profile_manager::get_all_profiles();

        // Build tag preview items with profile data attributes.
        $tagpreviews = [];
        foreach ($alltags as $tag) {
            // Get the image URL for the current profile.
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

        // Apply distraction-free mode if enabled for this course.
        // This must run before the has_set_url() guard because activity pages
        // (mod/*/view.php) often call require_course_login() before set_url(),
        // triggering page_set_course() before the URL is available.
        $distractionfree = $this->get_course()->distractionfree ?? false;
        if ($distractionfree) {
            // Check if user has overridden the default via user preference.
            $dfactive = get_user_preferences('format_mimo_df_active', 'true') === 'true';

            if ($dfactive) {
                // Add body class for distraction-free mode.
                // CSS is injected via hook callback in classes/hook_callbacks.php.
                $page->add_body_class('format-mimo-distraction-free');
            }

            // Add toggle button as leftmost header action (before compact nav and home button).
            $dflabel = get_string('aria_toggle_distractionfree', 'format_mimo');
            $page->add_header_action(
                '<button class="mimo-df-btn" type="button"' .
                ' data-action="toggle-distraction-free"' .
                ' title="' . s($dflabel) . '">' .
                '<i class="fa fa-up-right-and-down-left-from-center mimo-df-btn__icon--expand" aria-hidden="true"></i>' .
                '<i class="fa fa-down-left-and-up-right-to-center mimo-df-btn__icon--collapse" aria-hidden="true"></i>' .
                '<span class="sr-only">' . s($dflabel) . '</span>' .
                '</button>'
            );

            // Initialize JavaScript module for toggle functionality.
            $page->requires->js_call_amd('format_mimo/distraction_free', 'init');
        }

        // For non-editing users (students), replace the secondary navigation bar
        // with a compact three-dot dropdown in the header actions area.
        // Placed before the home button so it appears to its left.
        // Also runs before the has_set_url() guard for the same reason as above.
        $coursecontext = \core\context\course::instance($this->courseid);
        if (!has_capability('moodle/course:update', $coursecontext)) {
            $page->add_body_class('format-mimo-compact-secondarynav');
            $menulabel = get_string('compactnav_menu', 'format_mimo');
            $dropdownid = 'mimo-compact-nav-' . $this->courseid;
            $page->add_header_action(
                '<div class="dropdown mimo-compact-nav">' .
                '<button class="mimo-compact-nav-btn" type="button"' .
                ' id="' . $dropdownid . '" data-bs-toggle="dropdown"' .
                ' aria-expanded="false" title="' . s($menulabel) . '">' .
                '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" width="20" height="20">' .
                '<circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/>' .
                '</svg>' .
                '<span class="sr-only">' . s($menulabel) . '</span>' .
                '</button>' .
                '<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="' . $dropdownid . '"' .
                ' data-region="mimo-secondarynav-dropdown"></ul>' .
                '</div>'
            );
            $page->requires->js_call_amd('format_mimo/compact_nav', 'init');
        }

        // During course creation (e.g. from blocks_add_default_course_blocks),
        // $PAGE->set_url() has not been called yet. Skip all URL-dependent logic.
        if (!$page->has_set_url()) {
            return;
        }

        // Redirect course/section.php visits to course/view.php in multi-section mode
        // to preserve secondary navigation and keep users on the same page.
        if ($this->is_multisection_enabled()) {
            $pageurl = $page->url->get_path();
            if (strpos($pageurl, '/course/section.php') !== false) {
                $course = $this->get_course();
                $sectionnum = $this->get_sectionnum();
                $params = ['id' => $course->id];
                if ($sectionnum !== null) {
                    $params['section'] = $sectionnum;
                }
                redirect(new moodle_url('/course/view.php', $params));
            }
        }

        // In multi-section mode (learner view), add a body class so CSS can hide
        // the page-level section heading on course/section.php pages.
        if ($this->is_multisection_enabled() && !$page->user_is_editing()) {
            $page->add_body_class('format-mimo-multisection-view');
        }

        // In multi-section mode, add a "back to overview" button to the page header
        // when viewing a specific section wall (not the overview page).
        if ($this->is_multisection_enabled()) {
            $sectionparam = optional_param('section', null, PARAM_INT);
            if ($sectionparam !== null) {
                $course = $this->get_course();
                $overviewurl = new \moodle_url('/course/view.php', ['id' => $course->id, 'overview' => 1]);
                $this->add_home_button($page, $overviewurl);
            }
        }

        // Redirect logic that must happen BEFORE output starts.
        // (format.php is included after $OUTPUT->header(), so redirect() would fail there.)
        $pageurl = $page->url->get_path();
        if (strpos($pageurl, '/course/view.php') !== false) {
            $course = $this->get_course();
            if ($this->is_multisection_enabled()) {
                $sectionparam = optional_param('section', null, PARAM_INT);
                $overviewparam = optional_param('overview', 0, PARAM_INT);
                if ($sectionparam === null && !$overviewparam) {
                    // No section requested and not explicitly showing overview — redirect to last-visited wall.
                    $rememberedsection = $this->get_remembered_section();
                    if ($rememberedsection !== null) {
                        redirect(new \moodle_url('/course/view.php', [
                            'id' => $course->id,
                            'section' => $rememberedsection,
                        ]));
                    }
                }
            } else {
                // Single-section mode: redirect any section URL that is not section 1 to plain course view.
                // Section 0 is hidden; only section 1 is the wall.
                $sectionparam = optional_param('section', null, PARAM_INT);
                if ($sectionparam !== null && $sectionparam != 1) {
                    redirect(new \moodle_url('/course/view.php', ['id' => $course->id]));
                }
            }
        }
    }

    /**
     * Allows course format to execute code on moodle_page::set_cm().
     *
     * Adds a "back to home" button to the page header when viewing an activity page.
     * In multi-section mode, links to the overview. In single-section mode, links to the wall.
     *
     * @param moodle_page $page instance of page calling set_cm
     */
    public function page_set_cm(moodle_page $page) {
        $cm = $page->cm;
        if (!$cm || $cm->sectionnum < 1) {
            return;
        }
        $course = $this->get_course();
        if ($this->is_multisection_enabled()) {
            // Back arrow → returns to the section wall this activity belongs to.
            $wallurl = new \moodle_url('/course/view.php', ['id' => $course->id, 'section' => $cm->sectionnum]);
            $this->add_back_button($page, $wallurl);
            // Home button → returns to the overview.
            $overviewurl = new \moodle_url('/course/view.php', ['id' => $course->id, 'overview' => 1]);
            $this->add_home_button($page, $overviewurl);
        } else {
            $backurl = new \moodle_url('/course/view.php', ['id' => $course->id]);
            $this->add_home_button($page, $backurl);
        }
    }

    /**
     * Add a "back to wall" arrow button to the page header.
     *
     * @param moodle_page $page The page object
     * @param \moodle_url $url The URL the button should link to
     */
    private function add_back_button(moodle_page $page, \moodle_url $url): void {
        $btnlabel = get_string('backtowall', 'format_mimo');
        $page->add_header_action(
            \html_writer::link(
                $url,
                '<svg class="mimo-back-btn__icon" viewBox="0 0 24 24" fill="currentColor"' .
                ' aria-hidden="true" width="24" height="24">' .
                '<path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>' .
                \html_writer::span($btnlabel, 'sr-only'),
                ['class' => 'mimo-back-btn', 'title' => $btnlabel]
            )
        );
    }

    /**
     * Add the "back to home" button to the page header.
     *
     * @param moodle_page $page The page object
     * @param \moodle_url $url The URL the button should link to
     */
    private function add_home_button(moodle_page $page, \moodle_url $url): void {
        $btnlabel = get_string('backtooverview', 'format_mimo');
        $page->add_header_action(
            \html_writer::link(
                $url,
                '<svg class="mimo-overview-btn__icon" viewBox="0 0 24 24" fill="currentColor"' .
                ' aria-hidden="true" width="22" height="22">' .
                '<path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>' .
                \html_writer::span($btnlabel, 'sr-only'),
                ['class' => 'mimo-overview-btn', 'title' => $btnlabel]
            )
        );
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
function format_mimo_pluginfile(
    $course,
    $cm,
    $context,
    $filearea,
    $args,
    $forcedownload,
    array $options = []
) {
    require_login();

    // Section images use course context; tag/profile images use system context.
    if ($filearea === \format_mimo\section_image_manager::FILEAREA) {
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return false;
        }
        require_login($course, true);
    } else if ($context->contextlevel !== CONTEXT_SYSTEM) {
        return false;
    }

    $allowedareas = [
        \format_mimo\tag_manager::FILEAREA_CARDIMAGE,
        \format_mimo\tag_manager::FILEAREA_FILTERIMAGE,
        \format_mimo\profile_manager::FILEAREA_PROFILE_CARDIMAGE,
        \format_mimo\profile_manager::FILEAREA_PROFILE_FILTERIMAGE,
        \format_mimo\section_image_manager::FILEAREA,
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
    $file = $fs->get_file($context->id, 'format_mimo', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Adds a tag selector to the module edit form for courses using the mimo format.
 *
 * This callback is invoked by Moodle core for every module form. It adds a dropdown
 * that lets teachers assign or change the tag on an activity.
 *
 * @param \moodleform_mod $formwrapper The module form wrapper
 * @param \MoodleQuickForm $mform The form object
 * @return void
 */
function format_mimo_coursemodule_standard_elements($formwrapper, $mform): void {
    global $SESSION;

    $course = $formwrapper->get_course();
    if ($course->format !== 'mimo') {
        return;
    }

    $tags = \format_mimo\tag_manager::get_tags_for_course($course->id);
    if (empty($tags)) {
        return;
    }

    // Build options: 0 = no tag, then each available tag.
    $options = [0 => get_string('notag', 'format_mimo')];
    foreach ($tags as $tag) {
        $options[$tag->id] = $tag->name;
    }

    $mform->addElement('header', 'mimo_tagsection', get_string('activitytag', 'format_mimo'));
    $mform->addElement('select', 'mimo_cmtag', get_string('selecttag', 'format_mimo'), $options);
    $mform->addHelpButton('mimo_cmtag', 'selecttaghelp', 'format_mimo');

    // Determine default value.
    $defaulttagid = 0;
    $cm = $formwrapper->get_coursemodule();
    if ($cm) {
        // Editing existing module — load current tag assignment.
        $currenttag = \format_mimo\tag_manager::get_cm_tag($cm->id);
        if ($currenttag) {
            $defaulttagid = $currenttag->id;
        }
    } else if (!empty($SESSION->format_mimo_pending_tag)) {
        // Creating new module — pre-select tag from chooser flow.
        $defaulttagid = (int)$SESSION->format_mimo_pending_tag;
    }

    $mform->setDefault('mimo_cmtag', $defaulttagid);
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
function format_mimo_coursemodule_edit_post_actions($data, $course) {
    if ($course->format !== 'mimo') {
        return $data;
    }

    if (!isset($data->mimo_cmtag)) {
        return $data;
    }

    $cmid = $data->coursemodule;
    $tagid = (int)$data->mimo_cmtag;

    if ($tagid > 0) {
        \format_mimo\tag_manager::assign_tag_to_cm($cmid, $tagid);
    } else {
        \format_mimo\tag_manager::remove_cm_tag($cmid);
    }

    return $data;
}

/**
 * Pre-populate mimo completion defaults in the module creation form.
 *
 * When a teacher creates a new activity in a mimo course, this callback overrides
 * the core completion defaults with mimo-specific ones so the teacher sees the
 * intended defaults in the form before saving.
 *
 * @param \moodleform_mod $formwrapper The form wrapper object
 * @param \MoodleQuickForm $mform The form object
 * @return void
 */
function format_mimo_coursemodule_definition_after_data($formwrapper, $mform): void {
    $course = $formwrapper->get_course();
    if ($course->format !== 'mimo') {
        return;
    }

    // Only apply to new modules, not when editing existing ones.
    $current = $formwrapper->get_current();
    if (!empty($current->instance)) {
        return;
    }

    // Get the module type ID.
    $moduleid = (int)($current->module ?? 0);
    if (!$moduleid) {
        return;
    }

    // Check if we have a mimo completion override for this module type.
    $mimodefaults = \format_mimo\completion_defaults_manager::get_default($moduleid);
    if (!$mimodefaults) {
        return;
    }

    // Override core completion fields with mimo defaults.
    // Use setDefault() rather than getElement()->setValue() because radio buttons
    // require setDefault to properly select the correct option.
    $completion = (int)$mimodefaults->completion;
    if ($mform->elementExists('completion')) {
        $mform->setDefault('completion', $completion);
    }

    if ($completion === COMPLETION_TRACKING_AUTOMATIC) {
        if ($mform->elementExists('completionview') && (int)$mimodefaults->completionview) {
            $mform->setDefault('completionview', 1);
        }
        if (!empty($mimodefaults->completionusegrade)) {
            $modname = $current->modulename ?? '';
            $supportsgrades = plugin_supports('mod', $modname, FEATURE_GRADE_HAS_GRADE, false);
            if ($supportsgrades && $mform->elementExists('completionusegrade')) {
                $mform->setDefault('completionusegrade', 1);
            }
            if ((int)$mimodefaults->completionpassgrade && $mform->elementExists('completionpassgrade')) {
                $mform->setDefault('completionpassgrade', 1);
            }
        }
    }

    // Apply custom rules from the JSON blob.
    if (!empty($mimodefaults->customrules)) {
        $customrules = @json_decode($mimodefaults->customrules, true);
        if (is_array($customrules)) {
            unset($customrules['modids']);
            unset($customrules['id']);
            foreach ($customrules as $key => $value) {
                if ($mform->elementExists($key)) {
                    $mform->setDefault($key, $value);
                }
            }
        }
    }
}

/**
 * Implements callback for inplace editable (AJAX section name editing).
 *
 * Called by core when an inplace editable with component 'format_mimo' is saved.
 *
 * @param string $itemtype The type of item being edited (sectionname or sectionnamenl)
 * @param int $itemid The section id
 * @param string $newvalue The new value
 * @return \core\output\inplace_editable
 */
function format_mimo_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');

    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id WHERE s.id = ? AND c.format = ?',
            [$itemid, 'mimo'],
            MUST_EXIST
        );
        return course_get_format($section->course)->inplace_editable_update_section_name(
            $section,
            $itemtype,
            $newvalue
        );
    }
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
