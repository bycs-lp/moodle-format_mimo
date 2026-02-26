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
 * Activity profile image switcher for course settings form.
 *
 * Updates tag preview images and visibility when the activity profile dropdown is changed.
 * Image URLs are read from data-profileimages attributes on each tag preview item.
 *
 * @module     format_minimoodlewall/profile_image_switcher
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    PROFILE_SELECT: '#id_activityprofile',
    TAG_ITEM: '.mmw-tag-preview-item[data-profileimages]',
    TAG_IMAGE: '[data-tagimage]',
    TAG_NAME: '.mmw-tag-name',
};

/**
 * Initialize the profile image switcher.
 */
export const init = () => {
    registerEventListeners();
};

/**
 * Register event listeners.
 */
const registerEventListeners = () => {
    const profileSelect = document.querySelector(SELECTORS.PROFILE_SELECT);
    if (!profileSelect) {
        return;
    }

    profileSelect.addEventListener('change', () => {
        updateTagImages(profileSelect.value);
    });

    // Apply initial state for the currently selected profile.
    updateTagImages(profileSelect.value);
};

/**
 * Update all tag options for the selected profile (images, names, and enabled state).
 *
 * @param {string} profileName The selected profile name
 */
const updateTagImages = (profileName) => {
    const tagItems = document.querySelectorAll(SELECTORS.TAG_ITEM);

    tagItems.forEach((itemElement) => {
        // --- Enabled / disabled state ---
        const enabledJson = itemElement.dataset.profileenabled;
        if (enabledJson) {
            try {
                const enabledMap = JSON.parse(enabledJson);
                const isEnabled = enabledMap[profileName] !== undefined ? !!enabledMap[profileName] : true;
                itemElement.classList.toggle('d-none', !isEnabled);
            } catch (e) {
                // Ignore parse errors.
            }
        }

        // --- Name override ---
        const namesJson = itemElement.dataset.profilenames;
        if (namesJson) {
            try {
                const namesMap = JSON.parse(namesJson);
                const newName = namesMap[profileName];
                if (newName) {
                    const nameElement = itemElement.querySelector(SELECTORS.TAG_NAME);
                    if (nameElement) {
                        nameElement.textContent = newName;
                    }
                }
            } catch (e) {
                // Ignore parse errors.
            }
        }

        // --- Image switching ---
        const profileImagesJson = itemElement.dataset.profileimages;
        if (!profileImagesJson) {
            return;
        }

        let profileImages;
        try {
            profileImages = JSON.parse(profileImagesJson);
        } catch (e) {
            return;
        }

        const newImageUrl = profileImages[profileName];
        const imageElement = itemElement.querySelector(SELECTORS.TAG_IMAGE);

        if (!imageElement) {
            return;
        }

        const tagId = imageElement.dataset.tagimage;

        if (imageElement.tagName === 'IMG') {
            if (newImageUrl) {
                imageElement.src = newImageUrl;
                imageElement.style.display = '';
            } else {
                imageElement.style.display = 'none';
            }
        } else {
            // It's a placeholder span, replace with image if URL exists.
            if (newImageUrl) {
                const img = document.createElement('img');
                img.src = newImageUrl;
                img.className = 'mmw-tag-preview-img me-2';
                img.dataset.tagimage = tagId;
                img.alt = '';
                img.setAttribute('aria-hidden', 'true');
                imageElement.parentNode.replaceChild(img, imageElement);
            }
        }
    });
};
