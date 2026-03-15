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
 * Contains the activity name with inplace editable support for minimal wall format.
 *
 * @package    format_mimo
 * @copyright  2026 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo\output\courseformat\content;

use core_courseformat\output\local\content\cm\cmname as cmname_base;
use renderer_base;

/**
 * Class to render a course module name with inplace editing in minimal wall format.
 *
 * @package    format_mimo
 * @copyright  2026 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cmname extends cmname_base {

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output typically, the renderer that's calling this function
     * @return array data context for a mustache template
     */
    public function export_for_template(renderer_base $output): array {
        $data = parent::export_for_template($output);

        // Add any format-specific data here if needed in the future.
        // For now, we just use the core implementation which provides inplace editing.

        return $data;
    }

    /**
     * Returns the output class template path.
     *
     * Use core template for cmname to get standard inplace editing functionality.
     *
     * @param renderer_base $renderer typically, the renderer that's calling this function
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'core_courseformat/local/content/cm/cmname';
    }
}
