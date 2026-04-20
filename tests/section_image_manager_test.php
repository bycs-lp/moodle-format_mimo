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
 * Unit tests for section_image_manager.
 *
 * @package    format_mimo
 * @copyright  2026 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo;

/**
 * Tests for \format_mimo\section_image_manager.
 *
 * @package    format_mimo
 * @copyright  2026 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_mimo\section_image_manager
 */
final class section_image_manager_test extends \advanced_testcase {
    /** @var \stdClass Course fixture */
    private \stdClass $course;

    /**
     * Common setup.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course([
            'format' => 'mimo',
            'numsections' => 3,
        ]);
    }

    /**
     * Resolve a section_id from a section number on the fixture course.
     *
     * @param int $section Section number (0..n)
     * @return int course_sections.id
     */
    private function section_id(int $section): int {
        global $DB;
        return (int) $DB->get_field('course_sections', 'id', [
            'course' => $this->course->id,
            'section' => $section,
        ], MUST_EXIST);
    }

    /**
     * Place a fake image file into the section image area.
     *
     * @param int $sectionid course_sections.id
     * @param string $filename Filename stored under the '/' path.
     */
    private function create_section_image(int $sectionid, string $filename = 'section.png'): void {
        $context = \core\context\course::instance($this->course->id);
        get_file_storage()->create_file_from_string(
            [
                'contextid' => $context->id,
                'component' => section_image_manager::COMPONENT,
                'filearea'  => section_image_manager::FILEAREA,
                'itemid'    => $sectionid,
                'filepath'  => '/',
                'filename'  => $filename,
            ],
            'fake-bytes-' . $sectionid
        );
    }

    /**
     * get_filemanager_options returns the documented constraints.
     */
    public function test_get_filemanager_options(): void {
        $options = section_image_manager::get_filemanager_options();

        $this->assertIsArray($options);
        $this->assertSame(1, $options['maxfiles']);
        $this->assertArrayHasKey('accepted_types', $options);
    }

    /**
     * has_image / get_image_url return false/null for an untouched section.
     */
    public function test_has_image_and_url_when_empty(): void {
        $sectionid = $this->section_id(1);

        $this->assertFalse(section_image_manager::has_image($this->course->id, $sectionid));
        $this->assertNull(section_image_manager::get_image_url($this->course->id, $sectionid));
    }

    /**
     * has_image returns true and get_image_url returns a pluginfile URL after a file is stored.
     */
    public function test_has_image_and_url_when_present(): void {
        $sectionid = $this->section_id(1);
        $this->create_section_image($sectionid);

        $this->assertTrue(section_image_manager::has_image($this->course->id, $sectionid));

        $url = section_image_manager::get_image_url($this->course->id, $sectionid);
        $this->assertInstanceOf(\moodle_url::class, $url);
        $this->assertStringContainsString(section_image_manager::FILEAREA, $url->out(false));
    }

    /**
     * get_image_urls_for_course returns a map keyed by itemid (sectionid), skipping sections without files.
     */
    public function test_get_image_urls_for_course(): void {
        $s1 = $this->section_id(1);
        $s2 = $this->section_id(2);
        // Section 3 intentionally left without an image.
        $this->create_section_image($s1);
        $this->create_section_image($s2);

        $urls = section_image_manager::get_image_urls_for_course($this->course->id);

        $this->assertArrayHasKey($s1, $urls);
        $this->assertArrayHasKey($s2, $urls);
        $this->assertArrayNotHasKey($this->section_id(3), $urls);
        $this->assertInstanceOf(\moodle_url::class, $urls[$s1]);
    }

    /**
     * get_image_urls_for_course returns an empty array for a course with no section images.
     */
    public function test_get_image_urls_for_course_empty(): void {
        $urls = section_image_manager::get_image_urls_for_course($this->course->id);
        $this->assertSame([], $urls);
    }

    /**
     * delete_image removes only the targeted section's image.
     */
    public function test_delete_image(): void {
        $s1 = $this->section_id(1);
        $s2 = $this->section_id(2);
        $this->create_section_image($s1);
        $this->create_section_image($s2);

        section_image_manager::delete_image($this->course->id, $s1);

        $this->assertFalse(section_image_manager::has_image($this->course->id, $s1));
        $this->assertTrue(section_image_manager::has_image($this->course->id, $s2));
    }

    /**
     * delete_all_for_course wipes images across every section.
     */
    public function test_delete_all_for_course(): void {
        $s1 = $this->section_id(1);
        $s2 = $this->section_id(2);
        $this->create_section_image($s1);
        $this->create_section_image($s2);

        section_image_manager::delete_all_for_course($this->course->id);

        $this->assertFalse(section_image_manager::has_image($this->course->id, $s1));
        $this->assertFalse(section_image_manager::has_image($this->course->id, $s2));
    }

    /**
     * prepare_draft returns a usable draft item id populated with the existing file.
     */
    public function test_prepare_draft(): void {
        global $USER;

        $sectionid = $this->section_id(1);
        $this->create_section_image($sectionid, 'prep.png');

        $draftitemid = section_image_manager::prepare_draft($this->course->id, $sectionid);
        $this->assertIsInt($draftitemid);
        $this->assertGreaterThan(0, $draftitemid);

        $usercontext = \core\context\user::instance($USER->id);
        $draftfiles = get_file_storage()->get_area_files(
            $usercontext->id,
            'user',
            'draft',
            $draftitemid,
            '',
            false
        );
        $names = array_map(fn($f) => $f->get_filename(), $draftfiles);
        $this->assertContains('prep.png', $names);
    }

    /**
     * save_image moves files from a draft area into the section area.
     */
    public function test_save_image_from_draft(): void {
        global $USER;

        $sectionid = $this->section_id(1);

        // Seed a draft file for the current admin user.
        $draftitemid = file_get_unused_draft_itemid();
        $usercontext = \core\context\user::instance($USER->id);
        get_file_storage()->create_file_from_string(
            [
                'contextid' => $usercontext->id,
                'component' => 'user',
                'filearea'  => 'draft',
                'itemid'    => $draftitemid,
                'filepath'  => '/',
                'filename'  => 'new.png',
            ],
            'draft-bytes'
        );

        section_image_manager::save_image($this->course->id, $sectionid, $draftitemid);

        $this->assertTrue(section_image_manager::has_image($this->course->id, $sectionid));
    }
}
