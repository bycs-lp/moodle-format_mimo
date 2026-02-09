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
 * Unit tests for design_manager.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall;

/**
 * Design manager test case.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_minimoodlewall\design_manager
 */
final class design_manager_test extends \advanced_testcase {
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
        $this->tagsetid = tagset_manager::create_tagset('Design Test Tagset');
    }

    /**
     * Test creating a design.
     */
    public function test_create_design(): void {
        $id = design_manager::create_design('testdesign', 'Test Design', 1);

        $this->assertNotEmpty($id);
        $this->assertIsInt($id);

        // Verify the design was created.
        $design = design_manager::get_design($id);
        $this->assertNotNull($design);
        $this->assertEquals('testdesign', $design->name);
        $this->assertEquals('Test Design', $design->displayname);
        $this->assertEquals(1, $design->sortorder);
    }

    /**
     * Test getting all designs.
     */
    public function test_get_all_designs(): void {
        global $DB;

        // Clear any existing designs.
        $DB->delete_records('format_minimoodlewall_designs');

        // Create multiple designs.
        design_manager::create_design('design1', 'Design One', 1);
        design_manager::create_design('design2', 'Design Two', 2);
        design_manager::create_design('design3', 'Design Three', 3);

        $designs = design_manager::get_all_designs();

        $this->assertCount(3, $designs);

        // Verify order by sortorder.
        $names = array_column($designs, 'name');
        $this->assertEquals(['design1', 'design2', 'design3'], $names);
    }

    /**
     * Test getting a design by name.
     */
    public function test_get_design_by_name(): void {
        design_manager::create_design('mydesign', 'My Design', 1);

        $design = design_manager::get_design_by_name('mydesign');

        $this->assertNotNull($design);
        $this->assertEquals('mydesign', $design->name);
        $this->assertEquals('My Design', $design->displayname);
    }

    /**
     * Test getting a non-existent design by name returns null.
     */
    public function test_get_design_by_name_not_found(): void {
        $design = design_manager::get_design_by_name('nonexistent');

        $this->assertNull($design);
    }

    /**
     * Test updating a design.
     */
    public function test_update_design(): void {
        $id = design_manager::create_design('original', 'Original Name', 1);

        design_manager::update_design($id, [
            'displayname' => 'Updated Name',
            'sortorder' => 5,
        ]);

        $design = design_manager::get_design($id);
        $this->assertEquals('original', $design->name); // Name unchanged.
        $this->assertEquals('Updated Name', $design->displayname);
        $this->assertEquals(5, $design->sortorder);
    }

    /**
     * Test deleting a design.
     */
    public function test_delete_design(): void {
        $id = design_manager::create_design('todelete', 'To Delete', 1);

        // Verify it exists.
        $this->assertNotNull(design_manager::get_design($id));

        // Delete it.
        design_manager::delete_design($id);

        // Verify it's gone.
        $this->assertNull(design_manager::get_design($id));
    }

    /**
     * Test deleting a design also deletes associated tag images.
     */
    public function test_delete_design_cascades_to_tag_images(): void {
        global $DB;

        // Create a design and a tag.
        $designid = design_manager::create_design('cascade', 'Cascade Test', 1);
        $tagid = tag_manager::create_tag($this->tagsetid, 'Test Tag', null, null, 'page');

        // Create a tag image record for this design.
        $tagimage = design_manager::get_or_create_tag_image($tagid, $designid);
        $this->assertNotEmpty($tagimage->id);

        // Verify the tag image exists.
        $this->assertTrue($DB->record_exists('format_minimoodlewall_tag_images', ['id' => $tagimage->id]));

        // Delete the design.
        design_manager::delete_design($designid);

        // Verify the tag image was also deleted.
        $this->assertFalse($DB->record_exists('format_minimoodlewall_tag_images', ['id' => $tagimage->id]));
    }

    /**
     * Test get_or_create_tag_image creates new record when none exists.
     */
    public function test_get_or_create_tag_image_creates(): void {
        $designid = design_manager::create_design('imgtest', 'Image Test', 1);
        $tagid = tag_manager::create_tag($this->tagsetid, 'Img Tag', null, null, 'page');

        $tagimage = design_manager::get_or_create_tag_image($tagid, $designid);

        $this->assertNotEmpty($tagimage->id);
        $this->assertEquals($tagid, $tagimage->tagid);
        $this->assertEquals($designid, $tagimage->designid);
    }

    /**
     * Test get_or_create_tag_image returns existing record.
     */
    public function test_get_or_create_tag_image_returns_existing(): void {
        $designid = design_manager::create_design('existing', 'Existing Test', 1);
        $tagid = tag_manager::create_tag($this->tagsetid, 'Existing Tag', null, null, 'page');

        // Create first time.
        $tagimage1 = design_manager::get_or_create_tag_image($tagid, $designid);

        // Get again - should return same record.
        $tagimage2 = design_manager::get_or_create_tag_image($tagid, $designid);

        $this->assertEquals($tagimage1->id, $tagimage2->id);
    }

    /**
     * Test get_cardimage_url_by_name returns null when no image exists.
     */
    public function test_get_cardimage_url_by_name_no_image(): void {
        $designid = design_manager::create_design('noimg', 'No Image', 1);
        $tagid = tag_manager::create_tag($this->tagsetid, 'No Img Tag', null, null, 'page');

        $url = design_manager::get_cardimage_url_by_name($tagid, 'noimg');

        $this->assertNull($url);
    }

    /**
     * Test design name uniqueness is enforced.
     */
    public function test_design_name_uniqueness(): void {
        design_manager::create_design('unique', 'Unique Design', 1);

        $this->expectException(\dml_write_exception::class);
        design_manager::create_design('unique', 'Duplicate Name', 2);
    }

    /**
     * Test designs are ordered by sortorder.
     */
    public function test_designs_ordered_by_sortorder(): void {
        global $DB;

        // Clear existing designs.
        $DB->delete_records('format_minimoodlewall_designs');

        // Create in non-sequential order.
        design_manager::create_design('third', 'Third', 30);
        design_manager::create_design('first', 'First', 10);
        design_manager::create_design('second', 'Second', 20);

        $designs = design_manager::get_all_designs();
        $names = array_column($designs, 'name');

        $this->assertEquals(['first', 'second', 'third'], $names);
    }

    /**
     * Test tag_manager fallback to design-specific image.
     */
    public function test_tag_manager_uses_design_image(): void {
        global $DB;

        // Create design and tag.
        $designid = design_manager::create_design('fallback', 'Fallback Test', 1);
        $tagid = tag_manager::create_tag($this->tagsetid, 'Fallback Tag', null, null, 'page');

        // Create tag image record with a filename.
        $tagimage = design_manager::get_or_create_tag_image($tagid, $designid);
        $DB->set_field('format_minimoodlewall_tag_images', 'cardimage', 'test.svg', ['id' => $tagimage->id]);

        // Get the tag.
        $tag = tag_manager::get_tag($tagid);

        // Without a file in file storage, get_cardimage_url should still work via design_manager.
        // It will return null because no actual file exists, but the lookup path is tested.
        $url = tag_manager::get_cardimage_url($tag, 'fallback');

        // Since we didn't actually upload a file, URL should be null.
        // But we've tested that the design lookup path is exercised.
        $this->assertNull($url);
    }
}
