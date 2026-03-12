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
 * Dynamic form for editing a tag with per-profile overrides.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall\form;

defined('MOODLE_INTERNAL') || die();

use format_minimoodlewall\tag_manager;
use format_minimoodlewall\profile_manager;
use core_form\dynamic_form;
use context;

/**
 * Tag edit form (dynamic form for modal usage).
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tag_form extends dynamic_form {
    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        // Tag ID (hidden).
        $mform->addElement('hidden', 'tagid');
        $mform->setType('tagid', PARAM_INT);

        $selectedprofileid = $this->optional_param('selectedprofileid', 0, PARAM_INT);
        $tagid = $this->optional_param('tagid', 0, PARAM_INT);
        $activitytypes = $this->get_activity_types();

        if ($selectedprofileid) {
            // ---- Profile override mode: show only profile-specific fields ----
            $profile = profile_manager::get_profile($selectedprofileid);
            if ($profile) {
                $this->add_profile_section($mform, $profile, $activitytypes, $tagid);
            }
        } else {
            // ---- Base tag fields (only when no profile is selected) ----
            $mform->addElement('header', 'basetagheader', get_string('basetagfields', 'format_minimoodlewall'));
            $mform->setExpanded('basetagheader', true);

            // Tag name.
            $mform->addElement('text', 'name', get_string('tagname', 'format_minimoodlewall'), ['size' => 60]);
            $mform->setType('name', PARAM_TEXT);
            $mform->addRule('name', get_string('required'), 'required', null, 'client');

            // Activity type 1.
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

            // Card image (base).
            $mform->addElement(
                'filemanager',
                'cardimagefile',
                get_string('cardimage', 'format_minimoodlewall'),
                null,
                tag_manager::get_image_filemanager_options()
            );
            $mform->addHelpButton('cardimagefile', 'cardimage', 'format_minimoodlewall');

            // Filter image (base).
            $mform->addElement(
                'filemanager',
                'filterimagefile',
                get_string('filterimage', 'format_minimoodlewall'),
                null,
                tag_manager::get_image_filemanager_options()
            );
            $mform->addHelpButton('filterimagefile', 'filterimage', 'format_minimoodlewall');
        }

        // Store profile IDs as hidden field for processing.
        $mform->addElement('hidden', 'profileids', $selectedprofileid ? (string) $selectedprofileid : '');
        $mform->setType('profileids', PARAM_TEXT);

        // Store selected profile ID as hidden field for re-rendering.
        $mform->addElement('hidden', 'selectedprofileid', $selectedprofileid);
        $mform->setType('selectedprofileid', PARAM_INT);
    }

    /**
     * Add form fields for a single profile's override section.
     *
     * @param \MoodleQuickForm $mform The form object
     * @param \stdClass $profile The profile record
     * @param array $activitytypes Available activity types
     * @param int $tagid Tag ID (0 for new tags)
     */
    protected function add_profile_section(\MoodleQuickForm $mform, \stdClass $profile, array $activitytypes, int $tagid): void {
        $mform->addElement(
            'header',
            'profileheader_' . $profile->id,
            get_string('profileoverrides', 'format_minimoodlewall', $profile->displayname)
        );
        $mform->setExpanded('profileheader_' . $profile->id, true);

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

        // Check per-profile override bgcolor format (only for displayed profile).
        $profileids = !empty($data['profileids']) ? explode(',', $data['profileids']) : [];
        foreach ($profileids as $profileid) {
            $fieldname = 'profile_bgcolor_' . $profileid;
            $overridecolor = $data[$fieldname] ?? '';
            if (!empty($overridecolor) && !preg_match('/^#([0-9a-fA-F]{6})$/', $overridecolor)) {
                $errors[$fieldname] = get_string('invalidcolor', 'format_minimoodlewall');
            }
        }

        // Check that the displayed profile has a card image (if a profile is selected),
        // or that at least one profile has an image (when editing base only, check all profiles).
        $tagid = $this->optional_param('tagid', 0, PARAM_INT);

        if (!empty($profileids)) {
            // Check if base already has a card image (draft or stored).
            $basehasimage = false;
            $basedraftid = $data['cardimagefile'] ?? 0;
            if ($basedraftid) {
                $basefileinfo = \file_get_draft_area_info($basedraftid);
                if (!empty($basefileinfo['filecount'])) {
                    $basehasimage = true;
                }
            }
            if (!$basehasimage && $tagid && tag_manager::has_cardimage($tagid)) {
                $basehasimage = true;
            }

            if (!$basehasimage) {
                // No base image — check the profile override has one.
                $hasimage = false;
                foreach ($profileids as $profileid) {
                    $fieldname = 'cardimage_profile_' . $profileid;
                    $draftid = $data[$fieldname] ?? 0;
                    $fileinfo = \file_get_draft_area_info($draftid);
                    if (!empty($fileinfo['filecount'])) {
                        $hasimage = true;
                        break;
                    }
                    if ($tagid) {
                        $profiletag = profile_manager::get_profile_tag_for_profile($tagid, (int)$profileid);
                        if ($profiletag && !empty($profiletag->cardimage)) {
                            $hasimage = true;
                            break;
                        }
                    }
                }
                if (!$hasimage) {
                    $firstprofileid = reset($profileids);
                    $errors['cardimage_profile_' . $firstprofileid] = get_string('atleastoneimage', 'format_minimoodlewall');
                }
            }
        }

        return $errors;
    }

    /**
     * Returns context where this form is used.
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        return \context_system::instance();
    }

    /**
     * Checks if current user has sufficient permissions.
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('moodle/site:config', \context_system::instance());
    }

    /**
     * Load in existing data as form defaults.
     */
    public function set_data_for_dynamic_submission(): void {
        $tagid = $this->optional_param('tagid', 0, PARAM_INT);
        $selectedprofileid = $this->optional_param('selectedprofileid', 0, PARAM_INT);

        $formdata = [];
        $formdata['tagid'] = $tagid;
        $formdata['selectedprofileid'] = $selectedprofileid;
        $formdata['profileids'] = $selectedprofileid ? (string) $selectedprofileid : '';

        if ($tagid) {
            $tag = tag_manager::get_tag($tagid);
            if ($tag) {
                if (!$selectedprofileid) {
                    // Base mode: load all base tag fields.
                    $formdata = array_merge($formdata, (array) $tag);
                    $formdata['tagid'] = $tag->id;
                }
            }

            if (!$selectedprofileid) {
                // Base image drafts.
                $formdata['cardimagefile'] = tag_manager::prepare_cardimage_draft($tagid);
                $formdata['filterimagefile'] = tag_manager::prepare_filterimage_draft($tagid);
            }

            // Profile-specific image drafts.
            if ($selectedprofileid) {
                $formdata['cardimage_profile_' . $selectedprofileid] =
                    profile_manager::prepare_cardimage_draft($tagid, $selectedprofileid);
                $formdata['filterimage_profile_' . $selectedprofileid] =
                    profile_manager::prepare_filterimage_draft($tagid, $selectedprofileid);
            }
        } else if (!$selectedprofileid) {
            // New tag (base mode only) - prepare empty draft areas.
            $formdata['cardimagefile'] = 0;
            $formdata['filterimagefile'] = 0;
        }

        $this->set_data($formdata);
    }

    /**
     * Process the form submission.
     *
     * @return array
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();

        $savedprofileids = !empty($data->profileids) ? explode(',', $data->profileids) : [];

        if (empty($savedprofileids)) {
            // Base tag mode: update or create the base tag.
            if (!empty($data->tagid)) {
                tag_manager::update_tag(
                    $data->tagid,
                    [
                        'name' => $data->name,
                        'activitytype1' => $data->activitytype1,
                        'activitytype2' => $data->activitytype2,
                        'activitytype3' => $data->activitytype3,
                        'bgcolor' => $data->bgcolor,
                        'imgplacement' => $data->imgplacement,
                        'imgsize' => $data->imgsize,
                    ]
                );
                $currenttagid = $data->tagid;
            } else {
                $currenttagid = tag_manager::create_tag(
                    $data->name,
                    null,
                    null,
                    $data->activitytype1,
                    $data->activitytype2,
                    $data->activitytype3,
                    $data->bgcolor,
                    $data->imgplacement,
                    $data->imgsize
                );
            }

            // Save base images.
            if (isset($data->cardimagefile)) {
                tag_manager::save_cardimage_from_draft($currenttagid, (int)$data->cardimagefile);
            }
            if (isset($data->filterimagefile)) {
                tag_manager::save_filterimage_from_draft($currenttagid, (int)$data->filterimagefile);
            }
        } else {
            // Profile override mode: tag must already exist.
            $currenttagid = $data->tagid;
        }

        // Save profile-specific images and overrides (only for the displayed profile).
        foreach ($savedprofileids as $profileid) {
            $cardfield = 'cardimage_profile_' . $profileid;
            $filterfield = 'filterimage_profile_' . $profileid;

            if (isset($data->$cardfield)) {
                profile_manager::save_cardimage_from_draft($currenttagid, (int)$profileid, (int)$data->$cardfield);
            }
            if (isset($data->$filterfield)) {
                profile_manager::save_filterimage_from_draft($currenttagid, (int)$profileid, (int)$data->$filterfield);
            }
        }

        // Save profile-specific override fields.
        foreach ($savedprofileids as $profileid) {
            $overrides = [];
            $fields = [
                'name' => 'profile_name_',
                'bgcolor' => 'profile_bgcolor_',
                'activitytype1' => 'profile_activitytype1_',
                'activitytype2' => 'profile_activitytype2_',
                'activitytype3' => 'profile_activitytype3_',
                'imgplacement' => 'profile_imgplacement_',
                'imgsize' => 'profile_imgsize_',
            ];

            foreach ($fields as $key => $prefix) {
                $fieldname = $prefix . $profileid;
                if (isset($data->$fieldname)) {
                    $overrides[$key] = $data->$fieldname !== '' ? $data->$fieldname : null;
                }
            }

            $enabledfield = 'profile_enabled_' . $profileid;
            if (isset($data->$enabledfield)) {
                $overrides['enabled'] = (int)$data->$enabledfield;
            }

            if (!empty($overrides)) {
                $pt = profile_manager::get_or_create_profile_tag($currenttagid, (int)$profileid);
                profile_manager::update_profile_tag($pt->id, $overrides);
            }
        }

        return [
            'result' => true,
            'tagid' => $currenttagid,
        ];
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX.
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/course/format/minimoodlewall/tag_management.php');
    }
}
