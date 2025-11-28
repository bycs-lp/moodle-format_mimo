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
 * Activity description management interface for minimoodlewall course format.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use format_minimoodlewall\activity_description_manager;
use format_minimoodlewall\form\activity_descriptions_form;

admin_externalpage_setup('format_minimoodlewall_activitydescriptions');

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url('/course/format/minimoodlewall/activity_descriptions.php');
$PAGE->set_title(get_string('activitydescriptions', 'format_minimoodlewall'));
$PAGE->set_heading(get_string('activitydescriptions', 'format_minimoodlewall'));

$mform = new activity_descriptions_form();

if ($mform->is_cancelled()) {
    redirect($PAGE->url);
} else if ($data = $mform->get_data()) {
    $availabletypes = activity_description_manager::get_available_activity_types();
    
    foreach ($availabletypes as $type) {
        $descfieldname = 'description_' . $type['name'];
        $tagfieldname = 'desctag_' . $type['name'];
        
        if (isset($data->$descfieldname)) {
            $description = trim($data->$descfieldname);
            $desctagid = isset($data->$tagfieldname) ? $data->$tagfieldname : null;
            
            // Convert 0 (no tag) to null.
            if ($desctagid === 0) {
                $desctagid = null;
            }
            
            if (empty($description)) {
                // Empty description - delete if exists.
                activity_description_manager::delete_description($type['name']);
            } else {
                // Save or update description.
                activity_description_manager::save_description($type['name'], $description, $desctagid);
            }
        }
    }
    
    redirect($PAGE->url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('activitydescriptions', 'format_minimoodlewall'));

$mform->display();

echo $OUTPUT->footer();
