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

namespace format_mimo;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Backup/restore coverage for format_mimo tag data.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class backup_restore_test extends \advanced_testcase {
    /** @var array<string> list of temp backup ids to clean up */
    private array $backupdirs = [];

    /** @var tag_manager Tag manager instance. */
    private tag_manager $tagmanager;

    /** @var profile_manager Profile manager instance. */
    private profile_manager $profilemanager;

    /**
     * Set up before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->tagmanager = \core\di::get(tag_manager::class);
        $this->profilemanager = \core\di::get(profile_manager::class);
    }

    /**
     * Ensure backups containing tags restore the mappings correctly.
     */
    public function test_backup_and_restore_preserves_cm_tags(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $tagid = $this->tagmanager->create_tag('Backup Tag');

        // Create course.
        $course = $generator->create_course([
            'format' => 'mimo',
        ]);
        $page = $generator->create_module('page', ['course' => $course->id]);

        $this->tagmanager->assign_tag_to_cm($page->cmid, $tagid);

        $backupid = 'mimo_backup_' . random_string(6);
        $this->backup_course_to_tempdir((int)$course->id, $backupid);
        $restoredcourseid = $this->restore_course_from_backup($backupid, 'Restored mimo');

        $tag = $DB->get_record_sql(
            "SELECT t.name
               FROM {format_mimo_cmtags} cmt
               JOIN {course_modules} cm ON cm.id = cmt.cmid
               JOIN {modules} m ON m.id = cm.module
               JOIN {format_mimo_tags} t ON t.id = cmt.tagid
              WHERE cm.course = :courseid AND m.name = :modname",
            ['courseid' => $restoredcourseid, 'modname' => 'page']
        );

        $this->assertNotFalse($tag);
        $this->assertEquals('Backup Tag', $tag->name);
    }

    /**
     * Test that tag fields (bgcolor, imgplacement, activitytype3) survive backup/restore.
     */
    public function test_backup_and_restore_preserves_tag_fields(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $tagid = $this->tagmanager->create_tag(
            'Colored Tag',
            null, // Cardimage.
            null, // Filterimage.
            null, // Activitytype1.
            null, // Activitytype2.
            'quiz', // Activitytype3.
            '#ff5733', // Bgcolor.
            'lower' // Imgplacement.
        );

        $course = $generator->create_course([
            'format' => 'mimo',
        ]);
        $quiz = $generator->create_module('quiz', ['course' => $course->id]);
        $this->tagmanager->assign_tag_to_cm($quiz->cmid, $tagid);

        $backupid = 'mimo_fields_' . random_string(6);
        $this->backup_course_to_tempdir((int) $course->id, $backupid);
        $restoredcourseid = $this->restore_course_from_backup($backupid, 'Restored fields test');

        // Retrieve the tag that was restored and mapped to the new course.
        $tag = $DB->get_record_sql(
            "SELECT t.*
               FROM {format_mimo_cmtags} cmt
               JOIN {course_modules} cm ON cm.id = cmt.cmid
               JOIN {format_mimo_tags} t ON t.id = cmt.tagid
              WHERE cm.course = :courseid",
            ['courseid' => $restoredcourseid]
        );

        $this->assertNotFalse($tag, 'Tag should exist in restored course');
        $this->assertEquals('Colored Tag', $tag->name);
        $this->assertEquals('#ff5733', $tag->bgcolor);
        $this->assertEquals('lower', $tag->imgplacement);
        $this->assertEquals('quiz', $tag->activitytype3);
    }

    /**
     * Test that profiles and profile_tags are backed up and restored.
     */
    public function test_backup_and_restore_preserves_profiles(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();

        // Create a profile.
        $profileid = $this->profilemanager->create_profile('teststyle', 'Test Style');

        // Create a tag.
        $tagid = $this->tagmanager->create_tag('Profile Tag');

        // Create a profile_tag entry linking the tag to the profile.
        $profiletag = $this->profilemanager->get_or_create_profile_tag($tagid, $profileid);
        $this->assertNotEmpty($profiletag->id, 'Profile tag record should be created');

        // Create the course and assign the tag.
        $course = $generator->create_course([
            'format' => 'mimo',
        ]);
        $page = $generator->create_module('page', ['course' => $course->id]);
        $this->tagmanager->assign_tag_to_cm($page->cmid, $tagid);

        // Backup and restore.
        $backupid = 'mimo_profile_' . random_string(6);
        $this->backup_course_to_tempdir((int) $course->id, $backupid);
        $restoredcourseid = $this->restore_course_from_backup($backupid, 'Restored profile test');

        // Verify the profile exists (reused by name or recreated).
        $profile = $this->profilemanager->get_profile_by_name('teststyle');
        $this->assertNotNull($profile, 'Profile should exist after restore');
        $this->assertEquals('Test Style', $profile->displayname);

        // Verify the profile_tag record was restored.
        $restoredtag = $DB->get_record_sql(
            "SELECT t.*
               FROM {format_mimo_cmtags} cmt
               JOIN {course_modules} cm ON cm.id = cmt.cmid
               JOIN {format_mimo_tags} t ON t.id = cmt.tagid
              WHERE cm.course = :courseid",
            ['courseid' => $restoredcourseid]
        );
        $this->assertNotFalse($restoredtag, 'Tag should be restored');

        $restoredprofiletag = $this->profilemanager->get_profile_tag_for_profile($restoredtag->id, $profile->id);
        $this->assertNotNull($restoredprofiletag, 'Profile tag should exist after restore');
    }

    /**
     * Test that profile format option is preserved through backup/restore.
     */
    public function test_backup_and_restore_preserves_profile_option(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $tagid = $this->tagmanager->create_tag('Profile Tag');
        $this->profilemanager->create_profile('optionprofile', 'Option Profile');

        // Create a course with a specific activity profile.
        $course = $generator->create_course([
            'format' => 'mimo',
            'activityprofile' => 'optionprofile',
        ]);
        $page = $generator->create_module('page', ['course' => $course->id]);
        $this->tagmanager->assign_tag_to_cm($page->cmid, $tagid);

        // Backup and restore.
        $backupid = 'mimo_profopt_' . random_string(6);
        $this->backup_course_to_tempdir((int) $course->id, $backupid);
        $restoredcourseid = $this->restore_course_from_backup($backupid, 'Restored profile option test');

        // The activityprofile should be restored.
        $restoredprofile = $DB->get_field('course_format_options', 'value', [
            'courseid' => $restoredcourseid,
            'format' => 'mimo',
            'name' => 'activityprofile',
        ]);
        $this->assertEquals('optionprofile', $restoredprofile);
    }

    /**
     * Fingerprint-matched restore: the tag was deleted and recreated with identical
     * name/bgcolor/activitytypes since the backup, so it has a new id. The restore logic
     * must reuse it instead of creating a duplicate imported tag.
     */
    public function test_restore_reuses_tag_by_fingerprint(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();

        $tagid = $this->tagmanager->create_tag(
            'Fingerprint Match',
            null,
            null,
            'page',
            'forum',
            null,
            '#a1b2c3',
            'center',
        );

        $course = $generator->create_course(['format' => 'mimo']);
        $page = $generator->create_module('page', ['course' => $course->id]);
        $this->tagmanager->assign_tag_to_cm($page->cmid, $tagid);

        $before = $DB->count_records('format_mimo_tags', ['name' => 'Fingerprint Match']);
        $this->assertSame(1, $before);

        $backupid = 'mimo_fp_' . random_string(6);
        $this->backup_course_to_tempdir((int) $course->id, $backupid);

        // Recreate the tag, so it cannot be matched by id.
        $this->tagmanager->delete_tag($tagid);
        $tagid = $this->tagmanager->create_tag(
            'Fingerprint Match',
            null,
            null,
            'page',
            'forum',
            null,
            '#a1b2c3',
            'center',
        );

        $restoredcourseid = $this->restore_course_from_backup($backupid, 'FP restored');

        // No duplicate tag has been created: the existing one was reused.
        $this->assertSame(
            1,
            $DB->count_records('format_mimo_tags', ['name' => 'Fingerprint Match']),
            'Fingerprint match must reuse the existing tag, not insert a duplicate',
        );

        // The restored course module is bound to the recreated tag id.
        $restoredtagid = $DB->get_field_sql(
            "SELECT cmt.tagid
               FROM {format_mimo_cmtags} cmt
               JOIN {course_modules} cm ON cm.id = cmt.cmid
              WHERE cm.course = :courseid",
            ['courseid' => $restoredcourseid],
        );
        $this->assertSame($tagid, (int) $restoredtagid);
        $this->assertSame(0, $DB->count_records('format_mimo_profiles', ['scope' => 'imported']));
    }

    /**
     * Name-only match: the tag was deleted and recreated with the same name but a different
     * colour since the backup, so it has a new id. Fingerprint match fails but name match
     * succeeds, so the restore reuses the recreated tag and the admin's colour is preserved.
     */
    public function test_restore_reuses_tag_by_name_after_admin_edit(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();

        // Create the tag with the ORIGINAL colour, back up, then recreate it with another
        // colour. On restore only the name still matches.
        $tagid = $this->tagmanager->create_tag(
            'Name Match',
            null,
            null,
            'page',
            null,
            null,
            '#111111',
            'center',
        );

        $course = $generator->create_course(['format' => 'mimo']);
        $page = $generator->create_module('page', ['course' => $course->id]);
        $this->tagmanager->assign_tag_to_cm($page->cmid, $tagid);

        $backupid = 'mimo_nm_' . random_string(6);
        $this->backup_course_to_tempdir((int) $course->id, $backupid);

        // Admin recreates the tag with another colour after the backup was made.
        $this->tagmanager->delete_tag($tagid);
        $tagid = $this->tagmanager->create_tag(
            'Name Match',
            null,
            null,
            'page',
            null,
            null,
            '#999999',
            'center',
        );

        $restoredcourseid = $this->restore_course_from_backup($backupid, 'NM restored');

        // No duplicate tag has been created.
        $this->assertSame(
            1,
            $DB->count_records('format_mimo_tags', ['name' => 'Name Match']),
            'Name match must reuse the existing tag, not insert a duplicate',
        );

        // The restored cmtag points to the recreated tag.
        $restoredtagid = (int) $DB->get_field_sql(
            "SELECT cmt.tagid
               FROM {format_mimo_cmtags} cmt
               JOIN {course_modules} cm ON cm.id = cmt.cmid
              WHERE cm.course = :courseid",
            ['courseid' => $restoredcourseid],
        );
        $this->assertSame($tagid, $restoredtagid);

        // ...and the admin's post-backup colour is preserved (not overwritten).
        $this->assertSame(
            '#999999',
            $DB->get_field('format_mimo_tags', 'bgcolor', ['id' => $tagid]),
        );
    }

    /**
     * Section overview card images must survive backup/restore.
     *
     * Regression test: the 'course_section' mappings are only created by the
     * section tasks, which run after the course task. Restoring the images in
     * after_execute_course() therefore silently skipped every file; they must
     * be restored in after_restore_course() (final task) instead.
     */
    public function test_backup_and_restore_preserves_section_images(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course([
            'format' => 'mimo',
            'numsections' => 2,
        ], ['createsections' => true]);

        // Attach an image to section 2.
        $sectionid = (int) $DB->get_field('course_sections', 'id', [
            'course' => $course->id,
            'section' => 2,
        ]);
        $fs = get_file_storage();
        $fs->create_file_from_string(
            [
                'contextid' => \core\context\course::instance($course->id)->id,
                'component' => section_image_manager::COMPONENT,
                'filearea'  => section_image_manager::FILEAREA,
                'itemid'    => $sectionid,
                'filepath'  => '/',
                'filename'  => 'wall.png',
            ],
            'fake image content'
        );

        $backupid = 'mimo_secimg_' . random_string(6);
        $this->backup_course_to_tempdir((int) $course->id, $backupid);
        $restoredcourseid = $this->restore_course_from_backup($backupid, 'Section image restored');

        $newsectionid = (int) $DB->get_field('course_sections', 'id', [
            'course' => $restoredcourseid,
            'section' => 2,
        ]);
        $this->assertTrue(
            \core\di::get(section_image_manager::class)->has_image($restoredcourseid, $newsectionid),
            'Section image must be restored for the mapped section'
        );

        $url = \core\di::get(section_image_manager::class)->get_image_url($restoredcourseid, $newsectionid);
        $this->assertNotNull($url);
        $this->assertStringContainsString('wall.png', $url->out(false));
    }

    /**
     * Imported profiles must get fully materialized rows and their own image
     * copies (strict per-set images have no anchor fallback anymore).
     */
    public function test_imported_profile_rows_are_materialized_with_images(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();

        // Clear preseeded tags so the positional match deterministically hits
        // the tag created below.
        $DB->delete_records('format_mimo_tags');
        $DB->delete_records('format_mimo_profile_tags');
        $this->tagmanager->clear_tag_cache();
        $this->profilemanager->clear_request_caches();

        $tagid = $this->tagmanager->create_tag(
            'Original',
            null,
            null,
            'page',
            null,
            null,
            '#123123',
            'center',
        );

        // Anchor card image.
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => \core\context\system::instance()->id,
            'component' => 'format_mimo',
            'filearea' => tag_manager::FILEAREA_CARDIMAGE,
            'itemid' => $tagid,
            'filepath' => '/',
            'filename' => 'anchor.png',
        ], 'png');

        $course = $generator->create_course(['format' => 'mimo']);
        $page = $generator->create_module('page', ['course' => $course->id]);
        $this->tagmanager->assign_tag_to_cm($page->cmid, $tagid);

        $backupid = 'mimo_imp_' . random_string(6);
        $this->backup_course_to_tempdir((int) $course->id, $backupid);
        // Positional matching is only used for backups from other sites.
        $this->make_backup_foreign($backupid);

        // Break fingerprint AND name match → positional match → imported profile.
        $this->tagmanager->update_tag($tagid, ['name' => 'Renamed', 'bgcolor' => '#999999']);

        $restoredcourseid = $this->restore_course_from_backup($backupid, 'Imported profile restored');

        $imported = null;
        foreach ($this->profilemanager->get_all_profiles() as $p) {
            if (($p->scope ?? '') === 'imported') {
                $imported = $p;
            }
        }
        $this->assertNotNull($imported, 'Imported profile expected');

        $pt = $this->profilemanager->get_profile_tag_for_profile($tagid, (int) $imported->id);
        $this->assertNotNull($pt);
        // Fully materialized: no NULL holes.
        $this->assertSame('Original', $pt->name);
        $this->assertNotNull($pt->bgcolor);
        $this->assertNotNull($pt->imgplacement);
        $this->assertNotNull($pt->imgsize);
        $this->assertSame(1, (int) $pt->enabled);

        // Image copied into the imported profile's own area.
        $url = $this->profilemanager->get_cardimage_url($tagid, (int) $imported->id);
        $this->assertNotNull($url, 'Anchor image must be copied into the imported profile area');
    }

    /**
     * A tag deleted since the course backup was made must not be mapped to an unrelated
     * tag (e.g. by position) when restoring on the same site. It is recreated as an
     * imported tag instead, like an unknown tag from another site.
     */
    public function test_restore_on_same_site_recreates_tag_deleted_since_backup(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $keptid = $this->tagmanager->create_tag('Kept', null, null, 'page', null, null, '#111111');
        $deletedid = $this->tagmanager->create_tag('Deleted', null, null, 'page', null, null, '#222222');
        // Unused tag, a candidate for positional matching.
        $unrelatedid = $this->tagmanager->create_tag('Unrelated', null, null, 'page', null, null, '#333333');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'mimo']);
        $keptpage = $generator->create_module('page', ['course' => $course->id, 'name' => 'Kept page']);
        $deletedpage = $generator->create_module('page', ['course' => $course->id, 'name' => 'Deleted page']);
        $this->tagmanager->assign_tag_to_cm($keptpage->cmid, $keptid);
        $this->tagmanager->assign_tag_to_cm($deletedpage->cmid, $deletedid);

        $backupid = 'mimo_del_' . random_string(6);
        $this->backup_course_to_tempdir((int) $course->id, $backupid);

        $this->tagmanager->delete_tag($deletedid);

        $restoredcourseid = $this->restore_course_from_backup($backupid, 'Deleted tag restored');

        $this->assertFalse(
            $DB->record_exists('format_mimo_cmtags', ['tagid' => $unrelatedid]),
            'The deleted tag must not be mapped to an unrelated tag'
        );
        $this->assert_recreated_as_imported_tag($restoredcourseid, 'Deleted', 'Deleted page');
        $this->assertSame($keptid, $this->get_page_tagids($restoredcourseid)['Kept page']);
    }

    /**
     * On a cloned site or after reinstalling the plugin, the id of a tag in the backup may belong
     * to another tag. Such a tag must not be matched by id, as its creation time differs.
     */
    public function test_restore_on_same_site_does_not_match_other_tag_with_same_id(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $tagid = $this->tagmanager->create_tag('Original', null, null, 'page', null, null, '#123123');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'mimo']);
        $page = $generator->create_module('page', ['course' => $course->id, 'name' => 'Original page']);
        $this->tagmanager->assign_tag_to_cm($page->cmid, $tagid);

        $backupid = 'mimo_sameid_' . random_string(6);
        $this->backup_course_to_tempdir((int) $course->id, $backupid);

        // Turn the tag into another tag with the same id.
        $timecreated = (int) $DB->get_field('format_mimo_tags', 'timecreated', ['id' => $tagid]);
        $DB->update_record('format_mimo_tags', (object) [
            'id' => $tagid,
            'name' => 'Other tag',
            'bgcolor' => '#999999',
            'timecreated' => $timecreated - DAYSECS,
        ]);
        $this->tagmanager->clear_tag_cache();

        $restoredcourseid = $this->restore_course_from_backup($backupid, 'Same id restored');

        $this->assertFalse(
            $DB->record_exists('format_mimo_cmtags', ['tagid' => $tagid, 'cmid' => $this->get_page_cmid($restoredcourseid)]),
            'The tag must not be mapped to another tag with the same id'
        );
        $this->assert_recreated_as_imported_tag($restoredcourseid, 'Original', 'Original page');
    }

    /**
     * Assert that a backup tag was recreated as an imported tag for the restored course.
     *
     * @param int $courseid restored course
     * @param string $tagname name of the tag in the backup
     * @param string $pagename name of the page using the tag
     */
    private function assert_recreated_as_imported_tag(int $courseid, string $tagname, string $pagename): void {
        global $DB;

        $tag = $DB->get_record('format_mimo_tags', ['name' => $tagname, 'scope' => 'imported'], '*', MUST_EXIST);
        $this->assertSame((int) $tag->id, $this->get_page_tagids($courseid)[$pagename]);
        $this->assertArrayHasKey($tag->id, $this->tagmanager->get_imported_tags_for_course($courseid));

        // The course uses an imported profile, in which the tag is available.
        $profilename = $DB->get_field('course_format_options', 'value', [
            'courseid' => $courseid,
            'format' => 'mimo',
            'name' => 'activityprofile',
        ]);
        $profile = $this->profilemanager->get_profile_by_name($profilename);
        $this->assertSame('imported', $profile->scope);
        $this->assertArrayHasKey($tag->id, $this->tagmanager->get_tags_for_course($courseid));
    }

    /**
     * Get the id of the only page in a course.
     *
     * @param int $courseid course id
     * @return int course module id
     */
    private function get_page_cmid(int $courseid): int {
        global $DB;

        return (int) $DB->get_field_sql(
            "SELECT cm.id
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = 'page'
              WHERE cm.course = :courseid",
            ['courseid' => $courseid],
            MUST_EXIST
        );
    }

    /**
     * A tag renamed and recoloured by an admin since the course backup was made is recognised
     * by its id when restoring on the same site, although neither fingerprint nor name match.
     */
    public function test_restore_on_same_site_recognises_edited_tag_by_id(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $tagid = $this->tagmanager->create_tag('Original', null, null, 'page', null, null, '#123123');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'mimo']);
        $page = $generator->create_module('page', ['course' => $course->id, 'name' => 'Edited page']);
        $this->tagmanager->assign_tag_to_cm($page->cmid, $tagid);

        $backupid = 'mimo_edit_' . random_string(6);
        $this->backup_course_to_tempdir((int) $course->id, $backupid);

        // Break fingerprint and name match.
        $this->tagmanager->update_tag($tagid, ['name' => 'Renamed', 'bgcolor' => '#999999']);
        $tagcount = $DB->count_records('format_mimo_tags');

        $restoredcourseid = $this->restore_course_from_backup($backupid, 'Edited tag restored');

        $this->assertSame(['Edited page' => $tagid], $this->get_page_tagids($restoredcourseid));
        $this->assertSame($tagcount, $DB->count_records('format_mimo_tags'), 'No tag must be created');
        $this->assertSame(0, $DB->count_records('format_mimo_profiles', ['scope' => 'imported']));

        // The admin's edits are preserved.
        $tag = $DB->get_record('format_mimo_tags', ['id' => $tagid]);
        $this->assertSame('Renamed', $tag->name);
        $this->assertSame('#999999', $tag->bgcolor);
    }

    /**
     * Restoring an old activity backup into its original course must not assign a tag
     * that was deleted since the backup was made.
     */
    public function test_restore_activity_into_same_course_drops_deleted_tag(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $tagid = $this->tagmanager->create_tag('Deleted later', null, null, 'page');
        $course = $this->getDataGenerator()->create_course(['format' => 'mimo']);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $this->tagmanager->assign_tag_to_cm($page->cmid, $tagid);

        $userid = get_admin()->id;
        $bc = new \backup_controller(
            \backup::TYPE_1ACTIVITY,
            $page->cmid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $userid
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();
        $this->backupdirs[] = $backupid;

        $this->tagmanager->delete_tag($tagid);

        $rc = new \restore_controller(
            $backupid,
            $course->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $userid,
            \backup::TARGET_CURRENT_ADDING
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $newcmid = null;
        foreach ($rc->get_plan()->get_tasks() as $task) {
            if ($task instanceof \restore_activity_task) {
                $newcmid = (int) $task->get_moduleid();
                break;
            }
        }
        $rc->destroy();

        $this->assertNotNull($newcmid);
        $this->assertFalse(
            $DB->record_exists('format_mimo_cmtags', ['cmid' => $newcmid]),
            'The deleted tag must not be assigned'
        );
    }

    /**
     * Get the tag ids of all pages in a course.
     *
     * @param int $courseid course id
     * @return array tag id or null if untagged, keyed by page name, sorted by page name
     */
    private function get_page_tagids(int $courseid): array {
        global $DB;

        $records = $DB->get_records_sql(
            "SELECT cm.id, p.name, cmt.tagid
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = 'page'
               JOIN {page} p ON p.id = cm.instance
          LEFT JOIN {format_mimo_cmtags} cmt ON cmt.cmid = cm.id
              WHERE cm.course = :courseid",
            ['courseid' => $courseid]
        );
        $tagids = [];
        foreach ($records as $record) {
            $tagids[$record->name] = $record->tagid === null ? null : (int) $record->tagid;
        }
        ksort($tagids);
        return $tagids;
    }

    /**
     * Back up a course and extract it into the temp directory Moodle expects for restores.
     *
     * @param int $courseid course id to back up
     * @param string $backupid unique directory name inside temp/backup
     */
    private function backup_course_to_tempdir(int $courseid, string $backupid): void {
        global $CFG;

        $userid = get_admin()->id;
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $courseid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $userid
        );
        $bc->execute_plan();
        $results = $bc->get_results();
        /** @var \stored_file $file */
        $file = $results['backup_destination'];

        $destpath = $CFG->dataroot . '/temp/backup/' . $backupid;
        if (is_dir($destpath)) {
            fulldelete($destpath);
        }
        make_temp_directory('backup/' . $backupid);
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $destpath);
        $bc->destroy();

        $this->backupdirs[] = $backupid;
    }

    /**
     * Make an extracted backup look like it was created on another site.
     *
     * The restore compares the site identifier hash in moodle_backup.xml with the current site,
     * see backup_general_helper::backup_is_samesite(). Changing it makes is_samesite() false,
     * so the tag matching for foreign sites (fingerprint, name, position, new) is used.
     *
     * @param string $backupid directory name under temp/backup
     */
    private function make_backup_foreign(string $backupid): void {
        global $CFG;

        $file = $CFG->dataroot . '/temp/backup/' . $backupid . '/moodle_backup.xml';
        $xml = file_get_contents($file);
        $count = 0;
        $xml = preg_replace(
            '~<original_site_identifier_hash>[^<]*</original_site_identifier_hash>~',
            '<original_site_identifier_hash>' . md5('another site') . '</original_site_identifier_hash>',
            $xml,
            -1,
            $count
        );
        $this->assertSame(1, $count, 'The backup must contain exactly one site identifier hash');
        file_put_contents($file, $xml);
    }

    /**
     * Restore the previously generated backup into a brand new course.
     *
     * @param string $backupid directory name under temp/backup
     * @param string $coursename name for the restored course
     * @return int id of the restored course
     */
    private function restore_course_from_backup(string $backupid, string $coursename): int {
        global $DB;

        $categoryid = (int)$DB->get_field_select('course_categories', 'MIN(id)', 'parent = 0');
        $newcourseid = \restore_dbops::create_new_course($coursename, $coursename, $categoryid);

        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            get_admin()->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->get_plan()->get_setting('users')->set_value(false);
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }

    /**
     * Duplicating a single activity restores it without the course tags, so the
     * original tag must be reused on the same site.
     */
    public function test_duplicate_single_activity_keeps_cm_tag(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $tagid = $this->tagmanager->create_tag('Duplicate Tag', null, null, 'page');
        $profileid = $this->profilemanager->create_profile('dupset', 'Duplicate set');
        $this->profilemanager->materialize_profile_tag($tagid, $profileid, [], true);
        $course = $this->getDataGenerator()->create_course(['format' => 'mimo', 'activityprofile' => 'dupset']);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $this->tagmanager->assign_tag_to_cm($page->cmid, $tagid);

        // Use the core duplication, which does not run the mimo state actions.
        $newcm = \core_courseformat\formatactions::cm($course->id)->duplicate($page->cmid);

        $this->assertNotEquals($page->cmid, $newcm->id);
        $newtag = $this->tagmanager->get_cm_tag($newcm->id);
        $this->assertNotFalse($newtag);
        $this->assertEquals($tagid, $newtag->id);
    }

    /**
     * Backup types for importing content into another course.
     *
     * Neither an activity nor a section backup contains the tag definitions,
     * so the restore has to resolve the original tag on the same site.
     *
     * @return array
     */
    public static function import_type_provider(): array {
        return [
            'single activity' => [\backup::TYPE_1ACTIVITY],
            'single section' => [\backup::TYPE_1SECTION],
        ];
    }

    /**
     * Importing an activity or section into another course keeps the tag only if the
     * tag is available in the target course.
     *
     * @dataProvider import_type_provider
     * @param string $type backup type
     */
    public function test_import_keeps_cm_tag_only_if_available(string $type): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $tagid = $this->tagmanager->create_tag('Import Tag', null, null, 'page');
        $profileid = $this->profilemanager->create_profile('importset', 'Import set');
        $this->profilemanager->materialize_profile_tag($tagid, $profileid, [], true);
        $this->profilemanager->create_profile('otherset', 'Other set');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'mimo', 'activityprofile' => 'importset']);
        $sameprofilecourse = $generator->create_course(['format' => 'mimo', 'activityprofile' => 'importset']);
        $otherprofilecourse = $generator->create_course(['format' => 'mimo', 'activityprofile' => 'otherset']);
        $page = $generator->create_module('page', ['course' => $course->id, 'section' => 1]);
        $this->tagmanager->assign_tag_to_cm($page->cmid, $tagid);

        // The tag is enabled in the profile of the target course.
        $newcmid = $this->import_into_course($type, (int) $page->cmid, (int) $sameprofilecourse->id);
        $newtag = $this->tagmanager->get_cm_tag($newcmid);
        $this->assertNotFalse($newtag);
        $this->assertEquals($tagid, $newtag->id);

        // The tag is not enabled in the profile of the target course.
        $newcmid = $this->import_into_course($type, (int) $page->cmid, (int) $otherprofilecourse->id);
        $this->assertFalse($this->tagmanager->get_cm_tag($newcmid));
    }

    /**
     * An imported tag bound only to the source course is handled like any other tag
     * when importing into another course: the profile of the target course decides.
     *
     * @dataProvider import_type_provider
     * @param string $type backup type
     */
    public function test_import_imported_tag_depends_on_target_profile(string $type): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Like after restoring a course with an unknown tag: the imported tag is only
        // enabled in the imported profile of the source course.
        $tagid = $this->tagmanager->create_tag('Experiment', activitytype1: 'page', scope: 'imported');
        $sourceprofileid = $this->profilemanager->create_profile('imported_source', 'Imported source', null, 'imported');
        $this->profilemanager->materialize_profile_tag($tagid, $sourceprofileid, [], true);
        $disabledprofileid = $this->profilemanager->create_profile('disabledset', 'Disabled set');
        $this->profilemanager->materialize_profile_tag($tagid, $disabledprofileid, [], false);
        // An admin enabled the imported tag in a global profile without promoting it.
        $enabledprofileid = $this->profilemanager->create_profile('enabledset', 'Enabled set');
        $this->profilemanager->materialize_profile_tag($tagid, $enabledprofileid, [], true);

        $generator = $this->getDataGenerator();
        $source = $generator->create_course(['format' => 'mimo', 'activityprofile' => 'imported_source']);
        $this->tagmanager->bind_tag_to_course($tagid, (int) $source->id);
        $disabledcourse = $generator->create_course(['format' => 'mimo', 'activityprofile' => 'disabledset']);
        $enabledcourse = $generator->create_course(['format' => 'mimo', 'activityprofile' => 'enabledset']);
        $page = $generator->create_module('page', ['course' => $source->id, 'section' => 1]);
        $this->tagmanager->assign_tag_to_cm($page->cmid, $tagid);

        // The tag is disabled in the profile of the target course: it is dropped.
        $newcmid = $this->import_into_course($type, (int) $page->cmid, (int) $disabledcourse->id);
        $this->assertFalse($this->tagmanager->get_cm_tag($newcmid));

        // The tag is enabled in the profile of the target course: it is kept,
        // although it is only bound to the source course.
        $newcmid = $this->import_into_course($type, (int) $page->cmid, (int) $enabledcourse->id);
        $newtag = $this->tagmanager->get_cm_tag($newcmid);
        $this->assertNotFalse($newtag);
        $this->assertEquals($tagid, $newtag->id);
        $this->assertArrayNotHasKey(
            $tagid,
            $this->tagmanager->get_imported_tags_for_course((int) $enabledcourse->id),
            'The import must not bind the tag to the target course'
        );
    }

    /**
     * Restoring a whole course into another existing course with "overwrite course configuration"
     * restores the tag definitions, so the tags are mapped. Mapped tags must follow the same rule
     * as activity imports: only tags available in the target course are kept.
     */
    public function test_restore_course_into_existing_course_keeps_only_available_tags(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $enabledtagid = $this->tagmanager->create_tag('Enabled Tag', null, null, 'page');
        $disabledtagid = $this->tagmanager->create_tag('Disabled Tag', null, null, 'page');
        $profileid = $this->profilemanager->create_profile('courseset', 'Course set');
        $this->profilemanager->materialize_profile_tag($enabledtagid, $profileid, [], true);
        $this->profilemanager->create_profile('targetset', 'Target set');

        $generator = $this->getDataGenerator();
        $source = $generator->create_course(['format' => 'mimo', 'activityprofile' => 'courseset']);
        $target = $generator->create_course(['format' => 'mimo', 'activityprofile' => 'targetset']);
        $enabledpage = $generator->create_module('page', ['course' => $source->id]);
        $disabledpage = $generator->create_module('page', ['course' => $source->id]);
        $this->tagmanager->assign_tag_to_cm($enabledpage->cmid, $enabledtagid);
        // The tag was disabled in the profile after it had been assigned.
        $this->tagmanager->assign_tag_to_cm($disabledpage->cmid, $disabledtagid);
        $this->profilemanager->materialize_profile_tag($disabledtagid, $profileid, [], false);
        // Warm the course tags cache of the target course with its old profile. Core changes
        // the profile during the restore, so the restore must not use the stale cache.
        $this->assertEmpty($this->tagmanager->get_tags_for_course((int) $target->id));

        $cmids = $this->restore_course_into_existing_course(
            (int) $source->id,
            (int) $target->id,
            \backup::MODE_GENERAL,
            true
        );

        // Core overwrites the course format options, all tags were recognized, so no imported profile.
        $this->assertEquals('courseset', $DB->get_field('course_format_options', 'value', [
            'courseid' => $target->id,
            'format' => 'mimo',
            'name' => 'activityprofile',
        ]));

        $newtag = $this->tagmanager->get_cm_tag($cmids[$enabledpage->cmid]);
        $this->assertNotFalse($newtag);
        $this->assertEquals($enabledtagid, $newtag->id);
        $this->assertFalse($this->tagmanager->get_cm_tag($cmids[$disabledpage->cmid]));
    }

    /**
     * Importing a whole course into an existing course with the default settings does not restore
     * the course configuration and tag definitions. The tags are resolved on the same site and
     * checked against the profile of the target course, which must not be changed.
     */
    public function test_import_course_into_existing_course_keeps_target_profile(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $sharedtagid = $this->tagmanager->create_tag('Shared Tag', null, null, 'page');
        $sourceonlytagid = $this->tagmanager->create_tag('Source Tag', null, null, 'page');
        $sourceprofileid = $this->profilemanager->create_profile('sourceset', 'Source set');
        $this->profilemanager->materialize_profile_tag($sharedtagid, $sourceprofileid, [], true);
        $this->profilemanager->materialize_profile_tag($sourceonlytagid, $sourceprofileid, [], true);
        $targetprofileid = $this->profilemanager->create_profile('targetset', 'Target set');
        $this->profilemanager->materialize_profile_tag($sharedtagid, $targetprofileid, [], true);
        $this->profilemanager->materialize_profile_tag($sourceonlytagid, $targetprofileid, [], false);

        $generator = $this->getDataGenerator();
        $source = $generator->create_course(['format' => 'mimo', 'activityprofile' => 'sourceset']);
        $target = $generator->create_course(['format' => 'mimo', 'activityprofile' => 'targetset']);
        $sharedpage = $generator->create_module('page', ['course' => $source->id]);
        $sourceonlypage = $generator->create_module('page', ['course' => $source->id]);
        $this->tagmanager->assign_tag_to_cm($sharedpage->cmid, $sharedtagid);
        $this->tagmanager->assign_tag_to_cm($sourceonlypage->cmid, $sourceonlytagid);
        $profilecount = $DB->count_records('format_mimo_profiles');

        $cmids = $this->restore_course_into_existing_course(
            (int) $source->id,
            (int) $target->id,
            \backup::MODE_IMPORT,
            false
        );

        // The target course keeps its profile and no imported profile is created.
        $this->assertEquals('targetset', $DB->get_field('course_format_options', 'value', [
            'courseid' => $target->id,
            'format' => 'mimo',
            'name' => 'activityprofile',
        ]));
        $this->assertEquals($profilecount, $DB->count_records('format_mimo_profiles'));

        $newtag = $this->tagmanager->get_cm_tag($cmids[$sharedpage->cmid]);
        $this->assertNotFalse($newtag);
        $this->assertEquals($sharedtagid, $newtag->id);
        $this->assertFalse($this->tagmanager->get_cm_tag($cmids[$sourceonlypage->cmid]));
    }

    /**
     * Restore a whole course into an existing course, adding to its content.
     *
     * @param int $sourcecourseid course to back up
     * @param int $targetcourseid existing course to restore into
     * @param int $mode backup::MODE_GENERAL (restore from a backup file) or backup::MODE_IMPORT
     * @param bool $overwriteconf whether to overwrite the course configuration of the target course
     * @return array new course module ids, keyed by the old course module ids
     */
    private function restore_course_into_existing_course(
        int $sourcecourseid,
        int $targetcourseid,
        int $mode,
        bool $overwriteconf
    ): array {
        $userid = get_admin()->id;
        if ($mode === \backup::MODE_GENERAL) {
            $backupid = 'mimo_existing_' . random_string(6);
            $this->backup_course_to_tempdir($sourcecourseid, $backupid);
        } else {
            $bc = new \backup_controller(
                \backup::TYPE_1COURSE,
                $sourcecourseid,
                \backup::FORMAT_MOODLE,
                \backup::INTERACTIVE_NO,
                $mode,
                $userid
            );
            $backupid = $bc->get_backupid();
            $bc->execute_plan();
            $bc->destroy();
            $this->backupdirs[] = $backupid;
        }

        $rc = new \restore_controller(
            $backupid,
            $targetcourseid,
            \backup::INTERACTIVE_NO,
            $mode,
            $userid,
            \backup::TARGET_EXISTING_ADDING
        );
        if ($mode === \backup::MODE_GENERAL) {
            $rc->get_plan()->get_setting('users')->set_value(false);
        }
        $rc->get_plan()->get_setting('overwrite_conf')->set_value($overwriteconf);
        $rc->execute_precheck();
        $rc->execute_plan();

        $cmids = [];
        foreach ($rc->get_plan()->get_tasks() as $task) {
            if ($task instanceof \restore_activity_task) {
                $cmids[$task->get_old_moduleid()] = (int) $task->get_moduleid();
            }
        }
        $rc->destroy();
        return $cmids;
    }

    /**
     * Import an activity or its section into an existing course, like duplicating it to another course.
     *
     * @param string $type backup type, backup::TYPE_1ACTIVITY or backup::TYPE_1SECTION
     * @param int $cmid course module to import, or whose section to import
     * @param int $targetcourseid course to import into
     * @return int id of the new course module
     */
    private function import_into_course(string $type, int $cmid, int $targetcourseid): int {
        global $DB;

        $backupitemid = $cmid;
        if ($type === \backup::TYPE_1SECTION) {
            $backupitemid = (int) $DB->get_field('course_modules', 'section', ['id' => $cmid], MUST_EXIST);
        }

        $userid = get_admin()->id;
        $bc = new \backup_controller(
            $type,
            $backupitemid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $userid
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();
        $this->backupdirs[] = $backupid;

        $rc = new \restore_controller(
            $backupid,
            $targetcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $userid,
            \backup::TARGET_EXISTING_ADDING
        );
        $rc->execute_precheck();
        $rc->execute_plan();

        $newcmid = null;
        $oldcontextid = \core\context\module::instance($cmid)->id;
        foreach ($rc->get_plan()->get_tasks() as $task) {
            if ($task instanceof \restore_activity_task && $task->get_old_contextid() == $oldcontextid) {
                $newcmid = $task->get_moduleid();
                break;
            }
        }
        $rc->destroy();
        return $newcmid;
    }

    /**
     * Ensure we do not leave temporary backup directories around between tests.
     */
    protected function tearDown(): void {
        global $CFG;

        foreach ($this->backupdirs as $backupid) {
            $path = $CFG->dataroot . '/temp/backup/' . $backupid;
            if (is_dir($path)) {
                fulldelete($path);
            }
        }
        parent::tearDown();
    }
}
