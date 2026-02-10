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
    /** @var int Test tagset ID */
    private int $tagsetid;

    /**
     * Set up before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        tagset_manager::clear_tagset_cache();
        $this->tagsetid = tagset_manager::create_tagset('Test Tagset');
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
     * Test creating a tag.
     */
    public function test_create_tag(): void {
        $id = tag_manager::create_tag(
            $this->tagsetid,
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
        $this->assertEquals('Reading', $tag->name);
        $this->assertEquals('reading.svg', $tag->cardimage);
        $this->assertEquals('reading-small.svg', $tag->filterimage);
        $this->assertEquals('page', $tag->activitytype1);
        $this->assertEquals('book', $tag->activitytype2);
        $this->assertEquals('center', $tag->imgplacement);
        $this->assertEquals(0, $tag->sortorder);
        $this->assertEquals($this->tagsetid, $tag->tagsetid);
    }

    /**
     * Ensure custom colours are normalised when creating a tag.
     */
    public function test_create_tag_with_bgcolor(): void {
        $id = tag_manager::create_tag($this->tagsetid, 'Colourful', null, null, null, null, null, 'a1b2c3');
        $tag = tag_manager::get_tag($id);

        $this->assertEquals('#a1b2c3', $tag->bgcolor);
    }

    /**
     * Test getting all tags.
     */
    public function test_get_all_tags(): void {
        global $DB;

        // Clear any existing tags from install.
        $DB->delete_records('format_minimoodlewall_tags');
        tag_manager::clear_tag_cache();

        // Create multiple tags.
        tag_manager::create_tag($this->tagsetid, 'Tag 1', 'tag1.svg', 'tag1-small.svg', 'page');
        tag_manager::create_tag($this->tagsetid, 'Tag 2', 'tag2.svg', 'tag2-small.svg', 'quiz');
        tag_manager::create_tag($this->tagsetid, 'Tag 3', 'tag3.svg', 'tag3-small.svg', 'forum');

        $tags = tag_manager::get_all_tags();

        $this->assertCount(3, $tags);
    }

    /**
     * Test getting tags for a course.
     */
    public function test_get_tags_for_course(): void {
        global $DB;

        // Clear any existing tags.
        $DB->delete_records('format_minimoodlewall_tags');
        tag_manager::clear_tag_cache();

        // Create tags.
        $tag1id = tag_manager::create_tag($this->tagsetid, 'Reading', 'reading.svg', 'reading-small.svg', 'page');
        $tag2id = tag_manager::create_tag($this->tagsetid, 'Video', 'video.svg', 'video-small.svg', 'url');
        $tag3id = tag_manager::create_tag($this->tagsetid, 'Quiz', 'quiz.svg', 'quiz-small.svg', 'quiz');

        // Create a course with selected tags.
        $course = $this->getDataGenerator()->create_course([
            'format' => 'minimoodlewall',
            'selectedtags' => "$tag1id,$tag2id",
        ]);

        // Get tags for course.
        $tags = tag_manager::get_tags_for_course($course->id);

        $this->assertCount(2, $tags);
        $tagnames = array_column($tags, 'name');
        $this->assertContains('Reading', $tagnames);
        $this->assertContains('Video', $tagnames);
        $this->assertNotContains('Quiz', $tagnames);
    }

    /**
     * Test updating a tag.
     */
    public function test_update_tag(): void {
        $id = tag_manager::create_tag($this->tagsetid, 'Original', 'orig.svg', 'orig-small.svg', 'page');

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
        $id = tag_manager::create_tag($this->tagsetid, 'Colourful', null, null, null, null, null, '#445566');
        $tag = tag_manager::get_tag($id);

        $this->assertSame('#445566', tag_manager::get_tag_accent_color($tag));
    }

    /**
     * The accent resolver should fall back to the starters palette.
     */
    public function test_get_tag_accent_color_fallback_uses_palette(): void {
        $id = tag_manager::create_tag($this->tagsetid, 'Default Colour');
        $tag = tag_manager::get_tag($id);

        $palette = tag_manager::get_default_accent_palette();
        $this->assertContains(tag_manager::get_tag_accent_color($tag), $palette);
    }

    /**
     * Test deleting a tag.
     */
    public function test_delete_tag(): void {
        $id = tag_manager::create_tag($this->tagsetid, 'To Delete', 'test.svg', 'test-small.svg', 'page');

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

        // Create tag.
        $tagid = tag_manager::create_tag($this->tagsetid, 'Reading', 'reading.svg', 'reading-small.svg', 'page');

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

        // Create tag.
        $tagid = tag_manager::create_tag($this->tagsetid, 'Reading', 'reading.svg', 'reading-small.svg', 'page');

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
            ['Writing'],
            ['Watch'],
            ['Listen'],
            ['Discover'],
            ['Calculations'],
            ['Teamwork'],
            ['Show'],
            ['Practice'],
        ];
    }

    /**
     * Test initializing default tags.
     */
    public function test_initialize_default_tags(): void {
        global $DB;

        // Clear any existing tags and tagsets from install.
        $DB->delete_records('format_minimoodlewall_tags');
        $DB->delete_records('format_minimoodlewall_tagsets');
        tag_manager::clear_tag_cache();
        tagset_manager::clear_tagset_cache();

        tag_manager::initialize_default_tags();

        // Verify 9 default tags were created.
        $tags = tag_manager::get_all_tags();
        $this->assertCount(9, $tags);

        // Verify tag names.
        $tagnames = array_column($tags, 'name');
        $expectednames = ['Reading', 'Writing', 'Watch', 'Listen', 'Discover', 'Calculations', 'Teamwork', 'Show', 'Practice'];
        foreach ($expectednames as $expected) {
            $this->assertContains($expected, $tagnames);
        }
    }

    /**
     * Test that initialize_default_tags is idempotent.
     */
    public function test_initialize_default_tags_idempotent(): void {
        global $DB;

        // Clear any existing tags and tagsets from install.
        $DB->delete_records('format_minimoodlewall_tags');
        $DB->delete_records('format_minimoodlewall_tagsets');
        tag_manager::clear_tag_cache();
        tagset_manager::clear_tagset_cache();

        // Call twice.
        tag_manager::initialize_default_tags();
        tag_manager::initialize_default_tags();

        // Should still only have 9 tags.
        $tags = tag_manager::get_all_tags();
        $this->assertCount(9, $tags);
    }
}
