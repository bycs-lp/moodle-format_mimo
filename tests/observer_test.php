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

    /** @var int Test tagset ID */
    private $tagsetid;

    /** @var int Test tag ID */
    private $tagid;

    /**
     * Set up before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course with minimoodlewall format for most tests.
        $this->course = $this->getDataGenerator()->create_course(['format' => 'minimoodlewall']);

        // Create a tagset and tag.
        $this->tagsetid = tag_manager::create_tagset('Test Tagset', 'Test Description');
        $this->tagid = tag_manager::create_tag(
            $this->tagsetid,
            'Test Tag',
            'Description',
            'test.svg',
            'test-small.svg'
        );

        // Set course format option to use this tagset.
        $format = course_get_format($this->course->id);
        $format->update_course_format_options(['tagsetid' => $this->tagsetid]);
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

        // Create a course with specified format.
        $course = $this->getDataGenerator()->create_course(['format' => $format]);

        // Create a tagset and tag.
        $tagsetid = tag_manager::create_tagset('Test Tagset', 'Test Description');
        $tagid = tag_manager::create_tag($tagsetid, 'Test Tag', 'Description', 'test.svg', 'test-small.svg');

        // Set course format option if minimoodlewall.
        if ($format === 'minimoodlewall') {
            $formatobj = course_get_format($course->id);
            $formatobj->update_course_format_options(['tagsetid' => $tagsetid]);
        }

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
                    // Create a tagset.
                    $tagsetid = tag_manager::create_tagset('Test Tagset', 'Test Description');

                    // Set course format option.
                    $format = course_get_format($course->id);
                    $format->update_course_format_options(['tagsetid' => $tagsetid]);

                    // Set pending tag to non-existent tag ID.
                    $SESSION->format_minimoodlewall_pending_tag = 99999;
                },
                'message' => 'Invalid tag should not be assigned',
            ],
            'tag_from_wrong_tagset' => [
                'setup' => function ($course, $SESSION) {
                    // Create two tagsets with tags.
                    $tagsetid1 = tag_manager::create_tagset('Tagset 1', 'Description 1');
                    tag_manager::create_tag($tagsetid1, 'Tag 1', 'Description', 'test1.svg', 'test1-small.svg');

                    $tagsetid2 = tag_manager::create_tagset('Tagset 2', 'Description 2');
                    $tagid2 = tag_manager::create_tag($tagsetid2, 'Tag 2', 'Description', 'test2.svg', 'test2-small.svg');

                    // Set course format option to use tagset 1.
                    $format = course_get_format($course->id);
                    $format->update_course_format_options(['tagsetid' => $tagsetid1]);

                    // Set pending tag from tagset 2.
                    $SESSION->format_minimoodlewall_pending_tag = $tagid2;
                },
                'message' => 'Tag from different tagset should not be assigned',
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
}
