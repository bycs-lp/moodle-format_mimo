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
 * Section overview bulk selection support.
 *
 * Bridges the course editor reactive bulk state with the custom overview
 * section cards so that sections can be selected/deselected in bulk
 * editing mode. Watches `bulk:updated` on the course editor reactive
 * and toggles checkbox visibility + selection state on each card.
 *
 * @module     format_mimo/section_overview_bulk
 * @copyright  2026 MBS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {BaseComponent} from 'core/reactive';
import {getCurrentCourseEditor} from 'core_courseformat/courseeditor';
import DispatchActions from 'core_courseformat/local/content/actions';

/**
 * Reactive component that manages section bulk selection on the overview page.
 */
class SectionOverviewBulk extends BaseComponent {

    /**
     * Constructor hook.
     */
    create() {
        this.name = 'format_mimo_overview_bulk';
        this.selectors = {
            CARD: '[data-for="mimo-overview-card"]',
            BULKSELECT: '[data-for="sectionBulkSelect"]',
            BULKCHECKBOX: '[data-bulkcheckbox]',
        };
        this.classes = {
            HIDE: 'd-none',
            SELECTED: 'selected',
        };
    }

    /**
     * Called when the reactive state is ready.
     *
     * @param {Object} state the initial state
     */
    stateReady(state) {
        this._refreshBulk({state});
    }

    /**
     * Return the list of watchers for this component.
     *
     * @returns {Array} of watchers
     */
    getWatchers() {
        return [
            {watch: 'bulk:updated', handler: this._refreshBulk},
            // The overview cards are static HTML without per-section reactive rendering.
            // Reload the page when structural or visibility changes happen so the
            // overview reflects the new state.
            {watch: 'course.sectionlist:updated', handler: this._reloadPage},
            {watch: 'section.visible:updated', handler: this._reloadPage},
        ];
    }

    /**
     * Reload the page to reflect structural changes.
     *
     * Debounced so multiple rapid state updates (e.g. bulk visibility)
     * trigger only a single reload.
     *
     * @private
     */
    _reloadPage() {
        if (this._reloadTimer) {
            return;
        }
        this._reloadTimer = setTimeout(() => {
            window.location.reload();
        }, 300);
    }

    /**
     * Refresh the bulk selection UI on all overview cards.
     *
     * Shows/hides checkboxes, toggles selected state, and disables
     * checkboxes when a different element type (cm) is being selected.
     *
     * @param {Object} param
     * @param {Object} param.state the reactive state
     * @private
     */
    _refreshBulk({state}) {
        const bulk = state.bulk;
        const cards = this.getElements(this.selectors.CARD);

        cards?.forEach(card => {
            const id = card.dataset.id;
            if (!id) {
                return;
            }

            // Check if this section is bulk-editable in the reactive state.
            const section = this.reactive.get('section', id);
            if (!section?.bulkeditable) {
                return;
            }

            // Toggle checkbox container visibility.
            const bulkSelect = card.querySelector(this.selectors.BULKSELECT);
            if (bulkSelect) {
                bulkSelect.classList.toggle(this.classes.HIDE, !bulk.enabled);
            }

            // Update checkbox state.
            const checkbox = card.querySelector(this.selectors.BULKCHECKBOX);
            if (checkbox) {
                const isSectionType = (bulk.selectedType === '' || bulk.selectedType === 'section');
                const isDisabled = !bulk.enabled || !isSectionType;
                const isSelected = bulk.selectedType === 'section' && bulk.selection.includes(id);
                checkbox.checked = isSelected;
                checkbox.disabled = isDisabled;
                if (isDisabled) {
                    checkbox.removeAttribute('data-is-selectable');
                } else {
                    checkbox.dataset.isSelectable = 1;
                }
            }

            // Toggle selected class on the card.
            const isCardSelected = bulk.selectedType === 'section' && bulk.selection.includes(id);
            card.classList.toggle(this.classes.SELECTED, isCardSelected);
        });
    }
}

/**
 * Initialise section overview bulk selection.
 *
 * Retries if the course editor reactive is not yet available (MDL-87236).
 *
 * @param {number} retries Current retry count (internal, do not pass manually)
 */
export const init = (retries = 0) => {
    const reactive = getCurrentCourseEditor();
    const grid = document.querySelector('[data-region="mimo-overview-grid"]');

    if (!grid) {
        return;
    }

    // Check if the reactive has loaded its initial state by trying to get bulk data.
    let isReady = false;
    try {
        isReady = !!reactive?.get('bulk');
    } catch (e) {
        // State not yet initialized.
    }

    if (isReady) {
        const component = new SectionOverviewBulk({element: grid, reactive});
        // Initialize the core DispatchActions handler so bulk action buttons
        // (move, delete, availability) in the sticky footer work on the overview page.
        // Normally this is done by the core content component, but the overview
        // uses a custom template that bypasses it.
        if (reactive.isEditing && reactive.supportComponents) {
            new DispatchActions({...component, element: document.getElementById('page')});
        }
        return;
    }

    // Retry if the reactive is not ready yet (max ~5s).
    if (retries < 100) {
        setTimeout(() => init(retries + 1), 50);
    }
};
