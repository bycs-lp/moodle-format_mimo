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
 * Upgrade script for format_mimo.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade script for format_mimo.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function xmldb_format_mimo_upgrade($oldversion) {
    if ($oldversion < 2026082500) {
        $profilemanager = \core\di::get(\format_mimo\profile_manager::class);

        // Create the base profile as the first set. Collision rule: an
        // existing profile literally named 'base' is adopted as-is.
        if (!$profilemanager->get_profile_by_name('base')) {
            global $DB;
            $DB->execute('UPDATE {format_mimo_profiles} SET sortorder = sortorder + 1');
            $profilemanager->create_profile('base', get_string('profile_base', 'format_mimo'), 0);
        }

        // Materialize every tag × profile pair with the pre-upgrade resolved
        // values (missing rows resolved as enabled + inherited).
        $profilemanager->materialize_all_profile_tags(true);

        // Strict per-set images: preserve current appearance by copying
        // resolved anchor images into profile file areas.
        $profilemanager->copy_base_images_to_profile_tags();

        \core\di::get(\format_mimo\tag_manager::class)->clear_tag_cache();

        upgrade_plugin_savepoint(true, 2026082500, 'format', 'mimo');
    }

    return true;
}
