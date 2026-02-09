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
 * Tagset manager for format_minimoodlewall.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 MBS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall;

defined('MOODLE_INTERNAL') || die();

/**
 * Manages tag set CRUD and caching.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 MBS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tagset_manager {
    /** @var \cache_application Shared tag configuration cache */
    private static $cache = null;

    /**
     * Initialise the cache instance.
     */
    private static function init_cache(): void {
        if (self::$cache === null) {
            self::$cache = \cache::make('format_minimoodlewall', 'tagconfigurations');
        }
    }

    /**
     * Get all tagsets sorted by sortorder.
     *
     * @return array Array of tagset records keyed by id
     */
    public static function get_all_tagsets(): array {
        global $DB;
        self::init_cache();

        $cachekey = 'all_tagsets';
        $tagsets = self::$cache->get($cachekey);

        if ($tagsets === false) {
            $tagsets = $DB->get_records('format_minimoodlewall_tagsets', null, 'sortorder ASC, id ASC');
            self::$cache->set($cachekey, $tagsets);
        }

        return $tagsets;
    }

    /**
     * Get a specific tagset by ID.
     *
     * @param int $id Tagset ID
     * @return \stdClass|false Tagset record or false
     */
    public static function get_tagset(int $id): \stdClass|false {
        global $DB;
        self::init_cache();

        $cachekey = 'tagset_' . $id;
        $tagset = self::$cache->get($cachekey);

        if ($tagset === false) {
            $tagset = $DB->get_record('format_minimoodlewall_tagsets', ['id' => $id]);
            if ($tagset) {
                self::$cache->set($cachekey, $tagset);
            }
        }

        return $tagset;
    }

    /**
     * Create a new tagset.
     *
     * @param string $name Tagset name
     * @param string|null $description Optional description
     * @return int ID of the created tagset
     */
    public static function create_tagset(string $name, ?string $description = null): int {
        global $DB;

        $maxsort = $DB->get_field('format_minimoodlewall_tagsets', 'MAX(sortorder)', []);
        $sortorder = $maxsort ? (int)$maxsort + 1 : 0;

        $record = new \stdClass();
        $record->name = $name;
        $record->description = $description;
        $record->sortorder = $sortorder;
        $record->timecreated = time();
        $record->timemodified = time();

        $id = $DB->insert_record('format_minimoodlewall_tagsets', $record);

        self::invalidate_list_cache();

        return $id;
    }

    /**
     * Update a tagset.
     *
     * @param int $id Tagset ID
     * @param array $data Associative array of fields to update
     * @return bool Success
     */
    public static function update_tagset(int $id, array $data): bool {
        global $DB;

        $record = new \stdClass();
        $record->id = $id;
        $record->timemodified = time();

        foreach ($data as $key => $value) {
            $record->$key = $value;
        }

        $result = $DB->update_record('format_minimoodlewall_tagsets', $record);

        self::init_cache();
        self::$cache->delete('tagset_' . $id);
        self::$cache->delete('all_tagsets');

        return $result;
    }

    /**
     * Delete a tagset and cascade-delete all its tags and their cmtag mappings.
     *
     * @param int $id Tagset ID
     * @return bool Success
     */
    public static function delete_tagset(int $id): bool {
        global $DB;

        // Get all tags in this tagset so we can clean up their mappings and files.
        $tags = $DB->get_records('format_minimoodlewall_tags', ['tagsetid' => $id]);
        foreach ($tags as $tag) {
            tag_manager::delete_tag($tag->id);
        }

        $result = $DB->delete_records('format_minimoodlewall_tagsets', ['id' => $id]);

        self::init_cache();
        self::$cache->delete('tagset_' . $id);
        self::$cache->delete('all_tagsets');

        return $result;
    }

    /**
     * Invalidate the tagset list cache.
     */
    private static function invalidate_list_cache(): void {
        self::init_cache();
        self::$cache->delete('all_tagsets');
    }

    /**
     * Clear all tagset-related cache entries.
     */
    public static function clear_tagset_cache(): void {
        if (self::$cache !== null) {
            self::$cache->purge();
        }
        self::$cache = null;
    }
}
