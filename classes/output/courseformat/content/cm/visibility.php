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

namespace format_mimo\output\courseformat\content\cm;

use core_courseformat\output\local\content\cm\visibility as visibility_base;
use core\output\choicelist;
use format_mimo\done_manager;
use pix_icon;

/**
 * Visibility dropdown override for mimo format.
 *
 * Adds a "Done" option to the standard show/hide/stealth choices.
 *
 * @package    format_mimo
 * @copyright  2026 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class visibility extends visibility_base {
    /**
     * Check if the visibility badge is displayed.
     *
     * Always show in mimo so teachers can access Show/Hide/Stealth/Done from any state.
     *
     * @return bool
     */
    protected function show_visibility(): bool {
        return true;
    }

    /**
     * Get the icon for the visibility state.
     *
     * @param string $selected the visibility selected value
     * @return pix_icon
     */
    protected function get_icon(string $selected): pix_icon {
        if ($selected === 'done') {
            return new pix_icon('i/checked', '');
        }
        return parent::get_icon($selected);
    }

    /**
     * Get the selected choice value.
     *
     * @return string
     */
    protected function get_selected_choice_value(): string {
        if ($this->mod->visible && done_manager::is_done($this->mod->id)) {
            return 'done';
        }
        return parent::get_selected_choice_value();
    }

    /**
     * Build the data for the interactive dropdown.
     *
     * @param \renderer_base $output
     * @param choicelist $choice the choice list
     * @return \stdClass
     */
    protected function get_dropdown_data(
        \renderer_base $output,
        choicelist $choice,
    ): \stdClass {
        // Handle done state badge text.
        if ($this->mod->visible && done_manager::is_done($this->mod->id)) {
            $badgetext = $output->visually_hidden_text(get_string('availability'));
            $badgetext .= get_string('availability_done', 'format_mimo');
            $icon = $this->get_icon('done');

            $dropdown = new \core\output\local\dropdown\status(
                $output->render($icon) . ' ' . $badgetext,
                $choice,
                ['dialogwidth' => \core\output\local\dropdown\status::WIDTH['big']],
            );
            return (object) [
                'isInteractive' => true,
                'dropwdown' => $dropdown->export_for_template($output),
            ];
        }
        return parent::get_dropdown_data($output, $choice);
    }

    /**
     * Create a choice list for the dropdown.
     *
     * Adds the "Done" option after the standard show/hide/stealth choices.
     *
     * @return choicelist
     */
    protected function create_choice_list(): choicelist {
        $choice = parent::create_choice_list();

        $format = $this->format;
        $isdone = done_manager::is_done($this->mod->id);

        // Add "Done" option — marks activity as done (greyed out, excluded from completion).
        $nonajaxurl = $format->get_update_url(
            action: $isdone ? 'cm_undone' : 'cm_done',
            ids: [$this->mod->id],
            returnurl: $format->get_view_url($format->get_sectionnum(), ['navigation' => true]),
        );

        $choice->add_option(
            'done',
            get_string('availability_done', 'format_mimo'),
            [
                'description' => get_string('availability_done_help', 'format_mimo'),
                'icon' => $this->get_icon('done'),
                'url' => $nonajaxurl,
                'extras' => [
                    'data-id' => $this->mod->id,
                    'data-action' => $isdone ? 'cmUndone' : 'cmDone',
                ],
            ]
        );

        return $choice;
    }

    /**
     * Build the static badges data.
     *
     * @param \renderer_base $output typically, the renderer that's calling this function
     * @return \stdClass|null data context for a mustache template
     */
    public function build_static_data(\renderer_base $output): ?\stdClass {
        // Handle done state for non-editor view.
        if ($this->mod->visible && done_manager::is_done($this->mod->id)) {
            return (object) [
                'isInteractive' => false,
                'moddone' => true,
            ];
        }
        return parent::build_static_data($output);
    }
}
