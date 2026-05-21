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
     * Returns the fixture directory for the current Moodle branch.
     *
     * Always returns the exact branch path. If no matching directory exists,
     * the test will fail to signal that fixtures must be created.
     *
     * @return string absolute path to the expected fixture directory
     */
    private static function get_fixtures_dir(): string {
        global $CFG;
        return $CFG->dirroot . '/course/format/mimo/tests/fixtures/' . $CFG->branch;
    }

    /**
     * Returns the fallback fixture file from the highest previous branch.
     *
     * Used to compare whether a template has changed when no fixture
     * directory exists for the current branch.
     *
     * @param string $filename the fixture filename (e.g. "content-copy.mustache.file")
     * @return string|null path to the fallback fixture, or null if none found
     */
    private static function get_fallback_fixture(string $filename): ?string {
        global $CFG;
        $basedir = $CFG->dirroot . '/course/format/mimo/tests/fixtures';
        $branch = (int) $CFG->branch;

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

        // Pick the highest version that is strictly less than the current branch.
        $selected = null;
        foreach ($versions as $v) {
            if ($v < $branch) {
                $selected = $v;
            }
        }

        if ($selected === null) {
            return null;
        }

        $path = $basedir . '/' . $selected . '/' . $filename;
        return file_exists($path) ? $path : null;
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
        $datasets = [
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
        ];

        // The activitychooserbutton template was moved to core_courseformat in Moodle 5.1 (MDL-86337).
        if ($CFG->branch >= 501) {
            $datasets['activitychooserbutton'] = [
                'copy' => $fixtures . '/activitychooserbutton-copy.mustache.file',
                'orig' => $core . '/content/activitychooserbutton.mustache',
            ];
        }

        return $datasets;
    }

    /**
     * Checks for changes in any overridden template.
     *
     * @dataProvider overridden_templates_provider
     * @param string $copy path to the copy of the template
     * @param string $orig path to the original template
     */
    public function test_overridden_templates(string $copy, string $orig): void {
        global $CFG;
        if (!file_exists($copy)) {
            // No fixtures for this branch — compare against previous branch to report changes.
            $filename = basename($copy);
            $fallback = self::get_fallback_fixture($filename);
            $changed = '';
            if ($fallback && file_exists($orig)) {
                if (file_get_contents($fallback) !== file_get_contents($orig)) {
                    $changed = ' NOTE: This template HAS CHANGED compared to the previous branch fixture.';
                } else {
                    $changed = ' This template has not changed compared to the previous branch fixture.';
                }
            }
            $this->fail(
                'No fixture found at ' . $copy . '. '
                . 'Please create fixtures for Moodle branch ' . $CFG->branch . ': '
                . 'copy core templates from MOODLE_' . $CFG->branch . '_STABLE into '
                . 'tests/fixtures/' . $CFG->branch . '/.' . $changed
            );
        }
        $message = "The original template " . $orig . " has changed since the last update. "
            . "Please check for differences and update the fixture in tests/fixtures/" . $CFG->branch . "/!";
        $this->assertFileExists($orig);
        $this->assertFileEquals($copy, $orig, $message);
    }
}
