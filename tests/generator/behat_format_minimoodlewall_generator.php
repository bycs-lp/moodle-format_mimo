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
 * Behat data generator for format_minimoodlewall.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_format_minimoodlewall_generator extends behat_generator_base {
    /**
     * Get list of entities that can be created.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'courses' => [
                'singular' => 'course',
                'datagenerator' => 'course',
                'required' => ['fullname', 'shortname', 'tagsetid'],
                'switchids' => [],
            ],
            'tagsets' => [
                'singular' => 'tagset',
                'datagenerator' => 'tagset',
                'required' => ['name'],
                'switchids' => [],
            ],
            'tags' => [
                'singular' => 'tag',
                'datagenerator' => 'tag',
                'required' => ['tagset', 'name'],
                'switchids' => ['tagset' => 'tagsetid'],
            ],
            'cmtags' => [
                'singular' => 'cmtag',
                'datagenerator' => 'cmtag',
                'required' => ['cm', 'tag'],
                'switchids' => ['cm' => 'cmid', 'tag' => 'tagid'],
            ],
        ];
    }

    /**
     * Look up tagset id from name.
     *
     * @param string $tagsetname
     * @return int
     */
    protected function get_tagset_id(string $tagsetname): int {
        global $DB;

        $id = $DB->get_field('format_minimoodlewall_tagsets', 'id', ['name' => $tagsetname]);
        if (!$id) {
            throw new Exception('The specified tagset with name "' . $tagsetname . '" does not exist');
        }
        return $id;
    }

    /**
     * Look up tag id from name.
     *
     * @param string $tagname
     * @return int
     */
    protected function get_tag_id(string $tagname): int {
        global $DB;

        $id = $DB->get_field('format_minimoodlewall_tags', 'id', ['name' => $tagname]);
        if (!$id) {
            throw new Exception('The specified tag with name "' . $tagname . '" does not exist');
        }
        return $id;
    }

    /**
     * Look up course module id from activity name.
     *
     * @param string $cmname
     * @return int
     */
    protected function get_cm_id(string $cmname, ?int $courseid = null): int {
        global $DB;

        // Common activity types to check.
        $moduletypes = ['assign', 'quiz', 'page', 'forum', 'book'];
        $dbman = $DB->get_manager();

        foreach ($moduletypes as $modname) {
            if (!$dbman->table_exists($modname)) {
                continue;
            }
            $sql = "SELECT cm.id
                      FROM {course_modules} cm
                      JOIN {modules} m ON m.id = cm.module
                      JOIN {{$modname}} a ON a.id = cm.instance
                     WHERE m.name = :modname AND a.name = :name";
            $params = ['modname' => $modname, 'name' => $cmname];

            if (!empty($courseid)) {
                $sql .= " AND cm.course = :courseid";
                $params['courseid'] = $courseid;
            }

            $sql .= " ORDER BY cm.id DESC";

            $record = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE);
            if ($record && !empty($record->id)) {
                return (int)$record->id;
            }
        }

        throw new Exception('The specified course module with name "' . $cmname . '" does not exist');
    }

    /**
     * Resolve a course shortname to its database id.
     *
     * @param string $shortname
     * @return int
     */
    protected function resolve_course_id(string $shortname): int {
        global $DB;

        $id = $DB->get_field('course', 'id', ['shortname' => $shortname]);
        if (!$id) {
            throw new Exception('The specified course with shortname "' . $shortname . '" does not exist');
        }

        return (int)$id;
    }

    /**
     * Preprocess tagset data before creating.
     *
     * @param array $data
     * @return array
     */
    protected function preprocess_tagset($data) {
        // No preprocessing needed for tagsets.
        return $data;
    }

    /**
     * Preprocess tag data before creating.
     *
     * @param array $data
     * @return array
     */
    protected function preprocess_tag($data) {
        if (isset($data['tagset'])) {
            $data['tagsetid'] = $this->get_tagset_id($data['tagset']);
            unset($data['tagset']);
        }
        return $data;
    }

    /**
     * Preprocess cmtag data before creating.
     *
     * @param array $data
     * @return array
     */
    protected function preprocess_cmtag($data) {
        $courseid = null;
        if (!empty($data['course'])) {
            $courseid = $this->resolve_course_id($data['course']);
            unset($data['course']);
        }

        if (isset($data['cm'])) {
            $data['cmid'] = $this->get_cm_id($data['cm'], $courseid);
            unset($data['cm']);
        }
        if (isset($data['tag'])) {
            $data['tagid'] = $this->get_tag_id($data['tag']);
            unset($data['tag']);
        }
        return $data;
    }

    /**
     * Process courses table data for minimoodlewall format.
     * This hooks into Behat's course creation to handle tagsetid.
     *
     * @param array $data
     * @return array
     */
    public function process_course(array $data): void {
        global $CFG;

        require_once($CFG->dirroot . '/course/lib.php');

        $data = $this->normalise_course_data($data);

        $course = $this->datagenerator->create_course($data);

        $formatoptions = [
            'id' => $course->id,
            'tagsetid' => $data['tagsetid'],
            'enablefiltering' => $data['enablefiltering'],
            'designvariant' => $data['designvariant'],
        ];
        course_get_format($course->id)->update_course_format_options($formatoptions);
    }

    /**
     * Ensure course data mirrors the UI defaults for the minimoodlewall format.
     *
     * @param array $data
     * @return array
     */
    protected function normalise_course_data(array $data): array {
        $data['format'] = $data['format'] ?? 'minimoodlewall';
        if ($data['format'] !== 'minimoodlewall') {
            throw new coding_exception('format_minimoodlewall course generator only supports the minimoodlewall format.');
        }

        if (empty($data['tagsetid'])) {
            throw new coding_exception('Courses created via format_minimoodlewall generator must specify a tagsetid.');
        }
        if (!is_numeric($data['tagsetid'])) {
            $data['tagsetid'] = $this->get_tagset_id($data['tagsetid']);
        } else {
            $data['tagsetid'] = (int)$data['tagsetid'];
        }

        $data['enablefiltering'] = $this->resolve_boolean_flag($data['enablefiltering'] ?? 1);
        $data['designvariant'] = $data['designvariant'] ?? 'classic';
        $data['numsections'] = 1;

        return $data;
    }

    /**
     * Convert various truthy/falsy representations into a 0/1 integer.
     *
     * @param mixed $value
     * @return int
     */
    protected function resolve_boolean_flag($value): int {
        $truthy = ['1', 'true', 'yes', 'on'];
        $falsy = ['0', 'false', 'no', 'off'];

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        $normalized = strtolower((string)$value);
        if (in_array($normalized, $truthy, true)) {
            return 1;
        }
        if (in_array($normalized, $falsy, true)) {
            return 0;
        }

        return !empty($value) ? 1 : 0;
    }
}
