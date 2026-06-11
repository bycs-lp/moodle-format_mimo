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

namespace format_mimo;

/**
 * Manages mimo-specific default completion overrides per module type.
 *
 * Mimo completion defaults are stored in the format_mimo_compdefs table and
 * applied via the coursemodule_definition_after_data form callback, which
 * pre-populates the module creation form with the configured values.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_defaults_manager {
    /** @var string The DB table for mimo completion defaults. */
    const TABLE = 'format_mimo_compdefs';

    /**
     * Get the mimo completion default for a specific module type.
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
     * Get all mimo completion defaults, keyed by module id.
     *
     * @return array<int, \stdClass> Keyed by module id.
     */
    public static function get_all_defaults(): array {
        global $DB;
        return $DB->get_records(self::TABLE, null, '', '*', 0, 0);
    }

    /**
     * Get all mimo completion defaults, indexed by module id.
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
     * Save (upsert) a mimo completion default for a module type.
     *
     * @param int $moduleid The modules.id value.
     * @param \stdClass|array $data The completion data to save. Expected keys:
     *   completion, completionview, completionusegrade, completionpassgrade,
     *   completionexpected, customrules (JSON string or null).
     */
    public static function save_default(int $moduleid, $data): void {
        global $DB;

        $data = (object)$data;
        $now = \core\di::get(\core\clock::class)->time();

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
     * Delete the mimo completion default for a module type.
     *
     * @param int $moduleid The modules.id value.
     */
    public static function delete_default(int $moduleid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['module' => $moduleid]);
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
        unset($customdata['_qf__format_mimo_completion_defaults_form']);

        $record = new \stdClass();
        $record->completion = (int)($data['completion'] ?? COMPLETION_DISABLED);
        $record->completionview = (int)($data['completionview'] ?? COMPLETION_VIEW_NOT_REQUIRED);
        $record->completionusegrade = (int)($data['completionusegrade'] ?? 0);
        $record->completionpassgrade = (int)($data['completionpassgrade'] ?? 0);
        $record->completionexpected = (int)($data['completionexpected'] ?? 0);
        $record->customrules = !empty($customdata) ? json_encode($customdata) : null;

        return $record;
    }

    /**
     * Initialize default completion defaults for all known activity types.
     *
     * Called during plugin installation and upgrade. Only seeds if the
     * compdefs table is empty (will not overwrite admin customizations).
     *
     * Activities with custom completion rules get automatic completion with
     * those rules enabled. Gradeable activities also get completionusegrade=1.
     * Activities without custom rules get manual self-completion.
     *
     * @return bool True if defaults were created, false if table already had records.
     */
    public static function initialize_default_completion_defaults(): bool {
        global $DB;

        // Guard: don't overwrite existing customizations.
        if ($DB->record_exists(self::TABLE, [])) {
            return false;
        }

        // Tier A: Automatic + custom rule + require grade.
        // Tier B: Automatic + require grade only (no custom rules available).
        // Tier C: Automatic + custom rule, no grade.
        // Tier D: Automatic + view only.
        // Tier E: Manual self-completion.
        $defaults = [
            // Tier A: Custom rule + grade.
            'assign' => [
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'customrules' => ['completionsubmit' => 1],
            ],
            'lesson' => [
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'completionusegrade' => 1,
                'customrules' => ['completionendreached' => 1],
            ],

            // Tier B: Grade only.
            'h5pactivity' => [
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'completionusegrade' => 1,
            ],
            'lti' => [
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'completionusegrade' => 1,
            ],
            'game' => [
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'completionusegrade' => 1,
            ],

            // Tier C: Custom rule, no grade.
            'quiz' => [
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'customrules' => [
                    'completionminattemptsenabled' => 1,
                    'completionminattempts' => 1,
                ],
            ],
            'scorm' => [
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'customrules' => ['completionstatusrequired' => 4],
            ],
            'choice' => [
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'customrules' => ['completionsubmit' => 1],
            ],
            'feedback' => [
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'customrules' => ['completionsubmit' => 1],
            ],
            'individualfeedback' => [
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'customrules' => ['completionsubmit' => 1],
            ],
            'forum' => [
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'customrules' => ['completionposts' => 1],
            ],
            'journal' => [
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'customrules' => ['completion_create_entry' => 1],
            ],
            'mootyper' => [
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'customrules' => ['completionexercise' => 1],
            ],
            'bigbluebuttonbn' => [
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'customrules' => ['completionattendance' => 1],
            ],
            'learningmap' => [
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'customrules' => ['completiontype' => 2],
            ],

            // Tier D: View only.
            'hvp' => [
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'completionview' => COMPLETION_VIEW_REQUIRED,
            ],

            // Tier E: Manual self-completion.
            'page' => ['completion' => COMPLETION_TRACKING_MANUAL],
            'book' => ['completion' => COMPLETION_TRACKING_MANUAL],
            'resource' => ['completion' => COMPLETION_TRACKING_MANUAL],
            'url' => ['completion' => COMPLETION_TRACKING_MANUAL],
            'imscp' => ['completion' => COMPLETION_TRACKING_MANUAL],
            'folder' => ['completion' => COMPLETION_TRACKING_MANUAL],
            'wiki' => ['completion' => COMPLETION_TRACKING_MANUAL],
            'board' => ['completion' => COMPLETION_TRACKING_MANUAL],
            'checklist' => ['completion' => COMPLETION_TRACKING_MANUAL],
            'data' => ['completion' => COMPLETION_TRACKING_MANUAL],
            'glossary' => ['completion' => COMPLETION_TRACKING_MANUAL],
            'kanban' => ['completion' => COMPLETION_TRACKING_MANUAL],
            'ratingallocate' => ['completion' => COMPLETION_TRACKING_MANUAL],
            'subcourse' => ['completion' => COMPLETION_TRACKING_MANUAL],
            'workshop' => ['completion' => COMPLETION_TRACKING_MANUAL],
            'moodleoverflow' => ['completion' => COMPLETION_TRACKING_MANUAL],
            'lightboxgallery' => ['completion' => COMPLETION_TRACKING_MANUAL],
            'aichat' => ['completion' => COMPLETION_TRACKING_MANUAL],
            'mootimeter' => ['completion' => COMPLETION_TRACKING_MANUAL],
            'geogebra' => ['completion' => COMPLETION_TRACKING_MANUAL],
            'qbank' => ['completion' => COMPLETION_TRACKING_MANUAL],
        ];

        foreach ($defaults as $modname => $config) {
            // Look up the module type id; skip if not installed.
            $module = $DB->get_record('modules', ['name' => $modname], 'id', IGNORE_MISSING);
            if (!$module) {
                continue;
            }

            $record = new \stdClass();
            $record->completion = (int)($config['completion'] ?? COMPLETION_TRACKING_NONE);
            $record->completionview = (int)($config['completionview'] ?? 0);
            $record->completionusegrade = (int)($config['completionusegrade'] ?? 0);
            $record->completionpassgrade = (int)($config['completionpassgrade'] ?? 0);
            $record->completionexpected = 0;
            $record->customrules = !empty($config['customrules'])
                ? json_encode($config['customrules'])
                : null;

            self::save_default($module->id, $record);
        }

        return true;
    }
}
