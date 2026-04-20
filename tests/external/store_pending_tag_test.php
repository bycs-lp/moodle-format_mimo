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
 * Unit tests for the store_pending_tag external service.
 *
 * @package    format_mimo
 * @copyright  2026 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo\external;

use format_mimo\tag_manager;

/**
 * Tests for \format_mimo\external\store_pending_tag.
 *
 * @package    format_mimo
 * @copyright  2026 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_mimo\external\store_pending_tag
 */
final class store_pending_tag_test extends \advanced_testcase {
    /**
     * Clean up session state after each test.
     */
    protected function tearDown(): void {
        global $SESSION;
        unset($SESSION->format_mimo_pending_tag);
        parent::tearDown();
    }

    /**
     * Admin stores a pending tag id in the session.
     */
    public function test_store_pending_tag_as_admin(): void {
        global $SESSION;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['format' => 'mimo']);
        $tagid = tag_manager::create_tag('Pending', 'p.svg', 'p-small.svg', 'page');

        $result = store_pending_tag::execute($tagid, $course->id);

        $this->assertTrue($result['success']);
        $this->assertEquals($tagid, $SESSION->format_mimo_pending_tag);
    }

    /**
     * A student without manageactivities cannot store a pending tag.
     */
    public function test_store_pending_tag_requires_capability(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'mimo']);
        $tagid = tag_manager::create_tag('Pending', 'p.svg', 'p-small.svg', 'page');

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        store_pending_tag::execute($tagid, $course->id);
    }
}
