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
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo;

/**
 * Profile manager test case.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_mimo\profile_manager
 */
final class profile_manager_test extends \advanced_testcase {
    /** @var profile_manager Profile manager instance. */
    private profile_manager $profilemanager;

    /** @var tag_manager Tag manager instance. */
    private tag_manager $tagmanager;

    /**
     * Set up before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->profilemanager = \core\di::get(profile_manager::class);
        $this->tagmanager = \core\di::get(tag_manager::class);
    }

    /**
     * Test creating a profile.
     */
    public function test_create_profile(): void {
        $id = $this->profilemanager->create_profile('teststyle', 'Test Style', 1);

        $this->assertNotEmpty($id);
        $this->assertIsInt($id);

        // Verify the profile was created.
        $profile = $this->profilemanager->get_profile($id);
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
        $this->profilemanager->create_profile('style1', 'Style One', 1);
        $this->profilemanager->create_profile('style2', 'Style Two', 2);
        $this->profilemanager->create_profile('style3', 'Style Three', 3);

        $profiles = $this->profilemanager->get_all_profiles();

        $this->assertCount(3, $profiles);

        // Verify order by sortorder.
        $names = array_column($profiles, 'name');
        $this->assertEquals(['style1', 'style2', 'style3'], $names);
    }

    /**
     * Test getting a profile by name.
     */
    public function test_get_profile_by_name(): void {
        $this->profilemanager->create_profile('mystyle', 'My Style', 1);

        $profile = $this->profilemanager->get_profile_by_name('mystyle');

        $this->assertNotNull($profile);
        $this->assertEquals('mystyle', $profile->name);
        $this->assertEquals('My Style', $profile->displayname);
    }

    /**
     * Test getting a non-existent profile by name returns null.
     */
    public function test_get_profile_by_name_not_found(): void {
        $profile = $this->profilemanager->get_profile_by_name('nonexistent');

        $this->assertNull($profile);
    }

    /**
     * Test updating a profile.
     */
    public function test_update_profile(): void {
        $id = $this->profilemanager->create_profile('original', 'Original Name', 1);

        $this->profilemanager->update_profile($id, [
            'displayname' => 'Updated Name',
            'sortorder' => 5,
        ]);

        $profile = $this->profilemanager->get_profile($id);
        $this->assertEquals('original', $profile->name); // Name unchanged.
        $this->assertEquals('Updated Name', $profile->displayname);
        $this->assertEquals(5, $profile->sortorder);
    }

    /**
     * Test deleting a profile.
     */
    public function test_delete_profile(): void {
        $id = $this->profilemanager->create_profile('todelete', 'To Delete', 1);

        // Verify it exists.
        $this->assertNotNull($this->profilemanager->get_profile($id));

        // Delete it.
        $this->profilemanager->delete_profile($id);

        // Verify it's gone.
        $this->assertNull($this->profilemanager->get_profile($id));
    }

    /**
     * Test deleting a profile also deletes associated profile tag records.
     */
    public function test_delete_profile_cascades_to_profile_tags(): void {
        global $DB;

        // Create a profile and a tag.
        $profileid = $this->profilemanager->create_profile('cascade', 'Cascade Test', 1);
        $tagid = $this->tagmanager->create_tag('Test Tag', null, null, 'page');

        // Create a profile tag record for this profile.
        $profiletag = $this->profilemanager->get_or_create_profile_tag($tagid, $profileid);
        $this->assertNotEmpty($profiletag->id);

        // Verify the profile tag exists.
        $this->assertTrue($DB->record_exists('format_mimo_profile_tags', ['id' => $profiletag->id]));

        // Delete the profile.
        $this->profilemanager->delete_profile($profileid);

        // Verify the profile tag was also deleted.
        $this->assertFalse($DB->record_exists('format_mimo_profile_tags', ['id' => $profiletag->id]));
    }

    /**
     * Test get_or_create_profile_tag creates new record when none exists.
     */
    public function test_get_or_create_profile_tag_creates(): void {
        $profileid = $this->profilemanager->create_profile('imgtest', 'Image Test', 1);
        $tagid = $this->tagmanager->create_tag('Img Tag', null, null, 'page');

        $profiletag = $this->profilemanager->get_or_create_profile_tag($tagid, $profileid);

        $this->assertNotEmpty($profiletag->id);
        $this->assertEquals($tagid, $profiletag->tagid);
        $this->assertEquals($profileid, $profiletag->profileid);
    }

    /**
     * Test get_or_create_profile_tag returns existing record.
     */
    public function test_get_or_create_profile_tag_returns_existing(): void {
        $profileid = $this->profilemanager->create_profile('existing', 'Existing Test', 1);
        $tagid = $this->tagmanager->create_tag('Existing Tag', null, null, 'page');

        // Create first time.
        $profiletag1 = $this->profilemanager->get_or_create_profile_tag($tagid, $profileid);

        // Get again - should return same record.
        $profiletag2 = $this->profilemanager->get_or_create_profile_tag($tagid, $profileid);

        $this->assertEquals($profiletag1->id, $profiletag2->id);
    }

    /**
     * Test get_cardimage_url_by_name returns null when no image exists.
     */
    public function test_get_cardimage_url_by_name_no_image(): void {
        $this->profilemanager->create_profile('noimg', 'No Image', 1);
        $tagid = $this->tagmanager->create_tag('No Img Tag', null, null, 'page');

        $url = $this->profilemanager->get_cardimage_url_by_name($tagid, 'noimg');

        $this->assertNull($url);
    }

    /**
     * Test profile name uniqueness is enforced.
     */
    public function test_profile_name_uniqueness(): void {
        $this->profilemanager->create_profile('unique', 'Unique Style', 1);

        $this->expectException(\dml_write_exception::class);
        $this->profilemanager->create_profile('unique', 'Duplicate Name', 2);
    }

    /**
     * Test profiles are ordered by sortorder.
     */
    public function test_profiles_ordered_by_sortorder(): void {
        global $DB;

        // Clear existing profiles.
        $DB->delete_records('format_mimo_profiles');

        // Create in non-sequential order.
        $this->profilemanager->create_profile('third', 'Third', 30);
        $this->profilemanager->create_profile('first', 'First', 10);
        $this->profilemanager->create_profile('second', 'Second', 20);

        $profiles = $this->profilemanager->get_all_profiles();
        $names = array_column($profiles, 'name');

        $this->assertEquals(['first', 'second', 'third'], $names);
    }

    /**
     * When a profile has a stored card image, the tag_manager lookup must resolve
     * to that per-profile file (not to the tag-level default).
     */
    public function test_tag_manager_uses_profile_image(): void {
        global $DB;

        // Create profile and tag.
        $profileid = $this->profilemanager->create_profile('fallback', 'Fallback Test', 1);
        $tagid = $this->tagmanager->create_tag('Fallback Tag', 'base.svg', 'base-s.svg', 'page');

        // Create the profile_tag record and store an actual file in the profile card area.
        $profiletag = $this->profilemanager->get_or_create_profile_tag($tagid, $profileid);
        $filename = 'profilecard.svg';
        get_file_storage()->create_file_from_string(
            [
                'contextid' => \core\context\system::instance()->id,
                'component' => 'format_mimo',
                'filearea'  => profile_manager::FILEAREA_PROFILE_CARDIMAGE,
                'itemid'    => $profiletag->id,
                'filepath'  => '/',
                'filename'  => $filename,
            ],
            '<svg/>',
        );
        $DB->set_field('format_mimo_profile_tags', 'cardimage', $filename, ['id' => $profiletag->id]);

        // Invalidate caches so the request-level URL cache is not hit.
        $this->tagmanager->clear_tag_cache();

        $tag = $this->tagmanager->get_tag($tagid);
        $url = $this->tagmanager->get_cardimage_url($tag, 'fallback');

        $this->assertNotNull($url, 'Profile-specific card image must resolve to a URL');
        $this->assertStringContainsString($filename, $url->out());
        $this->assertStringContainsString(
            profile_manager::FILEAREA_PROFILE_CARDIMAGE,
            $url->out(),
        );
    }

    /**
     * get_profile should return the record when found and null otherwise.
     */
    public function test_get_profile_found_and_not_found(): void {
        $id = $this->profilemanager->create_profile('lookup', 'Lookup', 1);

        $profile = $this->profilemanager->get_profile($id);
        $this->assertNotNull($profile);
        $this->assertEquals('lookup', $profile->name);

        $this->assertNull($this->profilemanager->get_profile(999999));
    }

    /**
     * Renaming a profile should cascade to course_format_options.activityprofile.
     */
    public function test_update_profile_renames_course_format_options(): void {
        global $DB;

        $id = $this->profilemanager->create_profile('rename_me', 'Rename Me', 1);

        $course = $this->getDataGenerator()->create_course([
            'format' => 'mimo',
            'activityprofile' => 'rename_me',
        ]);

        $this->profilemanager->update_profile($id, ['name' => 'renamed']);

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

        $this->profilemanager->create_profile('opt1', 'Option One', 1);
        $this->profilemanager->create_profile('opt2', 'Option Two', 2);

        $options = $this->profilemanager->get_profile_options();

        $this->assertSame(['opt1' => 'Option One', 'opt2' => 'Option Two'], $options);
    }

    /**
     * get_profile_tag should return null for an unknown id.
     */
    public function test_get_profile_tag_not_found(): void {
        $this->assertNull($this->profilemanager->get_profile_tag(999999));
    }

    /**
     * get_profile_tags_for_tag should only return records for the matching tag.
     */
    public function test_get_profile_tags_for_tag(): void {
        $profile1 = $this->profilemanager->create_profile('p1', 'P1', 1);
        $profile2 = $this->profilemanager->create_profile('p2', 'P2', 2);
        $tag1 = $this->tagmanager->create_tag('T1');
        $tag2 = $this->tagmanager->create_tag('T2');

        $this->profilemanager->get_or_create_profile_tag($tag1, $profile1);
        $this->profilemanager->get_or_create_profile_tag($tag1, $profile2);
        $this->profilemanager->get_or_create_profile_tag($tag2, $profile1);

        $records = $this->profilemanager->get_profile_tags_for_tag($tag1);

        $this->assertCount(2, $records);
        foreach ($records as $record) {
            $this->assertEquals($tag1, (int) $record->tagid);
        }
    }

    /**
     * get_profile_tag_for_profile should return the record when present, null otherwise.
     */
    public function test_get_profile_tag_for_profile(): void {
        $profileid = $this->profilemanager->create_profile('ptforprofile', 'PT For Profile', 1);
        $tagid = $this->tagmanager->create_tag('PT Tag');

        // Initially no override record exists.
        $this->assertNull($this->profilemanager->get_profile_tag_for_profile($tagid, $profileid));

        $this->profilemanager->get_or_create_profile_tag($tagid, $profileid);

        $record = $this->profilemanager->get_profile_tag_for_profile($tagid, $profileid);
        $this->assertNotNull($record);
        $this->assertEquals($tagid, (int) $record->tagid);
        $this->assertEquals($profileid, (int) $record->profileid);
    }

    /**
     * update_profile_tag should normalize bgcolor and persist allowed fields only.
     */
    public function test_update_profile_tag_normalises_bgcolor(): void {
        $profileid = $this->profilemanager->create_profile('ptupdate', 'PT Update', 1);
        $tagid = $this->tagmanager->create_tag('PT Update Tag');
        $pt = $this->profilemanager->get_or_create_profile_tag($tagid, $profileid);

        $result = $this->profilemanager->update_profile_tag($pt->id, [
            'name' => 'Override Name',
            'bgcolor' => 'A1B2C3',
            'enabled' => 0,
            'notallowed' => 'ignored',
        ]);
        $this->assertTrue($result);

        $updated = $this->profilemanager->get_profile_tag($pt->id);
        $this->assertEquals('Override Name', $updated->name);
        $this->assertEquals('#a1b2c3', $updated->bgcolor);
        $this->assertEquals(0, (int) $updated->enabled);
        $this->assertObjectNotHasProperty('notallowed', $updated);
    }

    /**
     * delete_profile_tags_for_tag should remove all override records for the given tag only.
     */
    public function test_delete_profile_tags_for_tag(): void {
        $profile1 = $this->profilemanager->create_profile('d1', 'D1', 1);
        $profile2 = $this->profilemanager->create_profile('d2', 'D2', 2);
        $tag1 = $this->tagmanager->create_tag('Delete Me');
        $tag2 = $this->tagmanager->create_tag('Keep Me');

        $this->profilemanager->get_or_create_profile_tag($tag1, $profile1);
        $this->profilemanager->get_or_create_profile_tag($tag1, $profile2);
        $this->profilemanager->get_or_create_profile_tag($tag2, $profile1);

        $this->profilemanager->delete_profile_tags_for_tag($tag1);

        $this->assertEmpty($this->profilemanager->get_profile_tags_for_tag($tag1));
        $this->assertCount(1, $this->profilemanager->get_profile_tags_for_tag($tag2));
    }

    /**
     * resolve_tag_for_profile without a materialized row resolves as disabled.
     */
    public function test_resolve_tag_for_profile_no_override(): void {
        $profileid = $this->profilemanager->create_profile('resolve1', 'Resolve 1', 1);
        $tagid = $this->tagmanager->create_tag('Base', null, null, null, null, null, '#111111');
        $tag = $this->tagmanager->get_tag($tagid);

        $resolved = $this->profilemanager->resolve_tag_for_profile($tag, $profileid);

        // Anchor values are still exposed for greyed-out display.
        $this->assertEquals('Base', $resolved->name);
        $this->assertEquals('#111111', $resolved->bgcolor);
        $this->assertEquals(0, (int) $resolved->enabled);
    }

    /**
     * resolve_tag_for_profile applies non-null overrides and enabled flag.
     */
    public function test_resolve_tag_for_profile_with_override(): void {
        $profileid = $this->profilemanager->create_profile('resolve2', 'Resolve 2', 1);
        $tagid = $this->tagmanager->create_tag('BaseName', null, null, null, null, null, '#111111');
        $tag = $this->tagmanager->get_tag($tagid);

        $pt = $this->profilemanager->get_or_create_profile_tag($tagid, $profileid);
        $this->profilemanager->update_profile_tag($pt->id, [
            'name' => 'Overridden',
            'bgcolor' => '#222222',
            'enabled' => 0,
        ]);

        $resolved = $this->profilemanager->resolve_tag_for_profile($tag, $profileid);

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
        $this->tagmanager->clear_tag_cache();

        $profileid = $this->profilemanager->create_profile('resolveall', 'Resolve All', 1);
        $t1 = $this->tagmanager->create_tag('Enabled One');
        $t2 = $this->tagmanager->create_tag('Disabled One');

        $this->profilemanager->materialize_profile_tag($t1, $profileid, [], true);
        $pt = $this->profilemanager->get_or_create_profile_tag($t2, $profileid);
        $this->profilemanager->update_profile_tag($pt->id, ['enabled' => 0]);

        $all = $this->tagmanager->get_all_tags();

        $enabled = $this->profilemanager->resolve_tags_for_profile($all, $profileid, true);
        $this->assertArrayHasKey($t1, $enabled);
        $this->assertArrayNotHasKey($t2, $enabled);

        $withdisabled = $this->profilemanager->resolve_tags_for_profile($all, $profileid, false);
        $this->assertArrayHasKey($t1, $withdisabled);
        $this->assertArrayHasKey($t2, $withdisabled);
        $this->assertEquals(0, (int) $withdisabled[$t2]->enabled);
    }

    /**
     * get_image_filemanager_options exposes the filemanager config array.
     */
    public function test_profile_image_filemanager_options(): void {
        $options = $this->profilemanager->get_image_filemanager_options();

        $this->assertIsArray($options);
        $this->assertArrayHasKey('maxfiles', $options);
        $this->assertSame(1, $options['maxfiles']);
    }

    /**
     * A tag without a row in a set is disabled but shows anchor values.
     */
    public function test_resolve_missing_row_is_disabled(): void {
        $tagid = $this->tagmanager->create_tag('Solo', null, null, 'page');
        $tag = $this->tagmanager->get_tag($tagid);
        $profileid = $this->profilemanager->create_profile('emptyset', 'Empty set');

        $resolved = $this->profilemanager->resolve_tag_for_profile($tag, $profileid);

        $this->assertSame(0, (int) $resolved->enabled);
        $this->assertSame('Solo', $resolved->name);
    }

    /**
     * materialize_profile_tag fills every override field from the anchor.
     */
    public function test_materialize_profile_tag_fills_all_fields_from_anchor(): void {
        $tagid = $this->tagmanager->create_tag('Anchor', null, null, 'page', 'quiz', null, '#112233', 'lower', 'bigger');
        $profileid = $this->profilemanager->create_profile('mset', 'M set');

        $pt = $this->profilemanager->materialize_profile_tag($tagid, $profileid);

        $this->assertSame('Anchor', $pt->name);
        $this->assertSame('#112233', $pt->bgcolor);
        $this->assertSame('page', $pt->activitytype1);
        $this->assertSame('quiz', $pt->activitytype2);
        $this->assertSame('lower', $pt->imgplacement);
        $this->assertSame('bigger', $pt->imgsize);
        $this->assertSame(0, (int) $pt->enabled);
    }

    /**
     * materialize_profile_tag applies explicit values and enabled flag; repeat
     * calls keep existing state.
     */
    public function test_materialize_profile_tag_values_and_enabled_override(): void {
        $tagid = $this->tagmanager->create_tag('Anchor2', null, null, 'page');
        $profileid = $this->profilemanager->create_profile('mset2', 'M set 2');

        $pt = $this->profilemanager->materialize_profile_tag(
            $tagid,
            $profileid,
            ['name' => 'Renamed'],
            true
        );

        $this->assertSame('Renamed', $pt->name);
        $this->assertSame(1, (int) $pt->enabled);

        // Second call keeps enabled and existing values when not overridden.
        $pt2 = $this->profilemanager->materialize_profile_tag($tagid, $profileid);
        $this->assertSame('Renamed', $pt2->name);
        $this->assertSame(1, (int) $pt2->enabled);
    }

    /**
     * materialize_profile_tag fills NULL holes in a legacy row without
     * clobbering existing values.
     */
    public function test_materialize_fills_null_holes_in_existing_row(): void {
        global $DB;
        $tagid = $this->tagmanager->create_tag('Holey', null, null, 'page', null, null, '#445566');
        $profileid = $this->profilemanager->create_profile('mset3', 'M set 3');
        // Simulate a legacy NULL-holed row.
        $DB->insert_record('format_mimo_profile_tags', (object) [
            'tagid' => $tagid, 'profileid' => $profileid,
            'name' => 'Kept', 'bgcolor' => null,
            'activitytype1' => null, 'activitytype2' => null, 'activitytype3' => null,
            'enabled' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $this->profilemanager->clear_request_caches();

        $pt = $this->profilemanager->materialize_profile_tag($tagid, $profileid);

        $this->assertSame('Kept', $pt->name);
        $this->assertSame('#445566', $pt->bgcolor);
        $this->assertSame('page', $pt->activitytype1);
        $this->assertSame(1, (int) $pt->enabled);
    }

    /**
     * get_default_profile_name returns the first global profile by sortorder.
     */
    public function test_get_default_profile_name_first_global_by_sortorder(): void {
        global $DB;
        $DB->delete_records('format_mimo_profiles');
        $this->profilemanager->clear_request_caches();
        $this->assertSame('', $this->profilemanager->get_default_profile_name());

        $this->profilemanager->create_profile('second', 'Second', 5);
        $this->profilemanager->create_profile('first', 'First', 1);
        $this->profilemanager->create_profile('imp', 'Imported', 0, 'imported');

        $this->assertSame('first', $this->profilemanager->get_default_profile_name());
    }

    /**
     * initialize_default_profiles creates the base profile first with fully
     * materialized, enabled rows for every tag.
     */
    public function test_initialize_default_profiles_creates_base_first(): void {
        $this->tagmanager->initialize_default_tags();
        $this->profilemanager->initialize_default_profiles();

        $base = $this->profilemanager->get_profile_by_name('base');
        $this->assertNotNull($base);
        $this->assertSame('global', $base->scope);
        $this->assertSame('base', $this->profilemanager->get_default_profile_name());

        // Every tag has a fully materialized, enabled row in the base profile.
        $tags = $this->tagmanager->get_all_tags();
        $this->assertNotEmpty($tags);
        foreach ($tags as $tag) {
            $pt = $this->profilemanager->get_profile_tag_for_profile((int) $tag->id, (int) $base->id);
            $this->assertNotNull($pt, "Tag {$tag->name} missing base row");
            $this->assertNotNull($pt->name);
            $this->assertNotNull($pt->activitytype1);
            $this->assertSame(1, (int) $pt->enabled);
        }
    }

    /**
     * Every seeded default profile must end up usable: its tags enabled (unless
     * the overrides disable them on purpose) and each enabled tag showing an
     * image. Regression test — override-seeded rows used to stay disabled and
     * the base set used to end up with no artwork at all, because fresh
     * profile_tags rows materialize as disabled and image resolution is strict
     * per set with no fallback to the anchor file area.
     */
    public function test_initialize_default_profiles_seeds_enabled_tags_with_images(): void {
        $this->tagmanager->initialize_default_tags();
        $this->profilemanager->initialize_default_profiles();

        $tags = $this->tagmanager->get_all_tags();

        foreach ($this->profilemanager->get_global_profiles() as $profile) {
            $enabledcount = 0;
            foreach ($tags as $tag) {
                $pt = $this->profilemanager->get_profile_tag_for_profile((int) $tag->id, (int) $profile->id);
                $this->assertNotNull($pt, "Tag {$tag->name} missing row in {$profile->name}");
                if (!$pt->enabled) {
                    continue;
                }
                $enabledcount++;
                $this->assertNotNull(
                    $this->profilemanager->get_cardimage_url((int) $tag->id, (int) $profile->id),
                    "Tag {$tag->name} has no card image in profile {$profile->name}"
                );
            }
            $this->assertGreaterThan(0, $enabledcount, "Profile {$profile->name} has no enabled tags");
        }
    }

    /**
     * materialize_all_profile_tags creates missing rows for every combination.
     */
    public function test_materialize_all_profile_tags_fills_gaps(): void {
        $profileid = $this->profilemanager->create_profile('gapset', 'Gap set');
        $tagid = $this->tagmanager->create_tag('Gappy', null, null, 'page');

        $this->profilemanager->materialize_all_profile_tags(true);

        $pt = $this->profilemanager->get_profile_tag_for_profile($tagid, $profileid);
        $this->assertNotNull($pt);
        $this->assertSame('Gappy', $pt->name);
        $this->assertSame(1, (int) $pt->enabled);
    }

    /**
     * copy_base_images_to_profile_tags copies anchor images into empty profile areas.
     */
    public function test_copy_base_images_to_profile_tags(): void {
        $this->profilemanager->create_profile('cpset', 'Copy set');
        $tagid = $this->tagmanager->create_tag('WithImg', null, null, 'page');

        $fs = get_file_storage();
        $ctx = \core\context\system::instance()->id;
        $fs->create_file_from_string([
            'contextid' => $ctx, 'component' => 'format_mimo',
            'filearea' => tag_manager::FILEAREA_CARDIMAGE,
            'itemid' => $tagid, 'filepath' => '/', 'filename' => 'card.png',
        ], 'png');

        $this->profilemanager->materialize_all_profile_tags(true);
        $this->profilemanager->copy_base_images_to_profile_tags();

        $tag = $this->tagmanager->get_tag($tagid);
        $url = $this->tagmanager->get_cardimage_url($tag, 'cpset');
        $this->assertNotNull($url, 'Resolved base image was not copied into the profile area');
    }

    /* ==================================== *
     * Imported profile lifecycle.          *
     * ==================================== */

    /**
     * promote_profile_to_global flips the profile scope, leaves course references
     * intact, and cascades promotion to any imported tags that have profile_tag rows.
     */
    public function test_promote_profile_to_global_cascades_to_imported_tags(): void {
        global $DB;

        $importedprofile = $this->profilemanager->create_profile(
            'imp_profile',
            'Imported Profile',
            99,
            'imported',
        );
        // Imported tag attached to this profile via a profile_tag row.
        $importedtagid = $this->tagmanager->create_tag(
            'ImpTag',
            'i.svg',
            'i-s.svg',
            'page',
            null,
            null,
            null,
            'center',
            'normal',
            'imported',
        );
        // Global tag attached to the same profile must not be re-promoted (no-op).
        $globaltagid = $this->tagmanager->create_tag('GlobalTag', 'g.svg', 'g-s.svg', 'page');
        $this->profilemanager->get_or_create_profile_tag($importedtagid, $importedprofile);
        $this->profilemanager->get_or_create_profile_tag($globaltagid, $importedprofile);

        $this->profilemanager->promote_profile_to_global($importedprofile);

        $this->assertSame(
            'global',
            $DB->get_field('format_mimo_profiles', 'scope', ['id' => $importedprofile]),
        );
        $this->assertSame(
            'global',
            $DB->get_field('format_mimo_tags', 'scope', ['id' => $importedtagid]),
            'Imported tags referenced by the promoted profile must also be promoted',
        );
        $this->assertSame(
            'global',
            $DB->get_field('format_mimo_tags', 'scope', ['id' => $globaltagid]),
            'Already-global tags stay global',
        );
    }

    /**
     * cleanup_orphaned_imported_profiles deletes imported profiles that are not
     * referenced by any course's activityprofile option, and leaves the rest alone.
     */
    public function test_cleanup_orphaned_imported_profiles(): void {
        global $DB;

        $orphanid = $this->profilemanager->create_profile('imp_orphan', 'Orphan', 99, 'imported');
        $usedid = $this->profilemanager->create_profile('imp_used', 'Used', 98, 'imported');
        $globalunused = $this->profilemanager->create_profile('glob_unused', 'GlobalUnused', 97, 'global');

        // Hook $usedid into a course by directly inserting its activityprofile
        // option row. This bypasses the format's value-allowlist, which does not
        // include imported profiles for programmatic set.
        $course = $this->getDataGenerator()->create_course(['format' => 'mimo']);
        $DB->set_field_select(
            'course_format_options',
            'value',
            'imp_used',
            "format = 'mimo' AND name = 'activityprofile' AND courseid = :courseid",
            ['courseid' => $course->id],
        );

        $this->profilemanager->cleanup_orphaned_imported_profiles();

        $this->assertFalse(
            $DB->record_exists('format_mimo_profiles', ['id' => $orphanid]),
            'Orphan imported profile must be deleted',
        );
        $this->assertTrue($DB->record_exists('format_mimo_profiles', ['id' => $usedid]));
        $this->assertTrue(
            $DB->record_exists('format_mimo_profiles', ['id' => $globalunused]),
            'Global profiles are out of scope for cleanup',
        );
    }
}
