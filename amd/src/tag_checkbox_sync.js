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
 * AMD module to synchronize tag checkboxes with the hidden selectedtags field.
 *
 * @module     format_minimoodlewall/tag_checkbox_sync
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Initialize the tag checkbox synchronization.
 *
 * @param {number[]} tagIds - Array of all available tag IDs.
 */
export const init = (tagIds) => {
    const hiddenField = document.querySelector('input[name="selectedtags"]');
    if (!hiddenField) {
        return;
    }

    /**
     * Collect all checked tag IDs and update the hidden field.
     */
    const syncToHiddenField = () => {
        const selectedIds = [];
        tagIds.forEach((tagId) => {
            const checkbox = document.querySelector(`input[name="selectedtag_${tagId}"]`);
            if (checkbox && checkbox.checked) {
                selectedIds.push(tagId);
            }
        });
        hiddenField.value = selectedIds.join(',');
    };

    // Attach change listeners to all tag checkboxes.
    tagIds.forEach((tagId) => {
        const checkbox = document.querySelector(`input[name="selectedtag_${tagId}"]`);
        if (checkbox) {
            checkbox.addEventListener('change', syncToHiddenField);
        }
    });

    // Initial sync on page load (in case checkboxes are pre-checked).
    syncToHiddenField();
};
