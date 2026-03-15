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
 * Unit tests for description_tag_manager.
 *
 * @package    format_mimo
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo;

defined('MOODLE_INTERNAL') || die();

/**
 * Test cases for description tag manager.
 *
 * @package    format_mimo
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_mimo\description_tag_manager
 */
final class description_tag_manager_test extends \advanced_testcase {
    /**
     * Test creating a description tag.
     */
    public function test_create_tag(): void {
        global $DB;
        $this->resetAfterTest(true);

        $tagid = description_tag_manager::create_tag('Test Tag', '#FF5733');

        $this->assertNotEmpty($tagid);
        $this->assertTrue($DB->record_exists('format_mimo_desc_tags', ['id' => $tagid]));

        $tag = $DB->get_record('format_mimo_desc_tags', ['id' => $tagid]);
        $this->assertEquals('Test Tag', $tag->name);
        $this->assertEquals('#FF5733', $tag->color);
    }

    /**
     * Test getting all tags.
     */
    public function test_get_all_tags(): void {
        global $DB;
        $this->resetAfterTest(true);

        // Clear default description tags seeded by install.
        $DB->delete_records('format_mimo_desc_tags');

        // Create some tags.
        description_tag_manager::create_tag('Tag 1', '#FF5733');
        description_tag_manager::create_tag('Tag 2', '#33FF57');
        description_tag_manager::create_tag('Tag 3', '#3357FF');

        $tags = description_tag_manager::get_all_tags();

        $this->assertCount(3, $tags);
        $tagnames = array_column(array_values($tags), 'name');
        $this->assertContains('Tag 1', $tagnames);
        $this->assertContains('Tag 2', $tagnames);
        $this->assertContains('Tag 3', $tagnames);
    }

    /**
     * Test getting a specific tag.
     */
    public function test_get_tag(): void {
        $this->resetAfterTest(true);

        $tagid = description_tag_manager::create_tag('Test Tag', '#FF5733');

        $tag = description_tag_manager::get_tag($tagid);

        $this->assertNotEmpty($tag);
        $this->assertEquals('Test Tag', $tag->name);
        $this->assertEquals('#FF5733', $tag->color);
    }

    /**
     * Test updating a tag.
     */
    public function test_update_tag(): void {
        global $DB;
        $this->resetAfterTest(true);

        $tagid = description_tag_manager::create_tag('Original Name', '#FF5733');

        $result = description_tag_manager::update_tag($tagid, 'Updated Name', '#33FF57');

        $this->assertTrue($result);

        $tag = $DB->get_record('format_mimo_desc_tags', ['id' => $tagid]);
        $this->assertEquals('Updated Name', $tag->name);
        $this->assertEquals('#33FF57', $tag->color);
    }

    /**
     * Test deleting a tag.
     */
    public function test_delete_tag(): void {
        global $DB;
        $this->resetAfterTest(true);

        $tagid = description_tag_manager::create_tag('Test Tag', '#FF5733');
        $this->assertTrue($DB->record_exists('format_mimo_desc_tags', ['id' => $tagid]));

        $result = description_tag_manager::delete_tag($tagid);

        $this->assertTrue($result);
        $this->assertFalse($DB->record_exists('format_mimo_desc_tags', ['id' => $tagid]));
    }

    /**
     * Test deleting a tag that is in use.
     */
    public function test_delete_tag_removes_references(): void {
        global $DB;
        $this->resetAfterTest(true);

        // Create tag and activity description.
        $tagid = description_tag_manager::create_tag('Test Tag', '#FF5733');
        activity_description_manager::save_description('assign', 'Test description', $tagid);

        // Verify tag is in use.
        $count = description_tag_manager::count_descriptions_with_tag($tagid);
        $this->assertEquals(1, $count);

        // Delete tag.
        description_tag_manager::delete_tag($tagid);

        // Verify tag reference was removed from activity description.
        $desc = $DB->get_record('format_mimo_actdesc', ['activitytype' => 'assign']);
        $this->assertNotEmpty($desc);
        $this->assertNull($desc->desctagid);
    }

    /**
     * Test counting descriptions with tag.
     */
    public function test_count_descriptions_with_tag(): void {
        $this->resetAfterTest(true);

        $tagid = description_tag_manager::create_tag('Test Tag', '#FF5733');

        // Initially no descriptions use this tag.
        $count = description_tag_manager::count_descriptions_with_tag($tagid);
        $this->assertEquals(0, $count);

        // Add some descriptions with this tag.
        activity_description_manager::save_description('assign', 'Assignment description', $tagid);
        activity_description_manager::save_description('quiz', 'Quiz description', $tagid);

        $count = description_tag_manager::count_descriptions_with_tag($tagid);
        $this->assertEquals(2, $count);
    }

    /**
     * Test getting tags for select options.
     */
    public function test_get_tags_for_select(): void {
        $this->resetAfterTest(true);

        description_tag_manager::create_tag('Tag A', '#FF5733');
        description_tag_manager::create_tag('Tag B', '#33FF57');

        $options = description_tag_manager::get_tags_for_select();

        $this->assertArrayHasKey(0, $options);
        $this->assertEquals('No tag', $options[0]);
        $this->assertContains('Tag A', $options);
        $this->assertContains('Tag B', $options);
    }

    /**
     * Test color validation.
     */
    public function test_is_valid_color(): void {
        $this->assertTrue(description_tag_manager::is_valid_color('#FF5733'));
        $this->assertTrue(description_tag_manager::is_valid_color('#123456'));
        $this->assertTrue(description_tag_manager::is_valid_color('#abcdef'));
        $this->assertTrue(description_tag_manager::is_valid_color('#ABCDEF'));

        $this->assertFalse(description_tag_manager::is_valid_color('#FF573'));  // Too short.
        $this->assertFalse(description_tag_manager::is_valid_color('#FF57333')); // Too long.
        $this->assertFalse(description_tag_manager::is_valid_color('FF5733'));   // Missing #.
        $this->assertFalse(description_tag_manager::is_valid_color('#GGGGGG'));  // Invalid chars.
        $this->assertFalse(description_tag_manager::is_valid_color(''));         // Empty.
    }

    /**
     * Test activity description with tag.
     */
    public function test_save_description_with_tag(): void {
        global $DB;
        $this->resetAfterTest(true);

        $tagid = description_tag_manager::create_tag('Assessment', '#FF5733');

        $result = activity_description_manager::save_description('quiz', 'This is a quiz', $tagid);

        $this->assertTrue($result);

        $desc = $DB->get_record('format_mimo_actdesc', ['activitytype' => 'quiz']);
        $this->assertNotEmpty($desc);
        $this->assertEquals('This is a quiz', $desc->description);
        $this->assertEquals($tagid, $desc->desctagid);
    }

    /**
     * Test updating description with different tag.
     */
    public function test_update_description_tag(): void {
        global $DB;
        $this->resetAfterTest(true);

        $tag1id = description_tag_manager::create_tag('Tag 1', '#FF5733');
        $tag2id = description_tag_manager::create_tag('Tag 2', '#33FF57');

        // Save with tag 1.
        activity_description_manager::save_description('assign', 'Assignment description', $tag1id);

        $desc = $DB->get_record('format_mimo_actdesc', ['activitytype' => 'assign']);
        $this->assertEquals($tag1id, $desc->desctagid);

        // Update to tag 2.
        activity_description_manager::save_description('assign', 'Assignment description', $tag2id);

        $desc = $DB->get_record('format_mimo_actdesc', ['activitytype' => 'assign']);
        $this->assertEquals($tag2id, $desc->desctagid);
    }

    /**
     * Test removing tag from description.
     */
    public function test_remove_tag_from_description(): void {
        global $DB;
        $this->resetAfterTest(true);

        $tagid = description_tag_manager::create_tag('Test Tag', '#FF5733');

        // Save with tag.
        activity_description_manager::save_description('forum', 'Forum description', $tagid);

        $desc = $DB->get_record('format_mimo_actdesc', ['activitytype' => 'forum']);
        $this->assertEquals($tagid, $desc->desctagid);

        // Update with null tag.
        activity_description_manager::save_description('forum', 'Forum description', null);

        $desc = $DB->get_record('format_mimo_actdesc', ['activitytype' => 'forum']);
        $this->assertNull($desc->desctagid);
    }

    /**
     * Test getting all descriptions returns tag information.
     */
    public function test_get_all_descriptions_includes_tag(): void {
        $this->resetAfterTest(true);

        $tagid = description_tag_manager::create_tag('Test Tag', '#FF5733');
        activity_description_manager::save_description('assign', 'Assignment description', $tagid);
        activity_description_manager::save_description('quiz', 'Quiz description', null);

        $descriptions = activity_description_manager::get_all_descriptions();

        $this->assertNotEmpty($descriptions);

        $assigndesc = null;
        $quizdesc = null;

        foreach ($descriptions as $desc) {
            if ($desc->activitytype === 'assign') {
                $assigndesc = $desc;
            } else if ($desc->activitytype === 'quiz') {
                $quizdesc = $desc;
            }
        }

        $this->assertNotNull($assigndesc);
        $this->assertEquals($tagid, $assigndesc->desctagid);

        $this->assertNotNull($quizdesc);
        $this->assertNull($quizdesc->desctagid);
    }
}
