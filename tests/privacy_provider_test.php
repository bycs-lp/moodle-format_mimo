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

use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use format_mimo\privacy\provider;

/**
 * Privacy provider tests for format_mimo.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_mimo\privacy\provider
 */
final class privacy_provider_test extends provider_testcase {

    public function test_get_metadata(): void {
        $collection = new collection('format_mimo');
        $collection = provider::get_metadata($collection);

        $items = $collection->get_collection();
        $this->assertCount(2, $items);
        $this->assertInstanceOf(\core_privacy\local\metadata\types\user_preference::class, $items[0]);
        $this->assertInstanceOf(\core_privacy\local\metadata\types\user_preference::class, $items[1]);
    }

    public function test_export_user_preferences_no_data(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        provider::export_user_preferences($user->id);

        $writer = writer::with_context(\context_system::instance());
        $prefs = $writer->get_user_preferences('format_mimo');

        $this->assertEmpty((array) $prefs);
    }

    public function test_export_user_preferences_with_data(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $course = $this->getDataGenerator()->create_course(['format' => 'mimo']);

        set_user_preference('format_mimo_lastsection_' . $course->id, 3, $user);

        provider::export_user_preferences($user->id);

        $writer = writer::with_context(\context_system::instance());
        $prefs = $writer->get_user_preferences('format_mimo');

        $prefkey = 'format_mimo_lastsection_' . $course->id;
        $this->assertObjectHasProperty($prefkey, $prefs);
        $this->assertEquals(3, $prefs->$prefkey->value);
        $this->assertEquals(
            get_string('privacy:metadata:preference:lastsection', 'format_mimo', $course->id),
            $prefs->$prefkey->description
        );
    }

    public function test_export_user_preferences_multiple_courses(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $course1 = $this->getDataGenerator()->create_course(['format' => 'mimo']);
        $course2 = $this->getDataGenerator()->create_course(['format' => 'mimo']);

        set_user_preference('format_mimo_lastsection_' . $course1->id, 2, $user);
        set_user_preference('format_mimo_lastsection_' . $course2->id, 5, $user);

        provider::export_user_preferences($user->id);

        $writer = writer::with_context(\context_system::instance());
        $prefs = $writer->get_user_preferences('format_mimo');

        $key1 = 'format_mimo_lastsection_' . $course1->id;
        $key2 = 'format_mimo_lastsection_' . $course2->id;

        $this->assertObjectHasProperty($key1, $prefs);
        $this->assertObjectHasProperty($key2, $prefs);
        $this->assertEquals(2, $prefs->$key1->value);
        $this->assertEquals(5, $prefs->$key2->value);
    }
}
