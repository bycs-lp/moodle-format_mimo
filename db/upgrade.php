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

    if ($oldversion < 2026010700) {
        // Remove tagset architecture - migrate to single flat tag list with course-based tag selection.

        // Step 1: For each course using minimoodlewall format with a tagsetid,
        // collect all tag IDs from that tagset and store as selectedtags course option.
        $sql = "SELECT cfo.courseid, cfo.value AS tagsetid
                  FROM {course_format_options} cfo
                  JOIN {course} c ON c.id = cfo.courseid
                 WHERE cfo.format = 'minimoodlewall'
                   AND cfo.name = 'tagsetid'
                   AND cfo.value IS NOT NULL
                   AND cfo.value != ''
                   AND cfo.value != '0'";
        $courses = $DB->get_records_sql($sql);

        foreach ($courses as $course) {
            // Get all tag IDs from this tagset.
            $tagids = $DB->get_fieldset_select(
                'format_minimoodlewall_tags',
                'id',
                'tagsetid = :tagsetid',
                ['tagsetid' => $course->tagsetid],
                'sortorder ASC, id ASC'
            );

            if (!empty($tagids)) {
                // Store as selectedtags course format option.
                $selectedtags = implode(',', $tagids);

                // Check if selectedtags option already exists.
                $existing = $DB->get_record('course_format_options', [
                    'courseid' => $course->courseid,
                    'format' => 'minimoodlewall',
                    'name' => 'selectedtags',
                ]);

                if ($existing) {
                    $existing->value = $selectedtags;
                    $DB->update_record('course_format_options', $existing);
                } else {
                    $option = new stdClass();
                    $option->courseid = $course->courseid;
                    $option->format = 'minimoodlewall';
                    $option->sectionid = 0;
                    $option->name = 'selectedtags';
                    $option->value = $selectedtags;
                    $DB->insert_record('course_format_options', $option);
                }
            }
        }

        // Step 2: Delete the tagsetid course format options.
        $DB->delete_records_select(
            'course_format_options',
            "format = 'minimoodlewall' AND name = 'tagsetid'"
        );

        // Step 3: Drop tagsetid foreign key and index from tags table.
        $table = new xmldb_table('format_minimoodlewall_tags');

        // Drop foreign key first.
        $key = new xmldb_key('tagsetid', XMLDB_KEY_FOREIGN, ['tagsetid'], 'format_minimoodlewall_tagsets', ['id']);
        $dbman->drop_key($table, $key);

        // Drop the composite index.
        $index = new xmldb_index('tagsetid_sortorder', XMLDB_INDEX_NOTUNIQUE, ['tagsetid', 'sortorder']);
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        // Step 4: Drop tagsetid column from tags table.
        $field = new xmldb_field('tagsetid');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Step 5: Add new sortorder index (without tagsetid).
        $index = new xmldb_index('sortorder', XMLDB_INDEX_NOTUNIQUE, ['sortorder']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Step 6: Drop the tagsets table.
        $table = new xmldb_table('format_minimoodlewall_tagsets');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Step 7: Clear caches.
        $cache = cache::make('format_minimoodlewall', 'tagconfigurations');
        $cache->purge();
        $cache = cache::make('format_minimoodlewall', 'activitytagmappings');
        $cache->purge();

        upgrade_plugin_savepoint(true, 2026010700, 'format', 'minimoodlewall');
    }

    if ($oldversion < 2026010800) {
        // Create designs table.
        $table = new xmldb_table('format_minimoodlewall_designs');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('displayname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('name_unique', XMLDB_INDEX_UNIQUE, ['name']);
        $table->add_index('sortorder', XMLDB_INDEX_NOTUNIQUE, ['sortorder']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Create tag_images table for design-specific images.
        $table = new xmldb_table('format_minimoodlewall_tag_images');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('tagid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('designid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('cardimage', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('filterimage', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('tagid', XMLDB_KEY_FOREIGN, ['tagid'], 'format_minimoodlewall_tags', ['id']);
        $table->add_key('designid', XMLDB_KEY_FOREIGN, ['designid'], 'format_minimoodlewall_designs', ['id']);
        $table->add_index('tagid_designid_unique', XMLDB_INDEX_UNIQUE, ['tagid', 'designid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Seed default designs.
        $now = time();
        $designs = [
            ['name' => 'classic', 'displayname' => 'Classic', 'sortorder' => 1],
            ['name' => 'light', 'displayname' => 'Light', 'sortorder' => 2],
            ['name' => 'dark', 'displayname' => 'Dark', 'sortorder' => 3],
        ];

        foreach ($designs as $design) {
            if (!$DB->record_exists('format_minimoodlewall_designs', ['name' => $design['name']])) {
                $record = (object) $design;
                $record->timecreated = $now;
                $record->timemodified = $now;
                $DB->insert_record('format_minimoodlewall_designs', $record);
            }
        }

        // Migrate existing tag images to the first design (classic).
        $classicdesign = $DB->get_record('format_minimoodlewall_designs', ['name' => 'classic']);
        if ($classicdesign) {
            $tags = $DB->get_records('format_minimoodlewall_tags');
            foreach ($tags as $tag) {
                // Check if tag_images record already exists.
                if (!$DB->record_exists('format_minimoodlewall_tag_images', [
                    'tagid' => $tag->id,
                    'designid' => $classicdesign->id,
                ])) {
                    $record = new stdClass();
                    $record->tagid = $tag->id;
                    $record->designid = $classicdesign->id;
                    $record->cardimage = $tag->cardimage;
                    $record->filterimage = $tag->filterimage;
                    $record->timecreated = $now;
                    $record->timemodified = $now;
                    $DB->insert_record('format_minimoodlewall_tag_images', $record);
                }
            }
        }

        upgrade_plugin_savepoint(true, 2026010800, 'format', 'minimoodlewall');
    }

    return true;
}
