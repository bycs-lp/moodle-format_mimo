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
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';

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
        const hasimage = button.dataset.hasimage === '1';

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

        if (hasimage) {
            modalForm.addEventListener(modalForm.events.LOADED, () => {
                addDeleteButton(modalForm);
            });
        }

        modalForm.show();
    });
};

/**
 * Add a "Delete image" button to the modal footer, asking for confirmation before submitting.
 *
 * @param {ModalForm} modalForm the open modal form instance
 */
const addDeleteButton = async(modalForm) => {
    const footer = modalForm.modal.getFooter()[0];
    if (footer.querySelector('[data-action="delete-sectionimage"]')) {
        return;
    }

    const deleteText = await getString('sectionimage_delete', 'format_mimo');
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-outline-danger me-auto';
    button.dataset.action = 'delete-sectionimage';
    button.textContent = deleteText;
    footer.prepend(button);

    button.addEventListener('click', async() => {
        const confirmModal = await ModalSaveCancel.create({
            title: deleteText,
            body: await getString('sectionimage_delete_confirm', 'format_mimo'),
            buttons: {save: deleteText},
        });

        confirmModal.getRoot().on(ModalEvents.save, () => {
            const form = modalForm.modal.getRoot()[0].querySelector('form');
            const hidden = form.querySelector('[name="deleteimage"]');
            hidden.value = '1';
            form.requestSubmit();
        });
        confirmModal.getRoot().on(ModalEvents.hidden, () => {
            confirmModal.destroy();
        });
        confirmModal.show();
    });
};
