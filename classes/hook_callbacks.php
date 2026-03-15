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

/**
 * Hook callbacks for minimoodlewall format.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Callback to add distraction-free mode CSS to head.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook
     */
    public static function before_standard_head_html_generation(
        \core\hook\output\before_standard_head_html_generation $hook,
    ): void {
        global $COURSE, $CFG;

        // Only apply to courses using minimoodlewall format.
        if (empty($COURSE->id) || $COURSE->id == SITEID) {
            return;
        }

        $format = course_get_format($COURSE);
        if ($format->get_format() !== 'minimoodlewall') {
            return;
        }

        // Generate CSS content.
        $css = self::get_distraction_free_css_content();
        
        // Add CSS to head.
        $hook->add_html('<style type="text/css" id="format-minimoodlewall-df-css">' . $css . '</style>');
    }

    /**
     * Generate dynamic CSS content for distraction-free mode based on plugin settings.
     *
     * @return string CSS content (without style tags)
     */
    protected static function get_distraction_free_css_content() {
        // Get settings.
        $hideselectors = get_config('format_minimoodlewall', 'distractionfreeselectors');
        $nopaddingselectors = get_config('format_minimoodlewall', 'nopaddingselectors');
        $closedrawers = get_config('format_minimoodlewall', 'closedrawers');

        // Default values if not set.
        if (empty($hideselectors)) {
            $hideselectors = "nav.fixed-top\n#nav-drawer\n#page-footer\n.activity-navigation\n" .
                           "#region-main-settings-menu\n.drawer-toggles\n.secondary-navigation";
        }
        if (empty($nopaddingselectors)) {
            $nopaddingselectors = "#page\n#topofscroll";
        }

        // Build CSS.
        $css = '';

        // Hide selectors.
        $selectors = array_filter(array_map('trim', explode("\n", $hideselectors)));
        if (!empty($selectors)) {
            $prefixed = array_map(function ($sel) {
                return 'body.format-minimoodlewall-distraction-free ' . $sel;
            }, $selectors);
            $css .= implode(",\n", $prefixed) . " {\n";
            $css .= "    display: none !important;\n";
            $css .= "}\n\n";
        }

        // No padding selectors.
        $nopaddinglist = array_filter(array_map('trim', explode("\n", $nopaddingselectors)));
        if (!empty($nopaddinglist)) {
            foreach ($nopaddinglist as $selector) {
                $css .= "body.format-minimoodlewall-distraction-free {$selector} {\n";
                $css .= "    padding-top: 0 !important;\n";
                $css .= "}\n\n";
            }
        }

        // Additional fixed styles for activity header.
        $css .= "body.format-minimoodlewall-distraction-free .activity-header {\n";
        $css .= "    margin-bottom: 0 !important;\n";
        $css .= "}\n\n";

        // Close drawers CSS (if enabled).
        if ($closedrawers) {
            $css .= "body.format-minimoodlewall-distraction-free [data-region=\"drawer\"] {\n";
            $css .= "    display: none !important;\n";
            $css .= "}\n";
        }

        return $css;
    }
}
