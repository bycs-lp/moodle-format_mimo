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
 * Tag-based filtering for minimoodlewall activity cards.
 *
 * @module     format_minimoodlewall/tag_filter
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';

/** Custom event name for coordinating with pagination module. */
const EVENT_NAME = 'minimoodlewall:filterchange';

/**
 * Dispatch a custom filter change event so pagination can react.
 *
 * Event coordination:
 * - When active=true: Pagination disables and shows all activities
 * - When active=false: Pagination re-enables and restores page view
 *
 * @param {boolean} active - Whether a filter is currently active
 * @returns {void}
 */
const notifyFilterChange = (active) => {
    document.dispatchEvent(new CustomEvent(EVENT_NAME, {
        detail: {active: active}
    }));
};

/**
 * Announce filter status to screen readers via live region.
 *
 * Accessibility:
 * - Finds sr-only live region with role="status" aria-live="polite"
 * - Announces count and tag name when filter active
 * - Announces "showing all" when filter cleared
 * - Screen readers will speak the message without moving focus
 *
 * @param {string} tagName - Name of the active tag, or empty for cleared filter
 * @param {number} visibleCount - Number of visible activities
 * @param {number} totalCount - Total number of activities
 * @returns {void}
 */
const announceFilterStatus = (tagName, visibleCount, totalCount) => {
    const liveRegion = document.querySelector('[data-region="filter-status"]');
    if (!liveRegion) {
        return;
    }

    if (tagName) {
        // Use Moodle string API when available, fallback to English
        liveRegion.textContent = `Showing ${visibleCount} of ${totalCount} activities tagged '${tagName}'.`;
    } else {
        liveRegion.textContent = `Filter cleared. Showing all ${totalCount} activities.`;
    }
};

/**
 * Clear inline display styles applied by filtering.
 *
 * Restoration process:
 * - Removes hidden attribute
 * - Removes 'is-filtered-out' class (for potential CSS hooks)
 * - Removes inline display style (restores grid layout)
 *
 * Called when filter is deactivated to show all activities again.
 *
 * @param {HTMLElement[]} items - Array of activity list item elements
 * @returns {void}
 */
const clearFilterStyles = (items) => {
    items.forEach((item) => {
        item.hidden = false;
        item.classList.remove('is-filtered-out');
        item.style.removeProperty('display');
    });
};

/**
 * Apply the selected filter to the activity cards.
 *
 * Filtering strategy:
 * - Compares each activity's data-tagid with selected tag
 * - Matching activities: unhidden, visible, no special class
 * - Non-matching activities: hidden, display:none, marked with class
 *
 * Note: Activities must have data-tagid attribute set by renderer.
 * Missing or empty data-tagid will not match any filter.
 *
 * @param {HTMLElement[]} items - Array of activity list item elements
 * @param {string} tagid - ID of the tag to filter by
 * @returns {void}
 */
const applyFilter = (items, tagid) => {
    items.forEach((item) => {
        const itemTag = item.dataset.tagid || '';
        if (itemTag === tagid) {
            item.hidden = false;
            item.classList.remove('is-filtered-out');
            item.style.removeProperty('display');
        } else {
            item.hidden = true;
            item.classList.add('is-filtered-out');
            item.style.display = 'none';
        }
    });
};

/**
 * Update filter buttons to reflect the active state.
 *
 * Visual states:
 * - Active button: 'is-active' class, aria-pressed="true"
 * - Inactive buttons when filter active: 'is-muted' class
 * - All buttons when no filter: no special classes, aria-pressed="false"
 *
 * Accessibility:
 * - Uses aria-pressed to indicate toggle button state
 * - Screen readers announce "pressed" or "not pressed"
 *
 * @param {HTMLElement} bar - Filter bar container element
 * @param {HTMLElement|null} activeButton - The button that is now active, or null for none
 * @returns {void}
 */
const updateButtons = (bar, activeButton) => {
    const buttons = bar.querySelectorAll('[data-action="tag-filter"]');
    buttons.forEach((button) => {
        const isActive = button === activeButton;
        button.classList.toggle('is-active', isActive);
        button.classList.toggle('is-muted', !!activeButton && button !== activeButton);
        button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
};

/**
 * Initialize the filter bar listeners and state management.
 *
 * Setup process:
 * 1. Locate activity container (next sibling or parent's descendant)
 * 2. Collect all activity items and preserve original DOM order
 * 3. Enable buttons that have activities with matching tags
 * 4. Attach click handler for filter toggling
 *
 * State management:
 * - activeTag: Currently selected tag ID (empty string = no filter)
 * - originalOrder: Array preserving initial activity sequence
 *
 * Reordering strategy:
 * - When filter active: Matching activities move to top, others follow
 * - When filter inactive: Restore originalOrder array sequence
 * - Uses DocumentFragment for efficient batch DOM manipulation
 *
 * Click behavior:
 * - Click active filter: Deactivates and shows all
 * - Click inactive filter: Activates and shows only matching
 * - Prevents default to avoid navigation
 *
 * @param {HTMLElement} bar - Filter bar element with [data-region="minimoodlewall-filterbar"]
 * @returns {void}
 */
const initFilterBar = (bar) => {
    try {
        const sibling = bar.nextElementSibling;
        let activityContainer = null;
        if (sibling && sibling.classList.contains('minimoodlewall-activities')) {
            activityContainer = sibling;
        } else {
            activityContainer = bar.parentElement.querySelector('.minimoodlewall-activities');
        }

        if (!activityContainer) {
            return;
        }

        const activityItems = Array.from(activityContainer.querySelectorAll('li[data-id]'));
        if (!activityItems.length) {
            return;
        }

        const originalOrder = activityItems.slice();

        const restoreOriginalOrder = () => {
            const fragment = document.createDocumentFragment();
            originalOrder.forEach((item) => fragment.appendChild(item));
            activityContainer.appendChild(fragment);
        };

        const reorderActivitiesByTag = (tagid) => {
            const matching = [];
            const remaining = [];
            originalOrder.forEach((item) => {
                if ((item.dataset.tagid || '') === tagid) {
                    matching.push(item);
                } else {
                    remaining.push(item);
                }
            });
            const fragment = document.createDocumentFragment();
            matching.concat(remaining).forEach((item) => fragment.appendChild(item));
            activityContainer.appendChild(fragment);
        };

        // Buttons mirror the server-side tag list for this section.
        const filterButtons = Array.from(bar.querySelectorAll('[data-action="tag-filter"]'));
        if (!filterButtons.length) {
            return;
        }
        let activeTag = '';

        filterButtons.forEach((button) => {
            const hasActivities = button.dataset.hasactivities === '1';
            if (hasActivities) {
                button.disabled = false;
                button.classList.remove('is-empty');
            }
        });

        /**
         * Set or clear the active filter.
         *
         * Filter activation (tagid provided):
         * - Sets activeTag state
         * - Notifies pagination to disable
         * - Reorders activities (matching first)
         * - Applies visibility filtering
         * - Updates button visual states
         *
         * Filter deactivation (no tagid):
         * - Clears activeTag state
         * - Restores original activity order
         * - Clears all filter styles
         * - Resets button visual states
         * - Notifies pagination to re-enable
         *
         * @param {string} tagid - Tag ID to filter by, or empty string to clear
         * @param {HTMLElement|null} button - Button element that was clicked, or null
         * @returns {void}
         */
        const setFilter = (tagid, button) => {
            if (tagid) {
                activeTag = tagid;
                notifyFilterChange(true);
                reorderActivitiesByTag(tagid);
                applyFilter(activityItems, tagid);
                updateButtons(bar, button);

                // Announce filter status to screen readers
                const visibleCount = activityItems.filter(item => !item.hidden).length;
                const tagName = button ? button.dataset.tagName || '' : '';
                announceFilterStatus(tagName, visibleCount, activityItems.length);
            } else {
                activeTag = '';
                restoreOriginalOrder();
                clearFilterStyles(activityItems);
                updateButtons(bar, null);
                notifyFilterChange(false);

                // Announce filter cleared to screen readers
                announceFilterStatus('', activityItems.length, activityItems.length);
            }
        };

        bar.addEventListener('click', (event) => {
            const button = event.target.closest('[data-action="tag-filter"]');
            if (button && bar.contains(button)) {
                event.preventDefault();
                if (!button.dataset.tagid) {
                    return;
                }
                if (activeTag === button.dataset.tagid) {
                    setFilter('', null);
                } else {
                    setFilter(button.dataset.tagid, button);
                }
            }
        });
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Initialize all filter bars in the page.
 *
 * Scans for all elements with [data-region="minimoodlewall-filterbar"]
 * and initializes filtering functionality for each.
 *
 * Typically one filter bar per course section, but supports multiple.
 *
 * @returns {void}
 */
export const init = () => {
    document
        .querySelectorAll('[data-region="minimoodlewall-filterbar"]')
        .forEach((bar) => initFilterBar(bar));
};