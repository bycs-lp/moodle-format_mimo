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
 * Unit tests for completion defaults override feature.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * Tests for completion_defaults_manager and the observer completion override logic.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_mimo\completion_defaults_manager
 */
final class completion_defaults_test extends \advanced_testcase {
    /** @var \stdClass Test course with mimo format and completion enabled */
    private $course;

    /**
     * Set up before each test.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        // Clear seeded completion defaults so CRUD tests start from a clean state.
        $DB->delete_records('format_mimo_compdefs');

        // Create a course with mimo format and completion enabled.
        $this->course = $this->getDataGenerator()->create_course([
            'format' => 'mimo',
            'enablecompletion' => 1,
        ]);
    }

    /**
     * Helper: get the modules.id for a given module name.
     *
     * @param string $modname e.g. 'assign', 'page', 'url'.
     * @return int The module type ID.
     */
    private function get_module_id(string $modname): int {
        global $DB;
        return (int)$DB->get_field('modules', 'id', ['name' => $modname], MUST_EXIST);
    }

    /* ================================================ *
     * CRUD tests for completion_defaults_manager.      *
     * ================================================ */

    /**
     * Test saving and retrieving a completion default.
     */
    public function test_save_and_get_default(): void {
        $moduleid = $this->get_module_id('assign');

        // Initially no default exists.
        $this->assertNull(completion_defaults_manager::get_default($moduleid));

        // Save a default.
        completion_defaults_manager::save_default($moduleid, [
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 0,
            'completionusegrade' => 1,
            'completionpassgrade' => 1,
            'completionexpected' => 0,
            'customrules' => json_encode(['completionsubmit' => 1]),
        ]);

        // Retrieve it.
        $default = completion_defaults_manager::get_default($moduleid);
        $this->assertNotNull($default);
        $this->assertEquals(COMPLETION_TRACKING_AUTOMATIC, (int)$default->completion);
        $this->assertEquals(1, (int)$default->completionusegrade);
        $this->assertEquals(1, (int)$default->completionpassgrade);

        $customrules = json_decode($default->customrules, true);
        $this->assertEquals(1, $customrules['completionsubmit']);
    }

    /**
     * Test updating an existing completion default (upsert).
     */
    public function test_update_existing_default(): void {
        $moduleid = $this->get_module_id('assign');

        // Save initial default.
        completion_defaults_manager::save_default($moduleid, [
            'completion' => COMPLETION_TRACKING_MANUAL,
            'completionview' => 0,
            'completionusegrade' => 0,
            'completionpassgrade' => 0,
            'completionexpected' => 0,
            'customrules' => null,
        ]);

        // Update it.
        completion_defaults_manager::save_default($moduleid, [
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
            'completionusegrade' => 0,
            'completionpassgrade' => 0,
            'completionexpected' => 0,
            'customrules' => null,
        ]);

        // Should only have one record.
        $defaults = completion_defaults_manager::get_all_defaults_by_module();
        $this->assertCount(1, $defaults);
        $this->assertEquals(COMPLETION_TRACKING_AUTOMATIC, (int)$defaults[$moduleid]->completion);
        $this->assertEquals(1, (int)$defaults[$moduleid]->completionview);
    }

    /**
     * Test deleting a completion default.
     */
    public function test_delete_default(): void {
        $moduleid = $this->get_module_id('page');

        completion_defaults_manager::save_default($moduleid, [
            'completion' => COMPLETION_TRACKING_MANUAL,
            'completionview' => 0,
            'completionusegrade' => 0,
            'completionpassgrade' => 0,
            'completionexpected' => 0,
            'customrules' => null,
        ]);

        $this->assertNotNull(completion_defaults_manager::get_default($moduleid));

        completion_defaults_manager::delete_default($moduleid);

        $this->assertNull(completion_defaults_manager::get_default($moduleid));
    }

    /**
     * Test get_all_defaults_by_module returns correct keying.
     */
    public function test_get_all_defaults_by_module(): void {
        $assignid = $this->get_module_id('assign');
        $pageid = $this->get_module_id('page');

        completion_defaults_manager::save_default($assignid, [
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 0,
            'completionusegrade' => 1,
            'completionpassgrade' => 0,
            'completionexpected' => 0,
            'customrules' => null,
        ]);
        completion_defaults_manager::save_default($pageid, [
            'completion' => COMPLETION_TRACKING_MANUAL,
            'completionview' => 0,
            'completionusegrade' => 0,
            'completionpassgrade' => 0,
            'completionexpected' => 0,
            'customrules' => null,
        ]);

        $all = completion_defaults_manager::get_all_defaults_by_module();
        $this->assertCount(2, $all);
        $this->assertArrayHasKey($assignid, $all);
        $this->assertArrayHasKey($pageid, $all);
        $this->assertEquals(COMPLETION_TRACKING_AUTOMATIC, (int)$all[$assignid]->completion);
        $this->assertEquals(COMPLETION_TRACKING_MANUAL, (int)$all[$pageid]->completion);
    }

    /* ========================================= *
     * Observer integration: negative tests.     *
     * ========================================= */

    /**
     * Test that completion is NOT changed when no mimo default exists.
     */
    public function test_no_override_when_no_mimo_default(): void {
        global $DB;

        $pageid = $this->get_module_id('page');

        // Ensure NO mimo default exists.
        completion_defaults_manager::delete_default($pageid);

        // Clear any course/site completion defaults so core default is NONE.
        $DB->delete_records('course_completion_defaults', [
            'course' => $this->course->id,
            'module' => $pageid,
        ]);
        $DB->delete_records('course_completion_defaults', [
            'course' => SITEID,
            'module' => $pageid,
        ]);

        // Create a page.
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
        ]);

        // Without a mimo override the module keeps core's result (NONE).
        $cm = $DB->get_record('course_modules', ['id' => $page->cmid]);
        $this->assertSame(
            (int) COMPLETION_TRACKING_NONE,
            (int) $cm->completion,
            'No mimo default must not change completion tracking',
        );
        $this->assertSame(0, (int) $cm->completionview);
        $this->assertSame(0, (int) $cm->completionpassgrade);
        $this->assertNull($cm->completiongradeitemnumber);
    }

    /**
     * Test that no observer override occurs for non-mimo courses.
     */
    public function test_no_override_for_non_mimo_course(): void {
        global $DB;

        $pageid = $this->get_module_id('page');

        // Set a mimo override.
        completion_defaults_manager::save_default($pageid, [
            'completion' => COMPLETION_TRACKING_MANUAL,
            'completionview' => 0,
            'completionusegrade' => 0,
            'completionpassgrade' => 0,
            'completionexpected' => 0,
            'customrules' => null,
        ]);

        // Create a course with topics format (not mimo).
        $topicscourse = $this->getDataGenerator()->create_course([
            'format' => 'topics',
            'enablecompletion' => 1,
        ]);

        // Clear core defaults so the module gets COMPLETION_TRACKING_NONE.
        $DB->delete_records('course_completion_defaults', [
            'course' => $topicscourse->id,
            'module' => $pageid,
        ]);
        $DB->delete_records('course_completion_defaults', [
            'course' => SITEID,
            'module' => $pageid,
        ]);

        // Create a page in the topics course.
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $topicscourse->id,
        ]);

        // Completion should NOT be overridden (still NONE).
        $cm = $DB->get_record('course_modules', ['id' => $page->cmid]);
        $this->assertEquals(
            COMPLETION_TRACKING_NONE,
            (int)$cm->completion,
            'Override should not apply to non-mimo courses'
        );
    }

    /* ========================================== *
     * Unit tests for get_all_defaults().          *
     * ========================================== */

    /**
     * get_all_defaults returns every saved record.
     */
    public function test_get_all_defaults(): void {
        $assignid = $this->get_module_id('assign');
        $pageid = $this->get_module_id('page');

        completion_defaults_manager::save_default($assignid, [
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 0,
            'completionusegrade' => 1,
            'completionpassgrade' => 0,
            'completionexpected' => 0,
            'customrules' => null,
        ]);
        completion_defaults_manager::save_default($pageid, [
            'completion' => COMPLETION_TRACKING_MANUAL,
            'completionview' => 0,
            'completionusegrade' => 0,
            'completionpassgrade' => 0,
            'completionexpected' => 0,
            'customrules' => null,
        ]);

        $all = completion_defaults_manager::get_all_defaults();

        $this->assertCount(2, $all);
    }

    /**
     * Data provider for pack_form_data.
     *
     * @return array
     */
    public static function pack_form_data_provider(): array {
        return [
            'minimal core fields' => [
                'input' => [
                    'completion' => COMPLETION_TRACKING_MANUAL,
                    'completionview' => 0,
                ],
                'suffix' => '',
                'expected' => [
                    'completion' => COMPLETION_TRACKING_MANUAL,
                    'completionview' => 0,
                    'completionusegrade' => 0,
                    'completionpassgrade' => 0,
                    'completionexpected' => 0,
                    'customrules' => null,
                ],
            ],
            'custom rule becomes json' => [
                'input' => [
                    'completion' => COMPLETION_TRACKING_AUTOMATIC,
                    'completionview' => 0,
                    'completionusegrade' => 0,
                    'completionpassgrade' => 0,
                    'completionsubmit' => 1,
                ],
                'suffix' => '',
                'expected' => null,
                'expectedcustom' => ['completionsubmit' => 1],
            ],
            'passgrade reset when usegrade is zero' => [
                'input' => [
                    'completion' => COMPLETION_TRACKING_AUTOMATIC,
                    'completionview' => 0,
                    'completionusegrade' => 0,
                    'completionpassgrade' => 1,
                ],
                'suffix' => '',
                'expected' => [
                    'completion' => COMPLETION_TRACKING_AUTOMATIC,
                    'completionview' => 0,
                    'completionusegrade' => 0,
                    'completionpassgrade' => 0,
                    'completionexpected' => 0,
                    'customrules' => null,
                ],
            ],
            'suffix is stripped from keys' => [
                'input' => [
                    'completion_assign' => COMPLETION_TRACKING_AUTOMATIC,
                    'completionview_assign' => 0,
                    'completionusegrade_assign' => 1,
                    'completionpassgrade_assign' => 1,
                    'completionsubmit_assign' => 1,
                ],
                'suffix' => '_assign',
                'expected' => [
                    'completion' => COMPLETION_TRACKING_AUTOMATIC,
                    'completionview' => 0,
                    'completionusegrade' => 1,
                    'completionpassgrade' => 1,
                    'completionexpected' => 0,
                ],
                'expectedcustom' => ['completionsubmit' => 1],
            ],
            'noise keys are dropped' => [
                'input' => [
                    'completion' => COMPLETION_TRACKING_MANUAL,
                    'completionview' => 0,
                    'id' => 5,
                    'modids' => [1, 2],
                    'modules' => 'page',
                    'submitbutton' => 'Save',
                    '_qf__format_mimo_completion_defaults_form' => 1,
                ],
                'suffix' => '',
                'expected' => [
                    'completion' => COMPLETION_TRACKING_MANUAL,
                    'customrules' => null,
                ],
            ],
        ];
    }

    /**
     * pack_form_data normalizes raw form submissions into the record shape.
     *
     * @dataProvider pack_form_data_provider
     * @param array $input
     * @param string $suffix
     * @param array|null $expected Subset of fields that must match exactly.
     * @param array|null $expectedcustom Optional expected decoded custom rules.
     */
    public function test_pack_form_data(
        array $input,
        string $suffix,
        ?array $expected = null,
        ?array $expectedcustom = null,
    ): void {
        $record = completion_defaults_manager::pack_form_data($input, $suffix);

        if ($expected !== null) {
            foreach ($expected as $field => $value) {
                $this->assertSame($value, $record->$field, "Field $field");
            }
        }

        if ($expectedcustom !== null) {
            $this->assertNotNull($record->customrules);
            $this->assertSame($expectedcustom, json_decode($record->customrules, true));
        }
    }

    /**
     * initialize_default_completion_defaults should seed a populated table and be idempotent.
     */
    public function test_initialize_default_completion_defaults(): void {
        global $DB;

        // The setUp() already wiped the table, so this should seed.
        $this->assertTrue(completion_defaults_manager::initialize_default_completion_defaults());
        $this->assertGreaterThan(0, $DB->count_records('format_mimo_compdefs'));

        $firstcount = $DB->count_records('format_mimo_compdefs');

        // Second call should be a no-op (guard on empty table).
        $this->assertFalse(completion_defaults_manager::initialize_default_completion_defaults());
        $this->assertSame($firstcount, $DB->count_records('format_mimo_compdefs'));

        // Spot-check an entry: 'assign' should be automatic + have customrules.
        $assignid = $this->get_module_id('assign');
        $assign = completion_defaults_manager::get_default($assignid);
        $this->assertNotNull($assign);
        $this->assertEquals(COMPLETION_TRACKING_AUTOMATIC, (int) $assign->completion);
        $this->assertEquals(0, (int) $assign->completionusegrade);
        $this->assertNotNull($assign->customrules);
        $this->assertSame(['completionsubmit' => 1], json_decode($assign->customrules, true));
    }
}
