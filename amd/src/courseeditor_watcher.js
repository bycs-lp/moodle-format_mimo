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
 * Course editor reactive bridge for minimoodlewall format.
 *
 * A BaseComponent that watches the core course editor reactive state and
 * bridges changes into the wall state reactive. Also dispatches legacy
 * DOM events for modules not yet converted to wall state watchers.
 *
 * @module     format_minimoodlewall/courseeditor_watcher
 * @copyright  2025 MBS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {BaseComponent} from 'core/reactive';

/** Custom event names dispatched by this component. */
export const EVENTS = {
    /** Bulk editing mode toggled. Detail: {enabled: boolean} */
    BULK_CHANGE: 'minimoodlewall:bulkchange',
    /** Activity completion state changed. Detail: {cmId: number, completed: boolean} */
    COMPLETION_CHANGE: 'minimoodlewall:completionchange',
};

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
            // Legacy DOM event for unconverted modules.
            document.dispatchEvent(new CustomEvent(EVENTS.BULK_CHANGE, {
                detail: {enabled: true},
            }));
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
        // Legacy DOM event for unconverted modules.
        document.dispatchEvent(new CustomEvent(EVENTS.BULK_CHANGE, {
            detail: {enabled},
        }));
    }

    /**
     * Handle course module state updates.
     *
     * When a cm's completionstate changes, updates the data-completed
     * attribute on the activity list item and dispatches a completion
     * change event so tag_filter can recount.
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

        // Only dispatch event if value actually changed.
        if (activityItem.dataset.completed !== newValue) {
            activityItem.dataset.completed = newValue;

            document.dispatchEvent(new CustomEvent(EVENTS.COMPLETION_CHANGE, {
                detail: {cmId, completed: isComplete},
            }));
        }
    }
}
