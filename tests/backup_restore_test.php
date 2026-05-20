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

    /**
     * Ensure backups containing tags restore the mappings correctly.
     */
    public function test_backup_and_restore_preserves_cm_tags(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $tagid = tag_manager::create_tag('Backup Tag');

        // Create course.
        $course = $generator->create_course([
            'format' => 'mimo',
        ]);
        $page = $generator->create_module('page', ['course' => $course->id]);

        tag_manager::assign_tag_to_cm($page->cmid, $tagid);

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
        $tagid = tag_manager::create_tag(
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
        tag_manager::assign_tag_to_cm($quiz->cmid, $tagid);

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
        $profileid = profile_manager::create_profile('teststyle', 'Test Style');

        // Create a tag.
        $tagid = tag_manager::create_tag('Profile Tag');

        // Create a profile_tag entry linking the tag to the profile.
        $profiletag = profile_manager::get_or_create_profile_tag($tagid, $profileid);
        $this->assertNotEmpty($profiletag->id, 'Profile tag record should be created');

        // Create the course and assign the tag.
        $course = $generator->create_course([
            'format' => 'mimo',
        ]);
        $page = $generator->create_module('page', ['course' => $course->id]);
        tag_manager::assign_tag_to_cm($page->cmid, $tagid);

        // Backup and restore.
        $backupid = 'mimo_profile_' . random_string(6);
        $this->backup_course_to_tempdir((int) $course->id, $backupid);
        $restoredcourseid = $this->restore_course_from_backup($backupid, 'Restored profile test');

        // Verify the profile exists (reused by name or recreated).
        $profile = profile_manager::get_profile_by_name('teststyle');
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

        $restoredprofiletag = profile_manager::get_profile_tag_for_profile($restoredtag->id, $profile->id);
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
        $tagid = tag_manager::create_tag('Profile Tag');

        // Create a course with a specific activity profile.
        $course = $generator->create_course([
            'format' => 'mimo',
            'activityprofile' => 'explore',
        ]);
        $page = $generator->create_module('page', ['course' => $course->id]);
        tag_manager::assign_tag_to_cm($page->cmid, $tagid);

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
        $this->assertEquals('explore', $restoredprofile);
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

        $tagid = tag_manager::create_tag(
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
        tag_manager::assign_tag_to_cm($page->cmid, $tagid);

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
        $tagid = tag_manager::create_tag(
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
        tag_manager::assign_tag_to_cm($page->cmid, $tagid);

        $backupid = 'mimo_nm_' . random_string(6);
        $this->backup_course_to_tempdir((int) $course->id, $backupid);

        // Admin changes the tag colour after the backup was made.
        tag_manager::update_tag($tagid, ['bgcolor' => '#999999']);

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
