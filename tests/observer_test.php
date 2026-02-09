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
 * Unit tests for observer event handling.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall;

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Observer test case.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_minimoodlewall\observer
 */
final class observer_test extends \advanced_testcase {
    /** @var \stdClass Test course with minimoodlewall format */
    private $course;

    /** @var int Test tag ID */
    private $tagid;

    /**
     * Set up before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        tagset_manager::clear_tagset_cache();

        // Create a tagset.
        $tagsetid = tagset_manager::create_tagset('Test Tagset');

        // Create a tag.
        $this->tagid = tag_manager::create_tag(
            $tagsetid,
            'Test Tag',
            'test.svg',
            'test-small.svg',
            'page'
        );

        // Create a course with minimoodlewall format and this tag selected.
        $this->course = $this->getDataGenerator()->create_course([
            'format' => 'minimoodlewall',
            'selectedtags' => $this->tagid,
        ]);
    }

    /**
     * Clean up after each test.
     */
    protected function tearDown(): void {
        global $SESSION;

        // Clear caches to prevent cross-test contamination.
        \cache::make('format_minimoodlewall', 'tagconfigurations')->purge();
        \cache::make('format_minimoodlewall', 'activitytagmappings')->purge();
        tag_manager::clear_mapping_cache();

        // Ensure session is clean for next test.
        unset($SESSION->format_minimoodlewall_pending_tag);

        parent::tearDown();
    }

    /**
     * Test that tag is assigned when activity is created with pending tag in session.
     */
    public function test_tag_assigned_on_activity_creation(): void {
        global $SESSION;

        // Set pending tag in session (simulating what the JavaScript does).
        $SESSION->format_minimoodlewall_pending_tag = $this->tagid;

        // Create a course module (this triggers the course_module_created event).
        $module = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id]);

        // Verify the tag was assigned to the module.
        $assignedtag = tag_manager::get_cm_tag($module->cmid);

        $this->assertNotFalse($assignedtag, 'Tag should be assigned to the module');
        $this->assertEquals($this->tagid, $assignedtag->id, 'Assigned tag ID should match');

        // Verify the session was cleared.
        $this->assertObjectNotHasProperty(
            'format_minimoodlewall_pending_tag',
            $SESSION,
            'Pending tag should be removed from session'
        );
    }

    /**
     * Data provider for scenarios where no tag should be assigned.
     *
     * @return array Test scenarios [scenario_name, course_format, has_pending_tag, session_should_be_cleared, message]
     */
    public static function no_tag_assignment_provider(): array {
        return [
            'no_pending_tag_in_session' => [
                'format' => 'minimoodlewall',
                'haspending' => false,
                'sessioncleared' => true,
                'message' => 'No tag should be assigned when there is no pending tag',
            ],
            'wrong_course_format' => [
                'format' => 'topics',
                'haspending' => true,
                'sessioncleared' => false,
                'message' => 'No tag should be assigned to non-minimoodlewall courses',
            ],
        ];
    }

    /**
     * Test that no tag is assigned in various scenarios.
     *
     * @dataProvider no_tag_assignment_provider
     * @param string $format Course format
     * @param bool $haspending Whether to set a pending tag in session
     * @param bool $sessioncleared Whether session should be cleared after
     * @param string $message Assertion message
     */
    public function test_no_tag_assignment(
        string $format,
        bool $haspending,
        bool $sessioncleared,
        string $message
    ): void {
        global $SESSION;

        // Create a tag.
        $tagsetid = tagset_manager::create_tagset('Observer Tagset');
        $tagid = tag_manager::create_tag($tagsetid, 'Test Tag', 'test.svg', 'test-small.svg', 'page');

        // Create a course with specified format.
        $courseoptions = ['format' => $format];
        if ($format === 'minimoodlewall') {
            $courseoptions['selectedtags'] = $tagid;
        }
        $course = $this->getDataGenerator()->create_course($courseoptions);

        // Note: For minimoodlewall format, selectedtags is already set during course creation.

        // Set pending tag in session if requested.
        if ($haspending) {
            $SESSION->format_minimoodlewall_pending_tag = $tagid;
        } else {
            unset($SESSION->format_minimoodlewall_pending_tag);
        }

        // Create a course module.
        $module = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        // Verify no tag was assigned.
        $assignedtag = tag_manager::get_cm_tag($module->cmid);
        $this->assertFalse($assignedtag, $message);

        // Check session state.
        if ($sessioncleared) {
            $this->assertObjectNotHasProperty('format_minimoodlewall_pending_tag', $SESSION);
        } else {
            $this->assertObjectHasProperty('format_minimoodlewall_pending_tag', $SESSION);
        }
    }

    /**
     * Test that pending tag is cleared after successful assignment.
     */
    public function test_pending_tag_cleared_after_assignment(): void {
        global $SESSION;

        // Set pending tag in session.
        $SESSION->format_minimoodlewall_pending_tag = $this->tagid;

        // Create first module.
        $module1 = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id]);

        // Verify tag was assigned.
        $assignedtag1 = tag_manager::get_cm_tag($module1->cmid);
        $this->assertNotFalse($assignedtag1, 'Tag should be assigned to first module');

        // Verify session was cleared.
        $this->assertObjectNotHasProperty(
            'format_minimoodlewall_pending_tag',
            $SESSION,
            'Pending tag should be cleared after first assignment'
        );

        // Create second module (should not get any tag).
        $module2 = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);

        // Verify no tag was assigned to second module.
        $assignedtag2 = tag_manager::get_cm_tag($module2->cmid);
        $this->assertFalse($assignedtag2, 'Second module should not have a tag');
    }

    /**
     * Data provider for tag rejection scenarios.
     *
     * @return array Test scenarios with [scenario_name, setup_callback, expected_message]
     */
    public static function tag_rejection_provider(): array {
        return [
            'invalid_tag_id' => [
                'setup' => function ($course, $SESSION) {
                    // Create a tag.
                    $tagsetid = tagset_manager::create_tagset('Reject Tagset');
                    $tagid = tag_manager::create_tag($tagsetid, 'Valid Tag', 'test.svg', 'test-small.svg', 'page');

                    // Set course selected tags.
                    $format = course_get_format($course->id);
                    $format->update_course_format_options(['selectedtags' => $tagid]);

                    // Set pending tag to non-existent tag ID.
                    $SESSION->format_minimoodlewall_pending_tag = 99999;
                },
                'message' => 'Invalid tag should not be assigned',
            ],
            'tag_not_selected_for_course' => [
                'setup' => function ($course, $SESSION) {
                    // Create two tags.
                    $tagsetid = tagset_manager::create_tagset('Multi Tagset');
                    $tagid1 = tag_manager::create_tag($tagsetid, 'Tag 1', 'test1.svg', 'test1-small.svg', 'page');
                    $tagid2 = tag_manager::create_tag($tagsetid, 'Tag 2', 'test2.svg', 'test2-small.svg', 'url');

                    // Set course to only use tag 1.
                    $format = course_get_format($course->id);
                    $format->update_course_format_options(['selectedtags' => $tagid1]);

                    // Set pending tag to tag 2 (not selected for this course).
                    $SESSION->format_minimoodlewall_pending_tag = $tagid2;
                },
                'message' => 'Tag not selected for course should not be assigned',
            ],
        ];
    }

    /**
     * Test that invalid tags are not assigned.
     *
     * @dataProvider tag_rejection_provider
     * @param callable $setup Setup function that prepares the scenario
     * @param string $message Assertion message
     */
    public function test_invalid_tags_not_assigned(callable $setup, string $message): void {
        global $SESSION;

        // Create a course with minimoodlewall format.
        $course = $this->getDataGenerator()->create_course(['format' => 'minimoodlewall']);

        // Run the scenario-specific setup.
        $setup($course, $SESSION);

        // Create a course module.
        $module = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        // Verify no tag was assigned.
        $assignedtag = tag_manager::get_cm_tag($module->cmid);
        $this->assertFalse($assignedtag, $message);

        // Session should be cleared.
        $this->assertObjectNotHasProperty('format_minimoodlewall_pending_tag', $SESSION);
    }

    /**
     * Test that deleting a course module cleans up its cmtag record.
     */
    public function test_course_module_deleted_cleans_cmtags(): void {
        global $DB;

        // Create a module and assign a tag.
        $module = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id]);
        tag_manager::assign_tag_to_cm($module->cmid, $this->tagid);

        // Verify the cmtag exists.
        $cmtag = tag_manager::get_cm_tag($module->cmid);
        $this->assertNotFalse($cmtag, 'cmtag should exist before deletion');

        // Delete the module (this fires course_module_deleted event).
        course_delete_module($module->cmid);

        // Verify the cmtag record was cleaned up.
        $exists = $DB->record_exists('format_minimoodlewall_cmtags', ['cmid' => $module->cmid]);
        $this->assertFalse($exists, 'cmtag should be deleted when module is deleted');
    }

    /**
     * Test that deleting a course cleans up orphaned cmtag records.
     */
    public function test_course_deleted_cleans_orphaned_cmtags(): void {
        global $DB;

        // Create a second course to prove we don't wipe all cmtags.
        $course2 = $this->getDataGenerator()->create_course(['format' => 'minimoodlewall']);
        $module2 = $this->getDataGenerator()->create_module('page', ['course' => $course2->id]);
        tag_manager::assign_tag_to_cm($module2->cmid, $this->tagid);

        // Create modules in the course that will be deleted.
        $module1a = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id]);
        $module1b = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);
        tag_manager::assign_tag_to_cm($module1a->cmid, $this->tagid);
        tag_manager::assign_tag_to_cm($module1b->cmid, $this->tagid);

        // Verify cmtags exist.
        $this->assertNotFalse(tag_manager::get_cm_tag($module1a->cmid));
        $this->assertNotFalse(tag_manager::get_cm_tag($module1b->cmid));
        $this->assertNotFalse(tag_manager::get_cm_tag($module2->cmid));

        // Delete the first course (this fires course_deleted event).
        delete_course($this->course, false);

        // Verify cmtags for deleted course are gone.
        $exists1a = $DB->record_exists('format_minimoodlewall_cmtags', ['cmid' => $module1a->cmid]);
        $exists1b = $DB->record_exists('format_minimoodlewall_cmtags', ['cmid' => $module1b->cmid]);
        $this->assertFalse($exists1a, 'cmtag for deleted course module should be removed');
        $this->assertFalse($exists1b, 'cmtag for deleted course module should be removed');

        // Verify cmtag for the other course is untouched.
        $exists2 = $DB->record_exists('format_minimoodlewall_cmtags', ['cmid' => $module2->cmid]);
        $this->assertTrue($exists2, 'cmtag for unrelated course should survive');
    }
}
