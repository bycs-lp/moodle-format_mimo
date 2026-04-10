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
 * Unit tests for {@see completion_helper}.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_mimo\completion_helper
 */
final class completion_helper_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        completion_helper::reset_caches();
    }

    /**
     * Test that completion counts are zero when no completions exist.
     */
    public function test_counts_zero_when_no_completions(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course([
            'format' => 'mimo',
            'enablecompletion' => 1,
        ]);

        $page = $generator->create_module('page', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $counts = completion_helper::get_teacher_completion_counts($course->id);
        // The CM exists with completion enabled but nobody completed — should be absent or 0.
        $this->assertEquals(0, $counts[$page->cmid] ?? 0);
    }

    /**
     * Test that successful completions are counted correctly.
     */
    public function test_counts_successful_completions(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course([
            'format' => 'mimo',
            'enablecompletion' => 1,
        ]);

        $page = $generator->create_module('page', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        // Enrol 3 students.
        $student1 = $generator->create_user();
        $student2 = $generator->create_user();
        $student3 = $generator->create_user();
        $generator->enrol_user($student1->id, $course->id, 'student');
        $generator->enrol_user($student2->id, $course->id, 'student');
        $generator->enrol_user($student3->id, $course->id, 'student');

        // Manually mark completions in the DB.
        $now = time();
        $DB->insert_record('course_modules_completion', (object) [
            'coursemoduleid' => $page->cmid,
            'userid' => $student1->id,
            'completionstate' => COMPLETION_COMPLETE,
            'timemodified' => $now,
        ]);
        $DB->insert_record('course_modules_completion', (object) [
            'coursemoduleid' => $page->cmid,
            'userid' => $student2->id,
            'completionstate' => COMPLETION_COMPLETE_PASS,
            'timemodified' => $now,
        ]);
        // Student 3 has a failed completion — should NOT be counted.
        $DB->insert_record('course_modules_completion', (object) [
            'coursemoduleid' => $page->cmid,
            'userid' => $student3->id,
            'completionstate' => COMPLETION_COMPLETE_FAIL,
            'timemodified' => $now,
        ]);

        $counts = completion_helper::get_teacher_completion_counts($course->id);
        $this->assertEquals(2, $counts[$page->cmid]);
    }

    /**
     * Test that counts span multiple activities correctly.
     */
    public function test_counts_multiple_activities(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course([
            'format' => 'mimo',
            'enablecompletion' => 1,
        ]);

        $page1 = $generator->create_module('page', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $page2 = $generator->create_module('page', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');

        $now = time();
        // Complete page1 only.
        $DB->insert_record('course_modules_completion', (object) [
            'coursemoduleid' => $page1->cmid,
            'userid' => $student->id,
            'completionstate' => COMPLETION_COMPLETE,
            'timemodified' => $now,
        ]);

        $counts = completion_helper::get_teacher_completion_counts($course->id);
        $this->assertEquals(1, $counts[$page1->cmid]);
        $this->assertEquals(0, $counts[$page2->cmid] ?? 0);
    }

    /**
     * Test that activities without completion tracking are excluded.
     */
    public function test_excludes_activities_without_completion(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course([
            'format' => 'mimo',
            'enablecompletion' => 1,
        ]);

        // Activity with completion disabled.
        $page = $generator->create_module('page', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_NONE,
        ]);

        $counts = completion_helper::get_teacher_completion_counts($course->id);
        // The CM should not appear in counts at all.
        $this->assertArrayNotHasKey($page->cmid, $counts);
    }

    /**
     * Test tracked user count returns enrolled students.
     */
    public function test_tracked_user_count(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course([
            'format' => 'mimo',
            'enablecompletion' => 1,
        ]);

        // Enrol 2 students and 1 teacher.
        $student1 = $generator->create_user();
        $student2 = $generator->create_user();
        $teacher = $generator->create_user();
        $generator->enrol_user($student1->id, $course->id, 'student');
        $generator->enrol_user($student2->id, $course->id, 'student');
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');

        $count = completion_helper::get_tracked_user_count($course->id);
        // Only students have moodle/course:isincompletionreports by default.
        $this->assertEquals(2, $count);
    }

    /**
     * Test that both methods return values for the same course without errors on repeated calls.
     */
    public function test_repeated_calls_return_consistent_results(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course([
            'format' => 'mimo',
            'enablecompletion' => 1,
        ]);
        $page = $generator->create_module('page', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');

        $DB->insert_record('course_modules_completion', (object) [
            'coursemoduleid' => $page->cmid,
            'userid' => $student->id,
            'completionstate' => COMPLETION_COMPLETE,
            'timemodified' => time(),
        ]);

        // Two successive calls should return the same result.
        $counts1 = completion_helper::get_teacher_completion_counts($course->id);
        $counts2 = completion_helper::get_teacher_completion_counts($course->id);
        $this->assertEquals($counts1, $counts2);

        $tracked1 = completion_helper::get_tracked_user_count($course->id);
        $tracked2 = completion_helper::get_tracked_user_count($course->id);
        $this->assertEquals($tracked1, $tracked2);
    }

    /**
     * Test that the cmitem output class produces teacher completion data.
     */
    public function test_cmitem_teacher_view_output(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course([
            'format' => 'mimo',
            'enablecompletion' => 1,
        ]);

        $page = $generator->create_module('page', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
            'section' => 1,
        ]);

        // Enrol a student and mark activity complete.
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $DB->insert_record('course_modules_completion', (object) [
            'coursemoduleid' => $page->cmid,
            'userid' => $student->id,
            'completionstate' => COMPLETION_COMPLETE,
            'timemodified' => time(),
        ]);

        // Enrol teacher and view as teacher.
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $PAGE->set_course($course);
        $PAGE->set_url(new \moodle_url('/course/view.php', ['id' => $course->id]));

        $format = course_get_format($course);
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($page->cmid);

        $cmitemclass = $format->get_output_classname('content\\section\\cmitem');
        $cmitem = new $cmitemclass($format, $modinfo->get_section_info(1), $cm);

        $renderer = $PAGE->get_renderer('format_mimo');
        $data = $cmitem->export_for_template($renderer);

        // Teacher should see teacher view data.
        $this->assertTrue($data->cmformat->completion->hascompletion);
        $this->assertTrue($data->cmformat->completion->isteacherview);
        $this->assertEquals(1, $data->cmformat->completion->completedcount);
        $this->assertEquals(1, $data->cmformat->completion->trackedtotal);
        $this->assertStringContainsString('report/progress/index.php', $data->cmformat->completion->reporturl);
    }

    /**
     * Test that the cmitem output class produces student completion data.
     */
    public function test_cmitem_student_view_output(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course([
            'format' => 'mimo',
            'enablecompletion' => 1,
        ]);

        $page = $generator->create_module('page', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
            'section' => 1,
        ]);

        // Enrol student and mark activity complete.
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $DB->insert_record('course_modules_completion', (object) [
            'coursemoduleid' => $page->cmid,
            'userid' => $student->id,
            'completionstate' => COMPLETION_COMPLETE,
            'timemodified' => time(),
        ]);

        $this->setUser($student);

        $PAGE->set_course($course);
        $PAGE->set_url(new \moodle_url('/course/view.php', ['id' => $course->id]));

        $format = course_get_format($course);
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($page->cmid);

        $cmitemclass = $format->get_output_classname('content\\section\\cmitem');
        $cmitem = new $cmitemclass($format, $modinfo->get_section_info(1), $cm);

        $renderer = $PAGE->get_renderer('format_mimo');
        $data = $cmitem->export_for_template($renderer);

        // Student should see personal completion, not teacher view.
        $this->assertTrue($data->cmformat->completion->hascompletion);
        $this->assertObjectNotHasProperty('isteacherview', $data->cmformat->completion);
        $this->assertTrue($data->cmformat->completion->iscomplete);
    }

    /**
     * Test that the overview export includes teacher completion percentage per section.
     */
    public function test_overview_teacher_completion_percentage(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course([
            'format' => 'mimo',
            'enablecompletion' => 1,
            'numsections' => 2,
            'enablemultisection' => 1,
        ]);

        // Create activities in section 1.
        $page1 = $generator->create_module('page', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
            'section' => 1,
        ]);
        $page2 = $generator->create_module('page', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
            'section' => 1,
        ]);

        // Enrol 2 students.
        $student1 = $generator->create_user();
        $student2 = $generator->create_user();
        $generator->enrol_user($student1->id, $course->id, 'student');
        $generator->enrol_user($student2->id, $course->id, 'student');

        // Student 1 completes both, student 2 completes one.
        $now = time();
        $DB->insert_record('course_modules_completion', (object) [
            'coursemoduleid' => $page1->cmid,
            'userid' => $student1->id,
            'completionstate' => COMPLETION_COMPLETE,
            'timemodified' => $now,
        ]);
        $DB->insert_record('course_modules_completion', (object) [
            'coursemoduleid' => $page2->cmid,
            'userid' => $student1->id,
            'completionstate' => COMPLETION_COMPLETE,
            'timemodified' => $now,
        ]);
        $DB->insert_record('course_modules_completion', (object) [
            'coursemoduleid' => $page1->cmid,
            'userid' => $student2->id,
            'completionstate' => COMPLETION_COMPLETE,
            'timemodified' => $now,
        ]);

        // View as teacher.
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $course = get_course($course->id);
        $PAGE->set_course($course);
        $PAGE->set_url(new \moodle_url('/course/view.php', ['id' => $course->id]));

        $format = course_get_format($course);
        $contentclass = $format->get_output_classname('content');
        $content = new $contentclass($format);

        $renderer = $PAGE->get_renderer('format_mimo');
        $data = $content->export_for_template($renderer);

        // Should be overview mode (multisection with no section selected).
        $this->assertTrue($data->isoverview);

        // Find the section 1 card.
        $section1card = null;
        foreach ($data->overviewsections as $section) {
            if ($section->num === 1) {
                $section1card = $section;
                break;
            }
        }

        $this->assertNotNull($section1card, 'Section 1 should exist in overview');
        $this->assertTrue($section1card->isteacherview);
        $this->assertTrue($section1card->hastracking);

        // 3 completions out of 4 possible (2 CMs x 2 students) = 75%.
        $this->assertEquals(75, $section1card->completionpercent);
        $this->assertStringContainsString('report/progress/index.php', $section1card->reporturl);
    }
}
