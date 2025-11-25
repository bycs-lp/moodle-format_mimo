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
            'tagsetid' => [
                'default' => 0,
                'type' => PARAM_INT,
            ],
            'enablefiltering' => [
                'default' => 1,
                'type' => PARAM_BOOL,
            ],
            'designvariant' => [
                'default' => 'classic',
                'type' => PARAM_ALPHANUMEXT,
            ],
        ];
        
        if ($forupdate) {
            // Get available tagsets.
            $tagsets = \format_minimoodlewall\tag_manager::get_tagsets();
            $tagsetchoices = [0 => get_string('selecttagset', 'format_minimoodlewall')];
            foreach ($tagsets as $tagset) {
                $tagsetchoices[$tagset->id] = $tagset->name;
            }
            
            // Check if this is an existing course.
            $course = $this->get_course();
            $iscreating = empty($course->id) || $course->id == SITEID;
            
            // Add form elements for course settings.
            $courseformatoptions['tagsetid'] += [
                'label' => get_string('setting_tagsetid', 'format_minimoodlewall'),
                'help' => 'setting_tagsetid',
                'help_component' => 'format_minimoodlewall',
                'element_type' => 'select',
                'element_attributes' => [
                    $tagsetchoices,
                    $iscreating ? [] : ['disabled' => 'disabled'],
                ],
            ];
            $courseformatoptions['enablefiltering'] += [
                'label' => get_string('setting_enablefiltering', 'format_minimoodlewall'),
                'help' => 'setting_enablefiltering',
                'help_component' => 'format_minimoodlewall',
                'element_type' => 'advcheckbox',
            ];
            $courseformatoptions['designvariant'] += [
                'label' => get_string('setting_design', 'format_minimoodlewall'),
                'help' => 'setting_design',
                'help_component' => 'format_minimoodlewall',
                'element_type' => 'select',
                'element_attributes' => [[
                    'classic' => get_string('design_classic', 'format_minimoodlewall'),
                    'light' => get_string('design_light', 'format_minimoodlewall'),
                    'dark' => get_string('design_dark', 'format_minimoodlewall'),
                ]],
            ];
        }
        return $courseformatoptions;
    }

    /**
     * Require to pick a tag set when creating a course with this format.
     *
     * @param array $data Submitted form data
     * @param array $files Uploaded files
     * @param array $errors Existing validation errors
     * @return array
     */
    public function edit_form_validation($data, $files, $errors) {
        $errors = parent::edit_form_validation($data, $files, $errors);

        if (empty((int)($data['tagsetid'] ?? 0))) {
            $errors['tagsetid'] = get_string('error_required_tagset', 'format_minimoodlewall');
        }

        return $errors;
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
