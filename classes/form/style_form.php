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
 * Form for editing a style.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Style edit form.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class style_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        // Style ID (hidden).
        $mform->addElement('hidden', 'styleid');
        $mform->setType('styleid', PARAM_INT);

        // Style internal name.
        $mform->addElement('text', 'name', get_string('stylename', 'format_minimoodlewall'), ['size' => 30]);
        $mform->setType('name', PARAM_ALPHANUMEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('name', 'stylename', 'format_minimoodlewall');

        // Style display name.
        $mform->addElement('text', 'displayname', get_string('styledisplayname', 'format_minimoodlewall'), ['size' => 60]);
        $mform->setType('displayname', PARAM_TEXT);
        $mform->addRule('displayname', get_string('required'), 'required', null, 'client');

        // Sort order.
        $mform->addElement('text', 'sortorder', get_string('sortorder', 'format_minimoodlewall'), ['size' => 5]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);

        // Action buttons.
        $this->add_action_buttons();
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
        $existing = $DB->get_record('format_minimoodlewall_styles', ['name' => $data['name']]);
        if ($existing && $existing->id != ($data['styleid'] ?? 0)) {
            $errors['name'] = get_string('stylename_exists', 'format_minimoodlewall');
        }

        return $errors;
    }
}
