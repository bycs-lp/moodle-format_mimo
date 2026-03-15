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
 * Activity profile deletion confirmation modal.
 *
 * @module     format_mimo/profile_delete_confirm
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalDeleteCancel from 'core/modal_delete_cancel';
import ModalEvents from 'core/modal_events';
import Notification from 'core/notification';
import {get_string as getString} from 'core/str';

const SELECTORS = {
    DELETE_PROFILE: '[data-action="delete-profile"]',
};

/**
 * Initialize the profile deletion confirmation.
 */
export const init = () => {
    registerEventListeners();
};

/**
 * Register event listeners for delete actions.
 */
const registerEventListeners = () => {
    document.addEventListener('click', (event) => {
        const deleteButton = event.target.closest(SELECTORS.DELETE_PROFILE);
        if (deleteButton) {
            event.preventDefault();
            handleDeleteProfile(deleteButton);
        }
    });
};

/**
 * Handle profile deletion with confirmation modal.
 *
 * @param {HTMLElement} button The delete button element
 */
const handleDeleteProfile = async(button) => {
    try {
        const profileName = button.dataset.profileName;
        const deleteUrl = button.href;

        const modal = await ModalDeleteCancel.create({
            title: getString('deleteprofile', 'format_mimo'),
            body: getString('confirmdeleteprofile', 'format_mimo', profileName),
        });

        modal.getRoot().on(ModalEvents.delete, () => {
            window.location.href = deleteUrl;
        });

        modal.show();
    } catch (error) {
        Notification.exception(error);
    }
};
