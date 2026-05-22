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
 * Tag management modal forms (create/edit) using ModalForm.
 *
 * @module     format_mimo/tag_management_modal
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';

/**
 * Initialize the tag management modal form handlers.
 */
export const init = () => {
    document.addEventListener('click', handleClick);
};

/**
 * Handle click events for create/edit tag buttons.
 *
 * @param {Event} event The click event
 */
const handleClick = (event) => {
    const createButton = event.target.closest('[data-action="create-tag"]');
    if (createButton) {
        event.preventDefault();
        showTagForm(0, createButton);
        return;
    }

    const editButton = event.target.closest('[data-action="edit-tag"]');
    if (editButton) {
        event.preventDefault();
        const tagId = parseInt(editButton.dataset.tagId, 10);
        showTagForm(tagId, editButton);
        return;
    }

    // Allow clicking anywhere on a tag row (except delete) to trigger edit.
    if (!event.target.closest('[data-action="delete-tag"]')) {
        const tagRow = event.target.closest('tr[data-tag-id]');
        if (tagRow) {
            event.preventDefault();
            const tagId = parseInt(tagRow.dataset.tagId, 10);
            showTagForm(tagId, tagRow.querySelector('[data-action="edit-tag"]') || tagRow);
        }
    }
};

/**
 * Show the tag form in a modal dialog.
 *
 * @param {number} tagId The tag ID (0 for creating a new tag)
 * @param {HTMLElement} returnFocusElement Element to return focus to after modal closes
 */
const showTagForm = (tagId, returnFocusElement) => {
    const container = document.querySelector('[data-region="tag-management"]');
    const activeProfileId = parseInt(container?.dataset.activeProfileId || '0', 10);

    const modalForm = new ModalForm({
        formClass: 'format_mimo\\form\\tag_form',
        args: {
            tagid: tagId,
            selectedprofileid: activeProfileId,
        },
        modalConfig: {
            title: tagId
                ? getString('edittag', 'format_mimo')
                : getString('createtag', 'format_mimo'),
        },
        returnFocus: returnFocusElement,
    });

    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, () => {
        window.location.reload();
    });

    modalForm.show();
};
