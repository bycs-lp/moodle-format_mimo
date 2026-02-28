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

namespace format_minimoodlewall;

/**
 * Manages minimoodlewall-specific default completion overrides per module type.
 *
 * When a course module is created in a minimoodlewall course and its completion
 * matches the core Moodle defaults, the observer can silently replace the
 * completion settings with overrides stored in the format_minimoodlewall_compdefs table.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_defaults_manager {
    /** @var string The DB table for minimoodlewall completion defaults. */
    const TABLE = 'format_minimoodlewall_compdefs';

    /**
     * Get the minimoodlewall completion default for a specific module type.
     *
     * @param int $moduleid The modules.id value (the activity module type).
     * @return \stdClass|null The default record, or null if no override is set.
     */
    public static function get_default(int $moduleid): ?\stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['module' => $moduleid]);
        return $record ?: null;
    }

    /**
     * Get all minimoodlewall completion defaults, keyed by module id.
     *
     * @return array<int, \stdClass> Keyed by module id.
     */
    public static function get_all_defaults(): array {
        global $DB;
        return $DB->get_records(self::TABLE, null, '', '*', 0, 0);
    }

    /**
     * Get all minimoodlewall completion defaults, indexed by module id.
     *
     * @return array<int, \stdClass> Keyed by module id.
     */
    public static function get_all_defaults_by_module(): array {
        global $DB;
        $records = $DB->get_records(self::TABLE);
        $result = [];
        foreach ($records as $record) {
            $result[$record->module] = $record;
        }
        return $result;
    }

    /**
     * Save (upsert) a minimoodlewall completion default for a module type.
     *
     * @param int $moduleid The modules.id value.
     * @param \stdClass|array $data The completion data to save. Expected keys:
     *   completion, completionview, completionusegrade, completionpassgrade,
     *   completionexpected, customrules (JSON string or null).
     */
    public static function save_default(int $moduleid, $data): void {
        global $DB;

        $data = (object)$data;
        $now = time();

        $existing = $DB->get_record(self::TABLE, ['module' => $moduleid]);
        if ($existing) {
            $data->id = $existing->id;
            $data->module = $moduleid;
            $data->timemodified = $now;
            $DB->update_record(self::TABLE, $data);
        } else {
            $data->module = $moduleid;
            $data->timecreated = $now;
            $data->timemodified = $now;
            $DB->insert_record(self::TABLE, $data);
        }
    }

    /**
     * Delete the minimoodlewall completion default for a module type.
     *
     * @param int $moduleid The modules.id value.
     */
    public static function delete_default(int $moduleid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['module' => $moduleid]);
    }

    /**
     * Check whether a course module's current completion settings match the core defaults.
     *
     * This is used to decide if the observer should override the completion: if the
     * module was created with unmodified core defaults, the minimoodlewall override
     * should be applied. If the teacher customized completion in the form, we leave it alone.
     *
     * @param \stdClass $cmrecord The course_modules record (must include completion fields).
     * @param \stdClass $coredefaults The flattened result from \core_completion\manager::get_default_completion().
     * @param string $modname The module name (e.g. 'assign') for checking custom rules.
     * @return bool True if the module's completion matches core defaults exactly.
     */
    public static function matches_core_defaults(\stdClass $cmrecord, \stdClass $coredefaults, string $modname): bool {
        global $DB;

        // Compare core completion fields.
        // Note: core defaults has 'completionusegrade' but course_modules has 'completiongradeitemnumber'.
        $corefields = [
            'completion' => (int)($coredefaults->completion ?? COMPLETION_TRACKING_NONE),
            'completionview' => (int)($coredefaults->completionview ?? 0),
            'completionpassgrade' => (int)($coredefaults->completionpassgrade ?? 0),
        ];

        if ((int)$cmrecord->completion !== $corefields['completion']) {
            return false;
        }
        if ((int)$cmrecord->completionview !== $corefields['completionview']) {
            return false;
        }
        if ((int)$cmrecord->completionpassgrade !== $corefields['completionpassgrade']) {
            return false;
        }

        // Compare grade: coredefaults has 'completionusegrade' (0/1),
        // course_modules has 'completiongradeitemnumber' (null or 0).
        $coreusesgrade = !empty($coredefaults->completionusegrade);
        $cmusesgrade = !is_null($cmrecord->completiongradeitemnumber);
        if ($coreusesgrade !== $cmusesgrade) {
            return false;
        }

        // Compare custom rules if they exist in the core defaults.
        // The core defaults (flattened) include custom rule keys alongside the standard ones.
        // We need to read the module instance record and compare.
        $standardkeys = [
            'completion', 'completionview', 'completionusegrade',
            'completionpassgrade', 'completionexpected', 'modids',
        ];
        $customrulekeys = [];
        foreach ((array)$coredefaults as $key => $value) {
            if (!in_array($key, $standardkeys)) {
                $customrulekeys[$key] = $value;
            }
        }

        if (!empty($customrulekeys) && !empty($cmrecord->instance)) {
            $modinstance = $DB->get_record($modname, ['id' => $cmrecord->instance]);
            if ($modinstance) {
                foreach ($customrulekeys as $key => $expectedvalue) {
                    if (property_exists($modinstance, $key)) {
                        if ($modinstance->$key != $expectedvalue) {
                            return false;
                        }
                    }
                }
            }
        }

        return true;
    }

    /**
     * Apply minimoodlewall completion defaults to a course module.
     *
     * Updates both the course_modules record (core fields) and the module instance
     * record (custom rules from the JSON blob).
     *
     * @param \stdClass $cmrecord The course_modules record (must include id, instance).
     * @param \stdClass $mmwdefaults The minimoodlewall defaults record from the compdefs table.
     * @param string $modname The module name (e.g. 'assign').
     */
    public static function apply_defaults(\stdClass $cmrecord, \stdClass $mmwdefaults, string $modname): void {
        global $DB;

        $now = time();

        // Update course_modules with core completion fields.
        $cmdata = new \stdClass();
        $cmdata->id = $cmrecord->id;
        $cmdata->completion = (int)$mmwdefaults->completion;
        $cmdata->completionview = (int)$mmwdefaults->completionview;
        $cmdata->completionpassgrade = (int)$mmwdefaults->completionpassgrade;
        $cmdata->completionexpected = (int)$mmwdefaults->completionexpected;
        // Translate completionusegrade to completiongradeitemnumber.
        $cmdata->completiongradeitemnumber = !empty($mmwdefaults->completionusegrade) ? 0 : null;
        $cmdata->timemodified = $now;

        $DB->update_record('course_modules', $cmdata);

        // Apply custom rules to the module instance table if present.
        if (!empty($mmwdefaults->customrules)) {
            $customrules = @json_decode($mmwdefaults->customrules, true);
            if (is_array($customrules) && !empty($customrules) && !empty($cmrecord->instance)) {
                // Remove non-module fields that may have been stored in customrules.
                unset($customrules['modids']);
                unset($customrules['id']);

                if (!empty($customrules)) {
                    $moddata = ['id' => $cmrecord->instance, 'timemodified' => $now];
                    // Only set fields that actually exist on the module instance table.
                    $columns = $DB->get_columns($modname);
                    foreach ($customrules as $key => $value) {
                        if (isset($columns[$key])) {
                            $moddata[$key] = $value;
                        }
                    }
                    $DB->update_record($modname, (object)$moddata);
                }
            }
        }
    }

    /**
     * Pack form data into the format expected by the compdefs table.
     *
     * Takes the raw form submission data (which includes both core fields and
     * custom rules in a flat structure with optional suffix) and separates them
     * into the record format stored in compdefs.
     *
     * @param \stdClass|array $formdata The form submission data.
     * @param string $suffix The suffix used by the form (e.g. '_assign').
     * @return \stdClass The packed record suitable for save_default().
     */
    public static function pack_form_data($formdata, string $suffix = ''): \stdClass {
        $data = (array)$formdata;

        // Strip suffix from field names if present.
        if (!empty($suffix)) {
            $stripped = [];
            foreach ($data as $key => $value) {
                if (str_ends_with($key, $suffix)) {
                    $stripped[substr($key, 0, -strlen($suffix))] = $value;
                } else {
                    $stripped[$key] = $value;
                }
            }
            $data = $stripped;
        }

        $corefields = [
            'completion' => COMPLETION_DISABLED,
            'completionview' => COMPLETION_VIEW_NOT_REQUIRED,
            'completionexpected' => 0,
            'completionusegrade' => 0,
            'completionpassgrade' => 0,
        ];

        // Ensure core fields have defaults.
        if (!array_key_exists('completionusegrade', $data)) {
            $data['completionusegrade'] = 0;
        }
        if (!array_key_exists('completionpassgrade', $data)) {
            $data['completionpassgrade'] = 0;
        }
        if ((int)$data['completionusegrade'] === 0) {
            $data['completionpassgrade'] = 0;
        }

        // Separate custom rules from core fields.
        $customdata = array_diff_key($data, $corefields);
        // Remove non-completion fields that shouldn't be in customrules.
        unset($customdata['id']);
        unset($customdata['modids']);
        unset($customdata['modules']);
        unset($customdata['submitbutton']);
        unset($customdata['_qf__format_minimoodlewall_completion_defaults_form']);

        $record = new \stdClass();
        $record->completion = (int)($data['completion'] ?? COMPLETION_DISABLED);
        $record->completionview = (int)($data['completionview'] ?? COMPLETION_VIEW_NOT_REQUIRED);
        $record->completionusegrade = (int)($data['completionusegrade'] ?? 0);
        $record->completionpassgrade = (int)($data['completionpassgrade'] ?? 0);
        $record->completionexpected = (int)($data['completionexpected'] ?? 0);
        $record->customrules = !empty($customdata) ? json_encode($customdata) : null;

        return $record;
    }
}
