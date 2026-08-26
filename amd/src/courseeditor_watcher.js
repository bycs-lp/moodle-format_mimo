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
 * Course editor reactive bridge for mimo format.
 *
 * A BaseComponent that watches the core course editor reactive state and
 * bridges changes into the wall state reactive. Completion changes are
 * bridged via the notifyCompletionChange mutation.
 *
 * @module     format_mimo/courseeditor_watcher
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {BaseComponent} from 'core/reactive';
import Fragment from 'core/fragment';
import Templates from 'core/templates';
import Config from 'core/config';
import Pending from 'core/pending';

/**
 * Reactive bridge component that watches course editor state.
 */
export default class CourseEditorWatcher extends BaseComponent {

    /**
     * Component setup — store element, reactive reference, and optional wall state.
     *
     * @param {object} descriptor Component descriptor
     */
    create(descriptor) {
        this.element = descriptor.element;
        this.reactive = descriptor.reactive;
        /** @type {Reactive|null} Wall state reactive (set externally after construction). */
        this.wallState = null;
    }

    /**
     * Define reactive state watchers.
     *
     * @returns {object[]} Array of watcher definitions
     */
    getWatchers() {
        return [
            {watch: 'bulk:updated', handler: this._bulkUpdated},
            {watch: 'cm:updated', handler: this._cmUpdated},
            {watch: 'cm.isdone:updated', handler: this._cmDoneUpdated},
        ];
    }

    /**
     * Called when the reactive state is ready.
     *
     * Dispatches initial bulk state so modules that registered listeners
     * before the state loaded can react immediately.
     *
     * @param {object} state The full reactive state
     */
    stateReady(state) {
        const bulkEnabled = state?.bulk?.enabled ?? false;
        if (bulkEnabled) {
            this.wallState?.dispatch('setBulk', true);
        }
    }

    /**
     * Handle bulk state updates from the course editor.
     *
     * @param {object} param0 Event detail
     * @param {object} param0.element The bulk state object
     * @private
     */
    _bulkUpdated({element}) {
        const enabled = element?.enabled ?? false;
        this.wallState?.dispatch('setBulk', enabled);
    }

    /**
     * Handle course module state updates.
     *
     * When a cm's completionstate changes, updates the data-completed
     * attribute on the activity list item and dispatches the
     * notifyCompletionChange wall-state mutation so filter bars can recount.
     *
     * @param {object} param0 Event detail
     * @param {object} param0.element The cm state object
     * @private
     */
    _cmUpdated({element}) {
        if (!element?.id || element.completionstate === undefined) {
            return;
        }

        // Validate ID is numeric to prevent selector injection.
        const cmId = Number(element.id);
        if (!Number.isFinite(cmId) || cmId <= 0) {
            return;
        }

        const activityItem = document.querySelector(`li.activity[data-id="${cmId}"]`);
        if (!activityItem || activityItem.dataset.completed === undefined) {
            return;
        }

        const isComplete = element.completionstate > 0;
        const newValue = isComplete ? 'true' : 'false';

        // Only notify if the value actually changed.
        if (activityItem.dataset.completed !== newValue) {
            activityItem.dataset.completed = newValue;
            this.wallState?.dispatch('notifyCompletionChange', cmId, isComplete);
        }
    }

    /**
     * Handle done flag changes on a course module.
     *
     * The done styling and the visibility dropdown icon are backend rendered,
     * so the cmitem is reloaded from the server when cm.isdone changes.
     * Core's content component only reloads on visible/stealth changes, which
     * don't fire for done↔show transitions.
     *
     * @param {object} param0 Event detail
     * @param {object} param0.element The cm state object
     * @private
     */
    _cmDoneUpdated({element}) {
        const cmId = Number(element?.id);
        if (!Number.isFinite(cmId) || cmId <= 0) {
            return;
        }
        this._reloadCmItem(cmId);
    }

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
     * @private
     */
    _reloadCmItem(cmId) {
        const cmitem = document.querySelector(`li.activity[data-id="${cmId}"]`);
        if (!cmitem) {
            return;
        }
        const pending = new Pending('format_mimo/courseeditor_watcher:reloadCmItem:' + cmId);
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
            // Another state change may have replaced the node meanwhile.
            if (!document.contains(cmitem)) {
                pending.resolve();
                return false;
            }
            Templates.replaceNode(cmitem, html, js);
            this._syncBulkCheckbox(cmId);
            pending.resolve();
            return true;
        }).catch(() => {
            pending.resolve();
        });
    }

    /**
     * Show the bulk-edit checkbox on a freshly inserted cmitem element.
     *
     * The new node has no reactive component yet (no data-indexed), so the
     * checkbox is shown manually if bulk mode is active (same as
     * CmItem._refreshBulk).
     *
     * @param {number} cmId Course module id
     * @private
     */
    _syncBulkCheckbox(cmId) {
        const bulk = this.reactive.get('bulk');
        if (!bulk?.enabled) {
            return;
        }
        const newEl = document.querySelector(`li.activity[data-id="${cmId}"]`);
        if (!newEl) {
            return;
        }
        const bulkSelect = newEl.querySelector('[data-for="cmBulkSelect"]');
        if (bulkSelect) {
            bulkSelect.classList.remove('d-none');
        }
        // Allow card-click selection (same as CmItem._refreshBulk).
        newEl.dataset.action = 'toggleSelectionCm';
        newEl.dataset.preventDefault = '1';
    }
}
