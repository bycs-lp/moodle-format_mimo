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
 * Distraction-free mode toggle for minimoodlewall format.
 *
 * @module     format_minimoodlewall/distraction_free
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import {get_string as getString} from 'core/str';

/** Cookie name for distraction-free state. */
const COOKIE_NAME = 'format_minimoodlewall_df';

/** CSS class applied to body when distraction-free mode is active. */
const ACTIVE_CLASS = 'format-minimoodlewall-distraction-free';

/**
 * Set a cookie value.
 *
 * @param {string} name - Cookie name
 * @param {string} value - Cookie value
 * @param {number} days - Days until expiration (default: 365)
 * @returns {void}
 */
const setCookie = (name, value, days = 365) => {
    const date = new Date();
    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
    document.cookie = `${name}=${value};expires=${date.toUTCString()};path=/;SameSite=Lax`;
};

/**
 * Initialize distraction-free mode toggle functionality.
 *
 * @returns {void}
 */
export const init = () => {
    try {
        // Get current state - body class is already set server-side.
        const isActive = document.body.classList.contains(ACTIVE_CLASS);

        // Create toggle buttons.
        createToggleButtons(isActive);

        // Set up event listeners.
        setupToggleListeners();
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Create chevron toggle buttons at top and bottom of page.
 *
 * @param {boolean} isActive - Current state of distraction-free mode
 * @returns {void}
 */
const createToggleButtons = async(isActive) => {
    const ariaLabel = await getString('aria_toggle_distractionfree', 'format_minimoodlewall');

    // Top chevron (show when distraction-free is active).
    const topToggle = document.createElement('button');
    topToggle.className = 'format-minimoodlewall-df-toggle format-minimoodlewall-df-toggle-top';
    topToggle.setAttribute('data-action', 'toggle-distraction-free');
    topToggle.setAttribute('aria-label', ariaLabel);
    topToggle.innerHTML = '<i class="fa fa-chevron-down" aria-hidden="true"></i>';
    if (!isActive) {
        topToggle.style.display = 'none';
    }
    document.body.prepend(topToggle);

    // Bottom chevron (show when distraction-free is inactive).
    const bottomToggle = document.createElement('button');
    bottomToggle.className = 'format-minimoodlewall-df-toggle format-minimoodlewall-df-toggle-bottom';
    bottomToggle.setAttribute('data-action', 'toggle-distraction-free');
    bottomToggle.setAttribute('aria-label', ariaLabel);
    bottomToggle.innerHTML = '<i class="fa fa-chevron-up" aria-hidden="true"></i>';
    if (isActive) {
        bottomToggle.style.display = 'none';
    }

    // Insert after fixed-top nav or at start of page content.
    const nav = document.querySelector('nav.fixed-top');
    if (nav) {
        nav.after(bottomToggle);
    } else {
        document.body.prepend(bottomToggle);
    }
};

/**
 * Set up click event listeners for toggle buttons.
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

        // Toggle state.
        const isActive = document.body.classList.contains(ACTIVE_CLASS);
        if (isActive) {
            disableDistractionFree();
        } else {
            enableDistractionFree();
        }
    });
};

/**
 * Enable distraction-free mode.
 *
 * @returns {void}
 */
const enableDistractionFree = () => {
    document.body.classList.add(ACTIVE_CLASS);
    setCookie(COOKIE_NAME, 'true');

    // Show top chevron, hide bottom chevron.
    const topToggle = document.querySelector('.format-minimoodlewall-df-toggle-top');
    const bottomToggle = document.querySelector('.format-minimoodlewall-df-toggle-bottom');
    if (topToggle) {
        topToggle.style.display = '';
    }
    if (bottomToggle) {
        bottomToggle.style.display = 'none';
    }
};

/**
 * Disable distraction-free mode.
 *
 * @returns {void}
 */
const disableDistractionFree = () => {
    document.body.classList.remove(ACTIVE_CLASS);
    setCookie(COOKIE_NAME, 'false');

    // Hide top chevron, show bottom chevron.
    const topToggle = document.querySelector('.format-minimoodlewall-df-toggle-top');
    const bottomToggle = document.querySelector('.format-minimoodlewall-df-toggle-bottom');
    if (topToggle) {
        topToggle.style.display = 'none';
    }
    if (bottomToggle) {
        bottomToggle.style.display = '';
    }
};
