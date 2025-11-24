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

const EVENT_NAME = 'minimoodlewall:filterchange';

/**
 * Dispatch a custom filter change event so pagination can react.
 *
 * @param {boolean} active Whether a filter is active
 */
const notifyFilterChange = (active) => {
    document.dispatchEvent(new CustomEvent(EVENT_NAME, {
        detail: {active: active}
    }));
};

/**
 * Clear inline display styles applied by filtering.
 *
 * @param {HTMLElement[]} items Activity elements
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
 * @param {HTMLElement[]} items Activity elements
 * @param {string} tagid Selected tag id
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
 * @param {HTMLElement} bar Filter bar element
 * @param {HTMLElement|null} activeButton The button that is now active
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
 * Initialise the filter bar listeners.
 *
 * @param {HTMLElement} bar Filter bar element.
 */
const initFilterBar = (bar) => {
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

    const setFilter = (tagid, button) => {
        if (tagid) {
            activeTag = tagid;
            notifyFilterChange(true);
            reorderActivitiesByTag(tagid);
            applyFilter(activityItems, tagid);
            updateButtons(bar, button);
        } else {
            activeTag = '';
            restoreOriginalOrder();
            clearFilterStyles(activityItems);
            updateButtons(bar, null);
            notifyFilterChange(false);
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
};

export const init = () => {
    document
        .querySelectorAll('[data-region="minimoodlewall-filterbar"]')
        .forEach((bar) => initFilterBar(bar));
};