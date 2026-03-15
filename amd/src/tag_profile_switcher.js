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
 * Profile switcher for tag management admin page.
 *
 * Switches the tag table to show resolved values for the selected profile
 * without a full page reload. Updates URL, button styles, table content,
 * and edit links.
 *
 * @module     format_mimo/tag_profile_switcher
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {get_string as getString} from 'core/str';

const SELECTORS = {
    PROFILE_BUTTON: '[data-action="switch-profile"]',
    TAG_ROW: 'tr[data-tag-id]',
    EDIT_LINK: '[data-action="edit-tag"]',
};

/** @type {Object} Per-tag per-profile resolved data. */
let tagProfileData = {};

/** @type {string} Currently active profile name ('' = Default). */
let activeProfile = '';

/** @type {string} Base URL for the tag management page. */
let baseUrl = '';

/** @type {string} Cached disabled badge text. */
let disabledText = '';

/** @type {Object} Profile name to ID mapping. */
let profileIdMap = {};

/** @type {HTMLElement|null} The tag-management container element. */
let container = null;

/**
 * Initialize the profile switcher.
 * Data is read from data attributes on the tag-management container element.
 */
export const init = async() => {
    container = document.querySelector('[data-region="tag-management"]');
    if (!container) {
        return;
    }
    tagProfileData = JSON.parse(container.dataset.tagProfileData || '{}');
    profileIdMap = JSON.parse(container.dataset.profileIdMap || '{}');
    activeProfile = container.dataset.currentProfile || '';
    baseUrl = container.dataset.managementUrl || '';
    disabledText = await getString('profiletag_disabled', 'format_mimo');
    registerEventListeners();
};

/**
 * Register click handlers on profile buttons.
 */
const registerEventListeners = () => {
    document.addEventListener('click', (event) => {
        const button = event.target.closest(SELECTORS.PROFILE_BUTTON);
        if (button) {
            event.preventDefault();
            const profileName = button.dataset.profileName;
            switchProfile(profileName);
        }
    });
};

/**
 * Switch the displayed profile.
 *
 * @param {string} profileName Profile name to switch to ('' for Default)
 */
const switchProfile = (profileName) => {
    if (profileName === activeProfile) {
        return;
    }
    activeProfile = profileName;

    // Update the active profile ID on the container for other JS modules.
    if (container) {
        container.dataset.activeProfileId = profileIdMap[profileName] ?? 0;
    }

    // Update button styles.
    document.querySelectorAll(SELECTORS.PROFILE_BUTTON).forEach((btn) => {
        if (btn.dataset.profileName === profileName) {
            btn.classList.remove('btn-secondary');
            btn.classList.add('btn-primary');
        } else {
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-secondary');
        }
    });

    // Update URL without reload.
    const url = new URL(baseUrl);
    if (profileName) {
        url.searchParams.set('profile', profileName);
    }
    window.history.replaceState(null, '', url.toString());

    // Update each tag row.
    document.querySelectorAll(SELECTORS.TAG_ROW).forEach((row) => {
        const tagId = row.dataset.tagId;
        const profileData = tagProfileData[tagId];
        if (!profileData) {
            return;
        }
        const data = profileData[profileName] || profileData[''];
        if (!data) {
            return;
        }
        updateRow(row, data, tagId, profileName);
    });
};

/**
 * Update a single table row with profile-resolved data.
 *
 * @param {HTMLElement} row The table row
 * @param {Object} data Resolved tag data for this profile
 * @param {string} tagId Tag ID
 * @param {string} profileName Active profile name
 */
const updateRow = (row, data, tagId, profileName) => {
    // Opacity for disabled tags.
    row.style.opacity = data.enabled ? '' : '0.45';

    // Card image.
    const imgCell = row.querySelector('[data-field="cardimage"]');
    if (imgCell) {
        if (data.cardimageurl) {
            const img = document.createElement('img');
            img.src = data.cardimageurl;
            img.alt = data.name;
            img.style.cssText = 'width: 80px; height: 50px; object-fit: cover;';
            imgCell.replaceChildren(img);
        } else {
            imgCell.innerHTML = '<span class="text-muted">-</span>';
        }
    }

    // Name + disabled badge.
    const nameCell = row.querySelector('[data-field="name"]');
    if (nameCell) {
        let html = escapeHtml(data.name);
        if (!data.enabled) {
            html += ` <span class="badge bg-secondary ms-1">${escapeHtml(disabledText)}</span>`;
        }
        nameCell.innerHTML = html;
    }

    // Background color swatch + hex.
    const bgCell = row.querySelector('[data-field="bgcolor"]');
    if (bgCell) {
        const swatch = bgCell.querySelector('.mimo-color-swatch');
        const hex = bgCell.querySelector('.mimo-color-hex');
        if (swatch) {
            swatch.style.background = data.bgcolor;
        }
        if (hex) {
            hex.textContent = data.bgcolor;
        }
    }

    // Activity types.
    const at1 = row.querySelector('[data-field="activitytype1"]');
    const at2 = row.querySelector('[data-field="activitytype2"]');
    const at3 = row.querySelector('[data-field="activitytype3"]');
    if (at1) {
        at1.textContent = data.activitytype1;
    }
    if (at2) {
        at2.textContent = data.activitytype2;
    }
    if (at3) {
        at3.textContent = data.activitytype3;
    }

    // Update edit link URL.
    const editLink = row.querySelector(SELECTORS.EDIT_LINK);
    if (editLink) {
        const editUrl = new URL(baseUrl);
        editUrl.searchParams.set('action', 'edittag');
        editUrl.searchParams.set('tagid', tagId);
        if (profileName) {
            editUrl.searchParams.set('profile', profileName);
        }
        editLink.href = editUrl.toString();
    }
};

/**
 * Escape HTML entities in a string.
 *
 * @param {string} text Raw text
 * @returns {string} Escaped text
 */
const escapeHtml = (text) => {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
};
