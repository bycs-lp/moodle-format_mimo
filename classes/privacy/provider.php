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
 * Privacy Subsystem implementation for format_mimo.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\user_preference_provider;

/**
 * Privacy Subsystem for format_mimo implementing user_preference_provider.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements metadata_provider, user_preference_provider {
    /**
     * Describe the type of personal data stored by this plugin.
     *
     * @param collection $collection The collection to add metadata to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference(
            'format_mimo_lastsection',
            'privacy:metadata:preference:lastsection'
        );
        return $collection;
    }

    /**
     * Export all user preferences for the plugin.
     *
     * @param int $userid The userid of the user whose data is to be exported.
     */
    public static function export_user_preferences(int $userid): void {
        global $DB;

        $preferences = $DB->get_records_select(
            'user_preferences',
            $DB->sql_like('name', ':prefix') . ' AND userid = :userid',
            ['prefix' => 'format\\_mimo\\_lastsection\\_%', 'userid' => $userid]
        );

        foreach ($preferences as $preference) {
            $courseid = substr($preference->name, strlen('format_mimo_lastsection_'));
            \core_privacy\local\request\writer::export_user_preference(
                'format_mimo',
                $preference->name,
                $preference->value,
                get_string('privacy:metadata:preference:lastsection', 'format_mimo', $courseid)
            );
        }
    }
}
