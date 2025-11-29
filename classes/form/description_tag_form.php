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

use core_form\dynamic_form;
use context;
use format_minimoodlewall\description_tag_manager;

/**
 * Form for creating and editing description tags.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class description_tag_form extends dynamic_form {
    /**
     * Form definition.
     */
    public function definition() {
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
            if (!description_tag_manager::is_valid_color($data['color'])) {
                $errors['color'] = get_string('invalidcolor', 'format_minimoodlewall');
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
        $id = $this->optional_param('id', 0, PARAM_INT);

        if ($id) {
            $tag = description_tag_manager::get_tag($id);
            if ($tag) {
                $this->set_data($tag);
            }
        }
    }

    /**
     * Process the form submission.
     *
     * @return array
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();

        if (!empty($data->id)) {
            // Update existing tag.
            description_tag_manager::update_tag($data->id, $data->name, $data->color);
            $message = get_string('desctagsaved', 'format_minimoodlewall');
        } else {
            // Create new tag.
            description_tag_manager::create_tag($data->name, $data->color);
            $message = get_string('desctagcreated', 'format_minimoodlewall');
        }

        return [
            'result' => true,
            'message' => $message,
        ];
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX.
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/course/format/minimoodlewall/description_tags.php');
    }
}
