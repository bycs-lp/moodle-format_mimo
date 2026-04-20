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
 * Behat data generator for format_mimo.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_format_mimo_generator extends behat_generator_base {
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
                'required' => ['fullname', 'shortname'],
                'switchids' => [],
            ],
            'tags' => [
                'singular' => 'tag',
                'datagenerator' => 'tag',
                'required' => ['name'],
                'switchids' => [],
            ],
            'cmtags' => [
                'singular' => 'cmtag',
                'datagenerator' => 'cmtag',
                'required' => ['cm', 'tag'],
                'switchids' => ['cm' => 'cmid'],
            ],
            'activities' => [
                'singular' => 'activity',
                'datagenerator' => 'activity',
                'required' => ['activity', 'name', 'course'],
                'switchids' => ['course' => 'course', 'gradecategory' => 'gradecategory'],
            ],
            'description tags' => [
                'singular' => 'description tag',
                'datagenerator' => 'description_tag',
                'required' => ['name', 'color'],
                'switchids' => [],
            ],
            'activity descriptions' => [
                'singular' => 'activity description',
                'datagenerator' => 'activity_description',
                'required' => ['activitytype', 'description'],
                'switchids' => ['desctag' => 'desctagid'],
            ],
            'profiles' => [
                'singular' => 'profile',
                'datagenerator' => 'profile',
                'required' => ['name', 'displayname'],
                'switchids' => [],
            ],
        ];
    }

    /**
     * Look up tag id from name.
     *
     * @param string $tagname
     * @return int
     */
    protected function get_tag_id(string $tagname): int {
        global $DB;

        $id = $DB->get_field('format_mimo_tags', 'id', ['name' => $tagname]);
        if (!$id) {
            throw new Exception('The specified tag with name "' . $tagname . '" does not exist');
        }
        return (int)$id;
    }

    /**
     * Look up description tag id from name.
     *
     * @param string $tagname
     * @return int
     */
    protected function get_description_tag_id(string $tagname): int {
        global $DB;

        $id = $DB->get_field('format_mimo_desc_tags', 'id', ['name' => $tagname]);
        if (!$id) {
            throw new Exception('The specified description tag with name "' . $tagname . '" does not exist');
        }
        return $id;
    }

    /**
     * Look up description tag id from name (alias for switchids resolution).
     *
     * @param string $tagname
     * @return int
     */
    protected function get_desctag_id(string $tagname): int {
        return $this->get_description_tag_id($tagname);
    }

    /**
     * Look up course module id from activity name.
     *
     * @param string $cmname
     * @param int|null $courseid Optional course ID to narrow search
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
     * Resolve a course shortname or ID to its database id.
     *
     * @param string|int $course Course shortname or ID
     * @return int
     */
    protected function resolve_course_id($course): int {
        global $DB;

        // If already numeric, assume it's an ID and validate it exists.
        if (is_numeric($course)) {
            $courseid = (int)$course;
            if ($DB->record_exists('course', ['id' => $courseid])) {
                return $courseid;
            }
            throw new Exception('The specified course with id "' . $courseid . '" does not exist');
        }

        // Otherwise treat as shortname.
        $id = $DB->get_field('course', 'id', ['shortname' => $course]);
        if (!$id) {
            throw new Exception('The specified course with shortname "' . $course . '" does not exist');
        }

        return (int)$id;
    }

    /**
     * Preprocess tag data before creating.
     *
     * @param array $data
     * @return array
     */
    protected function preprocess_tag($data) {
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
     * Preprocess description tag data before creating.
     *
     * @param array $data
     * @return array
     */
    protected function preprocess_description_tag($data) {
        // No preprocessing needed.
        return $data;
    }

    /**
     * Preprocess activity description data before creating.
     *
     * @param array $data
     * @return array
     */
    protected function preprocess_activity_description($data) {
        // The switchids mechanism will handle desctag -> desctagid conversion.
        // No additional preprocessing needed.
        return $data;
    }

    /**
     * Process courses table data for mimo format.
     * This hooks into Behat's course creation to handle selectedtags.
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
            'enablefiltering' => $data['enablefiltering'],
            'enablemultisection' => $data['enablemultisection'] ?? 0,
            'activityprofile' => $data['activityprofile'] ?? 'explore',
            'backgrounddesign' => $data['backgrounddesign'] ?? $data['wallcolor'] ?? 'primary-school',
        ];
        course_get_format($course->id)->update_course_format_options($formatoptions);

        // Rebuild course cache to ensure format options are immediately available.
        rebuild_course_cache($course->id, true);
    }

    /**
     * Ensure course data mirrors the UI defaults for the mimo format.
     *
     * @param array $data
     * @return array
     */
    protected function normalise_course_data(array $data): array {
        global $DB;

        $data['format'] = $data['format'] ?? 'mimo';
        if ($data['format'] !== 'mimo') {
            throw new coding_exception('format_mimo course generator only supports the mimo format.');
        }

        // Handle selectedtags - can be a comma-separated list of tag names or IDs.
        if (!empty($data['selectedtags'])) {
            $tagnames = array_map('trim', explode(',', $data['selectedtags']));
            $tagids = [];
            foreach ($tagnames as $tagname) {
                if (is_numeric($tagname)) {
                    $tagids[] = (int)$tagname;
                } else {
                    $tagids[] = $this->get_tag_id($tagname);
                }
            }
            // The selectedtags is no longer a course format option, but we still resolve tag names
            // to IDs for backward compatibility in test data. The IDs are not stored.
            unset($data['selectedtags']);
        }

        $data['enablefiltering'] = $this->resolve_boolean_flag($data['enablefiltering'] ?? 1);
        $data['enablemultisection'] = $this->resolve_boolean_flag($data['enablemultisection'] ?? 0);
        $data['activityprofile'] = $data['activityprofile'] ?? 'explore';
        $data['numsections'] = (int) ($data['numsections'] ?? 1);

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

    /**
     * Process activity creation with optional tag assignment.
     *
     * @param array $data
     * @return void
     */
    public function process_activity(array $data): void {
        global $DB;

        // Extract tag if provided.
        $tagname = null;
        if (isset($data['tag'])) {
            $tagname = $data['tag'];
            unset($data['tag']);
        }

        // Create the activity using the core generator.
        $modulename = $data['activity'];
        $generator = $this->datagenerator->get_plugin_generator('mod_' . $modulename);

        if (!$generator) {
            throw new coding_exception("Activity type '{$modulename}' does not have a generator");
        }

        $instance = $generator->create_instance($data);

        // If a tag was specified, create the cmtag entry.
        if ($tagname && $instance) {
            // Use the course ID from the created instance to ensure we find the right CM.
            $courseid = isset($instance->course) ? $instance->course : null;

            // Get the course module ID.
            $cm = get_coursemodule_from_instance($modulename, $instance->id, $courseid);
            if ($cm) {
                $tagid = $this->get_tag_id($tagname);

                // Create cmtag entry.
                $this->componentdatagenerator->create_cmtag([
                    'cmid' => $cm->id,
                    'tagid' => $tagid,
                ]);
            }
        }
    }
}
