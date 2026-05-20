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
 * - Make a new copy of the updated original template in tests/fixtures/<branch>/.
 *
 * Fixture copies are stored per Moodle branch (e.g. tests/fixtures/500/, tests/fixtures/501/).
 * When running CI against a new Moodle version, create a new fixture directory by copying
 * the core templates for that version.
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
     * Resolves the fixture directory for the current Moodle branch.
     *
     * Uses exact branch match first (e.g. "501"), then falls back to the
     * highest available version directory that is <= the current branch.
     *
     * @return string absolute path to the fixture directory
     */
    private static function get_fixtures_dir(): string {
        global $CFG;
        $basedir = $CFG->dirroot . '/course/format/mimo/tests/fixtures';
        $branch = $CFG->branch;

        // Exact match for current branch.
        if (is_dir($basedir . '/' . $branch)) {
            return $basedir . '/' . $branch;
        }

        // Fallback: find the highest version directory <= current branch.
        $versions = [];
        foreach (scandir($basedir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (is_dir($basedir . '/' . $entry) && is_numeric($entry)) {
                $versions[] = (int) $entry;
            }
        }
        sort($versions);

        // Pick the highest version that doesn't exceed the current branch.
        $selected = null;
        foreach ($versions as $v) {
            if ($v <= (int) $branch) {
                $selected = $v;
            }
        }

        // If nothing fits (branch older than all fixtures), use the lowest available.
        if ($selected === null && !empty($versions)) {
            $selected = $versions[0];
        }

        return $basedir . '/' . $selected;
    }

    /**
     * Data Provider for all overridden templates.
     *
     * @return array
     */
    public static function overridden_templates_provider(): array {
        global $CFG;
        $fixtures = self::get_fixtures_dir();
        $core = $CFG->dirroot . '/course/format/templates/local';
        return [
            'content' => [
                'copy' => $fixtures . '/content-copy.mustache.file',
                'orig' => $core . '/content.mustache',
            ],
            'section' => [
                'copy' => $fixtures . '/section-copy.mustache.file',
                'orig' => $core . '/content/section.mustache',
            ],
            'cm' => [
                'copy' => $fixtures . '/cm-copy.mustache.file',
                'orig' => $core . '/content/cm.mustache',
            ],
            'cmname' => [
                'copy' => $fixtures . '/cmname-copy.mustache.file',
                'orig' => $core . '/content/cm/cmname.mustache',
            ],
            'controlmenu' => [
                'copy' => $fixtures . '/controlmenu-copy.mustache.file',
                'orig' => $core . '/content/cm/controlmenu.mustache',
            ],
            'cmitem' => [
                'copy' => $fixtures . '/cmitem-copy.mustache.file',
                'orig' => $core . '/content/section/cmitem.mustache',
            ],
            'activitychooserbutton' => [
                'copy' => $fixtures . '/activitychooserbutton-copy.mustache.file',
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
        if (!file_exists($copy)) {
            $this->markTestSkipped(
                'No fixture found at ' . $copy . '. '
                . 'Please create fixtures for Moodle branch ' . $GLOBALS['CFG']->branch . '.'
            );
        }
        $message = "The original template " . $orig . " has changed since the last update. "
            . "Please check for differences and update the fixture in tests/fixtures/" . $GLOBALS['CFG']->branch . "/!";
        $this->assertFileExists($orig);
        $this->assertFileEquals($copy, $orig, $message);
    }
}
