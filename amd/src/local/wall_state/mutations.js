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
 * Mutations for the minimoodlewall wall state reactive.
 *
 * Every mutation receives the stateManager as first argument and additional
 * parameters from the dispatch call. Mutations are the only code allowed
 * to modify state (via setReadOnly toggle).
 *
 * @module     format_minimoodlewall/local/wall_state/mutations
 * @copyright  2025 MBS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export default {

    /**
     * Set the active tag filter.
     *
     * @param {StateManager} stateManager
     * @param {string[]} tags tag names to filter by (empty array = no filter)
     */
    setTagFilter(stateManager, tags) {
        const state = stateManager.state;
        stateManager.setReadOnly(false);
        state.filters.tags = [...tags];
        stateManager.setReadOnly(true);
    },

    /**
     * Set the active completion filter.
     *
     * @param {StateManager} stateManager
     * @param {string} completion filter value: '', 'complete', 'incomplete'
     */
    setCompletionFilter(stateManager, completion) {
        const state = stateManager.state;
        stateManager.setReadOnly(false);
        state.filters.completion = completion;
        stateManager.setReadOnly(true);
    },

    /**
     * Set the current pagination page.
     *
     * @param {StateManager} stateManager
     * @param {number} page zero-based page index
     */
    setPage(stateManager, page) {
        const state = stateManager.state;
        stateManager.setReadOnly(false);
        state.pagination.page = page;
        stateManager.setReadOnly(true);
    },

    /**
     * Set bulk editing mode state.
     *
     * @param {StateManager} stateManager
     * @param {boolean} enabled whether bulk editing is active
     */
    setBulk(stateManager, enabled) {
        const state = stateManager.state;
        stateManager.setReadOnly(false);
        state.bulk.enabled = !!enabled;
        stateManager.setReadOnly(true);
    },

    /**
     * Update the activity order after a drag-drop reorder.
     *
     * @param {StateManager} stateManager
     * @param {number[]} orderedIds activity cm IDs in new visual order
     */
    reorderActivities(stateManager, orderedIds) {
        const state = stateManager.state;
        stateManager.setReadOnly(false);
        state.activityOrder.ids = [...orderedIds];
        stateManager.setReadOnly(true);
    },
};
