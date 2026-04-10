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

namespace format_mimo;

/**
 * Helper for batch-loading activity completion counts (teacher view).
 *
 * All results are request-cached so that rendering N activity cards
 * does not cause N+1 database queries.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_helper {
    /** @var array<int, array<int, int>> Cached completion counts keyed by courseid => [cmid => count]. */
    private static array $completioncounts = [];

    /** @var array<int, int> Cached tracked-user counts keyed by courseid. */
    private static array $trackedusercounts = [];

    /**
     * Reset all internal caches.
     *
     * This is intended for unit testing where the database is rolled back
     * between tests but static properties survive.
     */
    public static function reset_caches(): void {
        self::$completioncounts = [];
        self::$trackedusercounts = [];
    }

    /**
     * Get the number of successful completions per activity for a course.
     *
     * Successful means completionstate IN (COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS).
     * Only course modules with completion tracking enabled are included.
     *
     * @param int $courseid Course ID.
     * @return array<int, int> Map of coursemoduleid => completed user count.
     */
    public static function get_teacher_completion_counts(int $courseid): array {
        global $DB;

        if (array_key_exists($courseid, self::$completioncounts)) {
            return self::$completioncounts[$courseid];
        }

        // Determine which CMs have completion enabled.
        $modinfo = get_fast_modinfo($courseid);
        $completioninfo = new \completion_info($modinfo->get_course());

        $cmids = [];
        foreach ($modinfo->get_cms() as $cm) {
            if ($completioninfo->is_enabled($cm)) {
                $cmids[] = (int) $cm->id;
            }
        }

        $counts = [];
        if (!empty($cmids)) {
            [$insql, $params] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);
            $params['complete'] = COMPLETION_COMPLETE;
            $params['completepass'] = COMPLETION_COMPLETE_PASS;

            $sql = "SELECT coursemoduleid, COUNT(1) AS cnt
                      FROM {course_modules_completion}
                     WHERE coursemoduleid $insql
                       AND completionstate IN (:complete, :completepass)
                  GROUP BY coursemoduleid";

            $records = $DB->get_records_sql($sql, $params);
            foreach ($records as $rec) {
                $counts[(int) $rec->coursemoduleid] = (int) $rec->cnt;
            }
        }

        self::$completioncounts[$courseid] = $counts;
        return $counts;
    }

    /**
     * Get the number of users whose completion is tracked in a course.
     *
     * Uses the standard Moodle capability moodle/course:isincompletionreports.
     *
     * @param int $courseid Course ID.
     * @return int Number of tracked users.
     */
    public static function get_tracked_user_count(int $courseid): int {
        if (array_key_exists($courseid, self::$trackedusercounts)) {
            return self::$trackedusercounts[$courseid];
        }

        $course = get_course($courseid);
        $completioninfo = new \completion_info($course);
        $count = (int) $completioninfo->get_num_tracked_users();

        self::$trackedusercounts[$courseid] = $count;
        return $count;
    }
}
