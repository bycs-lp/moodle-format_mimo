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
 * Unit tests for the get_tags external service.
 *
 * @package    format_mimo
 * @copyright  2026 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo\external;

use core_external\external_api;
use format_mimo\profile_manager;
use format_mimo\tag_manager;

/**
 * Tests for \format_mimo\external\get_tags.
 *
 * @package    format_mimo
 * @copyright  2026 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_mimo\external\get_tags
 */
final class get_tags_test extends \advanced_testcase {
    /** @var tag_manager Tag manager instance. */
    private tag_manager $tagmanager;

    /** @var profile_manager Profile manager instance. */
    private profile_manager $profilemanager;

    /**
     * Set up before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->tagmanager = \core\di::get(tag_manager::class);
        $this->profilemanager = \core\di::get(profile_manager::class);
    }

    /**
     * Returned list should match the resolved tags for the course and conform to the
     * declared return structure.
     */
    public function test_get_tags_returns_course_tags(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Clear any preseeded tags for a deterministic assertion. The profile
        // overrides must go too: newly created tags may reuse the ids of the
        // deleted preseeded tags (sequence behaviour after a delete differs
        // between DB engines), and orphaned format_mimo_profile_tags rows would
        // then apply stale name overrides to them.
        $DB->delete_records('format_mimo_tags');
        $DB->delete_records('format_mimo_profile_tags');
        $this->profilemanager->clear_request_caches();

        $this->tagmanager->create_tag('Alpha', 'a.svg', 'a-small.svg', 'page');
        $this->tagmanager->create_tag('Bravo', 'b.svg', 'b-small.svg', 'quiz');

        // Enable both tags in an explicit set used by the course.
        $profileid = $this->profilemanager->create_profile('wstagset', 'WS tag set');
        foreach ($this->tagmanager->get_all_tags() as $tag) {
            $this->profilemanager->materialize_profile_tag((int) $tag->id, $profileid, [], true);
        }

        $course = $this->getDataGenerator()->create_course([
            'format' => 'mimo',
            'activityprofile' => 'wstagset',
        ]);

        $result = get_tags::execute($course->id);

        // Validate against the declared return shape.
        $result = external_api::clean_returnvalue(get_tags::execute_returns(), $result);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);

        $names = array_column($result, 'name');
        $this->assertContains('Alpha', $names);
        $this->assertContains('Bravo', $names);

        // Each entry should expose the documented keys.
        foreach ($result as $entry) {
            $this->assertArrayHasKey('id', $entry);
            $this->assertArrayHasKey('cardimage', $entry);
            $this->assertArrayHasKey('filterimage', $entry);
            $this->assertArrayHasKey('activitytype1', $entry);
            $this->assertArrayHasKey('activitytype2', $entry);
            $this->assertArrayHasKey('activitytype3', $entry);
            $this->assertArrayHasKey('sortorder', $entry);
        }
    }

    /**
     * An enrolled student can read the tags (no write capability needed).
     */
    public function test_get_tags_accessible_to_enrolled_user(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->tagmanager->create_tag('Readable', 'r.svg', 'r-small.svg', 'page');

        $course = $this->getDataGenerator()->create_course(['format' => 'mimo']);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $result = get_tags::execute($course->id);
        $result = external_api::clean_returnvalue(get_tags::execute_returns(), $result);

        $this->assertIsArray($result);
    }
}
