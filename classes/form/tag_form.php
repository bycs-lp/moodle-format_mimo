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
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo\form;


use format_mimo\tag_manager;
use format_mimo\profile_manager;
use core_form\dynamic_form;
use context;

/**
 * Tag edit form (dynamic form for modal usage).
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
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

        // The form always operates on one tagset (profile).
        $profile = \core\di::get(profile_manager::class)->get_profile($selectedprofileid);
        if ($profile) {
            $this->add_profile_section($mform, $profile, $activitytypes, $tagid);
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
            get_string('profileoverrides', 'format_mimo', $profile->displayname)
        );
        $mform->setExpanded('profileheader_' . $profile->id, true);

        // Tag name.
        $mform->addElement(
            'text',
            'profile_name_' . $profile->id,
            get_string('profiletag_name', 'format_mimo'),
            ['size' => 60]
        );
        $mform->setType('profile_name_' . $profile->id, PARAM_TEXT);
        $mform->addRule('profile_name_' . $profile->id, get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('profile_name_' . $profile->id, 'profiletag_name', 'format_mimo');

        // Background color.
        $mform->addElement(
            'text',
            'profile_bgcolor_' . $profile->id,
            get_string('profiletag_bgcolor', 'format_mimo'),
            ['size' => 8, 'type' => 'color']
        );
        $mform->setType('profile_bgcolor_' . $profile->id, PARAM_TEXT);
        $mform->addHelpButton('profile_bgcolor_' . $profile->id, 'profiletag_bgcolor', 'format_mimo');

        // Activity types (type 1 required, 2/3 optional).
        $mform->addElement(
            'select',
            'profile_activitytype1_' . $profile->id,
            get_string('profiletag_activitytype1', 'format_mimo'),
            $activitytypes
        );
        $mform->setType('profile_activitytype1_' . $profile->id, PARAM_TEXT);
        $mform->addRule('profile_activitytype1_' . $profile->id, get_string('required'), 'required', null, 'client');

        $mform->addElement(
            'select',
            'profile_activitytype2_' . $profile->id,
            get_string('profiletag_activitytype2', 'format_mimo'),
            $activitytypes
        );
        $mform->setType('profile_activitytype2_' . $profile->id, PARAM_TEXT);

        $mform->addElement(
            'select',
            'profile_activitytype3_' . $profile->id,
            get_string('profiletag_activitytype3', 'format_mimo'),
            $activitytypes
        );
        $mform->setType('profile_activitytype3_' . $profile->id, PARAM_TEXT);

        // Image placement.
        $placementoptions = [
            'center' => get_string('imgplacement_center', 'format_mimo'),
            'lower' => get_string('imgplacement_lower', 'format_mimo'),
        ];
        $mform->addElement(
            'select',
            'profile_imgplacement_' . $profile->id,
            get_string('profiletag_imgplacement', 'format_mimo'),
            $placementoptions
        );
        $mform->setType('profile_imgplacement_' . $profile->id, PARAM_TEXT);
        $mform->setDefault('profile_imgplacement_' . $profile->id, 'center');

        // Image size.
        $sizeoptions = [
            'bigger' => get_string('imgsize_bigger', 'format_mimo'),
            'normal' => get_string('imgsize_normal', 'format_mimo'),
            'smaller' => get_string('imgsize_smaller', 'format_mimo'),
        ];
        $mform->addElement(
            'select',
            'profile_imgsize_' . $profile->id,
            get_string('profiletag_imgsize', 'format_mimo'),
            $sizeoptions
        );
        $mform->setType('profile_imgsize_' . $profile->id, PARAM_TEXT);
        $mform->setDefault('profile_imgsize_' . $profile->id, 'normal');

        // Card image for this profile.
        $profilemanager = \core\di::get(profile_manager::class);
        $mform->addElement(
            'filemanager',
            'cardimage_profile_' . $profile->id,
            get_string('cardimage_for_profile', 'format_mimo', $profile->displayname),
            null,
            $profilemanager->get_image_filemanager_options()
        );
        $mform->addHelpButton('cardimage_profile_' . $profile->id, 'cardimage', 'format_mimo');

        // Filter image for this profile.
        $mform->addElement(
            'filemanager',
            'filterimage_profile_' . $profile->id,
            get_string('filterimage_for_profile', 'format_mimo', $profile->displayname),
            null,
            $profilemanager->get_image_filemanager_options()
        );
        $mform->addHelpButton('filterimage_profile_' . $profile->id, 'filterimage', 'format_mimo');

        // Defaults: resolved values (anchor values for never-configured sets)
        // when editing; palette default colour when creating.
        if ($tagid) {
            $anchor = \core\di::get(tag_manager::class)->get_tag($tagid);
            $resolved = $profilemanager->resolve_tag_for_profile($anchor, (int) $profile->id);
            $mform->setDefault('profile_name_' . $profile->id, $resolved->name);
            $mform->setDefault('profile_bgcolor_' . $profile->id, $resolved->bgcolor);
            $mform->setDefault('profile_activitytype1_' . $profile->id, (string) $resolved->activitytype1);
            $mform->setDefault('profile_activitytype2_' . $profile->id, (string) $resolved->activitytype2);
            $mform->setDefault('profile_activitytype3_' . $profile->id, (string) $resolved->activitytype3);
            $mform->setDefault('profile_imgplacement_' . $profile->id, $resolved->imgplacement ?? 'center');
            $mform->setDefault('profile_imgsize_' . $profile->id, $resolved->imgsize ?? 'normal');
        } else {
            $defaultcolor = \core\di::get(tag_manager::class)->get_default_accent_palette()[0] ?? '#dcecff';
            $mform->setDefault('profile_bgcolor_' . $profile->id, $defaultcolor);
        }
    }

    /**
     * Get list of available activity types.
     *
     * @return array Activity types
     */
    protected function get_activity_types() {
        return [
            '' => get_string('selectactivitytype', 'format_mimo'),
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

        // Check per-profile bgcolor format (only for displayed profile).
        $profileids = !empty($data['profileids']) ? explode(',', $data['profileids']) : [];
        foreach ($profileids as $profileid) {
            $fieldname = 'profile_bgcolor_' . $profileid;
            $overridecolor = $data[$fieldname] ?? '';
            if (!empty($overridecolor) && !preg_match('/^#([0-9a-fA-F]{6})$/', $overridecolor)) {
                $errors[$fieldname] = get_string('invalidcolor', 'format_mimo');
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
        return \core\context\system::instance();
    }

    /**
     * Checks if current user has sufficient permissions.
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('moodle/site:config', \core\context\system::instance());
    }

    /**
     * Load in existing data as form defaults.
     */
    public function set_data_for_dynamic_submission(): void {
        $tagid = $this->optional_param('tagid', 0, PARAM_INT);
        $selectedprofileid = $this->optional_param('selectedprofileid', 0, PARAM_INT);

        $formdata = [
            'tagid' => $tagid,
            'selectedprofileid' => $selectedprofileid,
            'profileids' => $selectedprofileid ? (string) $selectedprofileid : '',
        ];

        if ($tagid && $selectedprofileid) {
            $profilemanager = \core\di::get(profile_manager::class);
            $formdata['cardimage_profile_' . $selectedprofileid] =
                $profilemanager->prepare_cardimage_draft($tagid, $selectedprofileid);
            $formdata['filterimage_profile_' . $selectedprofileid] =
                $profilemanager->prepare_filterimage_draft($tagid, $selectedprofileid);
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

        $profileid = (int) $data->selectedprofileid;
        $tagmanager = \core\di::get(tag_manager::class);
        $profilemanager = \core\di::get(profile_manager::class);

        $values = [
            'name' => $data->{'profile_name_' . $profileid},
            'bgcolor' => $data->{'profile_bgcolor_' . $profileid},
            'activitytype1' => $data->{'profile_activitytype1_' . $profileid},
            'activitytype2' => $data->{'profile_activitytype2_' . $profileid} ?: null,
            'activitytype3' => $data->{'profile_activitytype3_' . $profileid} ?: null,
            'imgplacement' => $data->{'profile_imgplacement_' . $profileid},
            'imgsize' => $data->{'profile_imgsize_' . $profileid},
        ];

        if (!empty($data->tagid)) {
            // Edit: write this set's row only.
            $currenttagid = (int) $data->tagid;
            $profilemanager->materialize_profile_tag($currenttagid, $profileid, $values);
        } else {
            // Create: anchor row + enabled row in the creating set.
            $currenttagid = $tagmanager->create_tag(
                $values['name'],
                null,
                null,
                $values['activitytype1'],
                $values['activitytype2'],
                $values['activitytype3'],
                $values['bgcolor'],
                $values['imgplacement'],
                $values['imgsize'],
                'global',
                $profileid
            );
        }

        // Per-set images (also mirrored to anchor areas on create for
        // fingerprint/back-compat).
        $cardfield = 'cardimage_profile_' . $profileid;
        $filterfield = 'filterimage_profile_' . $profileid;
        if (isset($data->$cardfield)) {
            $profilemanager->save_cardimage_from_draft($currenttagid, $profileid, (int) $data->$cardfield);
            if (empty($data->tagid)) {
                $tagmanager->save_cardimage_from_draft($currenttagid, (int) $data->$cardfield);
            }
        }
        if (isset($data->$filterfield)) {
            $profilemanager->save_filterimage_from_draft($currenttagid, $profileid, (int) $data->$filterfield);
            if (empty($data->tagid)) {
                $tagmanager->save_filterimage_from_draft($currenttagid, (int) $data->$filterfield);
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
        return new \moodle_url('/course/format/mimo/tag_management.php');
    }
}
