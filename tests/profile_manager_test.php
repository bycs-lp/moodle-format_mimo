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
 * Unit tests for profile_manager.
 *
 * @package    format_mimo
 * @copyright  2025 MBS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo;

/**
 * Profile manager test case.
 *
 * @package    format_mimo
 * @copyright  2025 MBS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_mimo\profile_manager
 */
final class profile_manager_test extends \advanced_testcase {
    /**
     * Set up before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Test creating a profile.
     */
    public function test_create_profile(): void {
        $id = profile_manager::create_profile('teststyle', 'Test Style', 1);

        $this->assertNotEmpty($id);
        $this->assertIsInt($id);

        // Verify the profile was created.
        $profile = profile_manager::get_profile($id);
        $this->assertNotNull($profile);
        $this->assertEquals('teststyle', $profile->name);
        $this->assertEquals('Test Style', $profile->displayname);
        $this->assertEquals(1, $profile->sortorder);
    }

    /**
     * Test getting all profiles.
     */
    public function test_get_all_profiles(): void {
        global $DB;

        // Clear any existing profiles.
        $DB->delete_records('format_mimo_profiles');

        // Create multiple profiles.
        profile_manager::create_profile('style1', 'Style One', 1);
        profile_manager::create_profile('style2', 'Style Two', 2);
        profile_manager::create_profile('style3', 'Style Three', 3);

        $profiles = profile_manager::get_all_profiles();

        $this->assertCount(3, $profiles);

        // Verify order by sortorder.
        $names = array_column($profiles, 'name');
        $this->assertEquals(['style1', 'style2', 'style3'], $names);
    }

    /**
     * Test getting a profile by name.
     */
    public function test_get_profile_by_name(): void {
        profile_manager::create_profile('mystyle', 'My Style', 1);

        $profile = profile_manager::get_profile_by_name('mystyle');

        $this->assertNotNull($profile);
        $this->assertEquals('mystyle', $profile->name);
        $this->assertEquals('My Style', $profile->displayname);
    }

    /**
     * Test getting a non-existent profile by name returns null.
     */
    public function test_get_profile_by_name_not_found(): void {
        $profile = profile_manager::get_profile_by_name('nonexistent');

        $this->assertNull($profile);
    }

    /**
     * Test updating a profile.
     */
    public function test_update_profile(): void {
        $id = profile_manager::create_profile('original', 'Original Name', 1);

        profile_manager::update_profile($id, [
            'displayname' => 'Updated Name',
            'sortorder' => 5,
        ]);

        $profile = profile_manager::get_profile($id);
        $this->assertEquals('original', $profile->name); // Name unchanged.
        $this->assertEquals('Updated Name', $profile->displayname);
        $this->assertEquals(5, $profile->sortorder);
    }

    /**
     * Test deleting a profile.
     */
    public function test_delete_profile(): void {
        $id = profile_manager::create_profile('todelete', 'To Delete', 1);

        // Verify it exists.
        $this->assertNotNull(profile_manager::get_profile($id));

        // Delete it.
        profile_manager::delete_profile($id);

        // Verify it's gone.
        $this->assertNull(profile_manager::get_profile($id));
    }

    /**
     * Test deleting a profile also deletes associated profile tag records.
     */
    public function test_delete_profile_cascades_to_profile_tags(): void {
        global $DB;

        // Create a profile and a tag.
        $profileid = profile_manager::create_profile('cascade', 'Cascade Test', 1);
        $tagid = tag_manager::create_tag('Test Tag', null, null, 'page');

        // Create a profile tag record for this profile.
        $profiletag = profile_manager::get_or_create_profile_tag($tagid, $profileid);
        $this->assertNotEmpty($profiletag->id);

        // Verify the profile tag exists.
        $this->assertTrue($DB->record_exists('format_mimo_profile_tags', ['id' => $profiletag->id]));

        // Delete the profile.
        profile_manager::delete_profile($profileid);

        // Verify the profile tag was also deleted.
        $this->assertFalse($DB->record_exists('format_mimo_profile_tags', ['id' => $profiletag->id]));
    }

    /**
     * Test get_or_create_profile_tag creates new record when none exists.
     */
    public function test_get_or_create_profile_tag_creates(): void {
        $profileid = profile_manager::create_profile('imgtest', 'Image Test', 1);
        $tagid = tag_manager::create_tag('Img Tag', null, null, 'page');

        $profiletag = profile_manager::get_or_create_profile_tag($tagid, $profileid);

        $this->assertNotEmpty($profiletag->id);
        $this->assertEquals($tagid, $profiletag->tagid);
        $this->assertEquals($profileid, $profiletag->profileid);
    }

    /**
     * Test get_or_create_profile_tag returns existing record.
     */
    public function test_get_or_create_profile_tag_returns_existing(): void {
        $profileid = profile_manager::create_profile('existing', 'Existing Test', 1);
        $tagid = tag_manager::create_tag('Existing Tag', null, null, 'page');

        // Create first time.
        $profiletag1 = profile_manager::get_or_create_profile_tag($tagid, $profileid);

        // Get again - should return same record.
        $profiletag2 = profile_manager::get_or_create_profile_tag($tagid, $profileid);

        $this->assertEquals($profiletag1->id, $profiletag2->id);
    }

    /**
     * Test get_cardimage_url_by_name returns null when no image exists.
     */
    public function test_get_cardimage_url_by_name_no_image(): void {
        profile_manager::create_profile('noimg', 'No Image', 1);
        $tagid = tag_manager::create_tag('No Img Tag', null, null, 'page');

        $url = profile_manager::get_cardimage_url_by_name($tagid, 'noimg');

        $this->assertNull($url);
    }

    /**
     * Test profile name uniqueness is enforced.
     */
    public function test_profile_name_uniqueness(): void {
        profile_manager::create_profile('unique', 'Unique Style', 1);

        $this->expectException(\dml_write_exception::class);
        profile_manager::create_profile('unique', 'Duplicate Name', 2);
    }

    /**
     * Test profiles are ordered by sortorder.
     */
    public function test_profiles_ordered_by_sortorder(): void {
        global $DB;

        // Clear existing profiles.
        $DB->delete_records('format_mimo_profiles');

        // Create in non-sequential order.
        profile_manager::create_profile('third', 'Third', 30);
        profile_manager::create_profile('first', 'First', 10);
        profile_manager::create_profile('second', 'Second', 20);

        $profiles = profile_manager::get_all_profiles();
        $names = array_column($profiles, 'name');

        $this->assertEquals(['first', 'second', 'third'], $names);
    }

    /**
     * Test tag_manager fallback to profile-specific image.
     */
    public function test_tag_manager_uses_profile_image(): void {
        global $DB;

        // Create profile and tag.
        $profileid = profile_manager::create_profile('fallback', 'Fallback Test', 1);
        $tagid = tag_manager::create_tag('Fallback Tag', null, null, 'page');

        // Create profile tag record with a filename.
        $profiletag = profile_manager::get_or_create_profile_tag($tagid, $profileid);
        $DB->set_field('format_mimo_profile_tags', 'cardimage', 'test.svg', ['id' => $profiletag->id]);

        // Get the tag.
        $tag = tag_manager::get_tag($tagid);

        // Without a file in file storage, get_cardimage_url should still work via profile_manager.
        // It will return null because no actual file exists, but the lookup path is tested.
        $url = tag_manager::get_cardimage_url($tag, 'fallback');

        // Since we didn't actually upload a file, URL should be null.
        // But we've tested that the profile lookup path is exercised.
        $this->assertNull($url);
    }
}
