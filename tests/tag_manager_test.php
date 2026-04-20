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
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo;

/**
 * Tag manager test case.
 *
 * @package    format_mimo
 * @copyright  2025 Your Name
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

        // Clear any existing tags from install.
        $DB->delete_records('format_mimo_tags');
        tag_manager::clear_tag_cache();

        tag_manager::initialize_default_tags();

        // Verify 7 default tags were created.
        $tags = tag_manager::get_all_tags();
        $this->assertCount(7, $tags);

        // Verify tag names.
        $tagnames = array_column($tags, 'name');
        $expectednames = ['Reading', 'Discover', 'Writing', 'Show', 'Practice', 'Teamwork'];
        foreach ($expectednames as $expected) {
            $this->assertContains($expected, $tagnames);
        }
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

        // Should still only have 7 tags.
        $tags = tag_manager::get_all_tags();
        $this->assertCount(7, $tags);
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
}
