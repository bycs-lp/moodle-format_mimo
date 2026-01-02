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
 * Contains the control menu for activities in minimal wall format.
 *
 * @package    format_minimoodlewall
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall\output\courseformat\content\cm;

use core_courseformat\output\local\content\cm\controlmenu as controlmenu_base;
use stdClass;

/**
 * Class to render the control menu for activities in minimal wall format.
 *
 * This overrides the core controlmenu to display visibility dropdown directly
 * without the three-dots menu wrapper.
 *
 * @package    format_minimoodlewall
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class controlmenu extends controlmenu_base {

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output typically, the renderer that's calling this function
     * @return stdClass|null data context for a mustache template
     */
    public function export_for_template(\renderer_base $output): ?stdClass {
        if (!$this->format->show_activity_editor_options($this->mod)) {
            return null;
        }

        // Only check visibility capability.
        if (!has_capability('moodle/course:activityvisibility', $this->modcontext)) {
            return null;
        }

        // Get the visibility output class.
        $visibilityclass = $this->format->get_output_classname('content\\cm\\visibility');
        $visibility = new $visibilityclass($this->format, $this->section, $this->mod);
        
        // Get the editor data directly (which includes the dropdown).
        $visibilitydata = $visibility->build_editor_data($output);
        
        if (empty($visibilitydata)) {
            return null;
        }

        return (object)[
            'hasmenu' => true,
            'id' => $this->menuid,
            'visibility' => $visibilitydata,
        ];
    }
}
