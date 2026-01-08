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
 * Design deletion confirmation modal.
 *
 * @module     format_minimoodlewall/design_delete_confirm
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalDeleteCancel from 'core/modal_delete_cancel';
import ModalEvents from 'core/modal_events';
import Notification from 'core/notification';
import {get_string as getString} from 'core/str';

const SELECTORS = {
    DELETE_DESIGN: '[data-action="delete-design"]',
};

/**
 * Initialize the design deletion confirmation.
 */
export const init = () => {
    registerEventListeners();
};

/**
 * Register event listeners for delete actions.
 */
const registerEventListeners = () => {
    document.addEventListener('click', (event) => {
        const deleteButton = event.target.closest(SELECTORS.DELETE_DESIGN);
        if (deleteButton) {
            event.preventDefault();
            handleDeleteDesign(deleteButton);
        }
    });
};

/**
 * Handle design deletion with confirmation modal.
 *
 * @param {HTMLElement} button The delete button element
 */
const handleDeleteDesign = async(button) => {
    try {
        const designName = button.dataset.designName;
        const deleteUrl = button.href;

        const modal = await ModalDeleteCancel.create({
            title: getString('deletedesign', 'format_minimoodlewall'),
            body: getString('confirmdeletedesign', 'format_minimoodlewall', designName),
        });

        modal.getRoot().on(ModalEvents.delete, () => {
            window.location.href = deleteUrl;
        });

        modal.show();
    } catch (error) {
        Notification.exception(error);
    }
};
