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
 * Unit tests for the format_mimo course format class and lib.php callbacks.
 *
 * @package    format_mimo
 * @copyright  2026 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo;

/**
 * Covers small helpers, form option builders, edit callbacks and cleanup routines.
 *
 * @package    format_mimo
 * @copyright  2026 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_mimo::uses_sections
 * @covers     \format_mimo::uses_indentation
 * @covers     \format_mimo::supports_components
 * @covers     \format_mimo::supports_ajax
 * @covers     \format_mimo::get_default_blocks
 * @covers     \format_mimo::section_format_options
 * @covers     \format_mimo::course_format_options
 * @covers     \format_mimo::get_default_section_name
 * @covers     \format_mimo::get_section_name
 * @covers     \format_mimo::update_course_format_options
 * @covers     \format_mimo::is_section_visible
 * @covers     \format_mimo::can_delete_section
 * @covers     \format_mimo::allow_stealth_module_visibility
 * @covers     \format_mimo::get_format_options
 * @covers     \format_mimo::get_remembered_section
 * @covers     \format_mimo::delete_format_data
 * @covers     ::format_mimo_coursemodule_edit_post_actions
 * @covers     ::format_mimo_pluginfile
 */
final class format_mimo_test extends \advanced_testcase {
    /**
     * Create a mimo course and return its format instance.
     *
     * @param array $extra Additional course creation params.
     * @return array{0: \stdClass, 1: \format_mimo}
     */
    private function create_course_and_format(array $extra = []): array {
        $course = $this->getDataGenerator()->create_course(
            array_merge(['format' => 'mimo', 'numsections' => 3], $extra)
        );
        $format = course_get_format($course);
        return [$course, $format];
    }

    /**
     * Flag-returning helpers should match their documented contracts.
     */
    public function test_simple_flag_helpers(): void {
        $this->resetAfterTest();
        [, $format] = $this->create_course_and_format();

        $this->assertTrue($format->uses_sections());
        $this->assertFalse($format->uses_indentation());
        $this->assertTrue($format->supports_components());

        $ajax = $format->supports_ajax();
        $this->assertTrue($ajax->capable);

        $blocks = $format->get_default_blocks();
        $this->assertArrayHasKey(BLOCK_POS_LEFT, $blocks);
        $this->assertArrayHasKey(BLOCK_POS_RIGHT, $blocks);
        $this->assertSame([], $blocks[BLOCK_POS_LEFT]);
        $this->assertSame([], $blocks[BLOCK_POS_RIGHT]);
    }

    /**
     * section_format_options defaults the image fit to 'contain'.
     */
    public function test_section_format_options(): void {
        $this->resetAfterTest();
        [, $format] = $this->create_course_and_format();

        $options = $format->section_format_options();
        $this->assertArrayHasKey('sectionimagefit', $options);
        $this->assertSame('contain', $options['sectionimagefit']['default']);
        $this->assertSame(PARAM_ALPHA, $options['sectionimagefit']['type']);
    }

    /**
     * course_format_options returns only type+default without the form flag,
     * and full form metadata when called with $forupdate=true.
     */
    public function test_course_format_options_shapes(): void {
        $this->resetAfterTest();
        [, $format] = $this->create_course_and_format();

        $base = $format->course_format_options(false);
        foreach (['enablemultisection', 'enablefiltering', 'distractionfree', 'backgrounddesign', 'activityprofile'] as $key) {
            $this->assertArrayHasKey($key, $base);
            $this->assertArrayHasKey('default', $base[$key]);
            $this->assertArrayNotHasKey('label', $base[$key]);
        }

        $forupdate = $format->course_format_options(true);
        $this->assertArrayHasKey('label', $forupdate['enablemultisection']);
        $this->assertSame('advcheckbox', $forupdate['enablemultisection']['element_type']);
        $this->assertSame('select', $forupdate['backgrounddesign']['element_type']);
        $this->assertSame('select', $forupdate['activityprofile']['element_type']);
    }

    /**
     * get_section_name falls back to the format-provided default when the section has no custom name.
     */
    public function test_get_section_name_default_and_custom(): void {
        global $DB;

        $this->resetAfterTest();
        [$course, $format] = $this->create_course_and_format();

        // Default: section 1 in single-section mode renders as section0name.
        $modinfo = get_fast_modinfo($course);
        $section1 = $modinfo->get_section_info(1);
        $default = $format->get_default_section_name($section1);
        $this->assertSame(get_string('section0name', 'format_mimo'), $default);

        // Custom name is used verbatim (via format_string).
        $DB->set_field('course_sections', 'name', 'My wall', ['course' => $course->id, 'section' => 1]);
        rebuild_course_cache($course->id, true);
        $format = course_get_format($course);
        $this->assertSame('My wall', $format->get_section_name(1));

        // Multi-section mode uses the numbered default for sections > 1.
        $format->update_course_format_options(['enablemultisection' => 1]);
        $format = course_get_format($course);
        $section2 = get_fast_modinfo($course)->get_section_info(2);
        $numbered = $format->get_default_section_name($section2);
        $this->assertStringContainsString('2', $numbered);
    }

    /**
     * Section 0 should always be hidden; section 1 visible in single-section;
     * all non-zero visible in multi-section.
     */
    public function test_is_section_visible_rules(): void {
        $this->resetAfterTest();
        [$course, $format] = $this->create_course_and_format();

        $modinfo = get_fast_modinfo($course);
        $s0 = $modinfo->get_section_info(0);
        $s1 = $modinfo->get_section_info(1);
        $s2 = $modinfo->get_section_info(2);

        $this->assertFalse($format->is_section_visible($s0));
        $this->assertTrue($format->is_section_visible($s1));
        $this->assertFalse($format->is_section_visible($s2));

        $format->update_course_format_options(['enablemultisection' => 1]);
        $format = course_get_format($course);
        $modinfo = get_fast_modinfo($course);
        $this->assertFalse($format->is_section_visible($modinfo->get_section_info(0)));
        $this->assertTrue($format->is_section_visible($modinfo->get_section_info(1)));
        $this->assertTrue($format->is_section_visible($modinfo->get_section_info(2)));
    }

    /**
     * can_delete_section gates on multi-section mode and on section > 0.
     */
    public function test_can_delete_section(): void {
        $this->resetAfterTest();
        [$course, $format] = $this->create_course_and_format();

        $this->assertFalse($format->can_delete_section(1));
        $this->assertFalse($format->can_delete_section(0));

        $format->update_course_format_options(['enablemultisection' => 1]);
        $format = course_get_format($course);
        $this->assertTrue($format->can_delete_section(1));
        $this->assertFalse($format->can_delete_section(0));
    }

    /**
     * allow_stealth_module_visibility is true for section 0 or when section is visible.
     */
    public function test_allow_stealth_module_visibility(): void {
        $this->resetAfterTest();
        [, $format] = $this->create_course_and_format();

        $section0 = (object) ['section' => 0, 'visible' => 1];
        $section1hidden = (object) ['section' => 1, 'visible' => 0];
        $section1visible = (object) ['section' => 1, 'visible' => 1];

        $this->assertTrue($format->allow_stealth_module_visibility(null, $section0));
        $this->assertFalse($format->allow_stealth_module_visibility(null, $section1hidden));
        $this->assertTrue($format->allow_stealth_module_visibility(null, $section1visible));
    }

    /**
     * update_course_format_options persists changed options.
     */
    public function test_update_course_format_options_persists(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $format] = $this->create_course_and_format();

        profile_manager::create_profile('alternate', 'Alternate profile');

        $before = (array) $format->get_format_options();
        $this->assertSame(0, (int) ($before['enablemultisection'] ?? 0));
        $this->assertSame('explore', $before['activityprofile'] ?? '');

        $changed = $format->update_course_format_options(
            ['enablemultisection' => 1, 'activityprofile' => 'alternate'],
            $course
        );
        $this->assertTrue($changed);

        $after = (array) course_get_format($course)->get_format_options();
        $this->assertSame(1, (int) $after['enablemultisection']);
        $this->assertSame('alternate', $after['activityprofile']);
    }

    /**
     * get_remembered_section returns a valid stored section and clears stale preferences.
     */
    public function test_get_remembered_section(): void {
        $this->resetAfterTest();
        [$course, $format] = $this->create_course_and_format();
        $format->update_course_format_options(['enablemultisection' => 1]);
        $format = course_get_format($course);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        // No preference → null.
        $this->assertNull($format->get_remembered_section());

        // Valid preference → returned as-is.
        set_user_preference('format_mimo_lastsection_' . $course->id, 2);
        $this->assertSame(2, $format->get_remembered_section());

        // Stale preference pointing at a non-existent section should be cleared.
        set_user_preference('format_mimo_lastsection_' . $course->id, 99);
        $this->assertNull($format->get_remembered_section());
        $this->assertNull(get_user_preferences('format_mimo_lastsection_' . $course->id));
    }

    /**
     * delete_format_data removes orphan cmtag/cmdone rows and remembered-section prefs.
     */
    public function test_delete_format_data_cleans_orphans(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $format] = $this->create_course_and_format();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $tagid = tag_manager::create_tag('Cleanup', 'c.svg', 'c-small.svg', 'page');
        tag_manager::assign_tag_to_cm($page->cmid, $tagid);

        // User remembered-section preference should be removed as well.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        set_user_preference('format_mimo_lastsection_' . $course->id, 1);
        $this->assertEquals('1', get_user_preferences('format_mimo_lastsection_' . $course->id));

        $this->setAdminUser();

        // Simulate core's deletion order: remove the course_modules first so cmtag becomes orphaned.
        $DB->delete_records('course_modules', ['course' => $course->id]);

        $this->assertTrue($DB->record_exists('format_mimo_cmtags', ['cmid' => $page->cmid]));
        $format->delete_format_data();
        $this->assertFalse($DB->record_exists('format_mimo_cmtags', ['cmid' => $page->cmid]));

        $this->setUser($user);
        $this->assertNull(get_user_preferences('format_mimo_lastsection_' . $course->id));
    }

    /**
     * format_mimo_coursemodule_edit_post_actions assigns or removes the cm tag based on form input.
     */
    public function test_coursemodule_edit_post_actions(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['format' => 'mimo']);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $tagid = tag_manager::create_tag('Edit', 'e.svg', 'e-small.svg', 'page');

        // Non-mimo courses are a no-op.
        $topics = $this->getDataGenerator()->create_course(['format' => 'topics']);
        $data = (object) ['coursemodule' => $page->cmid, 'mimo_cmtag' => $tagid];
        $returned = format_mimo_coursemodule_edit_post_actions($data, $topics);
        $this->assertSame($data, $returned);
        $this->assertFalse(tag_manager::get_cm_tag($page->cmid));

        // Mimo course: assigning a tag.
        $data = (object) ['coursemodule' => $page->cmid, 'mimo_cmtag' => $tagid];
        format_mimo_coursemodule_edit_post_actions($data, $course);
        $assigned = tag_manager::get_cm_tag($page->cmid);
        $this->assertNotFalse($assigned);
        $this->assertEquals($tagid, $assigned->id);

        // Mimo course: mimo_cmtag = 0 removes the assignment.
        $data = (object) ['coursemodule' => $page->cmid, 'mimo_cmtag' => 0];
        format_mimo_coursemodule_edit_post_actions($data, $course);
        $this->assertFalse(tag_manager::get_cm_tag($page->cmid));

        // Missing mimo_cmtag key is a no-op.
        tag_manager::assign_tag_to_cm($page->cmid, $tagid);
        $data = (object) ['coursemodule' => $page->cmid];
        format_mimo_coursemodule_edit_post_actions($data, $course);
        $assigned = tag_manager::get_cm_tag($page->cmid);
        $this->assertEquals($tagid, $assigned->id);
    }

    /**
     * format_mimo_pluginfile returns false for disallowed areas and wrong contexts.
     */
    public function test_pluginfile_rejects_invalid_requests(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['format' => 'mimo']);
        $coursectx = \core\context\course::instance($course->id);
        $syscontext = \core\context\system::instance();

        // Unknown file area → false.
        $result = format_mimo_pluginfile(
            $course,
            null,
            $syscontext,
            'notarealarea',
            [1, 'x.png'],
            false
        );
        $this->assertFalse($result);

        // Tag cardimage requested against course context (wrong level) → false.
        $result = format_mimo_pluginfile(
            $course,
            null,
            $coursectx,
            tag_manager::FILEAREA_CARDIMAGE,
            [1, 'x.png'],
            false
        );
        $this->assertFalse($result);

        // Section image requested against system context (wrong level) → false.
        $result = format_mimo_pluginfile(
            $course,
            null,
            $syscontext,
            section_image_manager::FILEAREA,
            [1, 'x.png'],
            false
        );
        $this->assertFalse($result);

        // Missing args → false.
        $result = format_mimo_pluginfile(
            $course,
            null,
            $syscontext,
            tag_manager::FILEAREA_CARDIMAGE,
            [],
            false
        );
        $this->assertFalse($result);

        // Nonexistent file → false.
        $result = format_mimo_pluginfile(
            $course,
            null,
            $syscontext,
            tag_manager::FILEAREA_CARDIMAGE,
            [999999, 'missing.png'],
            false
        );
        $this->assertFalse($result);
    }
}
