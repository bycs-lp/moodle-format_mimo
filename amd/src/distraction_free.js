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
 * Distraction-free mode toggle for mimo format.
 *
 * @module     format_mimo/distraction_free
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import {setUserPreference} from 'core_user/repository';

/** User preference name for distraction-free state. */
const PREF_NAME = 'format_mimo_df_active';

/** CSS class applied to body when distraction-free mode is active. */
const ACTIVE_CLASS = 'format-mimo-distraction-free';

/**
 * Initialize distraction-free mode toggle functionality.
 * The toggle button is rendered server-side as a header action (.mimo-df-btn).
 *
 * @returns {void}
 */
export const init = () => {
    try {
        setupToggleListeners();
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Set up click event listeners for the header action toggle button.
 *
 * @returns {void}
 */
const setupToggleListeners = () => {
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action="toggle-distraction-free"]');
        if (!button) {
            return;
        }

        event.preventDefault();

        const isActive = document.body.classList.contains(ACTIVE_CLASS);
        const newState = !isActive;
        if (newState) {
            document.body.classList.add(ACTIVE_CLASS);
        } else {
            document.body.classList.remove(ACTIVE_CLASS);
        }
        setUserPreference(PREF_NAME, newState ? 'true' : 'false').catch(Notification.exception);
    });
};
