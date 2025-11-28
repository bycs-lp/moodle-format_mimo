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
 * Activity descriptions form for format_minimoodlewall.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use format_minimoodlewall\activity_description_manager;
use format_minimoodlewall\description_tag_manager;

/**
 * Form for managing activity type descriptions.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_descriptions_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        // Get all available activity types.
        $availabletypes = activity_description_manager::get_available_activity_types();
        $existingdescriptions = activity_description_manager::get_all_descriptions();

        // Get all available tags for select.
        $tagoptions = description_tag_manager::get_tags_for_select();

        // Create lookup array for existing descriptions.
        $descriptionmap = [];
        foreach ($existingdescriptions as $desc) {
            $descriptionmap[$desc->activitytype] = $desc;
        }

        $mform->addElement('header', 'activitydescriptionsheader', get_string('activitydescriptions', 'format_minimoodlewall'));
        $mform->addElement(
            'static',
            'activitydescriptions_help',
            '',
            get_string('activitydescriptions_help', 'format_minimoodlewall')
        );

        // Add textarea and tag selector for each activity type.
        foreach ($availabletypes as $type) {
            $currentdesc = $descriptionmap[$type['name']] ?? null;
            
            // Activity type label.
            $mform->addElement(
                'html',
                '<div style="margin-top: 20px;"><strong>' . s($type['displayname']) . '</strong></div>'
            );
            
            // Description textarea.
            $mform->addElement(
                'textarea',
                'description_' . $type['name'],
                get_string('description'),
                [
                    'rows' => 2,
                    'cols' => 80,
                    'placeholder' => get_string('activitydescription_placeholder', 'format_minimoodlewall'),
                ]
            );
            
            // Tag selector.
            $mform->addElement(
                'select',
                'desctag_' . $type['name'],
                get_string('descriptiontag', 'format_minimoodlewall'),
                $tagoptions
            );
            $mform->setType('desctag_' . $type['name'], PARAM_INT);
            if ($currentdesc && isset($currentdesc->desctagid)) {
                $mform->setDefault('desctag_' . $type['name'], $currentdesc->desctagid ?? 0);
            }
            $mform->setType('description_' . $type['name'], PARAM_TEXT);
            if ($currentdesc) {
                $mform->setDefault('description_' . $type['name'], $currentdesc->description ?? '');
            }
        }

        $this->add_action_buttons(false, get_string('savechanges'));
    }
}
