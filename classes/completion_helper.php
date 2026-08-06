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
 * Trust contract: the methods in this class do NOT perform capability
 * checks. Callers must ensure the current user is authorized to see
 * aggregated completion data (typically {@see has_capability()} on
 * 'report/progress:view' in the course context) before displaying the
 * returned values.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_helper {
    /** @var array<int, array<int, int>> Cached completion counts keyed by courseid => [cmid => count]. */
    private array $completioncounts = [];

    /** @var array<int, int[]> Cached tracked user IDs keyed by courseid. */
    private array $trackeduserids = [];

    /**
     * Constructor with DI-injected dependencies.
     *
     * Obtain the shared instance via \core\di::get(completion_helper::class).
     *
     * @param \moodle_database $db Database instance.
     */
    public function __construct(
        /** @var \moodle_database Database instance. */
        private readonly \moodle_database $db,
    ) {
    }

    /**
     * Get the list of user IDs whose completion is tracked in a course.
     *
     * "Tracked" means currently actively enrolled AND holding
     * moodle/course:isincompletionreports — the same definition Moodle's
     * {@see \completion_info::get_num_tracked_users()} uses.
     *
     * Result is request-cached so the (expensive) enrolment subquery is
     * executed at most once per course per request. Both
     * {@see self::get_teacher_completion_counts()} and
     * {@see self::get_tracked_user_count()} share this cache, which also
     * guarantees numerator and denominator refer to the exact same user set.
     *
     * @param int $courseid Course ID.
     * @return int[] List of user IDs.
     */
    private function get_tracked_userids(int $courseid): array {
        if (isset($this->trackeduserids[$courseid])) {
            return $this->trackeduserids[$courseid];
        }

        $context = \core\context\course::instance($courseid);
        $users = get_enrolled_users(
            $context,
            'moodle/course:isincompletionreports',
            0,
            'u.id',
            null,
            0,
            0,
            true
        );

        $this->trackeduserids[$courseid] = array_map('intval', array_keys($users));
        return $this->trackeduserids[$courseid];
    }

    /**
     * Get the number of successful completions per activity for a course.
     *
     * Successful means completionstate IN (COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS).
     * Only course modules with completion tracking enabled are included, and
     * only completions by users returned by {@see self::get_tracked_userids()}
     * are counted. This keeps the numerator consistent with
     * {@see self::get_tracked_user_count()} so the ratio cannot exceed 100 %
     * when users are unenrolled or change roles after completing an activity.
     *
     * @param int $courseid Course ID.
     * @return array<int, int> Map of coursemoduleid => completed user count.
     */
    public function get_teacher_completion_counts(int $courseid): array {
        global $CFG;

        require_once($CFG->libdir . '/completionlib.php');

        if (array_key_exists($courseid, $this->completioncounts)) {
            return $this->completioncounts[$courseid];
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
        $trackedids = $this->get_tracked_userids($courseid);
        if (!empty($cmids) && !empty($trackedids)) {
            [$cmsql, $cmparams] = $this->db->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'cmid');
            [$usersql, $userparams] = $this->db->get_in_or_equal($trackedids, SQL_PARAMS_NAMED, 'usr');

            $params = array_merge($cmparams, $userparams, [
                'complete' => COMPLETION_COMPLETE,
                'completepass' => COMPLETION_COMPLETE_PASS,
            ]);

            $sql = "SELECT coursemoduleid, COUNT(1) AS cnt
                      FROM {course_modules_completion}
                     WHERE coursemoduleid $cmsql
                       AND userid $usersql
                       AND completionstate IN (:complete, :completepass)
                  GROUP BY coursemoduleid";

            $records = $this->db->get_records_sql($sql, $params);
            foreach ($records as $rec) {
                $counts[(int) $rec->coursemoduleid] = (int) $rec->cnt;
            }
        }

        $this->completioncounts[$courseid] = $counts;
        return $counts;
    }

    /**
     * Get the number of users whose completion is tracked in a course.
     *
     * Uses the standard Moodle capability moodle/course:isincompletionreports.
     * Shares the request-cached user list with
     * {@see self::get_teacher_completion_counts()}.
     *
     * @param int $courseid Course ID.
     * @return int Number of tracked users.
     */
    public function get_tracked_user_count(int $courseid): int {
        return count($this->get_tracked_userids($courseid));
    }
}
