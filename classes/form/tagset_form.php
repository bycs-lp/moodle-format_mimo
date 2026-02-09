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
 * Form for editing a tagset.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 MBS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Tagset edit form.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 MBS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tagset_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        // Tagset ID (hidden).
        $mform->addElement('hidden', 'tagsetid');
        $mform->setType('tagsetid', PARAM_INT);

        // Tagset name.
        $mform->addElement('text', 'name', get_string('tagsetname', 'format_minimoodlewall'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        // Tagset description.
        $mform->addElement(
            'textarea',
            'description',
            get_string('tagsetdescription', 'format_minimoodlewall'),
            ['rows' => 3, 'cols' => 60]
        );
        $mform->setType('description', PARAM_TEXT);

        // Action buttons.
        $this->add_action_buttons();
    }

    /**
     * Validate form data.
     *
     * @param array $data Form data
     * @param array $files Files
     * @return array Validation errors
     */
    public function validation($data, $files): array {
        global $DB;

        $errors = parent::validation($data, $files);

        // Check for duplicate tagset name.
        $conditions = ['name' => $data['name']];
        if (!empty($data['tagsetid'])) {
            $existing = $DB->get_record('format_minimoodlewall_tagsets', $conditions);
            if ($existing && $existing->id != $data['tagsetid']) {
                $errors['name'] = get_string('tagsetname_exists', 'format_minimoodlewall');
            }
        } else {
            if ($DB->record_exists('format_minimoodlewall_tagsets', $conditions)) {
                $errors['name'] = get_string('tagsetname_exists', 'format_minimoodlewall');
            }
        }

        return $errors;
    }
}
