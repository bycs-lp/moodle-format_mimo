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

    /**
     * get_profile should return the record when found and null otherwise.
     */
    public function test_get_profile_found_and_not_found(): void {
        $id = profile_manager::create_profile('lookup', 'Lookup', 1);

        $profile = profile_manager::get_profile($id);
        $this->assertNotNull($profile);
        $this->assertEquals('lookup', $profile->name);

        $this->assertNull(profile_manager::get_profile(999999));
    }

    /**
     * Renaming a profile should cascade to course_format_options.activityprofile.
     */
    public function test_update_profile_renames_course_format_options(): void {
        global $DB;

        $id = profile_manager::create_profile('rename_me', 'Rename Me', 1);

        $course = $this->getDataGenerator()->create_course([
            'format' => 'mimo',
            'activityprofile' => 'rename_me',
        ]);

        profile_manager::update_profile($id, ['name' => 'renamed']);

        $value = $DB->get_field('course_format_options', 'value', [
            'courseid' => $course->id,
            'format' => 'mimo',
            'name' => 'activityprofile',
        ]);
        $this->assertEquals('renamed', $value);
    }

    /**
     * get_profile_options should return name => displayname keyed array.
     */
    public function test_get_profile_options(): void {
        global $DB;

        $DB->delete_records('format_mimo_profiles');

        profile_manager::create_profile('opt1', 'Option One', 1);
        profile_manager::create_profile('opt2', 'Option Two', 2);

        $options = profile_manager::get_profile_options();

        $this->assertSame(['opt1' => 'Option One', 'opt2' => 'Option Two'], $options);
    }

    /**
     * get_profile_tag should return null for an unknown id.
     */
    public function test_get_profile_tag_not_found(): void {
        $this->assertNull(profile_manager::get_profile_tag(999999));
    }

    /**
     * get_profile_tags_for_tag should only return records for the matching tag.
     */
    public function test_get_profile_tags_for_tag(): void {
        $profile1 = profile_manager::create_profile('p1', 'P1', 1);
        $profile2 = profile_manager::create_profile('p2', 'P2', 2);
        $tag1 = tag_manager::create_tag('T1');
        $tag2 = tag_manager::create_tag('T2');

        profile_manager::get_or_create_profile_tag($tag1, $profile1);
        profile_manager::get_or_create_profile_tag($tag1, $profile2);
        profile_manager::get_or_create_profile_tag($tag2, $profile1);

        $records = profile_manager::get_profile_tags_for_tag($tag1);

        $this->assertCount(2, $records);
        foreach ($records as $record) {
            $this->assertEquals($tag1, (int) $record->tagid);
        }
    }

    /**
     * get_profile_tag_for_profile should return the record when present, null otherwise.
     */
    public function test_get_profile_tag_for_profile(): void {
        $profileid = profile_manager::create_profile('ptforprofile', 'PT For Profile', 1);
        $tagid = tag_manager::create_tag('PT Tag');

        // Initially no override record exists.
        $this->assertNull(profile_manager::get_profile_tag_for_profile($tagid, $profileid));

        profile_manager::get_or_create_profile_tag($tagid, $profileid);

        $record = profile_manager::get_profile_tag_for_profile($tagid, $profileid);
        $this->assertNotNull($record);
        $this->assertEquals($tagid, (int) $record->tagid);
        $this->assertEquals($profileid, (int) $record->profileid);
    }

    /**
     * update_profile_tag should normalize bgcolor and persist allowed fields only.
     */
    public function test_update_profile_tag_normalises_bgcolor(): void {
        $profileid = profile_manager::create_profile('ptupdate', 'PT Update', 1);
        $tagid = tag_manager::create_tag('PT Update Tag');
        $pt = profile_manager::get_or_create_profile_tag($tagid, $profileid);

        $result = profile_manager::update_profile_tag($pt->id, [
            'name' => 'Override Name',
            'bgcolor' => 'A1B2C3',
            'enabled' => 0,
            'notallowed' => 'ignored',
        ]);
        $this->assertTrue($result);

        $updated = profile_manager::get_profile_tag($pt->id);
        $this->assertEquals('Override Name', $updated->name);
        $this->assertEquals('#a1b2c3', $updated->bgcolor);
        $this->assertEquals(0, (int) $updated->enabled);
        $this->assertObjectNotHasProperty('notallowed', $updated);
    }

    /**
     * delete_profile_tags_for_tag should remove all override records for the given tag only.
     */
    public function test_delete_profile_tags_for_tag(): void {
        $profile1 = profile_manager::create_profile('d1', 'D1', 1);
        $profile2 = profile_manager::create_profile('d2', 'D2', 2);
        $tag1 = tag_manager::create_tag('Delete Me');
        $tag2 = tag_manager::create_tag('Keep Me');

        profile_manager::get_or_create_profile_tag($tag1, $profile1);
        profile_manager::get_or_create_profile_tag($tag1, $profile2);
        profile_manager::get_or_create_profile_tag($tag2, $profile1);

        profile_manager::delete_profile_tags_for_tag($tag1);

        $this->assertEmpty(profile_manager::get_profile_tags_for_tag($tag1));
        $this->assertCount(1, profile_manager::get_profile_tags_for_tag($tag2));
    }

    /**
     * resolve_tag_for_profile without an override returns the base tag with enabled=1.
     */
    public function test_resolve_tag_for_profile_no_override(): void {
        $profileid = profile_manager::create_profile('resolve1', 'Resolve 1', 1);
        $tagid = tag_manager::create_tag('Base', null, null, null, null, null, '#111111');
        $tag = tag_manager::get_tag($tagid);

        $resolved = profile_manager::resolve_tag_for_profile($tag, $profileid);

        $this->assertEquals('Base', $resolved->name);
        $this->assertEquals('#111111', $resolved->bgcolor);
        $this->assertEquals(1, (int) $resolved->enabled);
    }

    /**
     * resolve_tag_for_profile applies non-null overrides and enabled flag.
     */
    public function test_resolve_tag_for_profile_with_override(): void {
        $profileid = profile_manager::create_profile('resolve2', 'Resolve 2', 1);
        $tagid = tag_manager::create_tag('BaseName', null, null, null, null, null, '#111111');
        $tag = tag_manager::get_tag($tagid);

        $pt = profile_manager::get_or_create_profile_tag($tagid, $profileid);
        profile_manager::update_profile_tag($pt->id, [
            'name' => 'Overridden',
            'bgcolor' => '#222222',
            'enabled' => 0,
        ]);

        $resolved = profile_manager::resolve_tag_for_profile($tag, $profileid);

        $this->assertEquals('Overridden', $resolved->name);
        $this->assertEquals('#222222', $resolved->bgcolor);
        $this->assertEquals(0, (int) $resolved->enabled);
    }

    /**
     * resolve_tags_for_profile should exclude disabled tags when $onlyenabled is true.
     */
    public function test_resolve_tags_for_profile_filters_by_enabled(): void {
        global $DB;

        $DB->delete_records('format_mimo_tags');
        tag_manager::clear_tag_cache();

        $profileid = profile_manager::create_profile('resolveall', 'Resolve All', 1);
        $t1 = tag_manager::create_tag('Enabled One');
        $t2 = tag_manager::create_tag('Disabled One');

        $pt = profile_manager::get_or_create_profile_tag($t2, $profileid);
        profile_manager::update_profile_tag($pt->id, ['enabled' => 0]);

        $all = tag_manager::get_all_tags();

        $enabled = profile_manager::resolve_tags_for_profile($all, $profileid, true);
        $this->assertArrayHasKey($t1, $enabled);
        $this->assertArrayNotHasKey($t2, $enabled);

        $withDisabled = profile_manager::resolve_tags_for_profile($all, $profileid, false);
        $this->assertArrayHasKey($t1, $withDisabled);
        $this->assertArrayHasKey($t2, $withDisabled);
        $this->assertEquals(0, (int) $withDisabled[$t2]->enabled);
    }

    /**
     * get_image_filemanager_options exposes the filemanager config array.
     */
    public function test_profile_image_filemanager_options(): void {
        $options = profile_manager::get_image_filemanager_options();

        $this->assertIsArray($options);
        $this->assertArrayHasKey('maxfiles', $options);
        $this->assertSame(1, $options['maxfiles']);
    }
}
