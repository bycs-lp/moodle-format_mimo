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

namespace format_mimo\output\courseformat\content;

use core_courseformat\output\local\content\bulkedittools as bulkedittools_base;

/**
 * Bulk edit tools override for mimo format.
 *
 * Replaces the core availability action with a mimo-specific one that
 * includes the "Done" option in the availability modal.
 *
 * @package    format_mimo
 * @copyright  2026 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulkedittools extends bulkedittools_base {
    /**
     * Generate the bulk edit control items of a course module.
     *
     * Overrides the availability action to use mimoAvailability, which opens
     * a custom modal that includes the "Done" visibility option.
     *
     * @return array of edit control items
     */
    protected function cm_control_items(): array {
        $controls = parent::cm_control_items();

        // Replace the core availability action with our custom one that includes "Done".
        if (isset($controls['availability'])) {
            $controls['availability']['action'] = 'mimoAvailability';
        }

        return $controls;
    }
}
