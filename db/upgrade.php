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
 * @copyright  2025 Tobias Garske
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

    if ($oldversion < 2026020900) {
        // Reintroduce tagset architecture: tags are grouped into tagsets,
        // courses select one tagset and then pick individual tags from it.

        // Step 1: Create tagsets table.
        $table = new xmldb_table('format_minimoodlewall_tagsets');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('name_unique', XMLDB_INDEX_UNIQUE, ['name']);
        $table->add_index('sortorder', XMLDB_INDEX_NOTUNIQUE, ['sortorder']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Step 2: Create a "Default" tagset and assign all existing tags to it.
        $now = time();
        $defaulttagsetid = null;

        if ($DB->record_exists('format_minimoodlewall_tags', [])) {
            $defaulttagset = new stdClass();
            $defaulttagset->name = 'Default';
            $defaulttagset->description = 'Auto-created tagset for existing tags.';
            $defaulttagset->sortorder = 0;
            $defaulttagset->timecreated = $now;
            $defaulttagset->timemodified = $now;
            $defaulttagsetid = $DB->insert_record('format_minimoodlewall_tagsets', $defaulttagset);
        }

        // Step 3: Add tagsetid column to tags table (nullable first for migration).
        $tagtable = new xmldb_table('format_minimoodlewall_tags');
        $field = new xmldb_field('tagsetid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'id');

        if (!$dbman->field_exists($tagtable, $field)) {
            $dbman->add_field($tagtable, $field);
        }

        // Step 4: Assign all existing tags to the default tagset.
        if ($defaulttagsetid) {
            $DB->set_field('format_minimoodlewall_tags', 'tagsetid', $defaulttagsetid);
        }

        // Step 5: Make tagsetid NOT NULL.
        $field = new xmldb_field('tagsetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');
        $dbman->change_field_notnull($tagtable, $field);

        // Step 6: Drop standalone sortorder index, add composite tagsetid_sortorder index and FK.
        $oldindex = new xmldb_index('sortorder', XMLDB_INDEX_NOTUNIQUE, ['sortorder']);
        if ($dbman->index_exists($tagtable, $oldindex)) {
            $dbman->drop_index($tagtable, $oldindex);
        }

        $newindex = new xmldb_index('tagsetid_sortorder', XMLDB_INDEX_NOTUNIQUE, ['tagsetid', 'sortorder']);
        if (!$dbman->index_exists($tagtable, $newindex)) {
            $dbman->add_index($tagtable, $newindex);
        }

        $key = new xmldb_key('tagsetid', XMLDB_KEY_FOREIGN, ['tagsetid'], 'format_minimoodlewall_tagsets', ['id']);
        $dbman->add_key($tagtable, $key);

        // Step 7: Clear caches.
        $cache = cache::make('format_minimoodlewall', 'tagconfigurations');
        $cache->purge();
        $cache = cache::make('format_minimoodlewall', 'activitytagmappings');
        $cache->purge();

        // Step 8: Set tagsetid on all existing minimoodlewall courses.
        // Without this, editing a course post-upgrade fails validation (tagsetid = 0).
        if ($defaulttagsetid) {
            $courses = $DB->get_records('course', ['format' => 'minimoodlewall']);
            foreach ($courses as $course) {
                $existing = $DB->get_record('course_format_options', [
                    'courseid' => $course->id,
                    'format' => 'minimoodlewall',
                    'name' => 'tagsetid',
                ]);
                if ($existing) {
                    if (empty($existing->value) || $existing->value == '0') {
                        $existing->value = $defaulttagsetid;
                        $DB->update_record('course_format_options', $existing);
                    }
                } else {
                    $DB->insert_record('course_format_options', (object)[
                        'courseid' => $course->id,
                        'format' => 'minimoodlewall',
                        'sectionid' => 0,
                        'name' => 'tagsetid',
                        'value' => $defaulttagsetid,
                    ]);
                }
            }
        }

        upgrade_plugin_savepoint(true, 2026020900, 'format', 'minimoodlewall');
    }

    if ($oldversion < 2026021000) {
        // Rename "design" to "style" across all tables, columns, file areas, and format options.

        // Step 1: Rename table format_minimoodlewall_designs -> format_minimoodlewall_styles.
        $table = new xmldb_table('format_minimoodlewall_designs');
        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'format_minimoodlewall_styles');
        }

        // Step 2: Rename column designid -> styleid in tag_images table.
        $table = new xmldb_table('format_minimoodlewall_tag_images');

        // Drop the old foreign key and index first.
        $key = new xmldb_key('designid', XMLDB_KEY_FOREIGN, ['designid'], 'format_minimoodlewall_styles', ['id']);
        $dbman->drop_key($table, $key);

        $index = new xmldb_index('tagid_designid_unique', XMLDB_INDEX_UNIQUE, ['tagid', 'designid']);
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        // Rename the column.
        $field = new xmldb_field('designid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'tagid');
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'styleid');
        }

        // Re-add index and foreign key with new names.
        $index = new xmldb_index('tagid_styleid_unique', XMLDB_INDEX_UNIQUE, ['tagid', 'styleid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $key = new xmldb_key('styleid', XMLDB_KEY_FOREIGN, ['styleid'], 'format_minimoodlewall_styles', ['id']);
        $dbman->add_key($table, $key);

        // Step 3: Rename course format option designvariant -> stylevariant.
        $DB->execute(
            "UPDATE {course_format_options}
                SET name = 'stylevariant'
              WHERE format = 'minimoodlewall' AND name = 'designvariant'"
        );

        // Step 4: Migrate file areas in mdl_files.
        $DB->execute(
            "UPDATE {files}
                SET filearea = 'styletagcard'
              WHERE component = 'format_minimoodlewall' AND filearea = 'designtagcard'"
        );
        $DB->execute(
            "UPDATE {files}
                SET filearea = 'styletagfilter'
              WHERE component = 'format_minimoodlewall' AND filearea = 'designtagfilter'"
        );

        upgrade_plugin_savepoint(true, 2026021000, 'format', 'minimoodlewall');
    }

    // Phase 1: Remove tagset architecture (single flat tag list).
    // Phase 2: Rename "style" → "profile" (activity profiles).
    // Phase 3: Extend profile_tags with name/bgcolor/activitytype overrides + enabled flag.
    if ($oldversion < 2026022600) {
        // ========== Phase 1: Remove tagsets ==========

        // Step 1: Ensure every course has selectedtags populated from its tagset.
        $sql = "SELECT cfo.courseid, cfo.value AS tagsetid
                  FROM {course_format_options} cfo
                  JOIN {course} c ON c.id = cfo.courseid
                 WHERE cfo.format = 'minimoodlewall'
                   AND cfo.name = 'tagsetid'
                   AND cfo.value IS NOT NULL
                   AND cfo.value != ''
                   AND cfo.value != '0'";
        $courses = $DB->get_records_sql($sql);

        $tagtable = new xmldb_table('format_minimoodlewall_tags');
        $tagsetidfield = new xmldb_field('tagsetid');

        foreach ($courses as $course) {
            if ($dbman->field_exists($tagtable, $tagsetidfield)) {
                $tagids = $DB->get_fieldset_select(
                    'format_minimoodlewall_tags',
                    'id',
                    'tagsetid = :tagsetid',
                    ['tagsetid' => $course->tagsetid],
                    'sortorder ASC, id ASC'
                );
            } else {
                $tagids = [];
            }

            if (!empty($tagids)) {
                $selectedtags = implode(',', $tagids);
                $existing = $DB->get_record('course_format_options', [
                    'courseid' => $course->courseid,
                    'format' => 'minimoodlewall',
                    'name' => 'selectedtags',
                ]);
                if ($existing) {
                    if (empty($existing->value)) {
                        $existing->value = $selectedtags;
                        $DB->update_record('course_format_options', $existing);
                    }
                } else {
                    $DB->insert_record('course_format_options', (object)[
                        'courseid' => $course->courseid,
                        'format' => 'minimoodlewall',
                        'sectionid' => 0,
                        'name' => 'selectedtags',
                        'value' => $selectedtags,
                    ]);
                }
            }
        }

        // Step 2: Delete tagsetid course format options.
        $DB->delete_records_select(
            'course_format_options',
            "format = 'minimoodlewall' AND name = 'tagsetid'"
        );

        // Step 3: Drop tagsetid FK + index + column from tags table.
        $tagtable = new xmldb_table('format_minimoodlewall_tags');

        $key = new xmldb_key('tagsetid', XMLDB_KEY_FOREIGN, ['tagsetid'], 'format_minimoodlewall_tagsets', ['id']);
        $dbman->drop_key($tagtable, $key);

        $index = new xmldb_index('tagsetid_sortorder', XMLDB_INDEX_NOTUNIQUE, ['tagsetid', 'sortorder']);
        if ($dbman->index_exists($tagtable, $index)) {
            $dbman->drop_index($tagtable, $index);
        }

        $field = new xmldb_field('tagsetid');
        if ($dbman->field_exists($tagtable, $field)) {
            $dbman->drop_field($tagtable, $field);
        }

        // Add standalone sortorder index.
        $index = new xmldb_index('sortorder', XMLDB_INDEX_NOTUNIQUE, ['sortorder']);
        if (!$dbman->index_exists($tagtable, $index)) {
            $dbman->add_index($tagtable, $index);
        }

        // Step 4: Drop tagsets table.
        $table = new xmldb_table('format_minimoodlewall_tagsets');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // ========== Phase 2: Rename "style" → "profile" ==========

        // Step 5: Rename table styles → profiles.
        $table = new xmldb_table('format_minimoodlewall_styles');
        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'format_minimoodlewall_profiles');
        }

        // Step 6: Rename tag_images → profile_tags and column styleid → profileid.
        $table = new xmldb_table('format_minimoodlewall_tag_images');
        if ($dbman->table_exists($table)) {
            // Drop old FK and unique index.
            $key = new xmldb_key('styleid', XMLDB_KEY_FOREIGN, ['styleid'], 'format_minimoodlewall_profiles', ['id']);
            $dbman->drop_key($table, $key);

            $index = new xmldb_index('tagid_styleid_unique', XMLDB_INDEX_UNIQUE, ['tagid', 'styleid']);
            if ($dbman->index_exists($table, $index)) {
                $dbman->drop_index($table, $index);
            }

            // Rename column.
            $field = new xmldb_field('styleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'tagid');
            if ($dbman->field_exists($table, $field)) {
                $dbman->rename_field($table, $field, 'profileid');
            }

            // Re-add index and FK with new names.
            $index = new xmldb_index('tagid_profileid_unique', XMLDB_INDEX_UNIQUE, ['tagid', 'profileid']);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }

            $key = new xmldb_key('profileid', XMLDB_KEY_FOREIGN, ['profileid'], 'format_minimoodlewall_profiles', ['id']);
            $dbman->add_key($table, $key);

            // Rename the table.
            $dbman->rename_table($table, 'format_minimoodlewall_profile_tags');
        }

        // Step 7: Rename course format option stylevariant → activityprofile.
        $DB->execute(
            "UPDATE {course_format_options}
                SET name = 'activityprofile'
              WHERE format = 'minimoodlewall' AND name = 'stylevariant'"
        );

        // Step 8: Migrate file areas.
        $DB->execute(
            "UPDATE {files}
                SET filearea = 'profiletagcard'
              WHERE component = 'format_minimoodlewall' AND filearea = 'styletagcard'"
        );
        $DB->execute(
            "UPDATE {files}
                SET filearea = 'profiletagfilter'
              WHERE component = 'format_minimoodlewall' AND filearea = 'styletagfilter'"
        );

        // ========== Phase 3: Extend profile_tags with overrides ==========

        $ptable = new xmldb_table('format_minimoodlewall_profile_tags');

        // Step 9: Add name override column.
        $field = new xmldb_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'profileid');
        if (!$dbman->field_exists($ptable, $field)) {
            $dbman->add_field($ptable, $field);
        }

        // Step 10: Add bgcolor override column.
        $field = new xmldb_field('bgcolor', XMLDB_TYPE_CHAR, '7', null, null, null, null, 'name');
        if (!$dbman->field_exists($ptable, $field)) {
            $dbman->add_field($ptable, $field);
        }

        // Step 11: Add activitytype override columns.
        $field = new xmldb_field('activitytype1', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'bgcolor');
        if (!$dbman->field_exists($ptable, $field)) {
            $dbman->add_field($ptable, $field);
        }

        $field = new xmldb_field('activitytype2', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'activitytype1');
        if (!$dbman->field_exists($ptable, $field)) {
            $dbman->add_field($ptable, $field);
        }

        $field = new xmldb_field('activitytype3', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'activitytype2');
        if (!$dbman->field_exists($ptable, $field)) {
            $dbman->add_field($ptable, $field);
        }

        // Step 12: Add enabled flag.
        $field = new xmldb_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'activitytype3');
        if (!$dbman->field_exists($ptable, $field)) {
            $dbman->add_field($ptable, $field);
        }

        // Step 13: Purge all caches.
        $cache = cache::make('format_minimoodlewall', 'tagconfigurations');
        $cache->purge();
        $cache = cache::make('format_minimoodlewall', 'activitytagmappings');
        $cache->purge();

        upgrade_plugin_savepoint(true, 2026022600, 'format', 'minimoodlewall');
    }

    // Step 11: Move all activities from section 1 to section 0 for minimoodlewall courses.
    // The format now uses section 0 as the sole activity container (previously section 1).
    if ($oldversion < 2026021102) {
        // Find all courses using minimoodlewall format.
        $courses = $DB->get_records('course', ['format' => 'minimoodlewall'], '', 'id');

        foreach ($courses as $course) {
            // Get section 0 and section 1 records for this course.
            $section0 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0]);
            $section1 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1]);

            if (!$section0 || !$section1) {
                // If either section doesn't exist, skip this course.
                continue;
            }

            // Move all course modules from section 1 to section 0.
            $modulesinsection1 = $DB->get_records('course_modules', ['course' => $course->id, 'section' => $section1->id]);
            if (!empty($modulesinsection1)) {
                // Update all modules to point to section 0.
                $DB->execute(
                    "UPDATE {course_modules} SET section = :section0id WHERE course = :courseid AND section = :section1id",
                    ['section0id' => $section0->id, 'courseid' => $course->id, 'section1id' => $section1->id]
                );

                // Merge the sequence: append section 1's module sequence to section 0's.
                $seq0 = trim($section0->sequence ?? '', ',');
                $seq1 = trim($section1->sequence ?? '', ',');
                $newsequence = '';
                if ($seq0 !== '' && $seq1 !== '') {
                    $newsequence = $seq0 . ',' . $seq1;
                } else if ($seq0 !== '') {
                    $newsequence = $seq0;
                } else {
                    $newsequence = $seq1;
                }
                $DB->set_field('course_sections', 'sequence', $newsequence, ['id' => $section0->id]);
            }

            // Clear section 1's sequence and delete it if it has no summary.
            $section1 = $DB->get_record('course_sections', ['id' => $section1->id]);
            if ($section1) {
                $hassummary = !empty(trim($section1->summary ?? ''));
                if (!$hassummary) {
                    $DB->delete_records('course_sections', ['id' => $section1->id]);
                } else {
                    // Just clear the sequence if there's a summary we don't want to lose.
                    $DB->set_field('course_sections', 'sequence', '', ['id' => $section1->id]);
                }
            }

            // Rebuild the course cache for this course.
            rebuild_course_cache($course->id, true);
        }

        upgrade_plugin_savepoint(true, 2026021102, 'format', 'minimoodlewall');
    }

    // Step: Remove selectedtags course format option.
    // Tags are now determined automatically by the course's activity profile.
    if ($oldversion < 2026022601) {
        $DB->delete_records_select(
            'course_format_options',
            "format = :format AND name = :name",
            ['format' => 'minimoodlewall', 'name' => 'selectedtags']
        );

        upgrade_plugin_savepoint(true, 2026022601, 'format', 'minimoodlewall');
    }

    // Step: Add imgsize column to tags table and imgplacement+imgsize overrides to profile_tags.
    if ($oldversion < 2026022800) {
        $dbman = $DB->get_manager();

        // Add imgsize to tags table.
        $table = new xmldb_table('format_minimoodlewall_tags');
        $field = new xmldb_field('imgsize', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'normal', 'imgplacement');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add imgplacement override to profile_tags table.
        $table = new xmldb_table('format_minimoodlewall_profile_tags');
        $field = new xmldb_field('imgplacement', XMLDB_TYPE_CHAR, '10', null, null, null, null, 'enabled');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add imgsize override to profile_tags table.
        $field = new xmldb_field('imgsize', XMLDB_TYPE_CHAR, '10', null, null, null, null, 'imgplacement');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026022800, 'format', 'minimoodlewall');
    }

    // Step: Add completion defaults override table.
    if ($oldversion < 2026022801) {
        $dbman = $DB->get_manager();

        $table = new xmldb_table('format_minimoodlewall_compdefs');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('module', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('completion', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('completionview', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('completionusegrade', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('completionpassgrade', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('completionexpected', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('customrules', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('module_unique', XMLDB_INDEX_UNIQUE, ['module']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026022801, 'format', 'minimoodlewall');
    }

    // Rename 'wallcolor' format option to 'backgrounddesign'.
    if ($oldversion < 2026030500) {
        $DB->set_field('course_format_options', 'name', 'backgrounddesign', [
            'format' => 'minimoodlewall',
            'name' => 'wallcolor',
        ]);

        upgrade_plugin_savepoint(true, 2026030500, 'format', 'minimoodlewall');
    }

    // Map legacy backgrounddesign values to the new theme names.
    if ($oldversion < 2026030501) {
        $mapping = [
            'green' => 'primary-school',
            'white' => 'whiteboard',
            'dark'  => 'darkmode',
        ];
        $valuefield = $DB->sql_compare_text('value');
        foreach ($mapping as $oldval => $newval) {
            $DB->execute(
                "UPDATE {course_format_options}
                    SET value = ?
                  WHERE format = ?
                    AND name = ?
                    AND {$valuefield} = ?",
                [$newval, 'minimoodlewall', 'backgrounddesign', $oldval]
            );
        }

        upgrade_plugin_savepoint(true, 2026030501, 'format', 'minimoodlewall');
    }

    // Remove 'default' backgrounddesign value — migrate to 'primary-school'.
    if ($oldversion < 2026030502) {
        $valuefield = $DB->sql_compare_text('value');
        $DB->execute(
            "UPDATE {course_format_options}
                SET value = ?
              WHERE format = ?
                AND name = ?
                AND {$valuefield} = ?",
            ['primary-school', 'minimoodlewall', 'backgrounddesign', 'default']
        );

        upgrade_plugin_savepoint(true, 2026030502, 'format', 'minimoodlewall');
    }

    // Move all activities from section 0 to section 1 for minimoodlewall courses.
    // Section 0 is now hidden; section 1 is the default wall in both single-section
    // and multi-section modes. This reverses the earlier 2026021102 migration.
    if ($oldversion < 2026031400) {
        // Find all courses using minimoodlewall format.
        $courses = $DB->get_records('course', ['format' => 'minimoodlewall'], '', 'id');

        foreach ($courses as $course) {
            // Ensure section 1 exists using direct DB insert (course_create_sections_if_missing
            // calls get_fast_modinfo which is not available during upgrade).
            if (!$DB->record_exists('course_sections', ['course' => $course->id, 'section' => 1])) {
                $sectionrecord = new \stdClass();
                $sectionrecord->course = $course->id;
                $sectionrecord->section = 1;
                $sectionrecord->summary = '';
                $sectionrecord->summaryformat = FORMAT_HTML;
                $sectionrecord->sequence = '';
                $sectionrecord->timemodified = time();
                $DB->insert_record('course_sections', $sectionrecord);
            }

            // Get section 0 and section 1 records for this course.
            $section0 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0]);
            $section1 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1]);

            if (!$section0 || !$section1) {
                continue;
            }

            // Move all course modules from section 0 to section 1.
            $modulesinsection0 = $DB->get_records('course_modules', ['course' => $course->id, 'section' => $section0->id]);
            if (!empty($modulesinsection0)) {
                // Update all modules to point to section 1.
                $DB->execute(
                    "UPDATE {course_modules} SET section = :section1id WHERE course = :courseid AND section = :section0id",
                    ['section1id' => $section1->id, 'courseid' => $course->id, 'section0id' => $section0->id]
                );

                // Merge the sequences: prepend section 0's modules to section 1's (preserve order).
                $seq0 = trim($section0->sequence ?? '', ',');
                $seq1 = trim($section1->sequence ?? '', ',');
                if ($seq0 !== '' && $seq1 !== '') {
                    $newsequence = $seq0 . ',' . $seq1;
                } else if ($seq0 !== '') {
                    $newsequence = $seq0;
                } else {
                    $newsequence = $seq1;
                }
                $DB->set_field('course_sections', 'sequence', $newsequence, ['id' => $section1->id]);

                // Clear section 0's sequence.
                $DB->set_field('course_sections', 'sequence', '', ['id' => $section0->id]);
            }
        }

        // Clear stale user preferences that remember section 0.
        $DB->delete_records_select(
            'user_preferences',
            $DB->sql_like('name', ':namepattern') . ' AND value = :sectionzero',
            ['namepattern' => 'format_minimoodlewall_lastsection_%', 'sectionzero' => '0']
        );

        upgrade_plugin_savepoint(true, 2026031400, 'format', 'minimoodlewall');
    }

    // Add scope columns to profiles and tags, and create course_tags binding table.
    if ($oldversion < 2026031401) {
        // Add scope column to profiles table.
        $table = new xmldb_table('format_minimoodlewall_profiles');
        $field = new xmldb_field('scope', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'global', 'displayname');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add scope column to tags table.
        $table = new xmldb_table('format_minimoodlewall_tags');
        $field = new xmldb_field('scope', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'global', 'name');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Create course_tags binding table.
        $table = new xmldb_table('format_minimoodlewall_course_tags');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('tagid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('tagid', XMLDB_KEY_FOREIGN, ['tagid'], 'format_minimoodlewall_tags', ['id']);
        $table->add_index('courseid_tagid_unique', XMLDB_INDEX_UNIQUE, ['courseid', 'tagid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026031401, 'format', 'minimoodlewall');
    }

    return true;
}
