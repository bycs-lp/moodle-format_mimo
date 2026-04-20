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
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Observer test case.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_mimo\observer
 */
final class observer_test extends \advanced_testcase {
    /** @var \stdClass Test course with mimo format */
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
        tag_manager::reset_caches();

        // Create a tag.
        $this->tagid = tag_manager::create_tag(
            'Test Tag',
            'test.svg',
            'test-small.svg',
            'page'
        );

        // Create a course with mimo format.
        $this->course = $this->getDataGenerator()->create_course([
            'format' => 'mimo',
        ]);
    }

    /**
     * Clean up after each test.
     */
    protected function tearDown(): void {
        global $SESSION;

        // Reset static cache references to avoid stale instances after
        // \phpunit_util::reset_all_data() resets the cache factory.
        tag_manager::reset_caches();

        // Ensure session is clean for next test.
        unset($SESSION->format_mimo_pending_tag);

        parent::tearDown();
    }

    /**
     * Test that tag is assigned when activity is created with pending tag in session.
     */
    public function test_tag_assigned_on_activity_creation(): void {
        global $SESSION;

        // Set pending tag in session (simulating what the JavaScript does).
        $SESSION->format_mimo_pending_tag = $this->tagid;

        // Create a course module (this triggers the course_module_created event).
        $module = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id]);

        // Verify the tag was assigned to the module.
        $assignedtag = tag_manager::get_cm_tag($module->cmid);

        $this->assertNotFalse($assignedtag, 'Tag should be assigned to the module');
        $this->assertEquals($this->tagid, $assignedtag->id, 'Assigned tag ID should match');

        // Verify the session was cleared.
        $this->assertObjectNotHasProperty(
            'format_mimo_pending_tag',
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
                'format' => 'mimo',
                'haspending' => false,
                'sessioncleared' => true,
                'message' => 'No tag should be assigned when there is no pending tag',
            ],
            'wrong_course_format' => [
                'format' => 'topics',
                'haspending' => true,
                'sessioncleared' => false,
                'message' => 'No tag should be assigned to non-mimo courses',
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
        $tagid = tag_manager::create_tag('Test Tag', 'test.svg', 'test-small.svg', 'page');

        // Create a course with specified format.
        $courseoptions = ['format' => $format];
        $course = $this->getDataGenerator()->create_course($courseoptions);

        // Set pending tag in session if requested.
        if ($haspending) {
            $SESSION->format_mimo_pending_tag = $tagid;
        } else {
            unset($SESSION->format_mimo_pending_tag);
        }

        // Create a course module.
        $module = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        // Verify no tag was assigned.
        $assignedtag = tag_manager::get_cm_tag($module->cmid);
        $this->assertFalse($assignedtag, $message);

        // Check session state.
        if ($sessioncleared) {
            $this->assertObjectNotHasProperty('format_mimo_pending_tag', $SESSION);
        } else {
            $this->assertObjectHasProperty('format_mimo_pending_tag', $SESSION);
        }
    }

    /**
     * Test that pending tag is cleared after successful assignment.
     */
    public function test_pending_tag_cleared_after_assignment(): void {
        global $SESSION;

        // Set pending tag in session.
        $SESSION->format_mimo_pending_tag = $this->tagid;

        // Create first module.
        $module1 = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id]);

        // Verify tag was assigned.
        $assignedtag1 = tag_manager::get_cm_tag($module1->cmid);
        $this->assertNotFalse($assignedtag1, 'Tag should be assigned to first module');

        // Verify session was cleared.
        $this->assertObjectNotHasProperty(
            'format_mimo_pending_tag',
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
                    tag_manager::create_tag('Valid Tag', 'test.svg', 'test-small.svg', 'page');

                    // Set pending tag to non-existent tag ID.
                    $SESSION->format_mimo_pending_tag = 99999;
                },
                'message' => 'Invalid tag should not be assigned',
            ],
            'tag_disabled_in_profile' => [
                'setup' => function ($course, $SESSION) {
                    // Create two tags.
                    $tagid1 = tag_manager::create_tag('Tag 1', 'test1.svg', 'test1-small.svg', 'page');
                    $tagid2 = tag_manager::create_tag('Tag 2', 'test2.svg', 'test2-small.svg', 'url');

                    // Create a profile and disable tag 2.
                    $profileid = profile_manager::create_profile('testprofile', 'Test Profile');
                    $pt = profile_manager::get_or_create_profile_tag($tagid2, $profileid);
                    profile_manager::update_profile_tag($pt->id, ['enabled' => 0]);

                    // Set course to use this profile.
                    $format = course_get_format($course->id);
                    $format->update_course_format_options(['activityprofile' => 'testprofile']);

                    // Set pending tag to tag 2 (disabled in profile).
                    $SESSION->format_mimo_pending_tag = $tagid2;
                },
                'message' => 'Tag disabled in profile should not be assigned',
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

        // Create a course with mimo format.
        $course = $this->getDataGenerator()->create_course(['format' => 'mimo']);

        // Run the scenario-specific setup.
        $setup($course, $SESSION);

        // Create a course module.
        $module = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        // Verify no tag was assigned.
        $assignedtag = tag_manager::get_cm_tag($module->cmid);
        $this->assertFalse($assignedtag, $message);

        // Session should be cleared.
        $this->assertObjectNotHasProperty('format_mimo_pending_tag', $SESSION);
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
        $exists = $DB->record_exists('format_mimo_cmtags', ['cmid' => $module->cmid]);
        $this->assertFalse($exists, 'cmtag should be deleted when module is deleted');
    }

    /**
     * Test that deleting a course cleans up cmtag records via delete_format_data().
     */
    public function test_course_deleted_cleans_orphaned_cmtags(): void {
        global $DB;

        // Create a second course to prove we don't wipe all cmtags.
        $course2 = $this->getDataGenerator()->create_course(['format' => 'mimo']);
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
        $exists1a = $DB->record_exists('format_mimo_cmtags', ['cmid' => $module1a->cmid]);
        $exists1b = $DB->record_exists('format_mimo_cmtags', ['cmid' => $module1b->cmid]);
        $this->assertFalse($exists1a, 'cmtag for deleted course module should be removed');
        $this->assertFalse($exists1b, 'cmtag for deleted course module should be removed');

        // Verify cmtag for the other course is untouched.
        $exists2 = $DB->record_exists('format_mimo_cmtags', ['cmid' => $module2->cmid]);
        $this->assertTrue($exists2, 'cmtag for unrelated course should survive');
    }

    /**
     * Deleting a section should delete any stored section image for that section
     * via the course_section_deleted observer.
     */
    public function test_course_section_deleted_cleans_section_image(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course([
            'format' => 'mimo',
            'numsections' => 2,
        ]);

        $sectionid = (int) $DB->get_field('course_sections', 'id', [
            'course' => $course->id,
            'section' => 1,
        ]);

        // Store a dummy image file in the section image area.
        $context = \core\context\course::instance($course->id);
        get_file_storage()->create_file_from_string(
            [
                'contextid' => $context->id,
                'component' => section_image_manager::COMPONENT,
                'filearea'  => section_image_manager::FILEAREA,
                'itemid'    => $sectionid,
                'filepath'  => '/',
                'filename'  => 'section.png',
            ],
            'fake-image-bytes'
        );

        $this->assertTrue(section_image_manager::has_image($course->id, $sectionid));

        // Delete the section (fires course_section_deleted).
        $sectioninfo = get_fast_modinfo($course->id)->get_section_info(1);
        course_delete_section($course->id, $sectioninfo, false, true);

        $this->assertFalse(section_image_manager::has_image($course->id, $sectionid));
    }

    /**
     * course_deleted must also remove done-flags, course_tags bindings, and orphaned
     * imported tags / imported profiles in addition to cmtag rows.
     *
     * Covers observer::course_deleted() branches that were previously uncovered.
     */
    public function test_course_deleted_full_cleanup(): void {
        global $DB;

        // Second course acts as a control: nothing attached to it must be touched.
        $survivor = $this->getDataGenerator()->create_course(['format' => 'mimo']);
        $survivormodule = $this->getDataGenerator()->create_module('page', ['course' => $survivor->id]);
        done_manager::set_done($survivormodule->cmid);

        // Tags and profiles with various scopes/attachment states relative to $this->course.
        $boundonlyimportedtag = tag_manager::create_tag(
            'BoundOnly', 'b.svg', 'b-s.svg', 'page',
            null, null, null, 'center', 'normal', 'imported',
        );
        tag_manager::bind_tag_to_course($boundonlyimportedtag, $this->course->id);

        $boundandsurvivingimportedtag = tag_manager::create_tag(
            'AlsoElsewhere', 'e.svg', 'e-s.svg', 'page',
            null, null, null, 'center', 'normal', 'imported',
        );
        tag_manager::bind_tag_to_course($boundandsurvivingimportedtag, $this->course->id);
        tag_manager::bind_tag_to_course($boundandsurvivingimportedtag, $survivor->id);

        $globaltag = $this->tagid; // Created in setUp().

        // Module in the doomed course with a done flag and a cmtag.
        $doomedmodule = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
        ]);
        tag_manager::assign_tag_to_cm($doomedmodule->cmid, $globaltag);
        done_manager::set_done($doomedmodule->cmid);

        // Imported profile used exclusively by the doomed course.
        $orphanprofileid = profile_manager::create_profile(
            'orphan_profile', 'Orphan Profile', 99, 'imported',
        );
        // Imported profile used by the survivor — must remain.
        $keptprofileid = profile_manager::create_profile(
            'kept_profile', 'Kept Profile', 98, 'imported',
        );
        // Directly wire the cfo rows to bypass the format's value-allowlist.
        $DB->set_field_select(
            'course_format_options',
            'value',
            'orphan_profile',
            "format = 'mimo' AND name = 'activityprofile' AND courseid = :courseid",
            ['courseid' => $this->course->id],
        );
        $DB->set_field_select(
            'course_format_options',
            'value',
            'kept_profile',
            "format = 'mimo' AND name = 'activityprofile' AND courseid = :courseid",
            ['courseid' => $survivor->id],
        );

        // Baseline sanity.
        $this->assertTrue($DB->record_exists('format_mimo_cmdone', ['cmid' => $doomedmodule->cmid]));
        $this->assertTrue(
            $DB->record_exists('format_mimo_cmdone', ['cmid' => $survivormodule->cmid]),
            'Survivor done flag must be persisted before course deletion',
        );
        $this->assertTrue($DB->record_exists('format_mimo_course_tags', [
            'courseid' => $this->course->id,
            'tagid' => $boundonlyimportedtag,
        ]));

        // Fire course_deleted.
        delete_course($this->course, false);

        // 1. done flags for the deleted course are gone; survivor's stay.
        $this->assertFalse(
            $DB->record_exists('format_mimo_cmdone', ['cmid' => $doomedmodule->cmid]),
            'Done flags for the deleted course must be purged',
        );
        $this->assertTrue(
            $DB->record_exists('format_mimo_cmdone', ['cmid' => $survivormodule->cmid]),
            'Done flags on unrelated courses must survive',
        );

        // 2. course_tags bindings for the deleted course are gone.
        $this->assertSame(
            0,
            $DB->count_records('format_mimo_course_tags', ['courseid' => $this->course->id]),
        );

        // 3. Orphaned imported tag (only bound to the deleted course, no cmtag refs) is deleted.
        $this->assertFalse(
            $DB->record_exists('format_mimo_tags', ['id' => $boundonlyimportedtag]),
            'Imported tag that became orphaned by course deletion must be removed',
        );

        // 4. Imported tag still bound to survivor must survive.
        $this->assertTrue(
            $DB->record_exists('format_mimo_tags', ['id' => $boundandsurvivingimportedtag]),
        );

        // 5. Orphan imported profile is gone; kept imported profile survives.
        $this->assertFalse(
            $DB->record_exists('format_mimo_profiles', ['id' => $orphanprofileid]),
            'Imported profile referenced only by the deleted course must be removed',
        );
        $this->assertTrue(
            $DB->record_exists('format_mimo_profiles', ['id' => $keptprofileid]),
        );

        // 6. Global tag is never cleaned up by this path.
        $this->assertTrue($DB->record_exists('format_mimo_tags', ['id' => $globaltag]));
    }
}
