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

defined('MOODLE_INTERNAL') || die();

use moodle_url;
use tabobject;

/**
 * Renders a shared tab navigation bar across all minimoodlewall admin pages.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_page_tabs {
    /**
     * Render the admin page tab navigation.
     *
     * @param string $currenttab The identifier of the currently active tab.
     * @return string The rendered HTML for the tab bar.
     */
    public static function render(string $currenttab): string {
        global $OUTPUT;

        $tabs = [
            new tabobject(
                'tags',
                new moodle_url('/course/format/minimoodlewall/tag_management.php'),
                get_string('tagmanagement', 'format_minimoodlewall')
            ),
            new tabobject(
                'descriptiontags',
                new moodle_url('/course/format/minimoodlewall/description_tags.php'),
                get_string('desctagmanagement', 'format_minimoodlewall')
            ),
            new tabobject(
                'activitydescriptions',
                new moodle_url('/course/format/minimoodlewall/activity_descriptions.php'),
                get_string('activitydescriptions', 'format_minimoodlewall')
            ),
            new tabobject(
                'profiles',
                new moodle_url('/course/format/minimoodlewall/profile_management.php'),
                get_string('profilemanagement', 'format_minimoodlewall')
            ),
            new tabobject(
                'completiondefaults',
                new moodle_url('/course/format/minimoodlewall/completion_defaults.php'),
                get_string('completiondefaults', 'format_minimoodlewall')
            ),
            new tabobject(
                'settings',
                new moodle_url('/admin/settings.php', ['section' => 'format_minimoodlewall']),
                get_string('distractionfreemode', 'format_minimoodlewall')
            ),
        ];

        return $OUTPUT->tabtree($tabs, $currenttab);
    }
}
