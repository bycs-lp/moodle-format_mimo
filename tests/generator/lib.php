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
     * Create a tag.
     *
     * @param array|stdClass $record Tag data
     * @return stdClass The created tag record
     */
    public function create_tag($record = null) {
        global $DB;

        $this->tagcount++;
        $record = (object)(array)$record;

        if (!isset($record->name)) {
            $record->name = 'Test Tag ' . $this->tagcount;
        }

        // Check if a tag with this name already exists and update it instead of creating a duplicate.
        $existing = $DB->get_record('format_minimoodlewall_tags', ['name' => $record->name]);
        if ($existing) {
            // Update existing tag with new values.
            $record->id = $existing->id;
            $record->timecreated = $existing->timecreated;
            $record->timemodified = time();

            // Set activity types.
            $record->activitytype1 = $record->activitytype1 ?? $existing->activitytype1;
            $record->activitytype2 = $record->activitytype2 ?? $existing->activitytype2;
            $record->activitytype3 = $record->activitytype3 ?? $existing->activitytype3 ?? null;
            $record->sortorder = $record->sortorder ?? $existing->sortorder;

            $DB->update_record('format_minimoodlewall_tags', $record);
        } else {
            // Create new tag.
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
        }

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

    /**
     * Create a description tag.
     *
     * @param array|stdClass $record Must include name and color
     * @return stdClass The created description tag record
     */
    public function create_description_tag($record = null) {
        global $DB;

        $record = (object)(array)$record;

        if (!isset($record->name)) {
            throw new coding_exception('name is required for creating description tag');
        }
        if (!isset($record->color)) {
            throw new coding_exception('color is required for creating description tag');
        }
        if (!isset($record->timecreated)) {
            $record->timecreated = time();
        }
        if (!isset($record->timemodified)) {
            $record->timemodified = time();
        }

        $record->id = $DB->insert_record('format_minimoodlewall_desc_tags', $record);

        return $record;
    }

    /**
     * Create an activity description.
     *
     * @param array|stdClass $record Must include activitytype and description
     * @return stdClass The created activity description record
     */
    public function create_activity_description($record = null) {
        global $DB;

        $record = (object)(array)$record;

        if (!isset($record->activitytype)) {
            throw new coding_exception('activitytype is required for creating activity description');
        }
        if (!isset($record->description)) {
            throw new coding_exception('description is required for creating activity description');
        }
        if (!isset($record->timecreated)) {
            $record->timecreated = time();
        }
        if (!isset($record->timemodified)) {
            $record->timemodified = time();
        }

        // Check if description already exists for this activity type.
        $existing = $DB->get_record(
            'format_minimoodlewall_actdesc',
            ['activitytype' => $record->activitytype]
        );
        if ($existing) {
            // Update existing description.
            $existing->description = $record->description;
            $existing->desctagid = $record->desctagid ?? null;
            $existing->timemodified = time();
            $DB->update_record('format_minimoodlewall_actdesc', $existing);
            return $existing;
        }

        $record->id = $DB->insert_record('format_minimoodlewall_actdesc', $record);

        return $record;
    }

    /**
     * @var int Counter for profile creation
     */
    protected $profilecount = 0;

    /**
     * Create a profile.
     *
     * @param array|stdClass $record Profile data with name and displayname
     * @return stdClass The created profile record
     */
    public function create_profile($record = null) {
        global $DB;

        $this->profilecount++;
        $record = (object)(array)$record;

        if (!isset($record->name)) {
            $record->name = 'testprofile' . $this->profilecount;
        }

        // Check if a profile with this name already exists.
        $existing = $DB->get_record('format_minimoodlewall_profiles', ['name' => $record->name]);
        if ($existing) {
            // Update existing profile with new values.
            $record->id = $existing->id;
            $record->timecreated = $existing->timecreated;
            $record->timemodified = time();
            $record->displayname = $record->displayname ?? $existing->displayname;
            $record->sortorder = $record->sortorder ?? $existing->sortorder;

            $DB->update_record('format_minimoodlewall_profiles', $record);
            return $record;
        }

        // Create new profile.
        if (!isset($record->displayname)) {
            $record->displayname = 'Test Profile ' . $this->profilecount;
        }
        if (!isset($record->sortorder)) {
            $record->sortorder = $this->profilecount;
        }
        if (!isset($record->timecreated)) {
            $record->timecreated = time();
        }
        if (!isset($record->timemodified)) {
            $record->timemodified = time();
        }

        $record->id = $DB->insert_record('format_minimoodlewall_profiles', $record);

        return $record;
    }
}
