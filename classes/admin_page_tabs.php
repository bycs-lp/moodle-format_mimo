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


use moodle_url;
use tabobject;

/**
 * Renders a shared tab navigation bar across all mimo admin pages.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
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
                new moodle_url('/course/format/mimo/tag_management.php'),
                get_string('tagmanagement', 'format_mimo')
            ),
            new tabobject(
                'descriptiontags',
                new moodle_url('/course/format/mimo/description_tags.php'),
                get_string('desctagmanagement', 'format_mimo')
            ),
            new tabobject(
                'activitydescriptions',
                new moodle_url('/course/format/mimo/activity_descriptions.php'),
                get_string('activitydescriptions', 'format_mimo')
            ),
            new tabobject(
                'profiles',
                new moodle_url('/course/format/mimo/profile_management.php'),
                get_string('profilemanagement', 'format_mimo')
            ),
            new tabobject(
                'completiondefaults',
                new moodle_url('/course/format/mimo/completion_defaults.php'),
                get_string('completiondefaults', 'format_mimo')
            ),
            new tabobject(
                'settings',
                new moodle_url('/admin/settings.php', ['section' => 'format_mimo']),
                get_string('distractionfreemode', 'format_mimo')
            ),
        ];

        return $OUTPUT->tabtree($tabs, $currenttab);
    }
}
