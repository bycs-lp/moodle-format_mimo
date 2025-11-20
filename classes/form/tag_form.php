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
 * Form for editing a tag.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

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

        // Tagset ID (hidden).
        $mform->addElement('hidden', 'tagsetid');
        $mform->setType('tagsetid', PARAM_INT);

        // Tag name.
        $mform->addElement('text', 'name', get_string('tagname', 'format_minimoodlewall'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        // Tag description.
        $mform->addElement(
            'textarea',
            'description',
            get_string('tagdescription', 'format_minimoodlewall'),
            ['rows' => 3, 'cols' => 60]
        );
        $mform->setType('description', PARAM_TEXT);

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

        // Card image filename.
        $mform->addElement(
            'text',
            'cardimage',
            get_string('cardimage', 'format_minimoodlewall'),
            ['size' => 40]
        );
        $mform->setType('cardimage', PARAM_FILE);
        $mform->addRule('cardimage', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('cardimage', 'cardimage', 'format_minimoodlewall');
        $mform->setDefault('cardimage', 'default.svg');

        // Filter image filename.
        $mform->addElement(
            'text',
            'filterimage',
            get_string('filterimage', 'format_minimoodlewall'),
            ['size' => 40]
        );
        $mform->setType('filterimage', PARAM_FILE);
        $mform->addRule('filterimage', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('filterimage', 'filterimage', 'format_minimoodlewall');
        $mform->setDefault('filterimage', 'default-small.svg');

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
            'assignment' => get_string('modulename', 'mod_assignment'),
            'book' => get_string('pluginname', 'mod_book'),
            'chat' => get_string('pluginname', 'mod_chat'),
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
            'survey' => get_string('pluginname', 'mod_survey'),
            'url' => get_string('pluginname', 'mod_url'),
            'wiki' => get_string('pluginname', 'mod_wiki'),
            'workshop' => get_string('pluginname', 'mod_workshop'),
        ];
    }
}
