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
 * Form for editing a tag.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall\form;

defined('MOODLE_INTERNAL') || die();

use format_minimoodlewall\tag_manager;
use format_minimoodlewall\style_manager;

global $CFG;
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/filelib.php');

/**
 * Tag edit form.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tag_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        // Tag ID (hidden).
        $mform->addElement('hidden', 'tagid');
        $mform->setType('tagid', PARAM_INT);

        // Tagset ID (hidden).
        $mform->addElement('hidden', 'tagsetid');
        $mform->setType('tagsetid', PARAM_INT);

        // Tag name.
        $mform->addElement('text', 'name', get_string('tagname', 'format_minimoodlewall'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        // Activity type 1.
        $activitytypes = $this->get_activity_types();
        $mform->addElement(
            'select',
            'activitytype1',
            get_string('activitytype1', 'format_minimoodlewall'),
            $activitytypes
        );
        $mform->setType('activitytype1', PARAM_TEXT);
        $mform->addRule('activitytype1', get_string('required'), 'required', null, 'client');

        // Activity type 2.
        $activitytypes2 = ['' => get_string('selectactivitytype', 'format_minimoodlewall')] + $activitytypes;
        $mform->addElement(
            'select',
            'activitytype2',
            get_string('activitytype2', 'format_minimoodlewall'),
            $activitytypes2
        );
        $mform->setType('activitytype2', PARAM_TEXT);

        // Activity type 3.
        $activitytypes3 = ['' => get_string('selectactivitytype', 'format_minimoodlewall')] + $activitytypes;
        $mform->addElement(
            'select',
            'activitytype3',
            get_string('activitytype3', 'format_minimoodlewall'),
            $activitytypes3
        );
        $mform->setType('activitytype3', PARAM_TEXT);

        // Image placement.
        $placementoptions = [
            $mform->createElement(
                'radio',
                'imgplacement',
                '',
                get_string('imgplacement_center', 'format_minimoodlewall'),
                'center'
            ),
            $mform->createElement(
                'radio',
                'imgplacement',
                '',
                get_string('imgplacement_lower', 'format_minimoodlewall'),
                'lower'
            ),
        ];
        $mform->addGroup(
            $placementoptions,
            'imgplacementgroup',
            get_string('imgplacement', 'format_minimoodlewall'),
            ['<br>'],
            false
        );
        $mform->setDefault('imgplacement', 'center');
        $mform->setType('imgplacement', PARAM_TEXT);

        // Background color.
        $defaultcolor = tag_manager::get_default_accent_palette()[0] ?? '#dcecff';
        $mform->addElement(
            'text',
            'bgcolor',
            get_string('tagbgcolor', 'format_minimoodlewall'),
            ['size' => 8, 'type' => 'color']
        );
        $mform->setType('bgcolor', PARAM_TEXT);
        $mform->setDefault('bgcolor', $defaultcolor);
        $mform->addHelpButton('bgcolor', 'tagbgcolor', 'format_minimoodlewall');

        // Style-specific images section.
        $mform->addElement('header', 'styleimagesheader', get_string('styleimages', 'format_minimoodlewall'));
        $mform->setExpanded('styleimagesheader', true);

        // Add image uploads for each style.
        $styles = style_manager::get_all_styles();
        foreach ($styles as $style) {
            // Card image for this style.
            $mform->addElement(
                'filemanager',
                'cardimage_style_' . $style->id,
                get_string('cardimage_for_style', 'format_minimoodlewall', $style->displayname),
                null,
                style_manager::get_image_filemanager_options()
            );
            $mform->addHelpButton('cardimage_style_' . $style->id, 'cardimage', 'format_minimoodlewall');

            // Filter image for this style (optional).
            $mform->addElement(
                'filemanager',
                'filterimage_style_' . $style->id,
                get_string('filterimage_for_style', 'format_minimoodlewall', $style->displayname),
                null,
                style_manager::get_image_filemanager_options()
            );
            $mform->addHelpButton('filterimage_style_' . $style->id, 'filterimage', 'format_minimoodlewall');
        }

        // Store style IDs as hidden field for processing.
        $styleids = array_keys($styles);
        $mform->addElement('hidden', 'styleids', implode(',', $styleids));
        $mform->setType('styleids', PARAM_TEXT);

        // Action buttons.
        $this->add_action_buttons();
    }

    /**
     * Get list of available activity types.
     *
     * @return array Activity types
     */
    protected function get_activity_types() {
        return [
            '' => get_string('selectactivitytype', 'format_minimoodlewall'),
            'assign' => get_string('pluginname', 'mod_assign'),
            'book' => get_string('pluginname', 'mod_book'),
            'choice' => get_string('pluginname', 'mod_choice'),
            'data' => get_string('pluginname', 'mod_data'),
            'feedback' => get_string('pluginname', 'mod_feedback'),
            'folder' => get_string('pluginname', 'mod_folder'),
            'forum' => get_string('pluginname', 'mod_forum'),
            'glossary' => get_string('pluginname', 'mod_glossary'),
            'h5pactivity' => get_string('pluginname', 'mod_h5pactivity'),
            'imscp' => get_string('pluginname', 'mod_imscp'),
            'label' => get_string('pluginname', 'mod_label'),
            'lesson' => get_string('pluginname', 'mod_lesson'),
            'lti' => get_string('pluginname', 'mod_lti'),
            'page' => get_string('pluginname', 'mod_page'),
            'quiz' => get_string('pluginname', 'mod_quiz'),
            'resource' => get_string('pluginname', 'mod_resource'),
            'scorm' => get_string('pluginname', 'mod_scorm'),
            'url' => get_string('pluginname', 'mod_url'),
            'wiki' => get_string('pluginname', 'mod_wiki'),
            'workshop' => get_string('pluginname', 'mod_workshop'),
        ];
    }

    /**
     * Validate form data.
     *
     * @param array $data Form data
     * @param array $files Files
     * @return array Validation errors
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        // Check background color format.
        $color = $data['bgcolor'] ?? '';
        if (!empty($color) && !preg_match('/^#([0-9a-fA-F]{6})$/', $color)) {
            $errors['bgcolor'] = get_string('invalidcolor', 'format_minimoodlewall');
        }

        // Check that at least one style has a card image.
        $tagid = $this->_customdata['tagid'] ?? 0;
        $styleids = !empty($data['styleids']) ? explode(',', $data['styleids']) : [];
        $hasanyimage = false;

        foreach ($styleids as $styleid) {
            $fieldname = 'cardimage_style_' . $styleid;
            $draftid = $data[$fieldname] ?? 0;
            $fileinfo = \file_get_draft_area_info($draftid);
            if (!empty($fileinfo['filecount'])) {
                $hasanyimage = true;
                break;
            }
            // Check if existing image exists.
            if ($tagid) {
                $tagimage = style_manager::get_tag_image_for_style($tagid, (int)$styleid);
                if ($tagimage && !empty($tagimage->cardimage)) {
                    $hasanyimage = true;
                    break;
                }
            }
        }

        if (!$hasanyimage && !empty($styleids)) {
            // Add error to first style's card image field.
            $firststyleid = reset($styleids);
            $errors['cardimage_style_' . $firststyleid] = get_string('atleastoneimage', 'format_minimoodlewall');
        }

        return $errors;
    }
}
