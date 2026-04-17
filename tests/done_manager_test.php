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
 * Unit tests for done_manager.
 *
 * @package    format_mimo
 * @copyright  2026 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_mimo\done_manager
 */
final class done_manager_test extends \advanced_testcase {
    /**
     * Set up before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        done_manager::reset_cache();
    }

    /**
     * Test setting and checking done flag.
     */
    public function test_set_and_is_done(): void {
        $course = $this->getDataGenerator()->create_course(['format' => 'mimo']);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $this->assertFalse(done_manager::is_done($page->cmid));

        done_manager::set_done($page->cmid);
        $this->assertTrue(done_manager::is_done($page->cmid));
    }

    /**
     * Test that set_done is idempotent.
     */
    public function test_set_done_idempotent(): void {
        $course = $this->getDataGenerator()->create_course(['format' => 'mimo']);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        done_manager::set_done($page->cmid);
        done_manager::set_done($page->cmid); // Should not throw.
        $this->assertTrue(done_manager::is_done($page->cmid));
    }

    /**
     * Test unsetting done flag.
     */
    public function test_unset_done(): void {
        $course = $this->getDataGenerator()->create_course(['format' => 'mimo']);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        done_manager::set_done($page->cmid);
        $this->assertTrue(done_manager::is_done($page->cmid));

        done_manager::unset_done($page->cmid);
        $this->assertFalse(done_manager::is_done($page->cmid));
    }

    /**
     * Test unset_done on a non-done activity is a no-op.
     */
    public function test_unset_done_noop(): void {
        $course = $this->getDataGenerator()->create_course(['format' => 'mimo']);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        done_manager::unset_done($page->cmid); // Should not throw.
        $this->assertFalse(done_manager::is_done($page->cmid));
    }

    /**
     * Test get_done_cmids returns all done activities for a course.
     */
    public function test_get_done_cmids(): void {
        $course = $this->getDataGenerator()->create_course(['format' => 'mimo']);
        $page1 = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $page2 = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $page3 = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        done_manager::set_done($page1->cmid);
        done_manager::set_done($page3->cmid);

        $doneids = done_manager::get_done_cmids($course->id);
        $this->assertCount(2, $doneids);
        $this->assertContains($page1->cmid, $doneids);
        $this->assertContains($page3->cmid, $doneids);
        $this->assertNotContains($page2->cmid, $doneids);
    }

    /**
     * Test get_done_cmids only returns activities from the specified course.
     */
    public function test_get_done_cmids_course_isolation(): void {
        $course1 = $this->getDataGenerator()->create_course(['format' => 'mimo']);
        $course2 = $this->getDataGenerator()->create_course(['format' => 'mimo']);
        $page1 = $this->getDataGenerator()->create_module('page', ['course' => $course1->id]);
        $page2 = $this->getDataGenerator()->create_module('page', ['course' => $course2->id]);

        done_manager::set_done($page1->cmid);
        done_manager::set_done($page2->cmid);

        $doneids1 = done_manager::get_done_cmids($course1->id);
        $this->assertCount(1, $doneids1);
        $this->assertContains($page1->cmid, $doneids1);
    }

    /**
     * Test delete_for_cm removes the done record.
     */
    public function test_delete_for_cm(): void {
        $course = $this->getDataGenerator()->create_course(['format' => 'mimo']);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        done_manager::set_done($page->cmid);
        $this->assertTrue(done_manager::is_done($page->cmid));

        done_manager::delete_for_cm($page->cmid);
        $this->assertFalse(done_manager::is_done($page->cmid));
    }

    /**
     * Test delete_for_course removes all done records for a course.
     */
    public function test_delete_for_course(): void {
        $course = $this->getDataGenerator()->create_course(['format' => 'mimo']);
        $page1 = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $page2 = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        done_manager::set_done($page1->cmid);
        done_manager::set_done($page2->cmid);

        done_manager::delete_for_course($course->id);

        $this->assertFalse(done_manager::is_done($page1->cmid));
        $this->assertFalse(done_manager::is_done($page2->cmid));
    }

    /**
     * Test that done activities are excluded from completion counts.
     *
     * Creates 3 activities with completion tracking, marks one as done,
     * and verifies that `get_done_cmids` returns it so consumers can
     * exclude it from completion totals.
     */
    public function test_done_excludes_from_completion_count(): void {
        global $CFG;
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria_activity.php');

        $course = $this->getDataGenerator()->create_course([
            'format' => 'mimo',
            'enablecompletion' => 1,
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create 3 activities with automatic completion.
        $page1 = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
        ]);
        $page2 = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
        ]);
        $page3 = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
        ]);

        // All 3 should be trackable.
        $modinfo = get_fast_modinfo($course);
        $completioninfo = new \completion_info($course);
        $trackable = 0;
        foreach ($modinfo->cms as $cm) {
            if ($completioninfo->is_enabled($cm)) {
                $trackable++;
            }
        }
        $this->assertEquals(3, $trackable);

        // Mark page2 as done.
        done_manager::set_done($page2->cmid);
        $donecmids = done_manager::get_done_cmids($course->id);

        // Filter trackable activities by excluding done ones (same logic as section.php).
        $countable = 0;
        foreach ($modinfo->cms as $cm) {
            if (in_array((int) $cm->id, $donecmids, true)) {
                continue;
            }
            if ($completioninfo->is_enabled($cm)) {
                $countable++;
            }
        }
        $this->assertEquals(2, $countable, 'Done activity should be excluded from completion count');
    }
}
