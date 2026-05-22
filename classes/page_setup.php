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

use moodle_page;
use moodle_url;
use html_writer;

/**
 * Page setup helper for the mimo course format.
 *
 * Handles distraction-free mode, compact navigation, redirect logic,
 * and navigation buttons (back/home) that are injected into page headers.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_setup {
    /** @var \format_mimo The format instance. */
    private \format_mimo $format;

    /**
     * Constructor.
     *
     * @param \format_mimo $format The course format instance.
     */
    public function __construct(\format_mimo $format) {
        $this->format = $format;
    }

    /**
     * Apply distraction-free mode if enabled for this course.
     *
     * Adds body class and toggle button to the page header.
     *
     * @param moodle_page $page The page object.
     */
    public function apply_distraction_free(moodle_page $page): void {
        $course = $this->format->get_course();
        $distractionfree = $course->distractionfree ?? false;
        if (!$distractionfree) {
            return;
        }

        // Check if user has overridden the default via user preference.
        $dfactive = get_user_preferences('format_mimo_df_active', 'true') === 'true';

        if ($dfactive) {
            $page->add_body_class('format-mimo-distraction-free');
        }

        // Add toggle button as leftmost header action.
        $dflabel = get_string('aria_toggle_distractionfree', 'format_mimo');
        $page->add_header_action(
            '<button class="mimo-df-btn" type="button"' .
            ' data-action="toggle-distraction-free"' .
            ' title="' . s($dflabel) . '">' .
            '<i class="fa fa-up-right-and-down-left-from-center mimo-df-btn__icon--expand" aria-hidden="true"></i>' .
            '<i class="fa fa-down-left-and-up-right-to-center mimo-df-btn__icon--collapse" aria-hidden="true"></i>' .
            '<span class="sr-only">' . s($dflabel) . '</span>' .
            '</button>'
        );

        $page->requires->js_call_amd('format_mimo/distraction_free', 'init');
    }

    /**
     * Apply compact secondary navigation for non-editing users.
     *
     * Replaces the secondary navigation bar with a three-dot dropdown.
     *
     * @param moodle_page $page The page object.
     */
    public function apply_compact_nav(moodle_page $page): void {
        $courseid = $this->format->get_courseid();
        $coursecontext = \core\context\course::instance($courseid);
        if (has_capability('moodle/course:update', $coursecontext)) {
            return;
        }

        $page->add_body_class('format-mimo-compact-secondarynav');
        $menulabel = get_string('compactnav_menu', 'format_mimo');
        $dropdownid = 'mimo-compact-nav-' . $courseid;
        $page->add_header_action(
            '<div class="dropdown mimo-compact-nav">' .
            '<button class="mimo-compact-nav-btn" type="button"' .
            ' id="' . $dropdownid . '" data-bs-toggle="dropdown"' .
            ' aria-expanded="false" title="' . s($menulabel) . '">' .
            '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" width="20" height="20">' .
            '<circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/>' .
            '</svg>' .
            '<span class="sr-only">' . s($menulabel) . '</span>' .
            '</button>' .
            '<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="' . $dropdownid . '"' .
            ' data-region="mimo-secondarynav-dropdown"></ul>' .
            '</div>'
        );
        $page->requires->js_call_amd('format_mimo/compact_nav', 'init');
    }

    /**
     * Handle redirects that must happen before output starts.
     *
     * Redirects section.php to view.php in multi-section mode, handles
     * remembered section logic, and enforces single-section constraints.
     *
     * @param moodle_page $page The page object.
     */
    public function handle_redirects(moodle_page $page): void {
        $course = $this->format->get_course();

        // Redirect course/section.php to course/view.php in multi-section mode.
        if ($this->format->is_multisection_enabled()) {
            $pageurl = $page->url->get_path();
            if (strpos($pageurl, '/course/section.php') !== false) {
                $sectionnum = $this->format->get_sectionnum();
                $params = ['id' => $course->id];
                if ($sectionnum !== null) {
                    $params['section'] = $sectionnum;
                }
                redirect(new moodle_url('/course/view.php', $params));
            }
        }

        // Handle view.php redirects.
        $pageurl = $page->url->get_path();
        if (strpos($pageurl, '/course/view.php') === false) {
            return;
        }

        if ($this->format->is_multisection_enabled()) {
            $sectionparam = optional_param('section', null, PARAM_INT);
            $overviewparam = optional_param('overview', 0, PARAM_INT);
            if ($sectionparam === null && !$overviewparam) {
                // No section requested and not explicitly showing overview — redirect to last-visited wall.
                $rememberedsection = $this->format->get_remembered_section();
                if ($rememberedsection !== null) {
                    redirect(new moodle_url('/course/view.php', [
                        'id' => $course->id,
                        'section' => $rememberedsection,
                    ]));
                }
            }
        } else {
            // Single-section mode: redirect any section URL that is not section 1 to plain course view.
            $sectionparam = optional_param('section', null, PARAM_INT);
            if ($sectionparam !== null && $sectionparam != 1) {
                redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
            }
        }
    }

    /**
     * Apply body classes for multi-section mode.
     *
     * @param moodle_page $page The page object.
     */
    public function apply_multisection_classes(moodle_page $page): void {
        if ($this->format->is_multisection_enabled() && !$page->user_is_editing()) {
            $page->add_body_class('format-mimo-multisection-view');
        }
    }

    /**
     * Add multi-section overview button when viewing a specific section wall.
     *
     * @param moodle_page $page The page object.
     */
    public function apply_multisection_overview_button(moodle_page $page): void {
        if (!$this->format->is_multisection_enabled()) {
            return;
        }

        $sectionparam = optional_param('section', null, PARAM_INT);
        if ($sectionparam !== null) {
            $course = $this->format->get_course();
            $overviewurl = new moodle_url('/course/view.php', ['id' => $course->id, 'overview' => 1]);
            $this->add_home_button($page, $overviewurl);
        }
    }

    /**
     * Add navigation buttons for activity pages.
     *
     * In multi-section mode: back arrow to section wall + home button to overview.
     * In single-section mode: home button to course view.
     *
     * @param moodle_page $page The page object.
     */
    public function apply_activity_navigation(moodle_page $page): void {
        $cm = $page->cm;
        if (!$cm || $cm->sectionnum < 1) {
            return;
        }
        $course = $this->format->get_course();
        if ($this->format->is_multisection_enabled()) {
            $wallurl = new moodle_url('/course/view.php', ['id' => $course->id, 'section' => $cm->sectionnum]);
            $this->add_back_button($page, $wallurl);
            $overviewurl = new moodle_url('/course/view.php', ['id' => $course->id, 'overview' => 1]);
            $this->add_home_button($page, $overviewurl);
        } else {
            $backurl = new moodle_url('/course/view.php', ['id' => $course->id]);
            $this->add_home_button($page, $backurl);
        }
    }

    /**
     * Add a "back to wall" arrow button to the page header.
     *
     * @param moodle_page $page The page object.
     * @param moodle_url $url The URL the button should link to.
     */
    public function add_back_button(moodle_page $page, moodle_url $url): void {
        $btnlabel = get_string('backtowall', 'format_mimo');
        $page->add_header_action(
            html_writer::link(
                $url,
                '<svg class="mimo-back-btn__icon" viewBox="0 0 24 24" fill="currentColor"' .
                ' aria-hidden="true" width="24" height="24">' .
                '<path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>' .
                html_writer::span($btnlabel, 'sr-only'),
                ['class' => 'mimo-back-btn', 'title' => $btnlabel]
            )
        );
    }

    /**
     * Add the "back to home" button to the page header.
     *
     * @param moodle_page $page The page object.
     * @param moodle_url $url The URL the button should link to.
     */
    public function add_home_button(moodle_page $page, moodle_url $url): void {
        $btnlabel = get_string('backtooverview', 'format_mimo');
        $page->add_header_action(
            html_writer::link(
                $url,
                '<svg class="mimo-overview-btn__icon" viewBox="0 0 24 24" fill="currentColor"' .
                ' aria-hidden="true" width="22" height="22">' .
                '<path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>' .
                html_writer::span($btnlabel, 'sr-only'),
                ['class' => 'mimo-overview-btn', 'title' => $btnlabel]
            )
        );
    }
}
