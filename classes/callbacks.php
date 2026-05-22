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

namespace format_mimo;

/**
 * Callback handler for format_mimo lib.php functions.
 *
 * Keeps lib.php lightweight by holding the implementation of Moodle callback
 * functions that are discovered by name (format_mimo_*) in lib.php.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class callbacks {
    /**
     * Serve files from the mimo course format.
     *
     * Supports the tag card/filter image file areas stored in the system context.
     *
     * @param \stdClass $course Course object (unused for system-context files)
     * @param \stdClass $cm Course module (unused)
     * @param \context $context Context the file belongs to
     * @param string $filearea File area name
     * @param array $args Remaining file path arguments
     * @param bool $forcedownload Whether the user must download the file
     * @param array $options Additional options passed to send_stored_file
     * @return void|false
     */
    public static function pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
        require_login();

        // Section images use course context; tag/profile images use system context.
        if ($filearea === section_image_manager::FILEAREA) {
            if ($context->contextlevel !== CONTEXT_COURSE) {
                return false;
            }
            require_login($course, true);
        } else if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return false;
        }

        $allowedareas = [
            tag_manager::FILEAREA_CARDIMAGE,
            tag_manager::FILEAREA_FILTERIMAGE,
            profile_manager::FILEAREA_PROFILE_CARDIMAGE,
            profile_manager::FILEAREA_PROFILE_FILTERIMAGE,
            section_image_manager::FILEAREA,
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
     * @param \moodleform_mod $formwrapper The module form wrapper
     * @param \MoodleQuickForm $mform The form object
     * @return void
     */
    public static function coursemodule_standard_elements($formwrapper, $mform): void {
        global $SESSION;

        $course = $formwrapper->get_course();
        if ($course->format !== 'mimo') {
            return;
        }

        $tags = tag_manager::get_tags_for_course($course->id);
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
            $currenttag = tag_manager::get_cm_tag($cm->id);
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
     * @param \stdClass $data The form submission data (includes $data->coursemodule)
     * @param \stdClass $course The course object
     * @return \stdClass The (possibly modified) data object
     */
    public static function coursemodule_edit_post_actions($data, $course) {
        if ($course->format !== 'mimo') {
            return $data;
        }

        if (!isset($data->mimo_cmtag)) {
            return $data;
        }

        $cmid = $data->coursemodule;
        $tagid = (int)$data->mimo_cmtag;

        if ($tagid > 0) {
            tag_manager::assign_tag_to_cm($cmid, $tagid);
        } else {
            tag_manager::remove_cm_tag($cmid);
        }

        return $data;
    }

    /**
     * Pre-populate mimo completion defaults in the module creation form.
     *
     * @param \moodleform_mod $formwrapper The form wrapper object
     * @param \MoodleQuickForm $mform The form object
     * @return void
     */
    public static function coursemodule_definition_after_data($formwrapper, $mform): void {
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
        $mimodefaults = completion_defaults_manager::get_default($moduleid);
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
     * @param string $itemtype The type of item being edited (sectionname or sectionnamenl)
     * @param int $itemid The section id
     * @param string $newvalue The new value
     * @return \core\output\inplace_editable
     */
    public static function inplace_editable($itemtype, $itemid, $newvalue) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
            $section = $DB->get_record_sql(
                'SELECT s.*
                   FROM {course_sections} s
                   JOIN {course} c ON s.course = c.id
                  WHERE s.id = :sectionid AND c.format = :format',
                ['sectionid' => $itemid, 'format' => 'mimo'],
                MUST_EXIST
            );
            return course_get_format($section->course)->inplace_editable_update_section_name(
                $section,
                $itemtype,
                $newvalue
            );
        }
    }
}
