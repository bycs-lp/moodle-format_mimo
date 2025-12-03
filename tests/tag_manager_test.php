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
 * Unit tests for tag_manager.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall;

/**
 * Tag manager test case.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_minimoodlewall\tag_manager
 */
final class tag_manager_test extends \advanced_testcase {
    /**
     * Set up before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Clean up after each test.
     */
    protected function tearDown(): void {
        global $SESSION;

        // Clear caches to prevent cross-test contamination.
        \cache::make('format_minimoodlewall', 'tagconfigurations')->purge();
        \cache::make('format_minimoodlewall', 'activitytagmappings')->purge();
        tag_manager::clear_mapping_cache();

        // Force tag_manager to re-initialize cache instances on next access.
        // This prevents stale static cache references from contaminating subsequent tests.
        $reflection = new \ReflectionClass(tag_manager::class);
        $tagcacheprop = $reflection->getProperty('tagcache');
        $tagcacheprop->setAccessible(true);
        $tagcacheprop->setValue(null, null);
        $mappingcacheprop = $reflection->getProperty('mappingcache');
        $mappingcacheprop->setAccessible(true);
        $mappingcacheprop->setValue(null, null);

        // Ensure session is clean for next test.
        unset($SESSION->format_minimoodlewall_pending_tag);

        parent::tearDown();
    }

    /**
     * Data provider for tagset creation tests.
     *
     * @return array
     */
    public static function tagset_data_provider(): array {
        return [
            'basic tagset' => [
                'name' => 'Test Tagset',
            ],
            'tagset with special characters' => [
                'name' => 'Tagset & Co.',
            ],
        ];
    }

    /**
     * Test creating a tagset.
     *
     * @dataProvider tagset_data_provider
     * @param string $name
     */
    public function test_create_tagset(string $name): void {
        $id = tag_manager::create_tagset($name);

        $this->assertNotEmpty($id);
        $this->assertIsInt($id);

        // Verify the tagset was created.
        $tagset = tag_manager::get_tagset($id);
        $this->assertNotFalse($tagset);
        $this->assertEquals($name, $tagset->name);
        $this->assertNotEmpty($tagset->timecreated);
        $this->assertNotEmpty($tagset->timemodified);
    }

    /**
     * Test getting all tagsets.
     */
    public function test_get_tagsets(): void {
        global $DB;

        // Clear any existing tagsets from install.
        $DB->delete_records('format_minimoodlewall_tagsets');
        $DB->delete_records('format_minimoodlewall_tags');
        tag_manager::clear_tag_cache();

        // Create multiple tagsets.
        tag_manager::create_tagset('Tagset 1');
        tag_manager::create_tagset('Tagset 2');
        tag_manager::create_tagset('Tagset 3');

        $tagsets = tag_manager::get_tagsets();

        $this->assertCount(3, $tagsets);

        // Check they are sorted by name.
        $names = array_column($tagsets, 'name');
        $this->assertEquals(['Tagset 1', 'Tagset 2', 'Tagset 3'], $names);
    }

    /**
     * Test updating a tagset.
     */
    public function test_update_tagset(): void {
        $id = tag_manager::create_tagset('Original Name');

        // Update the tagset.
        $result = tag_manager::update_tagset($id, 'Updated Name');

        $this->assertTrue($result);

        // Verify the update.
        $tagset = tag_manager::get_tagset($id);
        $this->assertEquals('Updated Name', $tagset->name);
    }

    /**
     * Test deleting a tagset.
     */
    public function test_delete_tagset(): void {
        $id = tag_manager::create_tagset('To Delete');

        // Create a tag in this tagset.
        $tagid = tag_manager::create_tag($id, 'Test Tag', 'test.svg', 'test-small.svg', 'page');

        // Delete the tagset.
        $result = tag_manager::delete_tagset($id);

        $this->assertTrue($result);

        // Verify tagset is deleted.
        $tagset = tag_manager::get_tagset($id);
        $this->assertFalse($tagset);

        // Verify tag is also deleted.
        $tag = tag_manager::get_tag($tagid);
        $this->assertFalse($tag);
    }

    /**
     * Test creating a tag.
     */
    public function test_create_tag(): void {
        $tagsetid = tag_manager::create_tagset('Tagset');

        $id = tag_manager::create_tag(
            $tagsetid,
            'Reading',
            'reading.svg',
            'reading-small.svg',
            'page',
            'book'
        );

        $this->assertNotEmpty($id);
        $this->assertIsInt($id);

        // Verify the tag was created.
        $tag = tag_manager::get_tag($id);
        $this->assertNotFalse($tag);
        $this->assertEquals($tagsetid, $tag->tagsetid);
        $this->assertEquals('Reading', $tag->name);
        $this->assertEquals('reading.svg', $tag->cardimage);
        $this->assertEquals('reading-small.svg', $tag->filterimage);
        $this->assertEquals('page', $tag->activitytype1);
        $this->assertEquals('book', $tag->activitytype2);
        $this->assertEquals('center', $tag->imgplacement);
        $this->assertEquals(0, $tag->sortorder);
    }

    /**
     * Ensure custom colours are normalised when creating a tag.
     */
    public function test_create_tag_with_bgcolor(): void {
        $tagsetid = tag_manager::create_tagset('Tagset');

        $id = tag_manager::create_tag($tagsetid, 'Colourful', null, null, null, null, null, 'a1b2c3');
        $tag = tag_manager::get_tag($id);

        $this->assertEquals('#a1b2c3', $tag->bgcolor);
    }

    /**
     * Test getting tags by tagset.
     */
    public function test_get_tags_by_tagset(): void {
        $tagsetid = tag_manager::create_tagset('Tagset');

        // Create multiple tags.
        tag_manager::create_tag($tagsetid, 'Tag 1', 'tag1.svg', 'tag1-small.svg', 'page');
        tag_manager::create_tag($tagsetid, 'Tag 2', 'tag2.svg', 'tag2-small.svg', 'quiz');
        tag_manager::create_tag($tagsetid, 'Tag 3', 'tag3.svg', 'tag3-small.svg', 'forum');

        $tags = tag_manager::get_tags_by_tagset($tagsetid);

        $this->assertCount(3, $tags);
    }

    /**
     * Test updating a tag.
     */
    public function test_update_tag(): void {
        $tagsetid = tag_manager::create_tagset('Tagset');
        $id = tag_manager::create_tag($tagsetid, 'Original', 'orig.svg', 'orig-small.svg', 'page');

        // Update the tag.
        $result = tag_manager::update_tag($id, [
            'name' => 'Updated',
            'cardimage' => 'updated.svg',
            'filterimage' => 'updated-small.svg',
            'activitytype1' => 'quiz',
            'activitytype2' => 'choice',
            'bgcolor' => '#123456',
            'imgplacement' => 'lower',
        ]);

        $this->assertTrue($result);

        // Verify the update.
        $tag = tag_manager::get_tag($id);
        $this->assertEquals('Updated', $tag->name);
        $this->assertEquals('updated.svg', $tag->cardimage);
        $this->assertEquals('updated-small.svg', $tag->filterimage);
        $this->assertEquals('quiz', $tag->activitytype1);
        $this->assertEquals('choice', $tag->activitytype2);
        $this->assertEquals('#123456', $tag->bgcolor);
        $this->assertEquals('lower', $tag->imgplacement);
    }

    /**
     * The accent resolver should prefer stored colours.
     */
    public function test_get_tag_accent_color_prefers_custom_colour(): void {
        $tagsetid = tag_manager::create_tagset('Tagset');
        $id = tag_manager::create_tag($tagsetid, 'Colourful', null, null, null, null, null, '#445566');
        $tag = tag_manager::get_tag($id);

        $this->assertSame('#445566', tag_manager::get_tag_accent_color($tag));
    }

    /**
     * The accent resolver should fall back to the starters palette.
     */
    public function test_get_tag_accent_color_fallback_uses_palette(): void {
        $tagsetid = tag_manager::create_tagset('Tagset');
        $id = tag_manager::create_tag($tagsetid, 'Default Colour');
        $tag = tag_manager::get_tag($id);

        $palette = tag_manager::get_default_accent_palette();
        $this->assertContains(tag_manager::get_tag_accent_color($tag), $palette);
    }

    /**
     * Test deleting a tag.
     */
    public function test_delete_tag(): void {
        $tagsetid = tag_manager::create_tagset('Tagset');
        $id = tag_manager::create_tag($tagsetid, 'To Delete', 'test.svg', 'test-small.svg', 'page');

        // Delete the tag.
        $result = tag_manager::delete_tag($id);

        $this->assertTrue($result);

        // Verify tag is deleted.
        $tag = tag_manager::get_tag($id);
        $this->assertFalse($tag);
    }

    /**
     * Test assigning a tag to a course module.
     */
    public function test_assign_tag_to_cm(): void {
        // Create course and module.
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        // Create tagset and tag.
        $tagsetid = tag_manager::create_tagset('Tagset');
        $tagid = tag_manager::create_tag($tagsetid, 'Reading', 'reading.svg', 'reading-small.svg', 'page');

        // Assign tag to module.
        $result = tag_manager::assign_tag_to_cm($page->cmid, $tagid);

        $this->assertTrue($result);

        // Verify assignment.
        $tag = tag_manager::get_cm_tag($page->cmid);
        $this->assertNotFalse($tag);
        $this->assertEquals($tagid, $tag->id);
        $this->assertEquals('Reading', $tag->name);
    }

    /**
     * Test unassigning a tag from a course module.
     */
    public function test_unassign_tag_from_cm(): void {
        // Create course and module.
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        // Create tagset and tag.
        $tagsetid = tag_manager::create_tagset('Tagset');
        $tagid = tag_manager::create_tag($tagsetid, 'Reading', 'reading.svg', 'reading-small.svg', 'page');

        // Assign and then unassign.
        tag_manager::assign_tag_to_cm($page->cmid, $tagid);
        $result = tag_manager::unassign_tag_from_cm($page->cmid);

        $this->assertTrue($result);

        // Verify unassignment.
        $tag = tag_manager::get_cm_tag($page->cmid);
        $this->assertFalse($tag);
    }

    /**
     * Data provider for default tag names.
     *
     * @return array
     */
    public static function default_tag_names_provider(): array {
        return [
            ['Reading'],
            ['Video'],
            ['Writing'],
            ['Quiz'],
            ['Discussion'],
            ['Data'],
            ['Lab'],
            ['Practice'],
        ];
    }

    /**
     * Test initializing default tags.
     */
    public function test_initialize_default_tags(): void {
        global $DB;

        // Clear any existing tagsets from install.
        $DB->delete_records('format_minimoodlewall_tagsets');
        $DB->delete_records('format_minimoodlewall_tags');
        tag_manager::clear_tag_cache();

        tag_manager::initialize_default_tags();

        // Verify default tagset was created.
        $tagsets = tag_manager::get_tagsets();
        $this->assertCount(1, $tagsets);

        $tagset = reset($tagsets);
        $this->assertEquals('Default Tags', $tagset->name);

        // Verify 8 default tags were created.
        $tags = tag_manager::get_tags_by_tagset($tagset->id);
        $this->assertCount(8, $tags);

        // Verify tag names.
        $tagnames = array_column($tags, 'name');
        $expectednames = ['Reading', 'Video', 'Writing', 'Quiz', 'Discussion', 'Data', 'Lab', 'Practice'];
        foreach ($expectednames as $expected) {
            $this->assertContains($expected, $tagnames);
        }
    }

    /**
     * Test that initialize_default_tags is idempotent.
     */
    public function test_initialize_default_tags_idempotent(): void {
        global $DB;

        // Clear any existing tagsets from install.
        $DB->delete_records('format_minimoodlewall_tagsets');
        $DB->delete_records('format_minimoodlewall_tags');
        tag_manager::clear_tag_cache();

        // Call twice.
        tag_manager::initialize_default_tags();
        tag_manager::initialize_default_tags();

        // Should still only have 1 tagset.
        $tagsets = tag_manager::get_tagsets();
        $this->assertCount(1, $tagsets);

        $tagset = reset($tagsets);
        $tags = tag_manager::get_tags_by_tagset($tagset->id);
        $this->assertCount(8, $tags);
    }
}
