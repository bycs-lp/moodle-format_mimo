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
 * Custom event helpers for the wall state reactive.
 *
 * @module     format_mimo/local/wall_state/events
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Event type constant. */
export const eventTypes = {
    wallStateChanged: 'format_mimo/wallStateChanged',
};

/**
 * Dispatch a wall state changed event.
 *
 * @param {object} detail the event detail payload
 * @param {EventTarget} target the element to dispatch on (defaults to document)
 */
export function dispatchWallStateChanged(detail, target) {
    if (target === undefined) {
        target = document;
    }
    target.dispatchEvent(new CustomEvent(eventTypes.wallStateChanged, {
        bubbles: true,
        detail,
    }));
}
