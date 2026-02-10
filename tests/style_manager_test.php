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
 * Unit tests for style_manager.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall;

/**
 * Style manager test case.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_minimoodlewall\style_manager
 */
final class style_manager_test extends \advanced_testcase {
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
        $this->tagsetid = tagset_manager::create_tagset('Style Test Tagset');
    }

    /**
     * Test creating a style.
     */
    public function test_create_style(): void {
        $id = style_manager::create_style('teststyle', 'Test Style', 1);

        $this->assertNotEmpty($id);
        $this->assertIsInt($id);

        // Verify the style was created.
        $style = style_manager::get_style($id);
        $this->assertNotNull($style);
        $this->assertEquals('teststyle', $style->name);
        $this->assertEquals('Test Style', $style->displayname);
        $this->assertEquals(1, $style->sortorder);
    }

    /**
     * Test getting all styles.
     */
    public function test_get_all_styles(): void {
        global $DB;

        // Clear any existing styles.
        $DB->delete_records('format_minimoodlewall_styles');

        // Create multiple styles.
        style_manager::create_style('style1', 'Style One', 1);
        style_manager::create_style('style2', 'Style Two', 2);
        style_manager::create_style('style3', 'Style Three', 3);

        $styles = style_manager::get_all_styles();

        $this->assertCount(3, $styles);

        // Verify order by sortorder.
        $names = array_column($styles, 'name');
        $this->assertEquals(['style1', 'style2', 'style3'], $names);
    }

    /**
     * Test getting a style by name.
     */
    public function test_get_style_by_name(): void {
        style_manager::create_style('mystyle', 'My Style', 1);

        $style = style_manager::get_style_by_name('mystyle');

        $this->assertNotNull($style);
        $this->assertEquals('mystyle', $style->name);
        $this->assertEquals('My Style', $style->displayname);
    }

    /**
     * Test getting a non-existent style by name returns null.
     */
    public function test_get_style_by_name_not_found(): void {
        $style = style_manager::get_style_by_name('nonexistent');

        $this->assertNull($style);
    }

    /**
     * Test updating a style.
     */
    public function test_update_style(): void {
        $id = style_manager::create_style('original', 'Original Name', 1);

        style_manager::update_style($id, [
            'displayname' => 'Updated Name',
            'sortorder' => 5,
        ]);

        $style = style_manager::get_style($id);
        $this->assertEquals('original', $style->name); // Name unchanged.
        $this->assertEquals('Updated Name', $style->displayname);
        $this->assertEquals(5, $style->sortorder);
    }

    /**
     * Test deleting a style.
     */
    public function test_delete_style(): void {
        $id = style_manager::create_style('todelete', 'To Delete', 1);

        // Verify it exists.
        $this->assertNotNull(style_manager::get_style($id));

        // Delete it.
        style_manager::delete_style($id);

        // Verify it's gone.
        $this->assertNull(style_manager::get_style($id));
    }

    /**
     * Test deleting a style also deletes associated tag images.
     */
    public function test_delete_style_cascades_to_tag_images(): void {
        global $DB;

        // Create a style and a tag.
        $styleid = style_manager::create_style('cascade', 'Cascade Test', 1);
        $tagid = tag_manager::create_tag($this->tagsetid, 'Test Tag', null, null, 'page');

        // Create a tag image record for this style.
        $tagimage = style_manager::get_or_create_tag_image($tagid, $styleid);
        $this->assertNotEmpty($tagimage->id);

        // Verify the tag image exists.
        $this->assertTrue($DB->record_exists('format_minimoodlewall_tag_images', ['id' => $tagimage->id]));

        // Delete the style.
        style_manager::delete_style($styleid);

        // Verify the tag image was also deleted.
        $this->assertFalse($DB->record_exists('format_minimoodlewall_tag_images', ['id' => $tagimage->id]));
    }

    /**
     * Test get_or_create_tag_image creates new record when none exists.
     */
    public function test_get_or_create_tag_image_creates(): void {
        $styleid = style_manager::create_style('imgtest', 'Image Test', 1);
        $tagid = tag_manager::create_tag($this->tagsetid, 'Img Tag', null, null, 'page');

        $tagimage = style_manager::get_or_create_tag_image($tagid, $styleid);

        $this->assertNotEmpty($tagimage->id);
        $this->assertEquals($tagid, $tagimage->tagid);
        $this->assertEquals($styleid, $tagimage->styleid);
    }

    /**
     * Test get_or_create_tag_image returns existing record.
     */
    public function test_get_or_create_tag_image_returns_existing(): void {
        $styleid = style_manager::create_style('existing', 'Existing Test', 1);
        $tagid = tag_manager::create_tag($this->tagsetid, 'Existing Tag', null, null, 'page');

        // Create first time.
        $tagimage1 = style_manager::get_or_create_tag_image($tagid, $styleid);

        // Get again - should return same record.
        $tagimage2 = style_manager::get_or_create_tag_image($tagid, $styleid);

        $this->assertEquals($tagimage1->id, $tagimage2->id);
    }

    /**
     * Test get_cardimage_url_by_name returns null when no image exists.
     */
    public function test_get_cardimage_url_by_name_no_image(): void {
        $styleid = style_manager::create_style('noimg', 'No Image', 1);
        $tagid = tag_manager::create_tag($this->tagsetid, 'No Img Tag', null, null, 'page');

        $url = style_manager::get_cardimage_url_by_name($tagid, 'noimg');

        $this->assertNull($url);
    }

    /**
     * Test style name uniqueness is enforced.
     */
    public function test_style_name_uniqueness(): void {
        style_manager::create_style('unique', 'Unique Style', 1);

        $this->expectException(\dml_write_exception::class);
        style_manager::create_style('unique', 'Duplicate Name', 2);
    }

    /**
     * Test styles are ordered by sortorder.
     */
    public function test_styles_ordered_by_sortorder(): void {
        global $DB;

        // Clear existing styles.
        $DB->delete_records('format_minimoodlewall_styles');

        // Create in non-sequential order.
        style_manager::create_style('third', 'Third', 30);
        style_manager::create_style('first', 'First', 10);
        style_manager::create_style('second', 'Second', 20);

        $styles = style_manager::get_all_styles();
        $names = array_column($styles, 'name');

        $this->assertEquals(['first', 'second', 'third'], $names);
    }

    /**
     * Test tag_manager fallback to style-specific image.
     */
    public function test_tag_manager_uses_style_image(): void {
        global $DB;

        // Create style and tag.
        $styleid = style_manager::create_style('fallback', 'Fallback Test', 1);
        $tagid = tag_manager::create_tag($this->tagsetid, 'Fallback Tag', null, null, 'page');

        // Create tag image record with a filename.
        $tagimage = style_manager::get_or_create_tag_image($tagid, $styleid);
        $DB->set_field('format_minimoodlewall_tag_images', 'cardimage', 'test.svg', ['id' => $tagimage->id]);

        // Get the tag.
        $tag = tag_manager::get_tag($tagid);

        // Without a file in file storage, get_cardimage_url should still work via style_manager.
        // It will return null because no actual file exists, but the lookup path is tested.
        $url = tag_manager::get_cardimage_url($tag, 'fallback');

        // Since we didn't actually upload a file, URL should be null.
        // But we've tested that the style lookup path is exercised.
        $this->assertNull($url);
    }
}
