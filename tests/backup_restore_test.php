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
     * Fingerprint-matched restore: a tag with identical name/bgcolor/activitytypes
     * already exists on the target site, so the restore logic must reuse it
     * instead of creating a duplicate imported tag.
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
        $restoredcourseid = $this->restore_course_from_backup($backupid, 'FP restored');

        // No duplicate tag has been created: the existing one was reused.
        $this->assertSame(
            1,
            $DB->count_records('format_mimo_tags', ['name' => 'Fingerprint Match']),
            'Fingerprint match must reuse the existing tag, not insert a duplicate',
        );

        // The restored course module is bound to the pre-existing tag id.
        $restoredtagid = $DB->get_field_sql(
            "SELECT cmt.tagid
               FROM {format_mimo_cmtags} cmt
               JOIN {course_modules} cm ON cm.id = cmt.cmid
              WHERE cm.course = :courseid",
            ['courseid' => $restoredcourseid],
        );
        $this->assertSame($tagid, (int) $restoredtagid);
    }

    /**
     * Name-only match after an admin edit: the backup tag has a different colour
     * from the target tag. Fingerprint match fails but name match succeeds, so the
     * restore reuses the target tag and the admin's edit is preserved.
     */
    public function test_restore_reuses_tag_by_name_after_admin_edit(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();

        // Create the tag with the ORIGINAL colour, back up, then change the colour
        // on the target site. On restore only the name still matches.
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

        // Admin changes the tag colour after the backup was made.
        $this->tagmanager->update_tag($tagid, ['bgcolor' => '#999999']);

        $restoredcourseid = $this->restore_course_from_backup($backupid, 'NM restored');

        // No duplicate tag has been created.
        $this->assertSame(
            1,
            $DB->count_records('format_mimo_tags', ['name' => 'Name Match']),
            'Name match must reuse the existing tag, not insert a duplicate',
        );

        // The restored cmtag points to the pre-existing tag.
        $restoredtagid = (int) $DB->get_field_sql(
            "SELECT cmt.tagid
               FROM {format_mimo_cmtags} cmt
               JOIN {course_modules} cm ON cm.id = cmt.cmid
              WHERE cm.course = :courseid",
            ['courseid' => $restoredcourseid],
        );
        $this->assertSame($tagid, $restoredtagid);

        // ...and the admin's post-backup colour edit is preserved (not overwritten).
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
