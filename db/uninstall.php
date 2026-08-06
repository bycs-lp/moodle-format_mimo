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
 * Pre-uninstallation script for format_mimo.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Pre uninstallation procedure.
 */
function xmldb_format_mimo_uninstall() {
    global $DB;

    // Remove the global distraction-free preference for all users.
    $DB->delete_records('user_preferences', ['name' => 'format_mimo_df_active']);

    // Remove per-course sticky-wall preferences for all users (name includes the course id suffix).
    $DB->delete_records_select(
        'user_preferences',
        $DB->sql_like('name', ':prefix'),
        ['prefix' => $DB->sql_like_escape('format_mimo_lastsection_') . '%']
    );

    return true;
}
