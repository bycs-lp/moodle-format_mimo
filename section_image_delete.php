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
 * Delete a section overview card image.
 *
 * @package    format_minimoodlewall
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

use format_minimoodlewall\section_image_manager;

$courseid = required_param('courseid', PARAM_INT);
$sectionid = required_param('sectionid', PARAM_INT);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($courseid);
require_capability('moodle/course:update', $context);
require_sesskey();

// Validate the section belongs to this course.
$DB->get_record('course_sections', ['id' => $sectionid, 'course' => $courseid], 'id', MUST_EXIST);

section_image_manager::delete_image($courseid, $sectionid);

$returnurl = new moodle_url('/course/view.php', ['id' => $courseid, 'overview' => 1]);
redirect($returnurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
