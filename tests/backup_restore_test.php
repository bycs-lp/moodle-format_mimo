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

namespace format_minimoodlewall;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Backup/restore coverage for format_minimoodlewall tag data.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 MBS
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
            'format' => 'minimoodlewall',
        ]);
        $page = $generator->create_module('page', ['course' => $course->id]);

        tag_manager::assign_tag_to_cm($page->cmid, $tagid);

        $backupid = 'mmw_backup_' . random_string(6);
        $this->backup_course_to_tempdir((int)$course->id, $backupid);
        $restoredcourseid = $this->restore_course_from_backup($backupid, 'Restored minimoodlewall');

        $tag = $DB->get_record_sql(
            "SELECT t.name
               FROM {format_minimoodlewall_cmtags} cmt
               JOIN {course_modules} cm ON cm.id = cmt.cmid
               JOIN {modules} m ON m.id = cm.module
               JOIN {format_minimoodlewall_tags} t ON t.id = cmt.tagid
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
            null, // cardimage.
            null, // filterimage.
            null, // activitytype1.
            null, // activitytype2.
            'quiz', // activitytype3.
            '#ff5733', // bgcolor.
            'lower' // imgplacement.
        );

        $course = $generator->create_course([
            'format' => 'minimoodlewall',
        ]);
        $quiz = $generator->create_module('quiz', ['course' => $course->id]);
        tag_manager::assign_tag_to_cm($quiz->cmid, $tagid);

        $backupid = 'mmw_fields_' . random_string(6);
        $this->backup_course_to_tempdir((int) $course->id, $backupid);
        $restoredcourseid = $this->restore_course_from_backup($backupid, 'Restored fields test');

        // Retrieve the tag that was restored and mapped to the new course.
        $tag = $DB->get_record_sql(
            "SELECT t.*
               FROM {format_minimoodlewall_cmtags} cmt
               JOIN {course_modules} cm ON cm.id = cmt.cmid
               JOIN {format_minimoodlewall_tags} t ON t.id = cmt.tagid
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
            'format' => 'minimoodlewall',
        ]);
        $page = $generator->create_module('page', ['course' => $course->id]);
        tag_manager::assign_tag_to_cm($page->cmid, $tagid);

        // Backup and restore.
        $backupid = 'mmw_profile_' . random_string(6);
        $this->backup_course_to_tempdir((int) $course->id, $backupid);
        $restoredcourseid = $this->restore_course_from_backup($backupid, 'Restored profile test');

        // Verify the profile exists (reused by name or recreated).
        $profile = profile_manager::get_profile_by_name('teststyle');
        $this->assertNotNull($profile, 'Profile should exist after restore');
        $this->assertEquals('Test Style', $profile->displayname);

        // Verify the profile_tag record was restored.
        $restoredtag = $DB->get_record_sql(
            "SELECT t.*
               FROM {format_minimoodlewall_cmtags} cmt
               JOIN {course_modules} cm ON cm.id = cmt.cmid
               JOIN {format_minimoodlewall_tags} t ON t.id = cmt.tagid
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
            'format' => 'minimoodlewall',
            'activityprofile' => 'classic',
        ]);
        $page = $generator->create_module('page', ['course' => $course->id]);
        tag_manager::assign_tag_to_cm($page->cmid, $tagid);

        // Backup and restore.
        $backupid = 'mmw_profopt_' . random_string(6);
        $this->backup_course_to_tempdir((int) $course->id, $backupid);
        $restoredcourseid = $this->restore_course_from_backup($backupid, 'Restored profile option test');

        // The activityprofile should be restored.
        $restoredprofile = $DB->get_field('course_format_options', 'value', [
            'courseid' => $restoredcourseid,
            'format' => 'minimoodlewall',
            'name' => 'activityprofile',
        ]);
        $this->assertEquals('classic', $restoredprofile);
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
