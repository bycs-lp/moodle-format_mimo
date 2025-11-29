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
 * External service to assign a tag to a course module.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use format_minimoodlewall\tag_manager;

/**
 * External service to assign a tag to a course module.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_tag extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'tagid' => new external_value(PARAM_INT, 'Tag ID'),
        ]);
    }

    /**
     * Assign a tag to a course module.
     *
     * @param int $cmid Course module ID
     * @param int $tagid Tag ID
     * @return array
     */
    public static function execute($cmid, $tagid) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'tagid' => $tagid,
        ]);

        // Verify the course module exists and user has permission.
        $cm = $DB->get_record('course_modules', ['id' => $params['cmid']], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        // Assign the tag.
        $success = tag_manager::assign_tag_to_cm($params['cmid'], $params['tagid']);

        return ['success' => $success];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new \core_external\external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the assignment was successful'),
        ]);
    }
}
