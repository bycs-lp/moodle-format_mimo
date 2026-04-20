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
 * Unit tests for the assign_tag external service.
 *
 * @package    format_mimo
 * @copyright  2026 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo\external;

use format_mimo\tag_manager;

/**
 * Tests for \format_mimo\external\assign_tag.
 *
 * @package    format_mimo
 * @copyright  2026 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_mimo\external\assign_tag
 */
final class assign_tag_test extends \advanced_testcase {
    /**
     * Admin can assign a tag to a course module.
     */
    public function test_assign_tag_as_admin(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['format' => 'mimo']);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $tagid = tag_manager::create_tag('External', 'e.svg', 'e-small.svg', 'page');

        $result = assign_tag::execute($page->cmid, $tagid);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);

        $assigned = tag_manager::get_cm_tag($page->cmid);
        $this->assertNotFalse($assigned);
        $this->assertEquals($tagid, $assigned->id);
    }

    /**
     * A user without moodle/course:manageactivities cannot assign tags.
     */
    public function test_assign_tag_requires_capability(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'mimo']);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $tagid = tag_manager::create_tag('External', 'e.svg', 'e-small.svg', 'page');

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        assign_tag::execute($page->cmid, $tagid);
    }

    /**
     * A bogus cmid raises an exception.
     */
    public function test_assign_tag_invalid_cmid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $tagid = tag_manager::create_tag('External', 'e.svg', 'e-small.svg', 'page');

        $this->expectException(\dml_exception::class);
        assign_tag::execute(999999, $tagid);
    }
}
