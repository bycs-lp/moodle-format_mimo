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
 * External functions for format_minimoodlewall.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use format_minimoodlewall\activity_description_manager;

/**
 * External functions for retrieving activity descriptions.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_activity_descriptions extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'activitytypes' => new external_multiple_structure(
                new external_value(PARAM_ALPHANUMEXT, 'Activity type name'),
                'List of activity types to get descriptions for',
                VALUE_REQUIRED
            ),
        ]);
    }

    /**
     * Get descriptions for specified activity types.
     *
     * @param array $activitytypes List of activity type names
     * @return array Array of descriptions
     */
    public static function execute(array $activitytypes) {
        global $PAGE;
        
        $params = self::validate_parameters(self::execute_parameters(), [
            'activitytypes' => $activitytypes,
        ]);

        // Set up a minimal context for rendering (required for external webservices).
        $PAGE->set_context(\context_system::instance());
        $renderer = $PAGE->get_renderer('core');

        $descriptions = [];
        foreach ($params['activitytypes'] as $type) {
            $desc = activity_description_manager::get_description($type);
            
            // Get activity icon and purpose.
            $icon = \core_course\output\activity_icon::from_modname($type);
            $iconhtml = $renderer->render($icon);
            $purpose = plugin_supports('mod', $type, FEATURE_MOD_PURPOSE, MOD_PURPOSE_OTHER);
            $purposeclass = self::get_purpose_classname($purpose);
            
            $descriptions[] = [
                'activitytype' => $type,
                'description' => $desc ?? '',
                'iconhtml' => $iconhtml,
                'purpose' => $purposeclass,
            ];
        }

        return $descriptions;
    }
    
    /**
     * Convert purpose constant to CSS class name.
     *
     * @param string $purpose The purpose constant
     * @return string The CSS class name
     */
    private static function get_purpose_classname($purpose) {
        $purposes = [
            MOD_PURPOSE_ADMINISTRATION => 'administration',
            MOD_PURPOSE_ASSESSMENT => 'assessment',
            MOD_PURPOSE_COLLABORATION => 'collaboration',
            MOD_PURPOSE_COMMUNICATION => 'communication',
            MOD_PURPOSE_CONTENT => 'content',
            MOD_PURPOSE_INTERACTIVECONTENT => 'interactivecontent',
            MOD_PURPOSE_OTHER => '',
        ];
        
        return $purposes[$purpose] ?? '';
    }

    /**
     * Returns description of method result value.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'activitytype' => new external_value(PARAM_ALPHANUMEXT, 'Activity type name'),
                'description' => new external_value(PARAM_RAW, 'Activity description'),
                'iconhtml' => new external_value(PARAM_RAW, 'Activity icon HTML'),
                'purpose' => new external_value(PARAM_ALPHANUMEXT, 'Activity purpose CSS class'),
            ])
        );
    }
}
