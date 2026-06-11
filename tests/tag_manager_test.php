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
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo;

/**
 * Tag manager test case.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_mimo\tag_manager
 */
final class tag_manager_test extends \advanced_testcase {
    /**
     * Set up before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        tag_manager::reset_caches();
    }

    /**
     * Clean up after each test.
     */
    protected function tearDown(): void {
        global $SESSION;

        // Reset static cache references to avoid stale instances after
        // \phpunit_util::reset_all_data() resets the cache factory.
        tag_manager::reset_caches();

        // Ensure session is clean for next test.
        unset($SESSION->format_mimo_pending_tag);

        parent::tearDown();
    }

    /**
     * Test creating a tag.
     */
    public function test_create_tag(): void {
        global $DB;

        // Capture the current max sortorder so the assertion is independent
        // of how many default tags the test-site fixture contains.
        $maxbefore = $DB->get_field_sql(
            "SELECT MAX(sortorder) FROM {format_mimo_tags}"
        );
        $expectedsort = ($maxbefore !== null && $maxbefore !== false)
            ? (int) $maxbefore + 1
            : 0;

        $id = tag_manager::create_tag(
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
        $this->assertEquals($expectedsort, $tag->sortorder);
    }

    /**
     * Ensure custom colours are normalised when creating a tag.
     */
    public function test_create_tag_with_bgcolor(): void {
        $id = tag_manager::create_tag('Colourful', null, null, null, null, null, 'a1b2c3');
        $tag = tag_manager::get_tag($id);

        $this->assertEquals('#a1b2c3', $tag->bgcolor);
    }

    /**
     * Test getting all tags.
     */
    public function test_get_all_tags(): void {
        global $DB;

        // Clear any existing tags from install.
        $DB->delete_records('format_mimo_tags');
        tag_manager::clear_tag_cache();

        // Create multiple tags.
        tag_manager::create_tag('Tag 1', 'tag1.svg', 'tag1-small.svg', 'page');
        tag_manager::create_tag('Tag 2', 'tag2.svg', 'tag2-small.svg', 'quiz');
        tag_manager::create_tag('Tag 3', 'tag3.svg', 'tag3-small.svg', 'forum');

        $tags = tag_manager::get_all_tags();

        $this->assertCount(3, $tags);
    }

    /**
     * Test getting tags for a course based on its activity profile.
     */
    public function test_get_tags_for_course(): void {
        global $DB;

        // Clear any existing tags.
        $DB->delete_records('format_mimo_tags');
        tag_manager::clear_tag_cache();

        // Create tags.
        $tag1id = tag_manager::create_tag('Reading', 'reading.svg', 'reading-small.svg', 'page');
        $tag2id = tag_manager::create_tag('Video', 'video.svg', 'video-small.svg', 'url');
        $tag3id = tag_manager::create_tag('Quiz', 'quiz.svg', 'quiz-small.svg', 'quiz');

        // Ensure an 'explore' profile exists.
        $profile = profile_manager::get_profile_by_name('explore');
        if (!$profile) {
            $profileid = profile_manager::create_profile('explore', 'Explore Level');
        } else {
            $profileid = $profile->id;
        }

        // Disable 'Quiz' tag for the explore profile.
        $pt = profile_manager::get_or_create_profile_tag($tag3id, $profileid);
        profile_manager::update_profile_tag($pt->id, ['enabled' => 0]);

        // Create a course with the explore activity profile.
        $course = $this->getDataGenerator()->create_course([
            'format' => 'mimo',
            'activityprofile' => 'explore',
        ]);

        // Get tags for course — should return only enabled tags.
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
        $id = tag_manager::create_tag('Original', 'orig.svg', 'orig-small.svg', 'page');

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
        $id = tag_manager::create_tag('Colourful', null, null, null, null, null, '#445566');
        $tag = tag_manager::get_tag($id);

        $this->assertSame('#445566', tag_manager::get_tag_accent_color($tag));
    }

    /**
     * The accent resolver should fall back to the starters palette.
     */
    public function test_get_tag_accent_color_fallback_uses_palette(): void {
        $id = tag_manager::create_tag('Default Colour');
        $tag = tag_manager::get_tag($id);

        $palette = tag_manager::get_default_accent_palette();
        $this->assertContains(tag_manager::get_tag_accent_color($tag), $palette);
    }

    /**
     * Test deleting a tag.
     */
    public function test_delete_tag(): void {
        $id = tag_manager::create_tag('To Delete', 'test.svg', 'test-small.svg', 'page');

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
        $tagid = tag_manager::create_tag('Reading', 'reading.svg', 'reading-small.svg', 'page');

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
        $tagid = tag_manager::create_tag('Reading', 'reading.svg', 'reading-small.svg', 'page');

        // Assign and then unassign.
        tag_manager::assign_tag_to_cm($page->cmid, $tagid);
        $result = tag_manager::unassign_tag_from_cm($page->cmid);

        $this->assertTrue($result);

        // Verify unassignment.
        $tag = tag_manager::get_cm_tag($page->cmid);
        $this->assertFalse($tag);
    }

    /**
     * Mapping cache writes must be targeted: mutating one cm's tag must not
     * evict other cms' cached mappings (regression test for site-wide purge).
     */
    public function test_mapping_cache_is_targeted(): void {
        $course = $this->getDataGenerator()->create_course();
        $page1 = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $page2 = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $tag1id = tag_manager::create_tag('Tag One');
        $tag2id = tag_manager::create_tag('Tag Two');

        tag_manager::assign_tag_to_cm($page1->cmid, $tag1id);
        tag_manager::assign_tag_to_cm($page2->cmid, $tag2id);

        // Prime the cache for both cms.
        tag_manager::get_cm_tag($page1->cmid);
        tag_manager::get_cm_tag($page2->cmid);

        // Mutating cm1 must keep cm2's cached entry intact.
        $cache = \cache::make('format_mimo', 'activitytagmappings');
        tag_manager::assign_tag_to_cm($page1->cmid, $tag2id);
        $this->assertNotFalse($cache->get('cm_' . $page2->cmid));

        // Write-through: cm1's entry reflects the new tag without a DB roundtrip.
        $this->assertEquals($tag2id, $cache->get('cm_' . $page1->cmid));
        $this->assertEquals($tag2id, tag_manager::get_cm_tag($page1->cmid)->id);

        // Removing cm1's tag writes the sentinel and keeps cm2 cached.
        tag_manager::remove_cm_tag($page1->cmid);
        $this->assertSame(0, $cache->get('cm_' . $page1->cmid));
        $this->assertFalse(tag_manager::get_cm_tag($page1->cmid));
        $this->assertNotFalse($cache->get('cm_' . $page2->cmid));
        $this->assertEquals($tag2id, tag_manager::get_cm_tag($page2->cmid)->id);
    }

    /**
     * Deleting a tag must evict the mappings of cms that used it,
     * while unrelated cms stay cached.
     */
    public function test_delete_tag_evicts_only_affected_mappings(): void {
        $course = $this->getDataGenerator()->create_course();
        $page1 = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $page2 = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $doomedid = tag_manager::create_tag('Doomed');
        $survivorid = tag_manager::create_tag('Survivor');

        tag_manager::assign_tag_to_cm($page1->cmid, $doomedid);
        tag_manager::assign_tag_to_cm($page2->cmid, $survivorid);

        // Prime both entries.
        tag_manager::get_cm_tag($page1->cmid);
        tag_manager::get_cm_tag($page2->cmid);

        tag_manager::delete_tag($doomedid);

        $cache = \cache::make('format_mimo', 'activitytagmappings');
        // Affected mapping evicted, fresh lookup reports untagged.
        $this->assertFalse($cache->get('cm_' . $page1->cmid));
        $this->assertFalse(tag_manager::get_cm_tag($page1->cmid));
        // Unrelated mapping survives.
        $this->assertNotFalse($cache->get('cm_' . $page2->cmid));
        $this->assertEquals($survivorid, tag_manager::get_cm_tag($page2->cmid)->id);
    }

    /**
     * evict_cm_mapping() drops a single cached entry.
     */
    public function test_evict_cm_mapping(): void {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $tagid = tag_manager::create_tag('Reading');

        tag_manager::assign_tag_to_cm($page->cmid, $tagid);
        tag_manager::get_cm_tag($page->cmid);

        tag_manager::evict_cm_mapping($page->cmid);

        $cache = \cache::make('format_mimo', 'activitytagmappings');
        $this->assertFalse($cache->get('cm_' . $page->cmid));
        // A fresh lookup re-primes from the DB.
        $this->assertEquals($tagid, tag_manager::get_cm_tag($page->cmid)->id);
    }

    /**
     * Test initializing default tags.
     */
    public function test_initialize_default_tags(): void {
        global $DB;

        // Clear any existing tags from install.
        $DB->delete_records('format_mimo_tags');
        tag_manager::clear_tag_cache();

        tag_manager::initialize_default_tags();

        // Verify 12 default tags were created.
        $tags = tag_manager::get_all_tags();
        $this->assertCount(12, $tags);

        // Verify the full set of default tag names in one strict comparison.
        $tagnames = array_column($tags, 'name');
        sort($tagnames);
        $expected = [
            get_string('tag_base_apply', 'format_mimo'),
            get_string('tag_base_compose', 'format_mimo'),
            get_string('tag_base_cooperate', 'format_mimo'),
            get_string('tag_base_inform', 'format_mimo'),
            get_string('tag_base_listen', 'format_mimo'),
            get_string('tag_base_practise', 'format_mimo'),
            get_string('tag_base_present', 'format_mimo'),
            get_string('tag_base_produce', 'format_mimo'),
            get_string('tag_base_project', 'format_mimo'),
            get_string('tag_base_receive', 'format_mimo'),
            get_string('tag_base_research', 'format_mimo'),
            get_string('tag_base_test', 'format_mimo'),
        ];
        sort($expected);
        $this->assertSame($expected, $tagnames);
    }

    /**
     * Test that initialize_default_tags is idempotent.
     */
    public function test_initialize_default_tags_idempotent(): void {
        global $DB;

        // Clear any existing tags from install.
        $DB->delete_records('format_mimo_tags');
        tag_manager::clear_tag_cache();

        // Call twice.
        tag_manager::initialize_default_tags();
        tag_manager::initialize_default_tags();

        // Should still only have 12 tags.
        $tags = tag_manager::get_all_tags();
        $this->assertCount(12, $tags);
    }

    /**
     * Data provider for normalize_hex_color.
     *
     * @return array
     */
    public static function normalize_hex_color_provider(): array {
        return [
            'null input'             => [null, null],
            'empty string'           => ['', null],
            'whitespace only'        => ['   ', null],
            'hash + lowercase hex'   => ['#abcdef', '#abcdef'],
            'hash + uppercase hex'   => ['#ABCDEF', '#abcdef'],
            'no hash, 6 hex'         => ['A1B2C3', '#a1b2c3'],
            'trimmed input'          => ['  #ff0000  ', '#ff0000'],
            'mixed case'             => ['#AaBbCc', '#aabbcc'],
            'too short (3 hex)'      => ['#abc', null],
            'too short (5 hex)'      => ['#abcde', null],
            'too long (7 hex)'       => ['#abcdef0', null],
            'invalid character'      => ['#gghhii', null],
            'non-hex garbage'        => ['red', null],
        ];
    }

    /**
     * Ensure normalize_hex_color handles the full range of inputs correctly.
     *
     * @dataProvider normalize_hex_color_provider
     * @param string|null $input
     * @param string|null $expected
     */
    public function test_normalize_hex_color(?string $input, ?string $expected): void {
        $this->assertSame($expected, tag_manager::normalize_hex_color($input));
    }

    /**
     * Default accent palette should be a non-empty list of hex colours.
     */
    public function test_get_default_accent_palette(): void {
        $palette = tag_manager::get_default_accent_palette();

        $this->assertIsArray($palette);
        $this->assertNotEmpty($palette);
        foreach ($palette as $color) {
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $color);
        }
    }

    /**
     * Filemanager options should define the expected constraints.
     */
    public function test_get_image_filemanager_options(): void {
        $options = tag_manager::get_image_filemanager_options();

        $this->assertIsArray($options);
        $this->assertArrayHasKey('maxfiles', $options);
        $this->assertArrayHasKey('accepted_types', $options);
        $this->assertSame(1, $options['maxfiles']);
    }

    /**
     * has_cardimage and has_filterimage should return false for a fresh tag
     * with no stored files.
     */
    public function test_has_image_returns_false_without_files(): void {
        $tagid = tag_manager::create_tag('Fresh');

        $this->assertFalse(tag_manager::has_cardimage($tagid));
        $this->assertFalse(tag_manager::has_filterimage($tagid));
    }

    /**
     * get_cardimage_url / get_filterimage_url should return null when no image exists.
     */
    public function test_get_image_url_returns_null_without_files(): void {
        $tagid = tag_manager::create_tag('Fresh');
        $tag = tag_manager::get_tag($tagid);

        $this->assertNull(tag_manager::get_cardimage_url($tag));
        $this->assertNull(tag_manager::get_filterimage_url($tag));
    }

    /**
     * get_tag should return false when the id does not exist.
     */
    public function test_get_tag_not_found(): void {
        $this->assertFalse(tag_manager::get_tag(999999));
    }

    /**
     * get_tag_usage_counts returns counts keyed by tag id, and respects sectionid.
     */
    public function test_get_tag_usage_counts(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['format' => 'mimo', 'numsections' => 2]);
        $tagid = tag_manager::create_tag('Usage', 'u.svg', 'u-small.svg', 'page');

        $page1 = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'section' => 1]);
        $page2 = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'section' => 1]);
        $page3 = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'section' => 2]);

        tag_manager::assign_tag_to_cm($page1->cmid, $tagid);
        tag_manager::assign_tag_to_cm($page2->cmid, $tagid);
        tag_manager::assign_tag_to_cm($page3->cmid, $tagid);

        // Empty tagids list returns empty array without touching DB.
        $this->assertSame([], tag_manager::get_tag_usage_counts($course->id, []));

        // Course-wide: 3 assignments.
        $counts = tag_manager::get_tag_usage_counts($course->id, [$tagid]);
        $this->assertArrayHasKey($tagid, $counts);
        $this->assertEquals(3, (int) $counts[$tagid]);

        // Scoped to section 1: 2 assignments.
        $section1id = (int) $DB->get_field('course_sections', 'id', [
            'course' => $course->id,
            'section' => 1,
        ]);
        $counts = tag_manager::get_tag_usage_counts($course->id, [$tagid], $section1id);
        $this->assertEquals(2, (int) $counts[$tagid]);
    }

    /**
     * clear_course_tags_cache should drop the cached resolution so the next
     * lookup rebuilds from the DB.
     */
    public function test_clear_course_tags_cache(): void {
        $course = $this->getDataGenerator()->create_course(['format' => 'mimo']);
        tag_manager::create_tag('Cached', 'c.svg', 'c-small.svg', 'page');

        // Populate the cache.
        $first = tag_manager::get_tags_for_course($course->id);

        // Invalidate, then ensure it still returns data consistent with DB.
        tag_manager::clear_course_tags_cache($course->id);
        $second = tag_manager::get_tags_for_course($course->id);

        $this->assertEquals(array_keys($first), array_keys($second));
    }

    /* ================================ *
     * Imported tag lifecycle.          *
     * ================================ */

    /**
     * bind/unbind an imported tag to a course and verify `get_imported_tags_for_course`.
     */
    public function test_imported_tag_binding_lifecycle(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['format' => 'mimo']);
        $globalid = tag_manager::create_tag('GlobalTag', 'g.svg', 'g-s.svg', 'page');
        $importedid = tag_manager::create_tag(
            'ImportedTag',
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

        // Before binding: no imported tags for the course.
        $this->assertSame([], tag_manager::get_imported_tags_for_course($course->id));

        tag_manager::bind_tag_to_course($importedid, $course->id);

        // Duplicate binding is a no-op (no exception, no second row).
        tag_manager::bind_tag_to_course($importedid, $course->id);
        $this->assertSame(
            1,
            $DB->count_records('format_mimo_course_tags', [
                'tagid' => $importedid,
                'courseid' => $course->id,
            ]),
        );

        $bound = tag_manager::get_imported_tags_for_course($course->id);
        $this->assertArrayHasKey($importedid, $bound);
        $this->assertArrayNotHasKey($globalid, $bound, 'Global tags must not appear in the imported bucket');

        tag_manager::unbind_tag_from_course($importedid, $course->id);
        $this->assertSame([], tag_manager::get_imported_tags_for_course($course->id));
    }

    /**
     * promote_tag_to_global flips the scope and drops all course bindings.
     */
    public function test_promote_tag_to_global(): void {
        global $DB;

        $course1 = $this->getDataGenerator()->create_course(['format' => 'mimo']);
        $course2 = $this->getDataGenerator()->create_course(['format' => 'mimo']);
        $tagid = tag_manager::create_tag(
            'ToPromote',
            'p.svg',
            'p-s.svg',
            'page',
            null,
            null,
            null,
            'center',
            'normal',
            'imported',
        );
        tag_manager::bind_tag_to_course($tagid, $course1->id);
        tag_manager::bind_tag_to_course($tagid, $course2->id);
        $this->assertSame(2, $DB->count_records('format_mimo_course_tags', ['tagid' => $tagid]));

        tag_manager::promote_tag_to_global($tagid);

        $this->assertSame(
            'global',
            $DB->get_field('format_mimo_tags', 'scope', ['id' => $tagid]),
        );
        $this->assertSame(0, $DB->count_records('format_mimo_course_tags', ['tagid' => $tagid]));
    }

    /**
     * cleanup_orphaned_imported_tags deletes imported tags that have no
     * course-bindings and no cmtag references, but leaves referenced ones alone.
     */
    public function test_cleanup_orphaned_imported_tags(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['format' => 'mimo']);
        $module = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        // Three imported tags with different attachment states.
        $orphan = tag_manager::create_tag(
            'Orphan',
            'o.svg',
            'o-s.svg',
            'page',
            null,
            null,
            null,
            'center',
            'normal',
            'imported',
        );
        $bound = tag_manager::create_tag(
            'Bound',
            'b.svg',
            'b-s.svg',
            'page',
            null,
            null,
            null,
            'center',
            'normal',
            'imported',
        );
        $cmattached = tag_manager::create_tag(
            'Attached',
            'a.svg',
            'a-s.svg',
            'page',
            null,
            null,
            null,
            'center',
            'normal',
            'imported',
        );

        tag_manager::bind_tag_to_course($bound, $course->id);
        tag_manager::assign_tag_to_cm($module->cmid, $cmattached);

        // Also a global tag with no attachments — must never be collected.
        $globalunused = tag_manager::create_tag('GlobalUnused', 'gu.svg', 'gu-s.svg', 'page');

        tag_manager::cleanup_orphaned_imported_tags();

        $this->assertFalse(
            $DB->record_exists('format_mimo_tags', ['id' => $orphan]),
            'Orphan imported tag must be deleted',
        );
        $this->assertTrue($DB->record_exists('format_mimo_tags', ['id' => $bound]));
        $this->assertTrue($DB->record_exists('format_mimo_tags', ['id' => $cmattached]));
        $this->assertTrue(
            $DB->record_exists('format_mimo_tags', ['id' => $globalunused]),
            'Global tags are out of scope for cleanup',
        );
    }

    /* ========================================== *
     * Restore-helper matching.                   *
     * ========================================== */

    /**
     * find_tag_by_fingerprint matches on name + bgcolor + activity types
     * (NULL-safe), and honours the excludeids filter.
     */
    public function test_find_tag_by_fingerprint(): void {
        $id1 = tag_manager::create_tag('Fingerprinted', 'f.svg', 'f-s.svg', 'page', 'forum', null, '#abcdef');
        // A differently-configured tag with the same name must not match.
        $id2 = tag_manager::create_tag('Fingerprinted', 'f.svg', 'f-s.svg', 'quiz', null, null, '#123456');

        $probe = (object) [
            'name' => 'Fingerprinted',
            'bgcolor' => '#abcdef',
            'activitytype1' => 'page',
            'activitytype2' => 'forum',
            'activitytype3' => null,
        ];

        $hit = tag_manager::find_tag_by_fingerprint($probe);
        $this->assertNotNull($hit);
        $this->assertSame($id1, (int) $hit->id);

        // Excluding the only match returns null.
        $this->assertNull(tag_manager::find_tag_by_fingerprint($probe, [$id1]));

        // Differing colour prevents matching.
        $noncolor = clone $probe;
        $noncolor->bgcolor = '#000000';
        $this->assertNull(tag_manager::find_tag_by_fingerprint($noncolor));

        // Sanity: the second tag with same name but different fingerprint is not returned.
        $this->assertNotSame($id2, (int) $hit->id);
    }

    /**
     * find_tag_by_name is a lenient fallback that ignores colour / activity types.
     */
    public function test_find_tag_by_name(): void {
        $id = tag_manager::create_tag('OnlyName', 'n.svg', 'n-s.svg', 'page');

        $hit = tag_manager::find_tag_by_name('OnlyName');
        $this->assertNotNull($hit);
        $this->assertSame($id, (int) $hit->id);

        // Name-mismatch returns null.
        $this->assertNull(tag_manager::find_tag_by_name('Nope'));

        // Exclusion works.
        $this->assertNull(tag_manager::find_tag_by_name('OnlyName', [$id]));
    }
}
