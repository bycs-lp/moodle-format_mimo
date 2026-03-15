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
 * Description tag manager for format_mimo.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo;

defined('MOODLE_INTERNAL') || die();

/**
 * Manages description tags for activity type descriptions.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class description_tag_manager {
    /**
     * Get all description tags.
     *
     * @return array Array of stdClass objects with id, name, color properties
     */
    public static function get_all_tags(): array {
        global $DB;
        return $DB->get_records('format_mimo_desc_tags', null, 'name ASC');
    }

    /**
     * Get a description tag by ID.
     *
     * @param int $id Tag ID
     * @return \stdClass|false Tag object or false if not found
     */
    public static function get_tag(int $id) {
        global $DB;
        return $DB->get_record('format_mimo_desc_tags', ['id' => $id]);
    }

    /**
     * Create a new description tag.
     *
     * @param string $name Tag name
     * @param string $color Hex color code (e.g., #FF5733)
     * @return int The ID of the created tag
     */
    public static function create_tag(string $name, string $color): int {
        global $DB;

        $color = trim($color);
        if (!self::is_valid_color($color)) {
            throw new \invalid_parameter_exception('Invalid hex color: ' . $color);
        }

        $time = time();
        $record = new \stdClass();
        $record->name = trim($name);
        $record->color = $color;
        $record->timecreated = $time;
        $record->timemodified = $time;

        return $DB->insert_record('format_mimo_desc_tags', $record);
    }

    /**
     * Update an existing description tag.
     *
     * @param int $id Tag ID
     * @param string $name Tag name
     * @param string $color Hex color code
     * @return bool Success status
     */
    public static function update_tag(int $id, string $name, string $color): bool {
        global $DB;

        $record = $DB->get_record('format_mimo_desc_tags', ['id' => $id]);
        if (!$record) {
            return false;
        }

        $color = trim($color);
        if (!self::is_valid_color($color)) {
            throw new \invalid_parameter_exception('Invalid hex color: ' . $color);
        }

        $record->name = trim($name);
        $record->color = $color;
        $record->timemodified = time();

        return $DB->update_record('format_mimo_desc_tags', $record);
    }

    /**
     * Delete a description tag.
     * This will remove the tag reference from all activity descriptions.
     *
     * @param int $id Tag ID
     * @return bool Success status
     */
    public static function delete_tag(int $id): bool {
        global $DB;

        // First, remove tag references from activity descriptions.
        $DB->set_field('format_mimo_actdesc', 'desctagid', null, ['desctagid' => $id]);

        // Clear the activity descriptions cache.
        activity_description_manager::clear_cache();

        // Delete the tag.
        return $DB->delete_records('format_mimo_desc_tags', ['id' => $id]);
    }

    /**
     * Get the count of activity descriptions using this tag.
     *
     * @param int $desctagid Tag ID
     * @return int Number of activity descriptions using this tag
     */
    public static function count_descriptions_with_tag(int $desctagid): int {
        global $DB;
        return $DB->count_records('format_mimo_actdesc', ['desctagid' => $desctagid]);
    }

    /**
     * Get tags as options for select element.
     *
     * @return array Array with tag IDs as keys and names as values
     */
    public static function get_tags_for_select(): array {
        $tags = self::get_all_tags();
        $options = [0 => get_string('notag', 'format_mimo')];

        foreach ($tags as $tag) {
            $options[$tag->id] = $tag->name;
        }

        return $options;
    }

    /**
     * Validate hex color format.
     *
     * @param string $color Color value to validate
     * @return bool True if valid hex color
     */
    public static function is_valid_color(string $color): bool {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $color) === 1;
    }

    /**
     * Initialize default description tags for a new installation.
     *
     * @return bool Success
     */
    public static function initialize_default_description_tags(): bool {
        global $DB;

        // Check if any description tags already exist.
        if ($DB->record_exists('format_mimo_desc_tags', [])) {
            return true;
        }

        $defaults = [
            ['name' => get_string('desctag_input', 'format_mimo'), 'color' => '#FFF176'],
            ['name' => get_string('desctag_practice', 'format_mimo'), 'color' => '#81C784'],
            ['name' => get_string('desctag_share', 'format_mimo'), 'color' => '#CE93D8'],
            ['name' => get_string('desctag_think', 'format_mimo'), 'color' => '#64B5F6'],
        ];

        foreach ($defaults as $tag) {
            self::create_tag($tag['name'], $tag['color']);
        }

        return true;
    }
}
