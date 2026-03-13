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
 * Dynamic form for uploading/changing a section overview card image.
 *
 * @package    format_minimoodlewall
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall\form;

use format_minimoodlewall\section_image_manager;
use core_form\dynamic_form;
use context;

/**
 * Section image upload dynamic form (opens in a modal).
 *
 * @package    format_minimoodlewall
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section_image_form extends dynamic_form {
    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'sectionid');
        $mform->setType('sectionid', PARAM_INT);

        $mform->addElement(
            'filepicker',
            'sectionimagefile',
            get_string('sectionimage', 'format_minimoodlewall'),
            null,
            section_image_manager::get_filemanager_options()
        );
    }

    /**
     * Returns context where this form is used.
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        $courseid = $this->optional_param('courseid', 0, PARAM_INT);
        return \context_course::instance($courseid);
    }

    /**
     * Checks if current user has sufficient permissions.
     */
    protected function check_access_for_dynamic_submission(): void {
        $courseid = $this->optional_param('courseid', 0, PARAM_INT);
        require_capability('moodle/course:update', \context_course::instance($courseid));
    }

    /**
     * Load existing data as form defaults.
     */
    public function set_data_for_dynamic_submission(): void {
        $courseid = $this->optional_param('courseid', 0, PARAM_INT);
        $sectionid = $this->optional_param('sectionid', 0, PARAM_INT);

        $draftitemid = section_image_manager::prepare_draft($courseid, $sectionid);

        $this->set_data([
            'courseid' => $courseid,
            'sectionid' => $sectionid,
            'sectionimagefile' => $draftitemid,
        ]);
    }

    /**
     * Process the form submission — save the uploaded image.
     *
     * @return array
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        section_image_manager::save_image((int) $data->courseid, (int) $data->sectionid, (int) $data->sectionimagefile);
        return ['result' => true];
    }

    /**
     * Returns URL to set in $PAGE->set_url() when form is being rendered or submitted via AJAX.
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        $courseid = $this->optional_param('courseid', 0, PARAM_INT);
        return new \moodle_url('/course/view.php', ['id' => $courseid, 'overview' => 1]);
    }
}
