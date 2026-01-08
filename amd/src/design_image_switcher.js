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
 * Image URLs are read from data-designimages attributes on each tag option.
 *
 * @module     format_minimoodlewall/design_image_switcher
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    DESIGN_SELECT: '#id_designvariant',
    TAG_OPTION: '.mmw-tag-option[data-designimages]',
    TAG_IMAGE: '[data-tagimage]',
};

/**
 * Initialize the design image switcher.
 */
export const init = () => {
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
    const tagOptions = document.querySelectorAll(SELECTORS.TAG_OPTION);

    tagOptions.forEach((optionElement) => {
        const designImagesJson = optionElement.dataset.designimages;
        if (!designImagesJson) {
            return;
        }

        let designImages;
        try {
            designImages = JSON.parse(designImagesJson);
        } catch (e) {
            return;
        }

        const newImageUrl = designImages[designName];
        const imageElement = optionElement.querySelector(SELECTORS.TAG_IMAGE);

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
                img.className = 'mmw-tag-preview me-2';
                img.dataset.tagimage = tagId;
                img.alt = '';
                img.setAttribute('aria-hidden', 'true');
                imageElement.parentNode.replaceChild(img, imageElement);
            }
        }
    });
};
