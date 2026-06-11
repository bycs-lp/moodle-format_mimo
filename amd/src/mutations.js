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
 * Format mimo mutations.
 *
 * Adds custom mutations to the course editor for the "Done" activity state.
 * All functions are declared as class attributes so that addMutations can
 * discover them (required because multiple plugins may add mutations).
 *
 * @module     format_mimo/mutations
 * @copyright  2026 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getCurrentCourseEditor} from 'core_courseformat/courseeditor';
import DefaultMutations from 'core_courseformat/local/courseeditor/mutations';
import CourseActions from 'core_courseformat/local/content/actions';
import Fragment from 'core/fragment';
import Templates from 'core/templates';
import Config from 'core/config';
import Pending from 'core/pending';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import {getString} from 'core/str';

/**
 * Reload a cmitem from the server and replace the DOM node.
 *
 * This follows the same pattern as core content.js _reloadCm to ensure
 * the full cmitem (including visibility dropdown) is re-rendered correctly.
 *
 * After DOM replacement, syncs the bulk-edit checkbox visibility with the
 * current reactive state. Core's _indexContents will create a full CmItem
 * component on the next state change (the new element lacks data-indexed).
 *
 * @param {number} cmId Course module id
 */
const reloadCmItem = async(cmId) => {
    const cmitem = document.querySelector(`li.activity[data-id="${cmId}"]`);
    if (!cmitem) {
        return;
    }
    const pending = new Pending('format_mimo/mutations:reloadCmItem:' + cmId);
    const promise = Fragment.loadFragment(
        'core_courseformat',
        'cmitem',
        Config.courseContextId,
        {
            id: cmId,
            courseid: Config.courseId,
        }
    );
    promise.then((html, js) => {
        if (!document.contains(cmitem)) {
            pending.resolve();
            return false;
        }
        Templates.replaceNode(cmitem, html, js);
        // Sync bulk-edit state on the freshly inserted DOM element.
        // The new node has no reactive component yet (no data-indexed),
        // so we manually show the checkbox if bulk mode is active.
        const reactive = getCurrentCourseEditor();
        const bulk = reactive.get('bulk');
        if (bulk?.enabled) {
            const newEl = document.querySelector(`li.activity[data-id="${cmId}"]`);
            if (newEl) {
                const bulkSelect = newEl.querySelector('[data-for="cmBulkSelect"]');
                if (bulkSelect) {
                    bulkSelect.classList.remove('d-none');
                }
                // Allow card-click selection (same as CmItem._refreshBulk).
                newEl.dataset.action = 'toggleSelectionCm';
                newEl.dataset.preventDefault = '1';
            }
        }
        pending.resolve();
        return true;
    }).catch(() => {
        pending.resolve();
    });
};

class MimoMutations extends DefaultMutations {

    /**
     * Mark course modules as done.
     *
     * @param {StateManager} stateManager the current state manager
     * @param {array} cmIds the list of cm ids
     */
    cmDone = async function(stateManager, cmIds) {
        const course = stateManager.get('course');
        this.cmLock(stateManager, cmIds, true);
        try {
            const updates = await this._callEditWebservice('cm_done', course.id, cmIds);
            this.bulkReset(stateManager);
            stateManager.processUpdates(updates);
        } finally {
            this.cmLock(stateManager, cmIds, false);
        }
        // Reload each cmitem from the server so the visibility dropdown icon updates.
        cmIds.forEach(id => reloadCmItem(id));
    };

    /**
     * Unmark course modules as done.
     *
     * @param {StateManager} stateManager the current state manager
     * @param {array} cmIds the list of cm ids
     */
    cmUndone = async function(stateManager, cmIds) {
        const course = stateManager.get('course');
        this.cmLock(stateManager, cmIds, true);
        try {
            const updates = await this._callEditWebservice('cm_undone', course.id, cmIds);
            this.bulkReset(stateManager);
            stateManager.processUpdates(updates);
        } finally {
            this.cmLock(stateManager, cmIds, false);
        }
        // Reload each cmitem from the server so the visibility dropdown icon updates.
        cmIds.forEach(id => reloadCmItem(id));
    };

    /**
     * Show course modules — force reload to clear done styling.
     *
     * When switching from done→show, cm.visible doesn't change (both are visible)
     * so core's _reloadCm watcher won't fire. We force a reload after the action.
     *
     * @param {StateManager} stateManager the current state manager
     * @param {array} cmIds the list of cm ids
     */
    cmShow = async function(stateManager, cmIds) {
        await this._cmBasicAction(stateManager, 'cm_show', cmIds);
        cmIds.forEach(id => reloadCmItem(id));
    };

    /**
     * Hide course modules — force reload to clear done styling.
     *
     * @param {StateManager} stateManager the current state manager
     * @param {array} cmIds the list of cm ids
     */
    cmHide = async function(stateManager, cmIds) {
        await this._cmBasicAction(stateManager, 'cm_hide', cmIds);
        cmIds.forEach(id => reloadCmItem(id));
    };

    /**
     * Stealth course modules — force reload to clear done styling.
     *
     * @param {StateManager} stateManager the current state manager
     * @param {array} cmIds the list of cm ids
     */
    cmStealth = async function(stateManager, cmIds) {
        await this._cmBasicAction(stateManager, 'cm_stealth', cmIds);
        cmIds.forEach(id => reloadCmItem(id));
    };
}

export const init = () => {
    const courseEditor = getCurrentCourseEditor();
    courseEditor.addMutations(new MimoMutations());
    CourseActions.addActions({
        cmDone: 'cmDone',
        cmUndone: 'cmUndone',
        mimoAvailability: mimoAvailabilityHandler,
    });
};

/**
 * Custom availability action handler that includes the "Done" option.
 *
 * Opens a modal with Show/Hide/Stealth/Done radio buttons. On submit,
 * dispatches the selected mutation with the bulk selection IDs.
 *
 * @param {HTMLElement} target The clicked action button element
 * @param {Event} event The click event
 */
const mimoAvailabilityHandler = async(target, event) => {
    event.preventDefault();

    const reactive = getCurrentCourseEditor();

    // Gather target IDs (same logic as core _getTargetIds).
    let cmIds = [];
    if (target?.dataset?.id) {
        cmIds.push(target.dataset.id);
    }
    const bulkType = target?.dataset?.bulk;
    if (bulkType) {
        const bulk = reactive.get('bulk');
        if (bulk.enabled && bulk.selectedType === bulkType) {
            cmIds = [...cmIds, ...bulk.selection];
        }
    }
    if (cmIds.length === 0) {
        return;
    }

    // Check stealth availability.
    const exporter = reactive.getExporter();
    const data = {
        allowstealth: exporter.canUseStealth(reactive.state, cmIds),
    };

    // Render the mimo-specific availability modal (includes Done option).
    const modal = await ModalSaveCancel.create({
        title: getString('availability', 'core'),
        body: Templates.render('format_mimo/local/content/cm/availabilitymodal', data),
        saveButtonText: getString('apply', 'core'),
    });

    modal.show();

    // Wait for the body to render before attaching listeners.
    const modalBody = await modal.getBodyPromise();

    // Disable save until a radio is selected.
    modal.setButtonDisabled('save', true);

    const radioOptions = modalBody[0].querySelectorAll('input[type="radio"]');
    radioOptions.forEach(radio => {
        radio.addEventListener('change', () => {
            modal.setButtonDisabled('save', false);
        });
        radio.parentNode.addEventListener('click', () => {
            radio.checked = true;
            modal.setButtonDisabled('save', false);
        });
        radio.parentNode.addEventListener('dblclick', (dbClickEvent) => {
            const mutation = radio?.value;
            if (mutation) {
                dbClickEvent.preventDefault();
                reactive.dispatch(mutation, cmIds);
                modal.destroy();
            }
        });
    });

    modal.getRoot().on(ModalEvents.save, () => {
        const checked = modalBody[0].querySelector('input[type="radio"]:checked');
        const mutation = checked?.value;
        if (mutation) {
            reactive.dispatch(mutation, cmIds);
        }
    });
};
