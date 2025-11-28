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
 * Description tag management JavaScript.
 *
 * @module     format_minimoodlewall/description_tag_management
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import Notification from 'core/notification';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import {get_string as getString} from 'core/str';

/**
 * Initialize the description tag management page.
 */
export const init = () => {
    registerEventListeners();
};

/**
 * Register event listeners for tag management actions.
 */
const registerEventListeners = () => {
    document.addEventListener('click', (event) => {
        // Handle create tag button.
        const createButton = event.target.closest('[data-action="create-tag"]');
        if (createButton) {
            event.preventDefault();
            showTagForm(0);
            return;
        }

        // Handle edit tag button.
        const editButton = event.target.closest('[data-action="edit-tag"]');
        if (editButton) {
            event.preventDefault();
            const tagId = parseInt(editButton.dataset.id);
            showTagForm(tagId);
            return;
        }

        // Handle delete tag button.
        const deleteButton = event.target.closest('[data-action="delete-tag"]');
        if (deleteButton) {
            event.preventDefault();
            const tagId = parseInt(deleteButton.dataset.id);
            const tagName = deleteButton.dataset.name;
            const usageCount = parseInt(deleteButton.dataset.usagecount);
            showDeleteConfirmation(tagId, tagName, usageCount);
            return;
        }
    });
};

/**
 * Show the tag form in a modal.
 *
 * @param {number} tagId The tag ID (0 for creating new tag)
 */
const showTagForm = (tagId) => {
    const modalForm = new ModalForm({
        formClass: 'format_minimoodlewall\\form\\description_tag_form',
        args: {id: tagId},
        modalConfig: {
            title: tagId ? M.util.get_string('editdesctag', 'format_minimoodlewall') :
                           M.util.get_string('createdesctag', 'format_minimoodlewall'),
        },
        returnFocus: tagId ? document.querySelector(`[data-action="edit-tag"][data-id="${tagId}"]`) :
                             document.querySelector('[data-action="create-tag"]'),
    });

    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (event) => {
        if (event.detail.result) {
            Notification.addNotification({
                message: event.detail.message,
                type: 'success',
            });
            // Reload the page to show updated tag list.
            window.location.reload();
        }
    });

    modalForm.show();
};

/**
 * Show delete confirmation modal.
 *
 * @param {number} tagId The tag ID to delete
 * @param {string} tagName The tag name
 * @param {number} usageCount How many descriptions use this tag
 */
const showDeleteConfirmation = async(tagId, tagName, usageCount) => {
    const titleStr = await getString('deletetag', 'format_minimoodlewall');
    const confirmStr = await getString('confirmdeletedestag', 'format_minimoodlewall', tagName);
    const warningStr = usageCount > 0 ?
        await getString('desctagusagewarning', 'format_minimoodlewall', usageCount) : '';

    let bodyText = confirmStr;
    if (warningStr) {
        bodyText += '<br><br>' + warningStr;
    }

    const modal = await ModalFactory.create({
        type: ModalFactory.types.SAVE_CANCEL,
        title: titleStr,
        body: bodyText,
    });

    modal.setSaveButtonText(await getString('delete'));

    modal.getRoot().on(ModalEvents.save, async() => {
        const deleteUrl = M.cfg.wwwroot + '/course/format/minimoodlewall/description_tags.php';
        const params = new URLSearchParams({
            'delete': tagId,
            'confirm': 1,
            'sesskey': M.cfg.sesskey,
        });

        try {
            window.location.href = `${deleteUrl}?${params.toString()}`;
        } catch (error) {
            Notification.exception(error);
        }
    });

    modal.show();
};
