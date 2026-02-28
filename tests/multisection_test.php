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
 * Unit tests for multi-section mode in the minimoodlewall course format.
 *
 * Tests cover:
 * - Creating activities in different sections and verifying they appear in the correct section.
 * - Format behaviour with multi-section enabled vs disabled.
 * - Section visibility, URL generation, course index support, and navigation.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall;

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

use core_courseformat\base as course_format;

/**
 * Multi-section mode test case.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_minimoodlewall
 */
final class multisection_test extends \advanced_testcase {
    /** @var \stdClass Course with multi-section enabled */
    private \stdClass $course;

    /** @var \format_minimoodlewall Format instance */
    private \format_minimoodlewall $format;

    /**
     * Set up a minimoodlewall course with multi-section mode and two sections.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course([
            'format' => 'minimoodlewall',
            'numsections' => 2,
        ]);

        // Enable multi-section mode.
        $this->enable_multisection($this->course);

        // Re-fetch format instance with fresh options.
        $this->format = course_get_format($this->course);
    }

    // -----------------------------------------------------------------------
    // Core multi-section toggle tests.
    // -----------------------------------------------------------------------

    /**
     * Test that multi-section mode can be toggled on and off.
     */
    public function test_multisection_toggle(): void {
        $this->assertTrue($this->format->is_multisection_enabled());

        // Disable it.
        $this->disable_multisection($this->course);
        $format = course_get_format($this->course);
        $this->assertFalse($format->is_multisection_enabled());
    }

    /**
     * Test that multi-section is off by default when creating a new course.
     */
    public function test_multisection_disabled_by_default(): void {
        $course = $this->getDataGenerator()->create_course(['format' => 'minimoodlewall']);
        $format = course_get_format($course);
        $this->assertFalse($format->is_multisection_enabled());
    }

    // -----------------------------------------------------------------------
    // Activities in sections: the main feature test.
    // -----------------------------------------------------------------------

    /**
     * Test that activities created in different sections belong to the correct section.
     *
     * This is the primary multi-section feature test: a teacher creates activities
     * across section 0, section 1, and section 2, then verifies each activity
     * appears only in its designated section.
     */
    public function test_activities_belong_to_correct_section(): void {
        $generator = $this->getDataGenerator();
        $modinfo = get_fast_modinfo($this->course);
        $sections = $modinfo->get_section_info_all();
        $this->assertGreaterThanOrEqual(3, count($sections), 'Course should have at least 3 sections (0, 1, 2)');

        // Create activities in section 0.
        $page0 = $generator->create_module('page', [
            'course' => $this->course->id,
            'section' => 0,
            'name' => 'Page in section 0',
        ]);
        $label0 = $generator->create_module('label', [
            'course' => $this->course->id,
            'section' => 0,
            'name' => 'Label in section 0',
        ]);

        // Create activities in section 1.
        $assign1 = $generator->create_module('assign', [
            'course' => $this->course->id,
            'section' => 1,
            'name' => 'Assignment in section 1',
        ]);
        $forum1 = $generator->create_module('forum', [
            'course' => $this->course->id,
            'section' => 1,
            'name' => 'Forum in section 1',
        ]);

        // Create activities in section 2.
        $quiz2 = $generator->create_module('quiz', [
            'course' => $this->course->id,
            'section' => 2,
            'name' => 'Quiz in section 2',
        ]);

        // Rebuild modinfo to pick up all changes.
        $modinfo = get_fast_modinfo($this->course);

        // Verify section 0 activities.
        $sec0cms = $modinfo->get_section_info(0)->get_sequence_cm_infos();
        $sec0names = array_map(fn($cm) => $cm->name, $sec0cms);
        $this->assertContains('Page in section 0', $sec0names);
        $this->assertContains('Label in section 0', $sec0names);
        $this->assertNotContains('Assignment in section 1', $sec0names);
        $this->assertNotContains('Quiz in section 2', $sec0names);

        // Verify section 1 activities.
        $sec1cms = $modinfo->get_section_info(1)->get_sequence_cm_infos();
        $sec1names = array_map(fn($cm) => $cm->name, $sec1cms);
        $this->assertContains('Assignment in section 1', $sec1names);
        $this->assertContains('Forum in section 1', $sec1names);
        $this->assertNotContains('Page in section 0', $sec1names);
        $this->assertNotContains('Quiz in section 2', $sec1names);

        // Verify section 2 activities.
        $sec2cms = $modinfo->get_section_info(2)->get_sequence_cm_infos();
        $sec2names = array_map(fn($cm) => $cm->name, $sec2cms);
        $this->assertContains('Quiz in section 2', $sec2names);
        $this->assertNotContains('Page in section 0', $sec2names);
        $this->assertNotContains('Assignment in section 1', $sec2names);
    }

    /**
     * Test that moving an activity between sections is reflected in modinfo.
     */
    public function test_move_activity_between_sections(): void {
        $generator = $this->getDataGenerator();

        $assign = $generator->create_module('assign', [
            'course' => $this->course->id,
            'section' => 1,
            'name' => 'Moveable assignment',
        ]);

        // Verify it starts in section 1.
        $modinfo = get_fast_modinfo($this->course);
        $sec1names = array_map(
            fn($cm) => $cm->name,
            $modinfo->get_section_info(1)->get_sequence_cm_infos()
        );
        $this->assertContains('Moveable assignment', $sec1names);

        // Move it to section 2.
        $cm = get_coursemodule_from_id('assign', $assign->cmid, $this->course->id);
        moveto_module($cm, $modinfo->get_section_info(2));

        // Verify it is now in section 2 and no longer in section 1.
        $modinfo = get_fast_modinfo($this->course);
        $sec1names = array_map(
            fn($cm) => $cm->name,
            $modinfo->get_section_info(1)->get_sequence_cm_infos()
        );
        $sec2names = array_map(
            fn($cm) => $cm->name,
            $modinfo->get_section_info(2)->get_sequence_cm_infos()
        );
        $this->assertNotContains('Moveable assignment', $sec1names);
        $this->assertContains('Moveable assignment', $sec2names);
    }

    // -----------------------------------------------------------------------
    // Section visibility tests.
    // -----------------------------------------------------------------------

    /**
     * Test that all sections are visible when multi-section is enabled.
     */
    public function test_all_sections_visible_in_multisection(): void {
        $modinfo = get_fast_modinfo($this->course);
        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->is_delegated()) {
                continue;
            }
            $this->assertTrue(
                $this->format->is_section_visible($section),
                "Section {$section->sectionnum} should be visible in multi-section mode."
            );
        }
    }

    /**
     * Test that only section 0 is visible when multi-section is disabled.
     */
    public function test_only_section0_visible_in_singlesection(): void {
        $this->disable_multisection($this->course);
        $format = course_get_format($this->course);
        $modinfo = get_fast_modinfo($this->course);

        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->is_delegated()) {
                continue;
            }
            if ($section->sectionnum == 0) {
                $this->assertTrue(
                    $format->is_section_visible($section),
                    'Section 0 should be visible in single-section mode.'
                );
            } else {
                $this->assertFalse(
                    $format->is_section_visible($section),
                    "Section {$section->sectionnum} should NOT be visible in single-section mode."
                );
            }
        }
    }

    // -----------------------------------------------------------------------
    // get_sectionnum() tests.
    // -----------------------------------------------------------------------

    /**
     * Test get_sectionnum returns 0 for single-section mode.
     */
    public function test_getsectionnum_singlesection_returns_zero(): void {
        $this->disable_multisection($this->course);
        $format = course_get_format($this->course);
        $this->assertSame(0, $format->get_sectionnum());
    }

    /**
     * Test get_sectionnum returns null by default in multi-section mode (before set_sectionnum).
     */
    public function test_getsectionnum_multisection_default_null(): void {
        // Fresh format instance without set_sectionnum called.
        $this->assertNull($this->format->get_sectionnum());
    }

    /**
     * Test get_sectionnum returns the section set via set_sectionnum.
     */
    public function test_getsectionnum_multisection_after_set(): void {
        $this->format->set_sectionnum(1);
        $this->assertSame(1, $this->format->get_sectionnum());

        $this->format->set_sectionnum(0);
        $this->assertSame(0, $this->format->get_sectionnum());
    }

    // -----------------------------------------------------------------------
    // URL generation tests.
    // -----------------------------------------------------------------------

    /**
     * Test that get_view_url uses course/view.php with section parameter in multi-section mode.
     */
    public function test_view_url_multisection_navigation(): void {
        $url = $this->format->get_view_url(1, ['navigation' => true]);
        $this->assertStringContainsString('/course/view.php', $url->out(false));
        $this->assertStringContainsString('section=1', $url->out(false));
        $this->assertStringNotContainsString('/course/section.php', $url->out(false));
    }

    /**
     * Test that get_view_url uses course/view.php with sr option in multi-section mode.
     */
    public function test_view_url_multisection_sr(): void {
        $url = $this->format->get_view_url(0, ['sr' => 2]);
        $this->assertStringContainsString('/course/view.php', $url->out(false));
        $this->assertStringContainsString('section=2', $url->out(false));
    }

    /**
     * Test that get_view_url without navigation option returns plain course URL.
     */
    public function test_view_url_multisection_no_navigation(): void {
        $url = $this->format->get_view_url(1);
        $this->assertStringContainsString('/course/view.php', $url->out(false));
        $this->assertStringNotContainsString('section=', $url->out(false));
    }

    /**
     * Test that get_view_url returns plain course URL in single-section mode.
     */
    public function test_view_url_singlesection(): void {
        $this->disable_multisection($this->course);
        $format = course_get_format($this->course);
        $url = $format->get_view_url(1, ['navigation' => true]);
        $this->assertStringContainsString('/course/view.php', $url->out(false));
        $this->assertStringContainsString('id=' . $this->course->id, $url->out(false));
        // Single-section should never have a section parameter.
        $this->assertStringNotContainsString('section=', $url->out(false));
    }

    /**
     * Test that navigation URL for section 0 is valid in multi-section mode.
     */
    public function test_view_url_section_zero_navigation(): void {
        $url = $this->format->get_view_url(0, ['navigation' => true]);
        $this->assertStringContainsString('section=0', $url->out(false));
    }

    // -----------------------------------------------------------------------
    // Course index support.
    // -----------------------------------------------------------------------

    /**
     * Test that course index is enabled only in multi-section mode.
     */
    public function test_uses_course_index(): void {
        $this->assertTrue($this->format->uses_course_index());

        $this->disable_multisection($this->course);
        $format = course_get_format($this->course);
        $this->assertFalse($format->uses_course_index());
    }

    // -----------------------------------------------------------------------
    // Section header stripping in output.
    // -----------------------------------------------------------------------

    /**
     * Test that section output strips headers in multi-section non-editing mode.
     */
    public function test_section_output_strips_headers_for_learners(): void {
        global $PAGE;

        // Create a student (non-editing by default).
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $this->setUser($student);

        $PAGE->set_course($this->course);

        // Set up the format to show section 1.
        $format = course_get_format($this->course);
        $format->set_sectionnum(1);

        // Get the section output class.
        $outputclass = $format->get_output_classname('content\\section');
        $modinfo = get_fast_modinfo($this->course);
        $sectioninfo = $modinfo->get_section_info(1);
        $widget = new $outputclass($format, $sectioninfo);

        $renderer = $PAGE->get_renderer('format_minimoodlewall');
        // Rendering may emit debugging notices (e.g. deprecated properties).
        $data = @$widget->export_for_template($renderer);
        $this->resetDebugging();

        // Headers should be stripped for learners in multi-section mode.
        $this->assertObjectNotHasProperty('header', $data);
        $this->assertObjectNotHasProperty('singleheader', $data);
    }

    // -----------------------------------------------------------------------
    // Completion scoping per section.
    // -----------------------------------------------------------------------

    /**
     * Test that completion counts are scoped per section in multi-section mode.
     *
     * Verifies that completion_info correctly reports different completion counts
     * per section when activities with manual completion are distributed across sections.
     */
    public function test_completion_scoped_per_section(): void {
        global $CFG, $DB;

        // Create a fresh course with completion enabled from the start.
        $CFG->enablecompletion = 1;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course([
            'format' => 'minimoodlewall',
            'numsections' => 2,
            'enablecompletion' => 1,
        ]);
        $this->enable_multisection($course);

        // Create activities in different sections.
        $page0a = $generator->create_module('page', [
            'course' => $course->id,
            'section' => 0,
            'name' => 'Page in s0',
        ]);
        $page0b = $generator->create_module('page', [
            'course' => $course->id,
            'section' => 0,
            'name' => 'Page2 in s0',
        ]);
        $page1 = $generator->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'Page in s1',
        ]);

        // Explicitly enable manual completion tracking on these modules.
        foreach ([$page0a->cmid, $page0b->cmid, $page1->cmid] as $cmid) {
            $DB->set_field('course_modules', 'completion', COMPLETION_TRACKING_MANUAL, ['id' => $cmid]);
        }
        rebuild_course_cache($course->id, true);
        $course = get_course($course->id);

        // Enrol a student and view as student.
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $modinfo = get_fast_modinfo($course);
        $completioninfo = new \completion_info($course);
        $this->assertNotEquals(COMPLETION_DISABLED, $completioninfo->is_enabled());

        // Count trackable activities per section.
        $sec0count = 0;
        $sec1count = 0;
        foreach ($modinfo->cms as $cm) {
            if (!$cm->uservisible || !$completioninfo->is_enabled($cm)) {
                continue;
            }
            if ((int)$cm->sectionnum === 0) {
                $sec0count++;
            } else if ((int)$cm->sectionnum === 1) {
                $sec1count++;
            }
        }

        $this->assertEquals(2, $sec0count, 'Section 0 should have 2 trackable activities');
        $this->assertEquals(1, $sec1count, 'Section 1 should have 1 trackable activity');

        // Verify the activities are indeed in the correct sections.
        $sec0names = array_map(
            fn($cm) => $cm->name,
            $modinfo->get_section_info(0)->get_sequence_cm_infos()
        );
        $sec1names = array_map(
            fn($cm) => $cm->name,
            $modinfo->get_section_info(1)->get_sequence_cm_infos()
        );
        $this->assertContains('Page in s0', $sec0names);
        $this->assertContains('Page2 in s0', $sec0names);
        $this->assertContains('Page in s1', $sec1names);
        $this->assertNotContains('Page in s1', $sec0names);
    }

    // -----------------------------------------------------------------------
    // Format option persistence.
    // -----------------------------------------------------------------------

    /**
     * Test that enablemultisection option is persisted in format options.
     */
    public function test_format_option_persisted(): void {
        $options = $this->format->get_format_options();
        $this->assertArrayHasKey('enablemultisection', $options);
        $this->assertEquals(1, $options['enablemultisection']);
    }

    /**
     * Test that a course with multiple sections reports the correct number of sections.
     */
    public function test_section_count(): void {
        $modinfo = get_fast_modinfo($this->course);
        $sections = $modinfo->get_section_info_all();
        // numsections=2 means sections 0, 1, 2.
        $nondelegated = array_filter($sections, fn($s) => !$s->is_delegated());
        $this->assertCount(3, $nondelegated);
    }

    // -----------------------------------------------------------------------
    // Data provider tests for get_view_url with various section numbers.
    // -----------------------------------------------------------------------

    /**
     * Data provider for URL section parameter tests.
     *
     * @return array
     */
    public static function view_url_section_provider(): array {
        return [
            'section 0' => ['sectionnum' => 0, 'expectedparam' => 'section=0'],
            'section 1' => ['sectionnum' => 1, 'expectedparam' => 'section=1'],
            'section 2' => ['sectionnum' => 2, 'expectedparam' => 'section=2'],
        ];
    }

    /**
     * Test get_view_url returns correct section parameter for different sections.
     *
     * @dataProvider view_url_section_provider
     * @param int $sectionnum Section number
     * @param string $expectedparam Expected URL parameter substring
     */
    public function test_view_url_section_parameter(int $sectionnum, string $expectedparam): void {
        $url = $this->format->get_view_url($sectionnum, ['navigation' => true]);
        $this->assertStringContainsString($expectedparam, $url->out(false));
        $this->assertStringContainsString('/course/view.php', $url->out(false));
    }

    // -----------------------------------------------------------------------
    // Section name tests.
    // -----------------------------------------------------------------------

    /**
     * Test default section names.
     */
    public function test_section_names(): void {
        $section0 = $this->format->get_section(0);
        $section1 = $this->format->get_section(1);

        // Section 0 has its own translation key.
        $name0 = $this->format->get_section_name($section0);
        $this->assertNotEmpty($name0);

        // Section 1 gets a numbered name.
        $name1 = $this->format->get_section_name($section1);
        $this->assertNotEmpty($name1);

        // Names should be different.
        $this->assertNotEquals($name0, $name1);
    }

    /**
     * Test custom section name is used when set.
     */
    public function test_custom_section_name(): void {
        global $DB;

        $modinfo = get_fast_modinfo($this->course);
        $section1 = $modinfo->get_section_info(1);
        $DB->set_field('course_sections', 'name', 'My Custom Section', ['id' => $section1->id]);

        // Must rebuild modinfo since we changed DB directly.
        rebuild_course_cache($this->course->id, true);
        $format = course_get_format($this->course);
        $section1 = $format->get_section(1);
        $name = $format->get_section_name($section1);
        $this->assertEquals('My Custom Section', $name);
    }

    // -----------------------------------------------------------------------
    // Format capabilities.
    // -----------------------------------------------------------------------

    /**
     * Test basic format capabilities.
     */
    public function test_format_capabilities(): void {
        $this->assertTrue($this->format->uses_sections());
        $this->assertFalse($this->format->uses_indentation());

        $ajaxsupport = $this->format->supports_ajax();
        $this->assertTrue($ajaxsupport->capable);

        $this->assertTrue($this->format->supports_components());
    }

    // -----------------------------------------------------------------------
    // Helpers.
    // -----------------------------------------------------------------------

    /**
     * Enable multi-section mode for a course.
     *
     * @param \stdClass $course
     */
    private function enable_multisection(\stdClass $course): void {
        $format = course_get_format($course);
        $format->update_course_format_options(['enablemultisection' => 1]);
    }

    /**
     * Disable multi-section mode for a course.
     *
     * @param \stdClass $course
     */
    private function disable_multisection(\stdClass $course): void {
        $format = course_get_format($course);
        $format->update_course_format_options(['enablemultisection' => 0]);
    }
}
