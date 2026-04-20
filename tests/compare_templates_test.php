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
 * Compares all by format_mimo overridden templates.
 *
 * @package    format_mimo
 * @copyright  2026 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo;

/**
 * Compares all by format_mimo overridden core course format templates.
 *
 * The tests compare a copy of the original template with the new version of the template
 * for all overridden templates.
 * Goal: Automatically find changes to overridden templates to be able to adjust them.
 *
 * If a test fails, this means the original template has changed since the last update.
 * Steps to do:
 * - Check and if necessary adjust overridden template in format_mimo!
 * - Make a new copy of the updated original template in tests/fixtures.
 *
 * @package    format_mimo
 * @copyright  2026 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      format_mimo
 * @group      mebis
 * @coversNothing
 */
final class compare_templates_test extends \advanced_testcase {
    /**
     * Data Provider for all overridden templates.
     *
     * @return array
     */
    public static function overridden_templates_provider(): array {
        global $CFG;
        $fixtures = $CFG->dirroot . '/course/format/mimo/tests/fixtures';
        $core = $CFG->dirroot . '/course/format/templates/local';
        return [
            'content' => [
                'copy' => $fixtures . '/content-copy.mustache',
                'orig' => $core . '/content.mustache',
            ],
            'section' => [
                'copy' => $fixtures . '/section-copy.mustache',
                'orig' => $core . '/content/section.mustache',
            ],
            'cm' => [
                'copy' => $fixtures . '/cm-copy.mustache',
                'orig' => $core . '/content/cm.mustache',
            ],
            'cmname' => [
                'copy' => $fixtures . '/cmname-copy.mustache',
                'orig' => $core . '/content/cm/cmname.mustache',
            ],
            'controlmenu' => [
                'copy' => $fixtures . '/controlmenu-copy.mustache',
                'orig' => $core . '/content/cm/controlmenu.mustache',
            ],
            'cmitem' => [
                'copy' => $fixtures . '/cmitem-copy.mustache',
                'orig' => $core . '/content/section/cmitem.mustache',
            ],
            'activitychooserbutton' => [
                'copy' => $fixtures . '/activitychooserbutton-copy.mustache',
                'orig' => $core . '/content/activitychooserbutton.mustache',
            ],
        ];
    }

    /**
     * Checks for changes in any overridden template.
     *
     * @dataProvider overridden_templates_provider
     * @param string $copy path to the copy of the template
     * @param string $orig path to the original template
     */
    public function test_overridden_templates(string $copy, string $orig): void {
        $message = "The original template " . $orig . " has changed since the last update. Please check for differences!";
        $this->assertFileExists($copy);
        $this->assertFileExists($orig);
        $this->assertFileEquals($copy, $orig, $message);
    }
}
