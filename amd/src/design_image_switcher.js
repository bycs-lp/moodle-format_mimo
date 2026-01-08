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
 * Design image switcher for course settings form.
 *
 * Updates tag preview images when the design variant dropdown is changed.
 *
 * @module     format_minimoodlewall/design_image_switcher
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    DESIGN_SELECT: '#id_designvariant',
    TAG_IMAGE: '[data-tagimage]',
};

let tagImageData = {};

/**
 * Initialize the design image switcher.
 *
 * @param {Object} imageData Object mapping tagid -> {designname: imageurl}
 */
export const init = (imageData) => {
    tagImageData = imageData;
    registerEventListeners();
};

/**
 * Register event listeners.
 */
const registerEventListeners = () => {
    const designSelect = document.querySelector(SELECTORS.DESIGN_SELECT);
    if (!designSelect) {
        return;
    }

    designSelect.addEventListener('change', () => {
        updateTagImages(designSelect.value);
    });
};

/**
 * Update all tag images for the selected design.
 *
 * @param {string} designName The selected design name
 */
const updateTagImages = (designName) => {
    const tagImages = document.querySelectorAll(SELECTORS.TAG_IMAGE);

    tagImages.forEach((element) => {
        const tagId = element.dataset.tagimage;
        if (!tagId || !tagImageData[tagId]) {
            return;
        }

        const newImageUrl = tagImageData[tagId][designName];

        if (element.tagName === 'IMG') {
            if (newImageUrl) {
                element.src = newImageUrl;
                element.style.display = '';
            } else {
                element.style.display = 'none';
            }
        } else {
            // It's a placeholder span, replace with image if URL exists.
            if (newImageUrl) {
                const img = document.createElement('img');
                img.src = newImageUrl;
                img.className = 'mmw-tag-preview me-2';
                img.dataset.tagimage = tagId;
                img.alt = '';
                img.setAttribute('aria-hidden', 'true');
                element.parentNode.replaceChild(img, element);
            }
        }
    });
};
