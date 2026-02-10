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
 * Style image switcher for course settings form.
 *
 * Updates tag preview images when the style variant dropdown is changed.
 * Image URLs are read from data-styleimages attributes on each tag option.
 *
 * @module     format_minimoodlewall/style_image_switcher
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    STYLE_SELECT: '#id_stylevariant',
    TAG_OPTION: '.mmw-tag-option[data-styleimages]',
    TAG_IMAGE: '[data-tagimage]',
};

/**
 * Initialize the style image switcher.
 */
export const init = () => {
    registerEventListeners();
};

/**
 * Register event listeners.
 */
const registerEventListeners = () => {
    const styleSelect = document.querySelector(SELECTORS.STYLE_SELECT);
    if (!styleSelect) {
        return;
    }

    styleSelect.addEventListener('change', () => {
        updateTagImages(styleSelect.value);
    });
};

/**
 * Update all tag images for the selected style.
 *
 * @param {string} styleName The selected style name
 */
const updateTagImages = (styleName) => {
    const tagOptions = document.querySelectorAll(SELECTORS.TAG_OPTION);

    tagOptions.forEach((optionElement) => {
        const styleImagesJson = optionElement.dataset.styleimages;
        if (!styleImagesJson) {
            return;
        }

        let styleImages;
        try {
            styleImages = JSON.parse(styleImagesJson);
        } catch (e) {
            return;
        }

        const newImageUrl = styleImages[styleName];
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
