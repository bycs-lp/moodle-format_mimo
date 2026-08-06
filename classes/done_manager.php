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
    /**
     * Request-level cache of done cmids per course.
     *
     * Shape: [courseid => [cmid => true]]. A value of `null` for a courseid
     * means "not yet primed". Using a map instead of a flat list lets
     * {@see self::is_done()} answer in O(1) without rescanning.
     *
     * @var array<int, array<int, true>>
     */
    private array $donecache = [];

    /**
     * Request-level cache of cmid => courseid resolutions.
     *
     * Only successful (non-zero) lookups are stored, so a cm created later
     * in the same request is still resolvable.
     *
     * @var array<int, int>
     */
    private array $cmcourse = [];

    /**
     * Constructor with DI-injected dependencies.
     *
     * Obtain the shared instance via \core\di::get(done_manager::class).
     *
     * @param \moodle_database $db Database instance.
     * @param \core\clock $clock Clock instance.
     */
    public function __construct(
        /** @var \moodle_database Database instance. */
        private readonly \moodle_database $db,
        /** @var \core\clock Clock instance. */
        private readonly \core\clock $clock,
    ) {
    }

    /**
     * Prime the cache for a course by loading its done cmids in one query.
     *
     * @param int $courseid Course ID.
     */
    private function prime_course(int $courseid): void {
        if (isset($this->donecache[$courseid])) {
            return;
        }
        $sql = "SELECT d.cmid
                  FROM {format_mimo_cmdone} d
                  JOIN {course_modules} cm ON cm.id = d.cmid
                 WHERE cm.course = :courseid";
        $records = $this->db->get_records_sql($sql, ['courseid' => $courseid]);
        $map = [];
        foreach ($records as $record) {
            $map[(int) $record->cmid] = true;
        }
        $this->donecache[$courseid] = $map;
    }

    /**
     * Resolve the course id for a given cm id.
     *
     * Results are request-cached so repeated lookups for the same cm
     * (e.g. several render helpers per activity card) cost one query at most.
     *
     * @param int $cmid Course module ID.
     * @return int Course ID, or 0 if the cm does not exist.
     */
    private function get_courseid_for_cm(int $cmid): int {
        if (isset($this->cmcourse[$cmid])) {
            return $this->cmcourse[$cmid];
        }
        $courseid = (int) $this->db->get_field('course_modules', 'course', ['id' => $cmid]);
        if ($courseid !== 0) {
            $this->cmcourse[$cmid] = $courseid;
        }
        return $courseid;
    }

    /**
     * Check if a course module is flagged as done.
     *
     * Results are request-cached per course: the first call for any cm in a
     * course loads the full done-cmid set in one query, subsequent calls for
     * the same course are served from memory.
     *
     * @param int $cmid Course module ID.
     * @param int|null $courseid Course ID if the caller already knows it,
     *                           avoiding a cmid lookup entirely.
     * @return bool
     */
    public function is_done(int $cmid, ?int $courseid = null): bool {
        if ($courseid !== null && $courseid > 0) {
            $this->cmcourse[$cmid] = $courseid;
        } else {
            $courseid = $this->get_courseid_for_cm($cmid);
            if ($courseid === 0) {
                return false;
            }
        }
        $this->prime_course($courseid);
        return isset($this->donecache[$courseid][$cmid]);
    }

    /**
     * Flag a course module as done.
     *
     * @param int $cmid Course module ID.
     */
    public function set_done(int $cmid): void {
        if (!$this->is_done($cmid)) {
            $this->db->insert_record('format_mimo_cmdone', (object) [
                'cmid' => $cmid,
                'timecreated' => $this->clock->time(),
            ]);
            $courseid = $this->get_courseid_for_cm($cmid);
            if ($courseid !== 0 && isset($this->donecache[$courseid])) {
                $this->donecache[$courseid][$cmid] = true;
            }
        }
    }

    /**
     * Remove the done flag from a course module.
     *
     * @param int $cmid Course module ID.
     */
    public function unset_done(int $cmid): void {
        $this->db->delete_records('format_mimo_cmdone', ['cmid' => $cmid]);
        $courseid = $this->get_courseid_for_cm($cmid);
        if ($courseid !== 0 && isset($this->donecache[$courseid])) {
            unset($this->donecache[$courseid][$cmid]);
        }
    }

    /**
     * Get all done course module IDs for a course.
     *
     * @param int $courseid Course ID.
     * @return int[] Array of cmids that are flagged done.
     */
    public function get_done_cmids(int $courseid): array {
        $this->prime_course($courseid);
        return array_keys($this->donecache[$courseid]);
    }

    /**
     * Clean up done records for a deleted course module.
     *
     * @param int $cmid Course module ID.
     */
    public function delete_for_cm(int $cmid): void {
        $courseid = $this->get_courseid_for_cm($cmid);
        $this->db->delete_records('format_mimo_cmdone', ['cmid' => $cmid]);
        unset($this->cmcourse[$cmid]);
        if ($courseid !== 0 && isset($this->donecache[$courseid])) {
            unset($this->donecache[$courseid][$cmid]);
        }
    }

    /**
     * Clean up all done records for a course.
     *
     * @param int $courseid Course ID.
     */
    public function delete_for_course(int $courseid): void {
        $sql = "DELETE FROM {format_mimo_cmdone}
                 WHERE cmid IN (SELECT id FROM {course_modules} WHERE course = :courseid)";
        $this->db->execute($sql, ['courseid' => $courseid]);
        unset($this->donecache[$courseid]);
    }
}
