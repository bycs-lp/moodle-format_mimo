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
 * Upgrade script for format_minimoodlewall.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade script for format_minimoodlewall.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function xmldb_format_minimoodlewall_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Automatically generated Moodle v4.5.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2025112001) {
        // Initialize default tags if they don't exist.
        \format_minimoodlewall\tag_manager::initialize_default_tags();

        upgrade_plugin_savepoint(true, 2025112001, 'format', 'minimoodlewall');
    }

    if ($oldversion < 2025112302) {
        upgrade_plugin_savepoint(true, 2025112302, 'format', 'minimoodlewall');
    }

    if ($oldversion < 2025112500) {
        $table = new xmldb_table('format_minimoodlewall_tags');
        $field = new xmldb_field('bgcolor', XMLDB_TYPE_CHAR, '7', null, null, null, null, 'filterimage');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $palette = \format_minimoodlewall\tag_manager::get_default_accent_palette();
        if (!empty($palette)) {
            $tags = $DB->get_records('format_minimoodlewall_tags', null, 'sortorder ASC, id ASC');
            $index = 0;
            $count = count($palette);
            foreach ($tags as $tag) {
                $color = $palette[$index % $count];
                $DB->set_field('format_minimoodlewall_tags', 'bgcolor', $color, ['id' => $tag->id]);
                $index++;
            }
        }

        upgrade_plugin_savepoint(true, 2025112500, 'format', 'minimoodlewall');
    }

    return true;
}
