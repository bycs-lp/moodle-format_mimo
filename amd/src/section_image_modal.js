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
 * Section image modal — opens a dynamic form for uploading/changing section overview card images.
 *
 * @module     format_mimo/section_image_modal
 * @copyright  2026 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import {getString} from 'core/str';

/**
 * Initialise click handler on the overview grid.
 */
export const init = () => {
    const grid = document.querySelector('[data-region="mimo-overview-grid"]');
    if (!grid) {
        return;
    }

    grid.addEventListener('click', async(event) => {
        const button = event.target.closest('[data-action="section-image"]');
        if (!button) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();

        const courseid = parseInt(button.dataset.courseid, 10);
        const sectionid = parseInt(button.dataset.sectionid, 10);
        const sectionname = button.dataset.sectionname || '';

        const title = await getString('sectionimage_upload_title', 'format_mimo', sectionname);

        const modalForm = new ModalForm({
            formClass: 'format_mimo\\form\\section_image_form',
            args: {courseid, sectionid},
            modalConfig: {title},
            returnFocus: button,
        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, () => {
            window.location.reload();
        });

        modalForm.show();
    });
};
