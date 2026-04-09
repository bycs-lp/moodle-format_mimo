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
     * Check if a course module is flagged as done.
     *
     * @param int $cmid Course module ID.
     * @return bool
     */
    public static function is_done(int $cmid): bool {
        global $DB;
        return $DB->record_exists(self::TABLE, ['cmid' => $cmid]);
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
    }

    /**
     * Get all done course module IDs for a course.
     *
     * @param int $courseid Course ID.
     * @return int[] Array of cmids that are flagged done.
     */
    public static function get_done_cmids(int $courseid): array {
        global $DB;
        $sql = "SELECT d.cmid
                  FROM {" . self::TABLE . "} d
                  JOIN {course_modules} cm ON cm.id = d.cmid
                 WHERE cm.course = :courseid";
        return array_map('intval', array_keys($DB->get_records_sql($sql, ['courseid' => $courseid])));
    }

    /**
     * Clean up done records for a deleted course module.
     *
     * @param int $cmid Course module ID.
     */
    public static function delete_for_cm(int $cmid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['cmid' => $cmid]);
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
    }
}
