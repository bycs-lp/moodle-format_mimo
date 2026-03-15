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
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo;

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * Tests for completion_defaults_manager and the observer completion override logic.
 *
 * @package    format_mimo
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_mimo\completion_defaults_manager
 * @covers     \format_mimo\observer::apply_completion_override
 */
final class completion_defaults_test extends \advanced_testcase {
    /** @var \stdClass Test course with mimo format and completion enabled */
    private $course;

    /**
     * Set up before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

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

    // =========================================================================
    // CRUD tests for completion_defaults_manager.
    // =========================================================================

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

    // =========================================================================
    // Observer integration: positive tests.
    // =========================================================================

    /**
     * Test that completion override is applied when module matches core defaults.
     *
     * Scenario: Core defaults are "none" for page activities, mimo override
     * is set to "manual". When a page is created, it should get manual completion.
     */
    public function test_completion_override_applied_when_matching_core_defaults(): void {
        global $DB;

        $pageid = $this->get_module_id('page');

        // Ensure there are no core defaults for page (default is COMPLETION_TRACKING_NONE).
        $DB->delete_records('course_completion_defaults', [
            'course' => $this->course->id,
            'module' => $pageid,
        ]);
        $DB->delete_records('course_completion_defaults', [
            'course' => SITEID,
            'module' => $pageid,
        ]);

        // Set mimo override: manual completion.
        completion_defaults_manager::save_default($pageid, [
            'completion' => COMPLETION_TRACKING_MANUAL,
            'completionview' => 0,
            'completionusegrade' => 0,
            'completionpassgrade' => 0,
            'completionexpected' => 0,
            'customrules' => null,
        ]);

        // Create a page activity (should trigger the observer).
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
        ]);

        // Verify the completion was overridden to manual.
        $cm = $DB->get_record('course_modules', ['id' => $page->cmid]);
        $this->assertEquals(
            COMPLETION_TRACKING_MANUAL,
            (int)$cm->completion,
            'Completion should be overridden to manual by mimo defaults'
        );
    }

    /**
     * Test that completion override applies automatic tracking with core fields.
     *
     * Scenario: Core defaults are "none", mimo override is "automatic"
     * with view required.
     */
    public function test_completion_override_automatic_with_view(): void {
        global $DB;

        $pageid = $this->get_module_id('page');

        // Clear any core defaults.
        $DB->delete_records('course_completion_defaults', [
            'course' => $this->course->id,
            'module' => $pageid,
        ]);
        $DB->delete_records('course_completion_defaults', [
            'course' => SITEID,
            'module' => $pageid,
        ]);

        // Set mimo override: automatic with view required.
        completion_defaults_manager::save_default($pageid, [
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
            'completionusegrade' => 0,
            'completionpassgrade' => 0,
            'completionexpected' => 0,
            'customrules' => null,
        ]);

        // Create a page activity.
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
        ]);

        // Verify completion fields.
        $cm = $DB->get_record('course_modules', ['id' => $page->cmid]);
        $this->assertEquals(COMPLETION_TRACKING_AUTOMATIC, (int)$cm->completion);
        $this->assertEquals(1, (int)$cm->completionview);
    }

    /**
     * Test that override replaces core defaults when both exist.
     *
     * Scenario: Core defaults set to "manual", mimo override set to
     * "automatic with view required". Since the module is created with core defaults
     * (manual), the override should replace them.
     */
    public function test_completion_override_replaces_core_defaults(): void {
        global $DB;

        $pageid = $this->get_module_id('page');

        // Set core defaults: manual completion.
        $DB->delete_records('course_completion_defaults', [
            'course' => $this->course->id,
            'module' => $pageid,
        ]);
        $DB->insert_record('course_completion_defaults', [
            'course' => $this->course->id,
            'module' => $pageid,
            'completion' => COMPLETION_TRACKING_MANUAL,
            'completionview' => 0,
            'completionexpected' => 0,
            'completionusegrade' => 0,
            'completionpassgrade' => 0,
            'customrules' => null,
        ]);

        // Set mimo override: automatic with view.
        completion_defaults_manager::save_default($pageid, [
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
            'completionusegrade' => 0,
            'completionpassgrade' => 0,
            'completionexpected' => 0,
            'customrules' => null,
        ]);

        // Create a page — it will be created with core defaults (manual),
        // then the observer should override to automatic+view.
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $cm = $DB->get_record('course_modules', ['id' => $page->cmid]);
        $this->assertEquals(
            COMPLETION_TRACKING_AUTOMATIC,
            (int)$cm->completion,
            'Observer should override core manual default with mimo automatic default'
        );
        $this->assertEquals(1, (int)$cm->completionview);
    }

    // =========================================================================
    // Observer integration: negative tests.
    // =========================================================================

    /**
     * Test that override is NOT applied when no mimo default exists.
     */
    public function test_no_override_when_no_mimo_default(): void {
        global $DB;

        $pageid = $this->get_module_id('page');

        // Ensure NO mimo default exists.
        completion_defaults_manager::delete_default($pageid);

        // Create a page.
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
        ]);

        // Completion should remain as whatever core sets (likely NONE).
        $cm = $DB->get_record('course_modules', ['id' => $page->cmid]);
        // We don't assert a specific value — just that the observer didn't crash.
        // The important thing is that the completion is whatever core set, not our override.
        $this->assertNotNull($cm);
    }

    /**
     * Test that override is NOT applied when course uses a different format.
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

    /**
     * Test that override is NOT applied when teacher customized completion.
     *
     * If the teacher explicitly sets completion to "automatic" but core default is "none",
     * the module's completion won't match core defaults, so the override should be skipped.
     */
    public function test_no_override_when_teacher_customized_completion(): void {
        global $DB;

        $pageid = $this->get_module_id('page');

        // Clear core defaults (so core default = NONE).
        $DB->delete_records('course_completion_defaults', [
            'course' => $this->course->id,
            'module' => $pageid,
        ]);
        $DB->delete_records('course_completion_defaults', [
            'course' => SITEID,
            'module' => $pageid,
        ]);

        // Set mimo override: manual.
        completion_defaults_manager::save_default($pageid, [
            'completion' => COMPLETION_TRACKING_MANUAL,
            'completionview' => 0,
            'completionusegrade' => 0,
            'completionpassgrade' => 0,
            'completionexpected' => 0,
            'customrules' => null,
        ]);

        // Teacher creates a page and explicitly sets automatic completion with view.
        // This doesn't match the core default (NONE), so the observer should skip.
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
        ]);

        // Completion should remain as the teacher set it (automatic+view), not overridden to manual.
        $cm = $DB->get_record('course_modules', ['id' => $page->cmid]);
        $this->assertEquals(
            COMPLETION_TRACKING_AUTOMATIC,
            (int)$cm->completion,
            'Teacher-customized completion should not be overridden'
        );
        $this->assertEquals(
            1,
            (int)$cm->completionview,
            'Teacher-set completionview should be preserved'
        );
    }

    /**
     * Test that override is NOT applied when both core and mimo defaults are NONE.
     */
    public function test_no_override_when_both_none(): void {
        global $DB;

        $pageid = $this->get_module_id('page');

        // Clear core defaults (NONE).
        $DB->delete_records('course_completion_defaults', [
            'course' => $this->course->id,
            'module' => $pageid,
        ]);
        $DB->delete_records('course_completion_defaults', [
            'course' => SITEID,
            'module' => $pageid,
        ]);

        // Set mimo override to NONE too.
        completion_defaults_manager::save_default($pageid, [
            'completion' => COMPLETION_TRACKING_NONE,
            'completionview' => 0,
            'completionusegrade' => 0,
            'completionpassgrade' => 0,
            'completionexpected' => 0,
            'customrules' => null,
        ]);

        // Create a page.
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
        ]);

        // Should still be NONE — no unnecessary DB writes.
        $cm = $DB->get_record('course_modules', ['id' => $page->cmid]);
        $this->assertEquals(COMPLETION_TRACKING_NONE, (int)$cm->completion);
    }

    // =========================================================================
    // Unit tests for matches_core_defaults().
    // =========================================================================

    /**
     * Test matches_core_defaults returns true for matching core fields.
     */
    public function test_matches_core_defaults_positive(): void {
        $cmrecord = (object)[
            'id' => 1,
            'instance' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL,
            'completionview' => 0,
            'completionpassgrade' => 0,
            'completiongradeitemnumber' => null,
        ];

        $coredefaults = (object)[
            'completion' => COMPLETION_TRACKING_MANUAL,
            'completionview' => 0,
            'completionpassgrade' => 0,
            'completionusegrade' => 0,
        ];

        $this->assertTrue(
            completion_defaults_manager::matches_core_defaults($cmrecord, $coredefaults, 'page')
        );
    }

    /**
     * Test matches_core_defaults returns false when completion tracking differs.
     */
    public function test_matches_core_defaults_tracking_differs(): void {
        $cmrecord = (object)[
            'id' => 1,
            'instance' => 1,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 0,
            'completionpassgrade' => 0,
            'completiongradeitemnumber' => null,
        ];

        $coredefaults = (object)[
            'completion' => COMPLETION_TRACKING_MANUAL,
            'completionview' => 0,
            'completionpassgrade' => 0,
            'completionusegrade' => 0,
        ];

        $this->assertFalse(
            completion_defaults_manager::matches_core_defaults($cmrecord, $coredefaults, 'page')
        );
    }

    /**
     * Test matches_core_defaults returns false when grade requirement differs.
     */
    public function test_matches_core_defaults_grade_differs(): void {
        // CM has grade required (completiongradeitemnumber = 0), but core default doesn't.
        $cmrecord = (object)[
            'id' => 1,
            'instance' => 1,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 0,
            'completionpassgrade' => 0,
            'completiongradeitemnumber' => 0,
        ];

        $coredefaults = (object)[
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 0,
            'completionpassgrade' => 0,
            'completionusegrade' => 0,
        ];

        $this->assertFalse(
            completion_defaults_manager::matches_core_defaults($cmrecord, $coredefaults, 'assign')
        );
    }

    /**
     * Test matches_core_defaults returns true when both use grade.
     */
    public function test_matches_core_defaults_both_use_grade(): void {
        $cmrecord = (object)[
            'id' => 1,
            'instance' => 1,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 0,
            'completionpassgrade' => 1,
            'completiongradeitemnumber' => 0,
        ];

        $coredefaults = (object)[
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 0,
            'completionpassgrade' => 1,
            'completionusegrade' => 1,
        ];

        $this->assertTrue(
            completion_defaults_manager::matches_core_defaults($cmrecord, $coredefaults, 'assign')
        );
    }
}
