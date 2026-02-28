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
 * Form for editing a tag with per-profile overrides.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall\form;

defined('MOODLE_INTERNAL') || die();

use format_minimoodlewall\tag_manager;
use format_minimoodlewall\profile_manager;

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

        // ---- Base tag fields ----
        $mform->addElement('header', 'basetagheader', get_string('basetagfields', 'format_minimoodlewall'));
        $mform->setExpanded('basetagheader', true);

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

        // Image size.
        $sizeoptions = [
            $mform->createElement(
                'radio',
                'imgsize',
                '',
                get_string('imgsize_bigger', 'format_minimoodlewall'),
                'bigger'
            ),
            $mform->createElement(
                'radio',
                'imgsize',
                '',
                get_string('imgsize_normal', 'format_minimoodlewall'),
                'normal'
            ),
            $mform->createElement(
                'radio',
                'imgsize',
                '',
                get_string('imgsize_smaller', 'format_minimoodlewall'),
                'smaller'
            ),
        ];
        $mform->addGroup(
            $sizeoptions,
            'imgsizegroup',
            get_string('imgsize', 'format_minimoodlewall'),
            ['<br>'],
            false
        );
        $mform->setDefault('imgsize', 'normal');
        $mform->setType('imgsize', PARAM_TEXT);

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

        // ---- Per-profile overrides and images ----
        $profiles = profile_manager::get_all_profiles();
        $tagid = $this->_customdata['tagid'] ?? 0;

        foreach ($profiles as $profile) {
            $mform->addElement(
                'header',
                'profileheader_' . $profile->id,
                get_string('profileoverrides', 'format_minimoodlewall', $profile->displayname)
            );
            $mform->setExpanded('profileheader_' . $profile->id, false);

            // Enabled flag.
            $mform->addElement(
                'advcheckbox',
                'profile_enabled_' . $profile->id,
                get_string('profiletag_enabled', 'format_minimoodlewall'),
                get_string('profiletag_enabled_desc', 'format_minimoodlewall'),
                [],
                [0, 1]
            );
            $mform->setDefault('profile_enabled_' . $profile->id, 1);

            // Override: Tag name.
            $mform->addElement(
                'text',
                'profile_name_' . $profile->id,
                get_string('profiletag_name', 'format_minimoodlewall'),
                ['size' => 60]
            );
            $mform->setType('profile_name_' . $profile->id, PARAM_TEXT);
            $mform->addHelpButton('profile_name_' . $profile->id, 'profiletag_name', 'format_minimoodlewall');

            // Override: Background color.
            $mform->addElement(
                'text',
                'profile_bgcolor_' . $profile->id,
                get_string('profiletag_bgcolor', 'format_minimoodlewall'),
                ['size' => 8, 'type' => 'color']
            );
            $mform->setType('profile_bgcolor_' . $profile->id, PARAM_TEXT);
            $mform->addHelpButton('profile_bgcolor_' . $profile->id, 'profiletag_bgcolor', 'format_minimoodlewall');

            // Override: Activity types.
            $overrideactivitytypes = ['' => get_string('inherit_from_base', 'format_minimoodlewall')] + $activitytypes;
            $mform->addElement(
                'select',
                'profile_activitytype1_' . $profile->id,
                get_string('profiletag_activitytype1', 'format_minimoodlewall'),
                $overrideactivitytypes
            );
            $mform->setType('profile_activitytype1_' . $profile->id, PARAM_TEXT);

            $mform->addElement(
                'select',
                'profile_activitytype2_' . $profile->id,
                get_string('profiletag_activitytype2', 'format_minimoodlewall'),
                $overrideactivitytypes
            );
            $mform->setType('profile_activitytype2_' . $profile->id, PARAM_TEXT);

            $mform->addElement(
                'select',
                'profile_activitytype3_' . $profile->id,
                get_string('profiletag_activitytype3', 'format_minimoodlewall'),
                $overrideactivitytypes
            );
            $mform->setType('profile_activitytype3_' . $profile->id, PARAM_TEXT);

            // Override: Image placement.
            $overrideplacementoptions = [
                '' => get_string('inherit_from_base', 'format_minimoodlewall'),
                'center' => get_string('imgplacement_center', 'format_minimoodlewall'),
                'lower' => get_string('imgplacement_lower', 'format_minimoodlewall'),
            ];
            $mform->addElement(
                'select',
                'profile_imgplacement_' . $profile->id,
                get_string('profiletag_imgplacement', 'format_minimoodlewall'),
                $overrideplacementoptions
            );
            $mform->setType('profile_imgplacement_' . $profile->id, PARAM_TEXT);

            // Override: Image size.
            $overridesizeoptions = [
                '' => get_string('inherit_from_base', 'format_minimoodlewall'),
                'bigger' => get_string('imgsize_bigger', 'format_minimoodlewall'),
                'normal' => get_string('imgsize_normal', 'format_minimoodlewall'),
                'smaller' => get_string('imgsize_smaller', 'format_minimoodlewall'),
            ];
            $mform->addElement(
                'select',
                'profile_imgsize_' . $profile->id,
                get_string('profiletag_imgsize', 'format_minimoodlewall'),
                $overridesizeoptions
            );
            $mform->setType('profile_imgsize_' . $profile->id, PARAM_TEXT);

            // Card image for this profile.
            $mform->addElement(
                'filemanager',
                'cardimage_profile_' . $profile->id,
                get_string('cardimage_for_profile', 'format_minimoodlewall', $profile->displayname),
                null,
                profile_manager::get_image_filemanager_options()
            );
            $mform->addHelpButton('cardimage_profile_' . $profile->id, 'cardimage', 'format_minimoodlewall');

            // Filter image for this profile.
            $mform->addElement(
                'filemanager',
                'filterimage_profile_' . $profile->id,
                get_string('filterimage_for_profile', 'format_minimoodlewall', $profile->displayname),
                null,
                profile_manager::get_image_filemanager_options()
            );
            $mform->addHelpButton('filterimage_profile_' . $profile->id, 'filterimage', 'format_minimoodlewall');

            // Load existing override data if editing.
            if ($tagid) {
                $pt = profile_manager::get_profile_tag_for_profile($tagid, $profile->id);
                if ($pt) {
                    $mform->setDefault('profile_enabled_' . $profile->id, (int) $pt->enabled);
                    if ($pt->name !== null) {
                        $mform->setDefault('profile_name_' . $profile->id, $pt->name);
                    }
                    if ($pt->bgcolor !== null) {
                        $mform->setDefault('profile_bgcolor_' . $profile->id, $pt->bgcolor);
                    }
                    if ($pt->activitytype1 !== null) {
                        $mform->setDefault('profile_activitytype1_' . $profile->id, $pt->activitytype1);
                    }
                    if ($pt->activitytype2 !== null) {
                        $mform->setDefault('profile_activitytype2_' . $profile->id, $pt->activitytype2);
                    }
                    if ($pt->activitytype3 !== null) {
                        $mform->setDefault('profile_activitytype3_' . $profile->id, $pt->activitytype3);
                    }
                    if ($pt->imgplacement !== null) {
                        $mform->setDefault('profile_imgplacement_' . $profile->id, $pt->imgplacement);
                    }
                    if ($pt->imgsize !== null) {
                        $mform->setDefault('profile_imgsize_' . $profile->id, $pt->imgsize);
                    }
                }
            }
        }

        // Store profile IDs as hidden field for processing.
        $profileids = array_keys($profiles);
        $mform->addElement('hidden', 'profileids', implode(',', $profileids));
        $mform->setType('profileids', PARAM_TEXT);

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

        // Check per-profile override bgcolor format.
        $profileids = !empty($data['profileids']) ? explode(',', $data['profileids']) : [];
        foreach ($profileids as $profileid) {
            $fieldname = 'profile_bgcolor_' . $profileid;
            $overridecolor = $data[$fieldname] ?? '';
            if (!empty($overridecolor) && !preg_match('/^#([0-9a-fA-F]{6})$/', $overridecolor)) {
                $errors[$fieldname] = get_string('invalidcolor', 'format_minimoodlewall');
            }
        }

        // Check that at least one profile has a card image.
        $tagid = $this->_customdata['tagid'] ?? 0;
        $hasanyimage = false;

        foreach ($profileids as $profileid) {
            $fieldname = 'cardimage_profile_' . $profileid;
            $draftid = $data[$fieldname] ?? 0;
            $fileinfo = \file_get_draft_area_info($draftid);
            if (!empty($fileinfo['filecount'])) {
                $hasanyimage = true;
                break;
            }
            // Check if existing image exists.
            if ($tagid) {
                $profiletag = profile_manager::get_profile_tag_for_profile($tagid, (int)$profileid);
                if ($profiletag && !empty($profiletag->cardimage)) {
                    $hasanyimage = true;
                    break;
                }
            }
        }

        if (!$hasanyimage && !empty($profileids)) {
            $firstprofileid = reset($profileids);
            $errors['cardimage_profile_' . $firstprofileid] = get_string('atleastoneimage', 'format_minimoodlewall');
        }

        return $errors;
    }
}
