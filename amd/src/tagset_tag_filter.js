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
 * AMD module to filter tag checkboxes based on the selected tagset.
 *
 * When the tagset dropdown changes, only checkboxes belonging to that tagset
 * are shown. Hidden checkboxes are unchecked and the hidden selectedtags field
 * is synchronised via tag_checkbox_sync.
 *
 * @module     format_minimoodlewall/tagset_tag_filter
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    TAGSET_SELECT: '#id_tagsetid_select',
    TAGSET_HIDDEN: 'input[name="tagsetid"]',
    TAG_CHECKBOX: 'input[name^="selectedtag_"]',
};

/**
 * Initialise the tagset tag filter.
 */
export const init = () => {
    const select = document.querySelector(SELECTORS.TAGSET_SELECT);
    if (!select) {
        return;
    }

    // Apply initial filter based on current selection.
    applyFilter(select);

    // Listen for changes.
    select.addEventListener('change', () => {
        applyFilter(select);
    });
};

/**
 * Show/hide tag checkboxes based on the selected tagset and sync hidden field.
 *
 * @param {HTMLSelectElement} select The tagset dropdown element.
 */
const applyFilter = (select) => {
    const selectedTagsetId = select.value;

    // Sync the hidden tagsetid field.
    const hiddenField = document.querySelector(SELECTORS.TAGSET_HIDDEN);
    if (hiddenField) {
        hiddenField.value = selectedTagsetId;
    }

    // Get all tag checkboxes.
    const checkboxes = document.querySelectorAll(SELECTORS.TAG_CHECKBOX);

    checkboxes.forEach((checkbox) => {
        const tagsetId = checkbox.getAttribute('data-tagsetid');
        // Find the closest form group container (fitem) to show/hide the whole row.
        const formGroup = checkbox.closest('.fitem');

        if (!selectedTagsetId || tagsetId === selectedTagsetId) {
            // Show this checkbox.
            if (formGroup) {
                formGroup.style.display = '';
            }
        } else {
            // Hide this checkbox and uncheck it.
            if (formGroup) {
                formGroup.style.display = 'none';
            }
            checkbox.checked = false;
            // Dispatch change event so tag_checkbox_sync picks it up.
            checkbox.dispatchEvent(new Event('change', {bubbles: true}));
        }
    });
};
