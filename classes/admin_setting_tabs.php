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

defined('MOODLE_INTERNAL') || die();

/**
 * Admin setting that renders the shared tab navigation for minimoodlewall admin pages.
 *
 * This is used in settings.php where we don't control $OUTPUT->header() directly.
 * It renders as a full-width tab bar above the other settings.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_minimoodlewall_admin_setting_tabs extends admin_setting {
    /**
     * Constructor.
     */
    public function __construct() {
        $this->nosave = true;
        parent::__construct(
            'format_minimoodlewall/admintabs',
            '',
            '',
            ''
        );
    }

    /**
     * Always returns true — nothing to store.
     *
     * @return bool
     */
    public function get_setting() {
        return true;
    }

    /**
     * Always returns empty string — nothing to write.
     *
     * @param mixed $data Unused.
     * @return string
     */
    public function write_setting($data) {
        return '';
    }

    /**
     * Render the tab navigation HTML.
     *
     * @param mixed $data Unused.
     * @param string $query Unused.
     * @return string The HTML output.
     */
    public function output_html($data, $query = '') {
        return \format_minimoodlewall\admin_page_tabs::render('settings');
    }
}
