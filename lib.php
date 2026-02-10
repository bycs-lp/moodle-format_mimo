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
        return true;
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
            'stylevariant' => [
                'default' => 'classic',
                'type' => PARAM_ALPHANUMEXT,
            ],
            'wallcolor' => [
                'default' => 'default',
                'type' => PARAM_ALPHANUMEXT,
            ],
            'tagsetid' => [
                'default' => 0,
                'type' => PARAM_INT,
            ],
            // selectedtags is handled manually in create_edit_form_elements() with custom checkboxes.
            'selectedtags' => [
                'default' => '',
                'type' => PARAM_SEQUENCE,
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
            // Load styles dynamically from database.
            $styleoptions = [];
            $styles = \format_minimoodlewall\style_manager::get_all_styles();
            foreach ($styles as $style) {
                $styleoptions[$style->name] = $style->displayname;
            }
            // Fallback to default if no styles exist.
            if (empty($styleoptions)) {
                $styleoptions['classic'] = get_string('style_classic', 'format_minimoodlewall');
            }
            $courseformatoptions['stylevariant'] += [
                'label' => get_string('setting_style', 'format_minimoodlewall'),
                'help' => 'setting_style',
                'help_component' => 'format_minimoodlewall',
                'element_type' => 'select',
                'element_attributes' => [$styleoptions],
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
            // selectedtags is a hidden element - custom checkboxes are added in create_edit_form_elements().
            $courseformatoptions['selectedtags'] += [
                'label' => '',
                'element_type' => 'hidden',
            ];
            // tagsetid is a hidden element - custom select is added in create_edit_form_elements().
            $courseformatoptions['tagsetid'] += [
                'label' => '',
                'element_type' => 'hidden',
            ];
        }

        return $courseformatoptions;
    }

    /**
     * Adds format options elements to the course/section edit form.
     *
     * Overrides parent to create custom checkbox elements for tag selection
     * with inline images displayed vertically.
     *
     * @param MoodleQuickForm $mform form the elements are added to
     * @param bool $forsection 'true' if this is a section edit form, 'false' if this is course edit form
     * @return array array of references to the added form elements
     */
    public function create_edit_form_elements(&$mform, $forsection = false) {
        global $PAGE;

        // Let parent handle all standard elements first.
        $elements = parent::create_edit_form_elements($mform, $forsection);

        // Only add tagset/tag elements for course edit form (not sections).
        if ($forsection) {
            return $elements;
        }

        // Get all tagsets.
        $tagsets = \format_minimoodlewall\tagset_manager::get_all_tagsets();
        if (empty($tagsets)) {
            return $elements;
        }

        // Get current course data.
        $course = $this->get_course();
        $currenttagsetid = $course->tagsetid ?? 0;

        // Get currently selected tags from course format options.
        $selectedtagids = [];
        if (!empty($course->selectedtags)) {
            $selectedtagids = array_map('intval', explode(',', $course->selectedtags));
        }
        $selectedtagsvalue = implode(',', $selectedtagids);

        // Set default values for the hidden fields (created by parent).
        $mform->setDefault('selectedtags', $selectedtagsvalue);
        $mform->setDefault('tagsetid', $currenttagsetid);

        // Build tagset dropdown options.
        $tagsetoptions = [0 => get_string('selecttagset', 'format_minimoodlewall')];
        foreach ($tagsets as $tagset) {
            $tagsetoptions[$tagset->id] = format_string($tagset->name);
        }

        // Add tagset select dropdown.
        $elements[] = $mform->addElement(
            'select',
            'tagsetid_select',
            get_string('setting_tagsetid', 'format_minimoodlewall'),
            $tagsetoptions
        );
        $mform->addHelpButton('tagsetid_select', 'setting_tagsetid', 'format_minimoodlewall');
        $mform->setDefault('tagsetid_select', $currenttagsetid);

        // Create a label/header for the tag checkboxes section.
        $elements[] = $mform->addElement(
            'static',
            'selectedtags_label',
            get_string('setting_selectedtags', 'format_minimoodlewall')
        );
        $mform->addHelpButton('selectedtags_label', 'setting_selectedtags', 'format_minimoodlewall');

        // Prepare renderer for mustache templates.
        $output = $PAGE->get_renderer('format_minimoodlewall');

        // Get current style variant for displaying correct images.
        $currentstyle = $course->stylevariant ?? 'classic';

        // Get all styles for passing image URLs to template data attributes.
        $styles = \format_minimoodlewall\style_manager::get_all_styles();

        // Add checkboxes for ALL tags across ALL tagsets, with data-tagsetid for JS filtering.
        $alltagids = [];
        foreach ($tagsets as $tagset) {
            $tagsinthistagset = \format_minimoodlewall\tag_manager::get_tags_by_tagset($tagset->id);
            foreach ($tagsinthistagset as $tag) {
                $alltagids[] = $tag->id;

                // Get the image URL for the current style.
                $imageurl = \format_minimoodlewall\tag_manager::get_cardimage_url($tag, $currentstyle);

                // Collect image URLs for all styles (stored as data attribute for JS).
                $styleimages = [];
                foreach ($styles as $style) {
                    $styleimageurl = \format_minimoodlewall\tag_manager::get_cardimage_url($tag, $style->name);
                    $styleimages[$style->name] = $styleimageurl ? $styleimageurl->out(false) : null;
                }

                // Render label using mustache template.
                $templatecontext = [
                    'name' => $tag->name,
                    'imageurl' => $imageurl ? $imageurl->out(false) : null,
                    'tagid' => $tag->id,
                    'styleimages' => json_encode($styleimages),
                ];
                $labelhtml = $output->render_from_template('format_minimoodlewall/form_tag_option', $templatecontext);

                $checkboxname = 'selectedtag_' . $tag->id;
                $attrs = ['data-tagsetid' => $tagset->id];
                $elements[] = $mform->addElement(
                    'advcheckbox',
                    $checkboxname,
                    '',
                    $labelhtml,
                    $attrs,
                    [0, $tag->id]
                );

                // Set default checked state based on current selection.
                if (in_array($tag->id, $selectedtagids)) {
                    $mform->setDefault($checkboxname, $tag->id);
                }
            }
        }

        // Initialize JS modules.
        $PAGE->requires->js_call_amd('format_minimoodlewall/tag_checkbox_sync', 'init', [$alltagids]);
        $PAGE->requires->js_call_amd('format_minimoodlewall/style_image_switcher', 'init');
        $PAGE->requires->js_call_amd('format_minimoodlewall/tagset_tag_filter', 'init');

        return $elements;
    }

    /**
     * Require at least one tag to be selected when creating/editing a course with this format.
     *
     * @param array $data Submitted form data
     * @param array $files Uploaded files
     * @param array $errors Existing validation errors
     * @return array
     */
    public function edit_form_validation($data, $files, $errors) {
        $errors = parent::edit_form_validation($data, $files, $errors);

        // Validate tagset is selected.
        $tagsetid = $data['tagsetid_select'] ?? ($data['tagsetid'] ?? 0);
        if (empty($tagsetid)) {
            $errors['tagsetid_select'] = get_string('error_required_tagset', 'format_minimoodlewall');
        }

        // Collect selected tags from individual checkboxes.
        $selectedtags = [];
        foreach ($data as $key => $value) {
            if (strpos($key, 'selectedtag_') === 0 && !empty($value)) {
                $selectedtags[] = $value;
            }
        }

        // Fallback: also check the hidden selectedtags field.
        if (empty($selectedtags)) {
            $selectedtagsvalue = $data['selectedtags'] ?? '';
            if (is_array($selectedtagsvalue)) {
                $selectedtags = array_filter($selectedtagsvalue);
            } else {
                $selectedtags = array_filter(explode(',', $selectedtagsvalue));
            }
        }

        if (empty($selectedtags)) {
            $errors['selectedtags_label'] = get_string('error_required_tags', 'format_minimoodlewall');
        }

        return $errors;
    }

    /**
     * Updates course format options.
     *
     * Overrides parent to collect selectedtags from individual checkbox fields
     * and convert to comma-separated string before storage.
     * Also clears the course tags cache when selectedtags changes.
     *
     * @param stdClass|array $data Data to update
     * @param stdClass $oldcourse Old course object
     * @return bool whether there were any changes to the options values
     */
    public function update_course_format_options($data, $oldcourse = null) {
        $data = (array)$data;

        // Sync tagsetid from the custom select to the hidden format option.
        if (isset($data['tagsetid_select'])) {
            $data['tagsetid'] = (int)$data['tagsetid_select'];
        }

        // Collect selected tags from individual checkbox fields (selectedtag_1, selectedtag_2, etc.).
        $selectedtags = [];
        foreach ($data as $key => $value) {
            if (strpos($key, 'selectedtag_') === 0 && !empty($value)) {
                $selectedtags[] = (int)$value;
            }
        }

        // If we found checkbox values, use them; otherwise check if selectedtags is already set.
        if (!empty($selectedtags)) {
            $data['selectedtags'] = implode(',', $selectedtags);
        } else if (isset($data['selectedtags']) && is_array($data['selectedtags'])) {
            // Fallback for autocomplete-style array.
            $data['selectedtags'] = implode(',', array_filter($data['selectedtags']));
        }

        $result = parent::update_course_format_options($data, $oldcourse);

        // Clear course tags cache if selectedtags was updated.
        if ($result && isset($data['selectedtags'])) {
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
        \format_minimoodlewall\style_manager::FILEAREA_STYLE_CARDIMAGE,
        \format_minimoodlewall\style_manager::FILEAREA_STYLE_FILTERIMAGE,
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
