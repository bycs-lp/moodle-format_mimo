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
 * Profile management modal forms (create/edit) using ModalForm.
 *
 * @module     format_mimo/profile_management_modal
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';

/**
 * Initialize the profile management modal form handlers.
 */
export const init = () => {
    document.addEventListener('click', handleClick);
};

/**
 * Handle click events for create/edit profile buttons.
 *
 * @param {Event} event The click event
 */
const handleClick = (event) => {
    const createButton = event.target.closest('[data-action="create-profile"]');
    if (createButton) {
        event.preventDefault();
        showProfileForm(0, createButton);
        return;
    }

    const editButton = event.target.closest('[data-action="edit-profile"]');
    if (editButton) {
        event.preventDefault();
        const profileId = parseInt(editButton.dataset.profileId, 10);
        showProfileForm(profileId, editButton);
    }
};

/**
 * Show the profile form in a modal dialog.
 *
 * @param {number} profileId The profile ID (0 for creating a new profile)
 * @param {HTMLElement} returnFocusElement Element to return focus to after modal closes
 */
const showProfileForm = (profileId, returnFocusElement) => {
    const modalForm = new ModalForm({
        formClass: 'format_mimo\\form\\profile_form',
        args: {
            profileid: profileId,
        },
        modalConfig: {
            title: profileId
                ? M.util.get_string('editprofile', 'format_mimo')
                : M.util.get_string('createprofile', 'format_mimo'),
        },
        returnFocus: returnFocusElement,
    });

    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, () => {
        window.location.reload();
    });

    modalForm.show();
};
