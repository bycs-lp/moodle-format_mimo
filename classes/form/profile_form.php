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
 * Dynamic form for editing an activity profile.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo\form;

defined('MOODLE_INTERNAL') || die();

use format_mimo\profile_manager;
use format_mimo\tag_manager;
use core_form\dynamic_form;
use context;

/**
 * Activity profile edit form (dynamic form for modal usage).
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_form extends dynamic_form {
    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        // Profile ID (hidden).
        $mform->addElement('hidden', 'profileid');
        $mform->setType('profileid', PARAM_INT);

        // Profile internal name.
        $mform->addElement('text', 'name', get_string('profilename', 'format_mimo'), ['size' => 30]);
        $mform->setType('name', PARAM_ALPHANUMEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('name', 'profilename', 'format_mimo');

        // Profile display name.
        $mform->addElement('text', 'displayname', get_string('profiledisplayname', 'format_mimo'), ['size' => 60]);
        $mform->setType('displayname', PARAM_TEXT);
        $mform->addRule('displayname', get_string('required'), 'required', null, 'client');

        // Sort order.
        $mform->addElement('text', 'sortorder', get_string('sortorder', 'format_mimo'), ['size' => 5]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);
    }

    /**
     * Validate the form data.
     *
     * @param array $data Submitted data
     * @param array $files Uploaded files
     * @return array Validation errors
     */
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);

        // Check for duplicate name.
        $existing = $DB->get_record('format_mimo_profiles', ['name' => $data['name']]);
        if ($existing && $existing->id != ($data['profileid'] ?? 0)) {
            $errors['name'] = get_string('profilename_exists', 'format_mimo');
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
        $profileid = $this->optional_param('profileid', 0, PARAM_INT);

        $formdata = ['profileid' => $profileid];

        if ($profileid) {
            $profile = profile_manager::get_profile($profileid);
            if ($profile) {
                $formdata['profileid'] = $profile->id;
                $formdata['name'] = $profile->name;
                $formdata['displayname'] = $profile->displayname;
                $formdata['sortorder'] = $profile->sortorder;
            }
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

        if (!empty($data->profileid)) {
            // Update existing profile.
            profile_manager::update_profile(
                $data->profileid,
                [
                    'name' => $data->name,
                    'displayname' => $data->displayname,
                    'sortorder' => $data->sortorder,
                ]
            );
            $profileid = $data->profileid;
        } else {
            // Create new profile.
            $profileid = profile_manager::create_profile(
                $data->name,
                $data->displayname,
                $data->sortorder
            );
        }

        return [
            'result' => true,
            'profileid' => $profileid,
        ];
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX.
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/course/format/mimo/profile_management.php');
    }
}
