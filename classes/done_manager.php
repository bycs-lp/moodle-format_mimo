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
 * Manager for the "Done" activity flag.
 *
 * Activities marked as "done" remain visible to students but are greyed out
 * on the wall and excluded from completion tracking counters.
 *
 * @package    format_mimo
 * @copyright  2026 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class done_manager {
    /** @var string Database table name. */
    private const TABLE = 'format_mimo_cmdone';

    /**
     * Request-level cache of done cmids per course.
     *
     * Shape: [courseid => [cmid => true]]. A value of `null` for a courseid
     * means "not yet primed". Using a map instead of a flat list lets
     * {@see self::is_done()} answer in O(1) without rescanning.
     *
     * @var array<int, array<int, true>>
     */
    private static array $donecache = [];

    /**
     * Reset the request-level cache.
     *
     * Intended for unit tests; the DB is rolled back between tests but
     * static properties survive.
     */
    public static function reset_cache(): void {
        self::$donecache = [];
    }

    /**
     * Prime the cache for a course by loading its done cmids in one query.
     *
     * @param int $courseid Course ID.
     */
    private static function prime_course(int $courseid): void {
        global $DB;
        if (isset(self::$donecache[$courseid])) {
            return;
        }
        $sql = "SELECT d.cmid
                  FROM {" . self::TABLE . "} d
                  JOIN {course_modules} cm ON cm.id = d.cmid
                 WHERE cm.course = :courseid";
        $records = $DB->get_records_sql($sql, ['courseid' => $courseid]);
        $map = [];
        foreach ($records as $record) {
            $map[(int) $record->cmid] = true;
        }
        self::$donecache[$courseid] = $map;
    }

    /**
     * Resolve the course id for a given cm id.
     *
     * Uses the core modinfo request cache when available (populated during
     * course rendering), falls back to a single {course_modules} query.
     *
     * @param int $cmid Course module ID.
     * @return int Course ID, or 0 if the cm does not exist.
     */
    private static function get_courseid_for_cm(int $cmid): int {
        global $DB;
        return (int) $DB->get_field('course_modules', 'course', ['id' => $cmid]);
    }

    /**
     * Check if a course module is flagged as done.
     *
     * Results are request-cached per course: the first call for any cm in a
     * course loads the full done-cmid set in one query, subsequent calls for
     * the same course are served from memory.
     *
     * @param int $cmid Course module ID.
     * @return bool
     */
    public static function is_done(int $cmid): bool {
        $courseid = self::get_courseid_for_cm($cmid);
        if ($courseid === 0) {
            return false;
        }
        self::prime_course($courseid);
        return isset(self::$donecache[$courseid][$cmid]);
    }

    /**
     * Flag a course module as done.
     *
     * @param int $cmid Course module ID.
     */
    public static function set_done(int $cmid): void {
        global $DB;
        if (!self::is_done($cmid)) {
            $DB->insert_record(self::TABLE, (object) [
                'cmid' => $cmid,
                'timecreated' => \core\di::get(\core\clock::class)->time(),
            ]);
            $courseid = self::get_courseid_for_cm($cmid);
            if ($courseid !== 0 && isset(self::$donecache[$courseid])) {
                self::$donecache[$courseid][$cmid] = true;
            }
        }
    }

    /**
     * Remove the done flag from a course module.
     *
     * @param int $cmid Course module ID.
     */
    public static function unset_done(int $cmid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['cmid' => $cmid]);
        $courseid = self::get_courseid_for_cm($cmid);
        if ($courseid !== 0 && isset(self::$donecache[$courseid])) {
            unset(self::$donecache[$courseid][$cmid]);
        }
    }

    /**
     * Get all done course module IDs for a course.
     *
     * @param int $courseid Course ID.
     * @return int[] Array of cmids that are flagged done.
     */
    public static function get_done_cmids(int $courseid): array {
        self::prime_course($courseid);
        return array_keys(self::$donecache[$courseid]);
    }

    /**
     * Clean up done records for a deleted course module.
     *
     * @param int $cmid Course module ID.
     */
    public static function delete_for_cm(int $cmid): void {
        global $DB;
        $courseid = self::get_courseid_for_cm($cmid);
        $DB->delete_records(self::TABLE, ['cmid' => $cmid]);
        if ($courseid !== 0 && isset(self::$donecache[$courseid])) {
            unset(self::$donecache[$courseid][$cmid]);
        }
    }

    /**
     * Clean up all done records for a course.
     *
     * @param int $courseid Course ID.
     */
    public static function delete_for_course(int $courseid): void {
        global $DB;
        $sql = "DELETE FROM {" . self::TABLE . "}
                 WHERE cmid IN (SELECT id FROM {course_modules} WHERE course = :courseid)";
        $DB->execute($sql, ['courseid' => $courseid]);
        unset(self::$donecache[$courseid]);
    }
}
