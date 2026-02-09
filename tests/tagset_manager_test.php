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
 * Unit tests for tagset_manager.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 MBS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall;

/**
 * Tagset manager test case.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 MBS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_minimoodlewall\tagset_manager
 */
final class tagset_manager_test extends \advanced_testcase {
    /**
     * Set up before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        tagset_manager::clear_tagset_cache();
    }

    /**
     * Clean up after each test.
     */
    protected function tearDown(): void {
        // Clear caches to prevent cross-test contamination.
        \cache::make('format_minimoodlewall', 'tagconfigurations')->purge();
        tagset_manager::clear_tagset_cache();

        parent::tearDown();
    }

    /**
     * Test creating a tagset.
     */
    public function test_create_tagset(): void {
        $id = tagset_manager::create_tagset('Science', 'Science-related tags');

        $this->assertNotEmpty($id);
        $this->assertIsInt($id);

        $tagset = tagset_manager::get_tagset($id);
        $this->assertNotFalse($tagset);
        $this->assertEquals('Science', $tagset->name);
        $this->assertEquals('Science-related tags', $tagset->description);
    }

    /**
     * Test creating a tagset without description.
     */
    public function test_create_tagset_no_description(): void {
        $id = tagset_manager::create_tagset('Minimal');

        $tagset = tagset_manager::get_tagset($id);
        $this->assertEquals('Minimal', $tagset->name);
        $this->assertEmpty($tagset->description);
    }

    /**
     * Test getting all tagsets.
     */
    public function test_get_all_tagsets(): void {
        global $DB;

        // Clear existing tagsets.
        $DB->delete_records('format_minimoodlewall_tagsets');
        tagset_manager::clear_tagset_cache();

        tagset_manager::create_tagset('First');
        tagset_manager::create_tagset('Second');
        tagset_manager::create_tagset('Third');

        $tagsets = tagset_manager::get_all_tagsets();

        $this->assertCount(3, $tagsets);
        $names = array_column($tagsets, 'name');
        $this->assertContains('First', $names);
        $this->assertContains('Second', $names);
        $this->assertContains('Third', $names);
    }

    /**
     * Test updating a tagset.
     */
    public function test_update_tagset(): void {
        $id = tagset_manager::create_tagset('Original', 'Original description');

        $result = tagset_manager::update_tagset($id, [
            'name' => 'Updated',
            'description' => 'Updated description',
        ]);

        $this->assertTrue($result);

        $tagset = tagset_manager::get_tagset($id);
        $this->assertEquals('Updated', $tagset->name);
        $this->assertEquals('Updated description', $tagset->description);
    }

    /**
     * Test deleting a tagset also deletes its tags.
     */
    public function test_delete_tagset_cascades(): void {
        $tagsetid = tagset_manager::create_tagset('To Delete');

        // Create tags within the tagset.
        tag_manager::create_tag($tagsetid, 'Tag A', null, null, 'page');
        tag_manager::create_tag($tagsetid, 'Tag B', null, null, 'quiz');

        // Verify tags exist.
        $tags = tag_manager::get_tags_by_tagset($tagsetid);
        $this->assertCount(2, $tags);

        // Delete the tagset.
        $result = tagset_manager::delete_tagset($tagsetid);
        $this->assertTrue($result);

        // Verify tagset is deleted.
        $tagset = tagset_manager::get_tagset($tagsetid);
        $this->assertFalse($tagset);

        // Verify tags are also deleted.
        $tags = tag_manager::get_tags_by_tagset($tagsetid);
        $this->assertEmpty($tags);
    }

    /**
     * Test getting a non-existent tagset returns false.
     */
    public function test_get_nonexistent_tagset(): void {
        $tagset = tagset_manager::get_tagset(99999);
        $this->assertFalse($tagset);
    }

    /**
     * Test tagset caching works correctly.
     */
    public function test_tagset_caching(): void {
        global $DB;

        // Clear existing tagsets.
        $DB->delete_records('format_minimoodlewall_tagsets');
        tagset_manager::clear_tagset_cache();

        $id = tagset_manager::create_tagset('Cached');

        // First call populates cache.
        $tagsets1 = tagset_manager::get_all_tagsets();
        $this->assertCount(1, $tagsets1);

        // Delete directly from DB, bypassing cache clear.
        $DB->delete_records('format_minimoodlewall_tagsets', ['id' => $id]);

        // Should still get cached result.
        $tagsets2 = tagset_manager::get_all_tagsets();
        $this->assertCount(1, $tagsets2);

        // Clear cache and re-fetch.
        tagset_manager::clear_tagset_cache();
        $tagsets3 = tagset_manager::get_all_tagsets();
        $this->assertCount(0, $tagsets3);
    }

    /**
     * Test tags belong to the correct tagset.
     */
    public function test_get_tags_by_tagset(): void {
        $tagset1 = tagset_manager::create_tagset('Set 1');
        $tagset2 = tagset_manager::create_tagset('Set 2');

        tag_manager::create_tag($tagset1, 'Tag A', null, null, 'page');
        tag_manager::create_tag($tagset1, 'Tag B', null, null, 'quiz');
        tag_manager::create_tag($tagset2, 'Tag C', null, null, 'forum');

        $tags1 = tag_manager::get_tags_by_tagset($tagset1);
        $tags2 = tag_manager::get_tags_by_tagset($tagset2);

        $this->assertCount(2, $tags1);
        $this->assertCount(1, $tags2);

        $names1 = array_column($tags1, 'name');
        $this->assertContains('Tag A', $names1);
        $this->assertContains('Tag B', $names1);

        $names2 = array_column($tags2, 'name');
        $this->assertContains('Tag C', $names2);
    }
}
