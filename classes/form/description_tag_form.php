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
 * Description tag form for format_minimoodlewall.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for creating and editing description tags.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class description_tag_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        // Tag ID (hidden field for editing).
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Tag name.
        $mform->addElement('text', 'name', get_string('desctagname', 'format_minimoodlewall'), ['size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        // Tag color (color picker).
        $mform->addElement('text', 'color', get_string('desctagcolor', 'format_minimoodlewall'), [
            'size' => 10,
            'placeholder' => '#FF5733',
        ]);
        $mform->setType('color', PARAM_TEXT);
        $mform->addRule('color', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('color', 'desctagcolor', 'format_minimoodlewall');

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Validation.
     *
     * @param array $data Form data
     * @param array $files Form files
     * @return array Errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Validate color format.
        if (!empty($data['color'])) {
            if (!\format_minimoodlewall\description_tag_manager::is_valid_color($data['color'])) {
                $errors['color'] = get_string('invalidcolor', 'format_minimoodlewall');
            }
        }

        return $errors;
    }
}
