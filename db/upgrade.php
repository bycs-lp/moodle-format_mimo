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

    if ($oldversion < 2025112501) {
        // Define table format_minimoodlewall_actdesc to be created.
        $table = new xmldb_table('format_minimoodlewall_actdesc');

        // Adding fields to table format_minimoodlewall_actdesc.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('activitytype', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table format_minimoodlewall_actdesc.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table format_minimoodlewall_actdesc.
        $table->add_index('activitytype_unique', XMLDB_INDEX_UNIQUE, ['activitytype']);

        // Conditionally launch create table for format_minimoodlewall_actdesc.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2025112501, 'format', 'minimoodlewall');
    }

    if ($oldversion < 2025112800) {
        // Add activitytype3 field to format_minimoodlewall_tags table.
        $table = new xmldb_table('format_minimoodlewall_tags');
        $field = new xmldb_field('activitytype3', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'activitytype2');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2025112800, 'format', 'minimoodlewall');
    }

    if ($oldversion < 2025112801) {
        // Drop unused description fields from tagsets and tags tables.
        $table = new xmldb_table('format_minimoodlewall_tagsets');
        $field = new xmldb_field('description');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        $table = new xmldb_table('format_minimoodlewall_tags');
        $field = new xmldb_field('description');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2025112801, 'format', 'minimoodlewall');
    }

    if ($oldversion < 2025112802) {
        // Create description tags table.
        $table = new xmldb_table('format_minimoodlewall_desc_tags');

        // Adding fields to table format_minimoodlewall_desc_tags.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('color', XMLDB_TYPE_CHAR, '7', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table format_minimoodlewall_desc_tags.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for format_minimoodlewall_desc_tags.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Add desctagid field to activity descriptions table.
        $table = new xmldb_table('format_minimoodlewall_actdesc');
        $field = new xmldb_field('desctagid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'description');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add index and foreign key for desctagid.
        $index = new xmldb_index('desctagid', XMLDB_INDEX_NOTUNIQUE, ['desctagid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $key = new xmldb_key('desctagid', XMLDB_KEY_FOREIGN, ['desctagid'], 'format_minimoodlewall_desc_tags', ['id']);
        $dbman->add_key($table, $key);

        upgrade_plugin_savepoint(true, 2025112802, 'format', 'minimoodlewall');
    }

    if ($oldversion < 2025112803) {
        // Rename tagid to desctagid in format_minimoodlewall_actdesc table.
        $table = new xmldb_table('format_minimoodlewall_actdesc');
        $field = new xmldb_field('tagid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'description');

        if ($dbman->field_exists($table, $field)) {
            // Drop old foreign key and index first.
            $key = new xmldb_key('tagid', XMLDB_KEY_FOREIGN, ['tagid'], 'format_minimoodlewall_desc_tags', ['id']);
            $dbman->drop_key($table, $key);

            $index = new xmldb_index('tagid', XMLDB_INDEX_NOTUNIQUE, ['tagid']);
            if ($dbman->index_exists($table, $index)) {
                $dbman->drop_index($table, $index);
            }

            // Rename the field.
            $field = new xmldb_field('tagid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'description');
            $dbman->rename_field($table, $field, 'desctagid');

            // Add new foreign key and index.
            $index = new xmldb_index('desctagid', XMLDB_INDEX_NOTUNIQUE, ['desctagid']);
            $dbman->add_index($table, $index);

            $key = new xmldb_key('desctagid', XMLDB_KEY_FOREIGN, ['desctagid'], 'format_minimoodlewall_desc_tags', ['id']);
            $dbman->add_key($table, $key);
        }

        // Clear the activity descriptions cache.
        $cache = cache::make('format_minimoodlewall', 'activity_descriptions');
        $cache->purge();

        upgrade_plugin_savepoint(true, 2025112803, 'format', 'minimoodlewall');
    }

    if ($oldversion < 2025120300) {
        // Add imgplacement field to format_minimoodlewall_tags table.
        $table = new xmldb_table('format_minimoodlewall_tags');
        $field = new xmldb_field('imgplacement', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'center', 'cardimage');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2025120300, 'format', 'minimoodlewall');
    }

    return true;
}
