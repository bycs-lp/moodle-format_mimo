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
 * Data generator for format_minimoodlewall.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_minimoodlewall_generator extends component_generator_base {

    /**
     * @var int Counter for tag creation
     */
    protected $tagcount = 0;

    /**
     * @var int Counter for tagset creation
     */
    protected $tagsetcount = 0;

    /**
     * Create a tag set.
     *
     * @param array|stdClass $record
     * @return stdClass The created tagset record
     */
    public function create_tagset($record = null) {
        global $DB;

        $this->tagsetcount++;
        $record = (object)(array)$record;

        if (!isset($record->name)) {
            $record->name = 'Test Tagset ' . $this->tagsetcount;
        }
        if (!isset($record->timecreated)) {
            $record->timecreated = time();
        }
        if (!isset($record->timemodified)) {
            $record->timemodified = time();
        }

        $record->id = $DB->insert_record('format_minimoodlewall_tagsets', $record);

        return $record;
    }

    /**
     * Create a tag.
     *
     * @param array|stdClass $record Must include tagsetid or tagset name
     * @return stdClass The created tag record
     */
    public function create_tag($record = null) {
        global $DB;

        $this->tagcount++;
        $record = (object)(array)$record;

        if (!isset($record->tagsetid)) {
            throw new coding_exception('tagsetid is required for creating tags');
        }

        if (!isset($record->name)) {
            $record->name = 'Test Tag ' . $this->tagcount;
        }
        if (!isset($record->activitytype1)) {
            $record->activitytype1 = 'assign';
        }
        if (!isset($record->activitytype2)) {
            $record->activitytype2 = 'quiz';
        }
        if (!isset($record->sortorder)) {
            $record->sortorder = $this->tagcount;
        }
        if (!isset($record->timecreated)) {
            $record->timecreated = time();
        }
        if (!isset($record->timemodified)) {
            $record->timemodified = time();
        }

        $record->id = $DB->insert_record('format_minimoodlewall_tags', $record);

        return $record;
    }

    /**
     * Assign a tag to a course module.
     *
     * @param array|stdClass $record Must include cmid and tagid
     * @return stdClass The created cmtag record
     */
    public function create_cmtag($record = null) {
        global $DB;

        $record = (object)(array)$record;

        if (!isset($record->cmid)) {
            throw new coding_exception('cmid is required for creating cmtag');
        }
        if (!isset($record->tagid)) {
            throw new coding_exception('tagid is required for creating cmtag');
        }
        if (!isset($record->timecreated)) {
            $record->timecreated = time();
        }

        // Check if tag is already assigned to this cm.
        $existing = $DB->get_record(
            'format_minimoodlewall_cmtags',
            ['cmid' => $record->cmid]
        );
        if ($existing) {
            // Update existing assignment.
            $existing->tagid = $record->tagid;
            $existing->timemodified = time();
            $DB->update_record('format_minimoodlewall_cmtags', $existing);
            return $existing;
        }

        $record->id = $DB->insert_record('format_minimoodlewall_cmtags', $record);

        return $record;
    }
}
