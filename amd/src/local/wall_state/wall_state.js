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
 * Wall state reactive store for mimo format.
 *
 * Owns the client-side UI state for the activity wall: filters, pagination,
 * bulk mode, and activity order. One instance is created per section and
 * cached for the page lifetime.
 *
 * State shape:
 *   filters:       {tags: string[], completion: string}
 *   pagination:    {page: number}
 *   bulk:          {enabled: boolean}
 *   activityOrder: {ids: number[]}
 *
 * @module     format_mimo/local/wall_state/wall_state
 * @copyright  2025 MBS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {Reactive} from 'core/reactive';
import mutations from 'format_mimo/local/wall_state/mutations';
import {eventTypes, dispatchWallStateChanged} from 'format_mimo/local/wall_state/events';

/** @type {Map<string, Reactive>} Cache of reactive instances keyed by section element id. */
const instances = new Map();

/**
 * Read initial activity order from the DOM.
 *
 * @param {HTMLElement} sectionElement the section container
 * @returns {number[]} ordered array of cm IDs
 */
function readActivityOrderFromDOM(sectionElement) {
    const container = sectionElement.querySelector('.mimo-activities');
    if (!container) {
        return [];
    }
    const items = container.querySelectorAll('li.activity[data-id]');
    return Array.from(items, (el) => Number(el.dataset.id));
}

/**
 * Build the initial state from server-rendered data attributes.
 *
 * @param {HTMLElement} sectionElement the section container
 * @returns {object} initial state for the reactive
 */
function buildInitialState(sectionElement) {
    return {
        filters: {tags: [], completion: ''},
        pagination: {page: 0},
        bulk: {enabled: false},
        activityOrder: {ids: readActivityOrderFromDOM(sectionElement)},
    };
}

/**
 * Get or create the wall state reactive for a section.
 *
 * @param {HTMLElement} sectionElement the .section-item or nearest section container
 * @returns {Reactive} the wall state reactive instance
 */
export function getWallState(sectionElement) {
    // Use the section data-id or fall back to a generated key.
    const key = sectionElement.dataset.id ?? sectionElement.id ?? 'default';

    if (instances.has(key)) {
        return instances.get(key);
    }

    const wallState = new Reactive({
        name: `mimo_wall_${key}`,
        eventName: eventTypes.wallStateChanged,
        eventDispatch: dispatchWallStateChanged,
        mutations,
        state: buildInitialState(sectionElement),
    });

    instances.set(key, wallState);
    return wallState;
}
