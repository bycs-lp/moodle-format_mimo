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
 * Tag manager for format_minimoodlewall.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall;

defined('MOODLE_INTERNAL') || die();

/**
 * Tag manager class for handling tag sets and tags.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tag_manager {

    /** @var \cache_application Cache for tag configurations */
    private static $tagcache = null;

    /** @var \cache_application Cache for activity-tag mappings */
    private static $mappingcache = null;

    /**
     * Initialize caches.
     */
    private static function init_caches() {
        if (self::$tagcache === null) {
            self::$tagcache = \cache::make('format_minimoodlewall', 'tagconfigurations');
        }
        if (self::$mappingcache === null) {
            self::$mappingcache = \cache::make('format_minimoodlewall', 'activitytagmappings');
        }
    }

    /**
     * Create a new tag set.
     *
     * @param string $name Name of the tag set
     * @param string|null $description Description of the tag set
     * @return int ID of the created tag set
     */
    public static function create_tagset($name, $description = null) {
        global $DB;

        $record = new \stdClass();
        $record->name = $name;
        $record->description = $description;
        $record->timecreated = time();
        $record->timemodified = time();

        $id = $DB->insert_record('format_minimoodlewall_tagsets', $record);
        self::clear_tag_cache();

        return $id;
    }

    /**
     * Get all tag sets.
     *
     * @return array Array of tag set records
     */
    public static function get_tagsets() {
        global $DB;
        return $DB->get_records('format_minimoodlewall_tagsets', null, 'name ASC');
    }

    /**
     * Get a specific tag set.
     *
     * @param int $id Tag set ID
     * @return \stdClass|false Tag set record or false
     */
    public static function get_tagset($id) {
        global $DB;
        return $DB->get_record('format_minimoodlewall_tagsets', ['id' => $id]);
    }

    /**
     * Update a tag set.
     *
     * @param int $id Tag set ID
     * @param string $name New name
     * @param string|null $description New description
     * @return bool Success
     */
    public static function update_tagset($id, $name, $description = null) {
        global $DB;

        $record = new \stdClass();
        $record->id = $id;
        $record->name = $name;
        $record->description = $description;
        $record->timemodified = time();

        $result = $DB->update_record('format_minimoodlewall_tagsets', $record);
        self::clear_tag_cache();

        return $result;
    }

    /**
     * Delete a tag set and all its tags.
     *
     * @param int $id Tag set ID
     * @return bool Success
     */
    public static function delete_tagset($id) {
        global $DB;

        // Delete all tags in this set first.
        $tags = self::get_tags_by_tagset($id);
        foreach ($tags as $tag) {
            self::delete_tag($tag->id);
        }

        $result = $DB->delete_records('format_minimoodlewall_tagsets', ['id' => $id]);
        self::clear_tag_cache();

        return $result;
    }

    /**
     * Create a new tag.
     *
     * @param int $tagsetid Tag set ID
     * @param string $name Tag name
     * @param string|null $description Tag description
     * @param string|null $cardimage Card image filename
     * @param string|null $filterimage Filter image filename
     * @param string|null $activitytype1 First suggested activity type
     * @param string|null $activitytype2 Second suggested activity type
     * @return int ID of the created tag
     */
    public static function create_tag($tagsetid, $name, $description = null, $cardimage = null,
            $filterimage = null, $activitytype1 = null, $activitytype2 = null) {
        global $DB;

        // Get next sort order.
        $maxsort = $DB->get_field('format_minimoodlewall_tags', 'MAX(sortorder)',
            ['tagsetid' => $tagsetid]);
        $sortorder = $maxsort ? $maxsort + 1 : 0;

        $record = new \stdClass();
        $record->tagsetid = $tagsetid;
        $record->name = $name;
        $record->description = $description;
        $record->cardimage = $cardimage;
        $record->filterimage = $filterimage;
        $record->activitytype1 = $activitytype1;
        $record->activitytype2 = $activitytype2;
        $record->sortorder = $sortorder;
        $record->timecreated = time();
        $record->timemodified = time();

        $id = $DB->insert_record('format_minimoodlewall_tags', $record);
        self::clear_tag_cache();

        return $id;
    }

    /**
     * Get all tags for a tag set.
     *
     * @param int $tagsetid Tag set ID
     * @return array Array of tag records
     */
    public static function get_tags_by_tagset($tagsetid) {
        global $DB;
        return $DB->get_records('format_minimoodlewall_tags', ['tagsetid' => $tagsetid], 'sortorder ASC');
    }

    /**
     * Get a specific tag.
     *
     * @param int $id Tag ID
     * @return \stdClass|false Tag record or false
     */
    public static function get_tag($id) {
        global $DB;
        self::init_caches();

        $cachekey = 'tag_' . $id;
        $tag = self::$tagcache->get($cachekey);

        if ($tag === false) {
            $tag = $DB->get_record('format_minimoodlewall_tags', ['id' => $id]);
            if ($tag) {
                self::$tagcache->set($cachekey, $tag);
            }
        }

        return $tag;
    }

    /**
     * Update a tag.
     *
     * @param int $id Tag ID
     * @param array $data Associative array of fields to update
     * @return bool Success
     */
    public static function update_tag($id, $data) {
        global $DB;

        $record = new \stdClass();
        $record->id = $id;
        $record->timemodified = time();

        foreach ($data as $key => $value) {
            $record->$key = $value;
        }

        $result = $DB->update_record('format_minimoodlewall_tags', $record);
        self::clear_tag_cache();

        return $result;
    }

    /**
     * Delete a tag and all its mappings.
     *
     * @param int $id Tag ID
     * @return bool Success
     */
    public static function delete_tag($id) {
        global $DB;

        // Delete all mappings for this tag.
        $DB->delete_records('format_minimoodlewall_cmtags', ['tagid' => $id]);

        $result = $DB->delete_records('format_minimoodlewall_tags', ['id' => $id]);
        self::clear_tag_cache();
        self::clear_mapping_cache();

        return $result;
    }

    /**
     * Assign a tag to a course module.
     *
     * @param int $cmid Course module ID
     * @param int $tagid Tag ID
     * @return int|bool ID of the created mapping or false
     */
    public static function assign_tag_to_cm($cmid, $tagid) {
        global $DB;

        // Check if mapping already exists.
        if ($DB->record_exists('format_minimoodlewall_cmtags', ['cmid' => $cmid])) {
            // Update existing mapping.
            $record = $DB->get_record('format_minimoodlewall_cmtags', ['cmid' => $cmid]);
            $record->tagid = $tagid;
            $DB->update_record('format_minimoodlewall_cmtags', $record);
            $result = $record->id;
        } else {
            // Create new mapping.
            $record = new \stdClass();
            $record->cmid = $cmid;
            $record->tagid = $tagid;
            $record->timecreated = time();
            $result = $DB->insert_record('format_minimoodlewall_cmtags', $record);
        }

        self::clear_mapping_cache();
        return $result;
    }

    /**
     * Get the tag assigned to a course module.
     *
     * @param int $cmid Course module ID
     * @return \stdClass|false Tag record or false
     */
    public static function get_cm_tag($cmid) {
        global $DB;
        self::init_caches();

        $cachekey = 'cm_' . $cmid;
        $tagid = self::$mappingcache->get($cachekey);

        if ($tagid === false) {
            $mapping = $DB->get_record('format_minimoodlewall_cmtags', ['cmid' => $cmid]);
            if ($mapping) {
                $tagid = $mapping->tagid;
                self::$mappingcache->set($cachekey, $tagid);
            } else {
                return false;
            }
        }

        return self::get_tag($tagid);
    }

    /**
     * Remove tag assignment from a course module.
     *
     * @param int $cmid Course module ID
     * @return bool Success
     */
    public static function remove_cm_tag($cmid) {
        global $DB;

        $result = $DB->delete_records('format_minimoodlewall_cmtags', ['cmid' => $cmid]);
        self::clear_mapping_cache();

        return $result;
    }

    /**
     * Clear tag configuration cache.
     */
    public static function clear_tag_cache() {
        self::init_caches();
        self::$tagcache->purge();
    }

    /**
     * Clear activity-tag mapping cache.
     */
    public static function clear_mapping_cache() {
        self::init_caches();
        self::$mappingcache->purge();
    }

    /**
     * Initialize default tags for a new installation.
     *
     * @return bool Success
     */
    public static function initialize_default_tags() {
        // Create default tag set.
        $tagsetid = self::create_tagset(
            get_string('defaulttagset', 'format_minimoodlewall'),
            get_string('defaulttagset_desc', 'format_minimoodlewall')
        );

        // Create default tags.
        $defaulttags = [
            ['name' => get_string('tag_reading', 'format_minimoodlewall'),
                'cardimage' => 'reading.svg', 'filterimage' => 'reading_small.svg',
                'activitytype1' => 'page', 'activitytype2' => 'resource'],
            ['name' => get_string('tag_video', 'format_minimoodlewall'),
                'cardimage' => 'video.svg', 'filterimage' => 'video_small.svg',
                'activitytype1' => 'url', 'activitytype2' => 'resource'],
            ['name' => get_string('tag_writing', 'format_minimoodlewall'),
                'cardimage' => 'writing.svg', 'filterimage' => 'writing_small.svg',
                'activitytype1' => 'assign', 'activitytype2' => 'forum'],
            ['name' => get_string('tag_quiz', 'format_minimoodlewall'),
                'cardimage' => 'quiz.svg', 'filterimage' => 'quiz_small.svg',
                'activitytype1' => 'quiz', 'activitytype2' => 'choice'],
            ['name' => get_string('tag_discussion', 'format_minimoodlewall'),
                'cardimage' => 'discussion.svg', 'filterimage' => 'discussion_small.svg',
                'activitytype1' => 'forum', 'activitytype2' => 'chat'],
            ['name' => get_string('tag_data', 'format_minimoodlewall'),
                'cardimage' => 'data.svg', 'filterimage' => 'data_small.svg',
                'activitytype1' => 'data', 'activitytype2' => 'questionnaire'],
            ['name' => get_string('tag_lab', 'format_minimoodlewall'),
                'cardimage' => 'lab.svg', 'filterimage' => 'lab_small.svg',
                'activitytype1' => 'assign', 'activitytype2' => 'workshop'],
            ['name' => get_string('tag_practice', 'format_minimoodlewall'),
                'cardimage' => 'practice.svg', 'filterimage' => 'practice_small.svg',
                'activitytype1' => 'quiz', 'activitytype2' => 'lesson'],
        ];

        foreach ($defaulttags as $tag) {
            self::create_tag($tagsetid, $tag['name'], null, $tag['cardimage'],
                $tag['filterimage'], $tag['activitytype1'], $tag['activitytype2']);
        }

        return true;
    }
}
