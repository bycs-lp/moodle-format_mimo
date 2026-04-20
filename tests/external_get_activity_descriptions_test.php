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
 * Unit tests for get_activity_descriptions external API.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo;

use ReflectionClass;
use ReflectionMethod;

/**
 * Test case for get_activity_descriptions external API.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_mimo\external\get_activity_descriptions
 * @runTestsInSeparateProcesses
 */
final class external_get_activity_descriptions_test extends \advanced_testcase {
    /**
     * Test that all Moodle core purpose constants are mapped.
     *
     * This test ensures our CSS class mapping stays in sync with Moodle core.
     * If Moodle adds new MOD_PURPOSE_* constants, this test will fail and remind
     * us to update the mapping in get_activity_descriptions::get_purpose_classname().
     */
    public function test_all_purpose_constants_are_mapped(): void {
        global $CFG;
        require_once($CFG->dirroot . '/lib/moodlelib.php');

        // Get all MOD_PURPOSE_* constants from Moodle core.
        $corepurposes = [];
        $constants = get_defined_constants(true)['user'];
        foreach ($constants as $name => $value) {
            if (strpos($name, 'MOD_PURPOSE_') === 0) {
                $corepurposes[$name] = $value;
            }
        }

        // Get the mapping from our external API class using reflection.
        $class = new ReflectionClass(\format_mimo\external\get_activity_descriptions::class);
        $method = $class->getMethod('get_purpose_classname');
        $method->setAccessible(true);

        // Test each core purpose constant.
        $unmapped = [];
        foreach ($corepurposes as $constantname => $constantvalue) {
            // Call the private method to get the CSS class.
            $cssclass = $method->invoke(null, $constantvalue);

            // Check if it returns a value (empty string is valid for MOD_PURPOSE_OTHER).
            if ($cssclass === null) {
                $unmapped[] = $constantname;
            }
        }

        // Assert all constants are mapped.
        $this->assertEmpty(
            $unmapped,
            'The following Moodle purpose constants are not mapped in get_purpose_classname(): ' .
            implode(', ', $unmapped) .
            '. Please update the $purposes array in get_activity_descriptions::get_purpose_classname().'
        );
    }

    /**
     * Test that mapped CSS classes are valid identifiers.
     *
     * Ensures all returned CSS class names follow valid CSS identifier rules.
     */
    public function test_purpose_css_classes_are_valid_identifiers(): void {
        global $CFG;
        require_once($CFG->dirroot . '/lib/moodlelib.php');

        // Get all MOD_PURPOSE_* constants.
        $corepurposes = [];
        $constants = get_defined_constants(true)['user'];
        foreach ($constants as $name => $value) {
            if (strpos($name, 'MOD_PURPOSE_') === 0) {
                $corepurposes[$name] = $value;
            }
        }

        // Get the mapping method.
        $class = new ReflectionClass(\format_mimo\external\get_activity_descriptions::class);
        $method = $class->getMethod('get_purpose_classname');
        $method->setAccessible(true);

        // Test each purpose.
        foreach ($corepurposes as $constantname => $constantvalue) {
            $cssclass = $method->invoke(null, $constantvalue);

            // Empty string is valid (for MOD_PURPOSE_OTHER).
            if ($cssclass === '') {
                continue;
            }

            // CSS class must be lowercase alphanumeric (no spaces, special chars).
            $this->assertMatchesRegularExpression(
                '/^[a-z][a-z0-9]*$/',
                $cssclass,
                "CSS class '$cssclass' for $constantname is not a valid lowercase alphanumeric identifier"
            );
        }
    }

    /**
     * Test that the execute method returns proper structure.
     */
    public function test_execute_returns_valid_structure(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // We just test with 'page' as it should exist in all Moodle installations.
        $activitytypes = ['page'];

        $results = \format_mimo\external\get_activity_descriptions::execute($activitytypes);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);

        $result = $results[0];
        $this->assertArrayHasKey('activitytype', $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('iconhtml', $result);
        $this->assertArrayHasKey('purpose', $result);
        $this->assertArrayHasKey('tagname', $result);
        $this->assertArrayHasKey('tagcolor', $result);

        $this->assertEquals('page', $result['activitytype']);
        $this->assertIsString($result['description']);
        $this->assertIsString($result['iconhtml']);
        $this->assertIsString($result['purpose']);
    }
}
