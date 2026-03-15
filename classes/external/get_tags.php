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
 * External service to get tags for a course.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use format_minimoodlewall\tag_manager;

/**
 * External service to get tags for a course.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_tags extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    /**
     * Get tags selected for a course.
     *
     * @param int $courseid Course ID
     * @return array
     */
    public static function execute($courseid) {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);

        $tags = tag_manager::get_tags_for_course($params['courseid']);

        $result = [];
        foreach ($tags as $tag) {
            $result[] = [
                'id' => $tag->id,
                'name' => format_string($tag->name, true, ['context' => $context]),
                'cardimage' => $tag->cardimage,
                'filterimage' => $tag->filterimage,
                'activitytype1' => $tag->activitytype1,
                'activitytype2' => $tag->activitytype2 ?? '',
                'activitytype3' => $tag->activitytype3 ?? '',
                'sortorder' => $tag->sortorder,
            ];
        }

        return $result;
    }

    /**
     * Returns description of method result value.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Tag ID'),
                'name' => new external_value(PARAM_TEXT, 'Tag name'),
                'cardimage' => new external_value(PARAM_TEXT, 'Card image filename'),
                'filterimage' => new external_value(PARAM_TEXT, 'Filter image filename'),
                'activitytype1' => new external_value(PARAM_TEXT, 'Primary activity type'),
                'activitytype2' => new external_value(PARAM_TEXT, 'Secondary activity type'),
                'activitytype3' => new external_value(PARAM_TEXT, 'Third activity type'),
                'sortorder' => new external_value(PARAM_INT, 'Sort order'),
            ])
        );
    }
}
