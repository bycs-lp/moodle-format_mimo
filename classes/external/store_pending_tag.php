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
 * External service to store a pending tag in session.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;

/**
 * External service to store a pending tag in session.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class store_pending_tag extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'tagid' => new external_value(PARAM_INT, 'Tag ID to store in session'),
            'courseid' => new external_value(PARAM_INT, 'Course ID for capability check'),
        ]);
    }

    /**
     * Store a tag ID in session for later assignment to a course module.
     *
     * @param int $tagid Tag ID
     * @param int $courseid Course ID
     * @return array
     */
    public static function execute($tagid, $courseid) {
        global $SESSION;

        $params = self::validate_parameters(self::execute_parameters(), [
            'tagid' => $tagid,
            'courseid' => $courseid,
        ]);

        // Validate context and capability.
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        // Store the tag ID in session.
        $SESSION->format_mimo_pending_tag = $params['tagid'];

        return ['success' => true];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the storage was successful'),
        ]);
    }
}
