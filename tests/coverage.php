<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Coverage information for the format_mimo plugin.
 *
 * @package    format_mimo
 * @copyright  2026 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

return new class extends phpunit_coverage_info {
    /** @var array Folders to include in coverage. */
    protected $includelistfolders = [
        'classes',
        'backup',
        'db',
    ];

    /** @var array Files to include in coverage. */
    protected $includelistfiles = [
        'lib.php',
        'format.php',
        'activity_descriptions.php',
        'completion_defaults.php',
        'description_tags.php',
        'profile_management.php',
        'section_image_delete.php',
        'settings.php',
        'tag_management.php',
    ];

    /** @var array Folders to exclude from coverage. */
    protected $excludelistfolders = [
        'tests',
        'amd/src',
        'templates',
        'lang',
    ];

    /** @var array Files to exclude from coverage. */
    protected $excludelistfiles = [];
};
