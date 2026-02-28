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
 * Completion defaults form for format_minimoodlewall.
 *
 * This form reuses the core default completion edit form to allow admins
 * to configure minimoodlewall-specific completion defaults per module type.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/completion/classes/edit_base_form.php');
require_once($CFG->dirroot . '/completion/classes/defaultedit_form.php');

/**
 * Form for editing minimoodlewall completion defaults for a single module type.
 *
 * Extends the core default completion form and pre-fills with existing
 * minimoodlewall defaults (or core defaults as fallback).
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_minimoodlewall_completion_defaults_form extends core_completion_defaultedit_form {
    /**
     * Form definition.
     *
     * Overrides the parent to inject minimoodlewall-specific data.
     */
    public function definition() {
        parent::definition();

        $mform = $this->_form;

        // Add a hidden field to identify this as a minimoodlewall form.
        $mform->addElement('hidden', 'mmw_form', 1);
        $mform->setType('mmw_form', PARAM_INT);
    }

    /**
     * Pre-fill the form with existing minimoodlewall defaults if available.
     *
     * This is called after definition() and populates the form with saved
     * minimoodlewall overrides rather than core defaults.
     */
    public function definition_after_data() {
        parent::definition_after_data();

        // If we have minimoodlewall defaults for this module, override the form values.
        if (!empty($this->_customdata['mmw_defaults'])) {
            $mmwdefaults = $this->_customdata['mmw_defaults'];
            $suffix = $this->get_suffix();

            $data = [];
            $data['completion' . $suffix] = (int)$mmwdefaults->completion;
            $data['completionview' . $suffix] = (int)$mmwdefaults->completionview;
            $data['completionusegrade' . $suffix] = (int)$mmwdefaults->completionusegrade;
            $data['completionpassgrade' . $suffix] = (int)$mmwdefaults->completionpassgrade;
            $data['completionexpected' . $suffix] = (int)$mmwdefaults->completionexpected;

            // Unpack custom rules.
            if (!empty($mmwdefaults->customrules)) {
                $customrules = @json_decode($mmwdefaults->customrules, true);
                if (is_array($customrules)) {
                    unset($customrules['modids']);
                    unset($customrules['id']);
                    foreach ($customrules as $key => $value) {
                        if (str_starts_with($key, 'completion')) {
                            $data[$key . $suffix] = $value;
                        } else {
                            $data[$key] = $value;
                        }
                    }
                }
            }

            $this->set_data($data);
        }
    }

    /**
     * Override the form identifier to be unique for minimoodlewall.
     *
     * @return string
     */
    protected function get_form_identifier() {
        return 'format_minimoodlewall_completion_defaults' . $this->get_suffix();
    }
}
