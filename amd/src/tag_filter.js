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

/** Duration in milliseconds for height transition animation. */
const HEIGHT_TRANSITION_MS = 300;

/** State object to track active filters across functions. */
const filterState = {
    activeTag: '',
    activeTagImageUrl: '',
    activeCompletion: '', // 'true', 'false', or '' (none)
};

/**
 * Selectors for sibling elements that should be included in the animated wrapper.
 * These elements follow .minimoodlewall-activities and should animate together.
 */
const WRAPPER_SIBLING_SELECTORS = [
    '.minimoodlewall-navigation-wrapper',
    '[data-region="completion-status"]',
];

/**
 * Collect sibling elements that should be wrapped together with the container.
 *
 * @param {HTMLElement} container - The .minimoodlewall-activities container
 * @returns {HTMLElement[]} Array of sibling elements to include in wrapper
 */
const collectWrappableSiblings = (container) => {
    const siblings = [];
    const parent = container.parentElement;
    if (!parent) {
        return siblings;
    }

    WRAPPER_SIBLING_SELECTORS.forEach((selector) => {
        const sibling = parent.querySelector(selector);
        if (sibling && sibling !== container) {
            siblings.push(sibling);
        }
    });

    return siblings;
};

/**
 * Animate container height change when filtering changes visible rows.
 *
 * Creates a temporary wrapper to animate height independently of the grid.
 * The wrapper captures the height, while the inner content reflows freely.
 * Also wraps sibling elements (navigation, completion status) to prevent layout jumps.
 *
 * @param {HTMLElement} container - The .minimoodlewall-activities container
 * @param {Function} applyChanges - Callback that applies the filter changes
 * @returns {void}
 */
const animateContainerHeight = (container, applyChanges) => {
    if (!container) {
        applyChanges();
        return;
    }

    // Get or create wrapper element around container.
    let wrapper = container.parentElement;
    let createdWrapper = false;
    let wrappedSiblings = [];

    // If parent isn't already a dedicated wrapper, create one.
    if (!wrapper.classList.contains('minimoodlewall-height-animator')) {
        const originalParent = container.parentElement;

        // Collect siblings to wrap before modifying DOM.
        wrappedSiblings = collectWrappableSiblings(container);

        wrapper = document.createElement('div');
        wrapper.className = 'minimoodlewall-height-animator';
        wrapper.style.overflow = 'hidden';

        // Insert wrapper before container and move container into it.
        originalParent.insertBefore(wrapper, container);
        wrapper.appendChild(container);

        // Move sibling elements into wrapper (preserving order).
        wrappedSiblings.forEach((sibling) => {
            wrapper.appendChild(sibling);
        });

        createdWrapper = true;
    }

    // Capture current height of wrapper before changes.
    const startHeight = wrapper.offsetHeight;

    // Lock wrapper height immediately.
    wrapper.style.height = `${startHeight}px`;
    wrapper.style.transition = 'none';

    // Force reflow to lock the height.
    void wrapper.offsetHeight;

    // Hide all cards using visibility.
    const allCards = Array.from(container.querySelectorAll('li[data-id]'));
    allCards.forEach((card) => {
        card.style.visibility = 'hidden';
    });

    // Apply the filter changes (cards get hidden/shown via display:none).
    applyChanges();

    // Get newly visible cards.
    const visibleCards = Array.from(container.querySelectorAll('li[data-id]:not([hidden])'));

    // Measure new natural height of wrapper content (wrapper is still locked but content reflows).
    // We need to measure all wrapped content, not just the container.
    let endHeight = container.offsetHeight;
    wrappedSiblings.forEach((sibling) => {
        endHeight += sibling.offsetHeight;
        // Account for margins between siblings.
        const style = window.getComputedStyle(sibling);
        endHeight += parseInt(style.marginTop, 10) || 0;
        endHeight += parseInt(style.marginBottom, 10) || 0;
    });

    /**
     * Unwrap elements and restore them to original parent.
     */
    const unwrapElements = () => {
        if (!createdWrapper) {
            return;
        }
        const wrapperParent = wrapper.parentElement;
        // Move container back before wrapper.
        wrapperParent.insertBefore(container, wrapper);
        // Move siblings back after container (in original order).
        let insertAfter = container;
        wrappedSiblings.forEach((sibling) => {
            insertAfter.after(sibling);
            insertAfter = sibling;
        });
        wrapper.remove();
    };

    /**
     * Fade in visible cards with animation.
     */
    const fadeInCards = () => {
        visibleCards.forEach((card) => {
            card.style.visibility = '';
            card.style.opacity = '0';
        });
        requestAnimationFrame(() => {
            visibleCards.forEach((card) => {
                card.style.transition = 'opacity 150ms ease';
                card.style.opacity = '1';
            });
            setTimeout(() => {
                visibleCards.forEach((card) => {
                    card.style.transition = '';
                    card.style.opacity = '';
                });
            }, 200);
        });
    };

    // Skip animation if height didn't change significantly.
    if (Math.abs(endHeight - startHeight) < 1) {
        wrapper.style.height = '';
        wrapper.style.transition = '';
        unwrapElements();
        fadeInCards();
        return;
    }

    // Animate wrapper height.
    wrapper.style.transition = `height ${HEIGHT_TRANSITION_MS}ms ease`;
    wrapper.style.height = `${endHeight}px`;

    // Fade cards in after height transition completes.
    setTimeout(() => {
        // Clean up wrapper.
        wrapper.style.height = '';
        wrapper.style.transition = '';
        wrapper.style.overflow = '';

        unwrapElements();
        fadeInCards();
    }, HEIGHT_TRANSITION_MS);
};

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
 * Apply combined tag and completion filters to the activity cards.
 *
 * Filtering strategy (AND logic):
 * - If tag filter active: item must have matching data-tagid
 * - If completion filter active: item must have matching data-completed
 * - Both filters use AND logic when combined
 *
 * @param {HTMLElement[]} items - Array of activity list item elements
 * @returns {number} Count of visible items after filtering
 */
const applyCombinedFilter = (items) => {
    let visibleCount = 0;
    items.forEach((item) => {
        let matchesTag = true;
        let matchesCompletion = true;

        // Check tag filter if active.
        if (filterState.activeTag) {
            const itemTag = item.dataset.tagid || '';
            matchesTag = (itemTag === filterState.activeTag);
        }

        // Check completion filter if active.
        if (filterState.activeCompletion) {
            const itemCompleted = item.dataset.completed;
            // Only filter items that have completion tracking.
            if (itemCompleted !== undefined) {
                matchesCompletion = (itemCompleted === filterState.activeCompletion);
            } else {
                // Items without completion tracking don't match completion filter.
                matchesCompletion = false;
            }
        }

        if (matchesTag && matchesCompletion) {
            item.hidden = false;
            item.classList.remove('is-filtered-out');
            item.style.removeProperty('display');
            visibleCount++;
        } else {
            item.hidden = true;
            item.classList.add('is-filtered-out');
            item.style.display = 'none';
        }
    });
    return visibleCount;
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
 * Update completion pill buttons to reflect active state.
 *
 * @param {HTMLElement} statusRegion - Completion status region element
 * @param {string} activeCompleted - 'true', 'false', or '' (none)
 * @returns {void}
 */
const updateCompletionPills = (statusRegion, activeCompleted) => {
    const pills = statusRegion.querySelectorAll('[data-action="completion-filter"]');
    pills.forEach((pill) => {
        const isActive = pill.dataset.completed === activeCompleted;
        pill.classList.toggle('is-active', isActive);
        pill.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
};

/**
 * Update completion counts displayed in the pills.
 *
 * @param {HTMLElement} statusRegion - Completion status region element
 * @param {HTMLElement[]} items - Array of activity items to count from
 * @returns {void}
 */
const updateCompletionCounts = (statusRegion, items) => {
    let completedCount = 0;
    let incompleteCount = 0;
    let totalWithCompletion = 0;

    items.forEach((item) => {
        // Skip hidden items when a tag filter is active.
        if (filterState.activeTag && item.hidden) {
            return;
        }
        // Only consider items with completion tracking.
        const completed = item.dataset.completed;
        if (completed === 'true') {
            completedCount++;
            totalWithCompletion++;
        } else if (completed === 'false') {
            incompleteCount++;
            totalWithCompletion++;
        }
    });

    const completedEl = statusRegion.querySelector('[data-count="completed"]');
    const incompleteEl = statusRegion.querySelector('[data-count="incomplete"]');
    if (completedEl) {
        completedEl.textContent = completedCount;
    }
    if (incompleteEl) {
        incompleteEl.textContent = incompleteCount;
    }

    // Update the completion stars display.
    updateCompletionStars(statusRegion, completedCount, totalWithCompletion);
};

/**
 * Update the completion stars display to show filled stars for completed
 * activities and smaller unfilled stars for incomplete activities.
 *
 * Stars are rendered dynamically based on the current counts.
 * A subtle entrance animation is applied to newly appearing stars.
 * When all activities are complete, the container gets an all-complete class.
 *
 * @param {HTMLElement} statusRegion - Completion status region element
 * @param {number} completedCount - Number of completed activities
 * @param {number} totalWithCompletion - Total activities with completion tracking
 * @returns {void}
 */
const updateCompletionStars = (statusRegion, completedCount, totalWithCompletion) => {
    const starsContainer = statusRegion.querySelector('[data-region="completion-stars"]');
    if (!starsContainer) {
        return;
    }

    const incompleteCount = totalWithCompletion - completedCount;
    const currentFilledCount = starsContainer.querySelectorAll('.star-icon:not(.star-icon--incomplete)').length;
    const currentUnfilledCount = starsContainer.querySelectorAll('.star-icon--incomplete').length;
    const allComplete = totalWithCompletion > 0 && completedCount === totalWithCompletion;

    // Only rebuild if counts changed.
    if (currentFilledCount !== completedCount || currentUnfilledCount !== incompleteCount) {
        const fragment = document.createDocumentFragment();

        // Filled stars for completed activities.
        for (let i = 0; i < completedCount; i++) {
            const span = document.createElement('span');
            span.className = 'star-icon';
            span.setAttribute('aria-hidden', 'true');
            const icon = document.createElement('i');
            icon.className = 'fa fa-star';
            span.appendChild(icon);
            // Animate only newly added stars.
            if (i >= currentFilledCount) {
                span.classList.add('star-new');
            }
            fragment.appendChild(span);
        }

        // Unfilled stars for incomplete activities.
        for (let i = 0; i < incompleteCount; i++) {
            const span = document.createElement('span');
            span.className = 'star-icon--incomplete';
            span.setAttribute('aria-hidden', 'true');
            const icon = document.createElement('i');
            icon.className = 'fa fa-star-o';
            span.appendChild(icon);
            fragment.appendChild(span);
        }

        starsContainer.innerHTML = '';
        starsContainer.appendChild(fragment);

        // Update aria label.
        starsContainer.setAttribute(
            'aria-label',
            completedCount + ' of ' + totalWithCompletion + ' activities completed'
        );
    }

    // Update data attributes and all-complete state.
    starsContainer.dataset.completedCount = completedCount;
    starsContainer.dataset.totalCount = totalWithCompletion;
    starsContainer.classList.toggle('all-complete', allComplete);
};

/**
 * Update the active tag image display in the completion status region.
 *
 * @param {HTMLElement} statusRegion - Completion status region element
 * @param {string} imageUrl - URL of the tag image to display, or empty to hide
 * @returns {void}
 */
const updateActiveTagImage = (statusRegion, imageUrl) => {
    const imageContainer = statusRegion.querySelector('[data-region="active-tag-image"]');
    if (!imageContainer) {
        return;
    }

    const img = imageContainer.querySelector('img');
    if (imageUrl) {
        if (img) {
            img.src = imageUrl;
        }
        imageContainer.hidden = false;
    } else {
        imageContainer.hidden = true;
    }
};

/**
 * Show or hide the "no activities" message.
 *
 * @param {HTMLElement} statusRegion - Completion status region element
 * @param {boolean} show - Whether to show the message
 * @returns {void}
 */
const toggleNoActivitiesMessage = (statusRegion, show) => {
    const msgEl = statusRegion.querySelector('[data-region="no-activities"]');
    if (msgEl) {
        msgEl.hidden = !show;
    }
};

/**
 * Initialize the filter bar listeners and state management.
 *
 * Setup process:
 * 1. Locate activity container (next sibling or parent's descendant)
 * 2. Collect all activity items and preserve original DOM order
 * 3. Enable buttons that have activities with matching tags
 * 4. Attach click handler for filter toggling
 * 5. Initialize completion status region if present
 *
 * State management:
 * - filterState.activeTag: Currently selected tag ID (empty string = no filter)
 * - filterState.activeCompletion: Currently selected completion state ('true', 'false', or '')
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

        // Find the completion status region.
        const statusRegion = bar.parentElement.querySelector('[data-region="completion-status"]');

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

        filterButtons.forEach((button) => {
            const hasActivities = button.dataset.hasactivities === '1';
            if (hasActivities) {
                button.disabled = false;
                button.classList.remove('is-empty');
            }
        });

        /**
         * Apply all active filters and update UI state with height animation.
         *
         * @returns {number} Count of visible items after filtering
         */
        const applyAllFilters = () => {
            let visibleCount;

            animateContainerHeight(activityContainer, () => {
                if (filterState.activeTag || filterState.activeCompletion) {
                    visibleCount = applyCombinedFilter(activityItems);
                } else {
                    clearFilterStyles(activityItems);
                    visibleCount = activityItems.length;
                }
            });

            // Update completion counts based on currently visible items.
            if (statusRegion) {
                updateCompletionCounts(statusRegion, activityItems);
                toggleNoActivitiesMessage(statusRegion, visibleCount === 0);
            }

            return visibleCount;
        };

        /**
         * Set or clear the tag filter.
         *
         * @param {string} tagid - Tag ID to filter by, or empty string to clear
         * @param {HTMLElement|null} button - Button element that was clicked, or null
         * @returns {void}
         */
        const setTagFilter = (tagid, button) => {
            if (tagid) {
                filterState.activeTag = tagid;
                // Store the tag image URL from the button.
                const img = button ? button.querySelector('img') : null;
                filterState.activeTagImageUrl = img ? img.src : '';

                notifyFilterChange(true);
                reorderActivitiesByTag(tagid);
                updateButtons(bar, button);

                // Update tag image in completion status region.
                if (statusRegion) {
                    updateActiveTagImage(statusRegion, filterState.activeTagImageUrl);
                }
            } else {
                filterState.activeTag = '';
                filterState.activeTagImageUrl = '';
                restoreOriginalOrder();
                updateButtons(bar, null);

                // Hide tag image in completion status region.
                if (statusRegion) {
                    updateActiveTagImage(statusRegion, '');
                }

                // Only notify pagination if no filters are active.
                if (!filterState.activeCompletion) {
                    notifyFilterChange(false);
                }
            }

            const visibleCount = applyAllFilters();

            // Announce filter status to screen readers.
            const tagName = button ? button.dataset.tagName || '' : '';
            announceFilterStatus(tagName, visibleCount, activityItems.length);
        };

        /**
         * Set or clear the completion filter.
         *
         * @param {string} completed - 'true', 'false', or '' to clear
         * @returns {void}
         */
        const setCompletionFilter = (completed) => {
            if (completed) {
                filterState.activeCompletion = completed;
                notifyFilterChange(true);
            } else {
                filterState.activeCompletion = '';
                // Only notify pagination if no filters are active.
                if (!filterState.activeTag) {
                    notifyFilterChange(false);
                }
            }

            if (statusRegion) {
                updateCompletionPills(statusRegion, completed);
            }

            applyAllFilters();
        };

        // Tag filter click handler.
        bar.addEventListener('click', (event) => {
            const button = event.target.closest('[data-action="tag-filter"]');
            if (button && bar.contains(button)) {
                event.preventDefault();
                if (!button.dataset.tagid) {
                    return;
                }
                if (filterState.activeTag === button.dataset.tagid) {
                    setTagFilter('', null);
                } else {
                    setTagFilter(button.dataset.tagid, button);
                }
            }
        });

        // Completion filter click handler.
        if (statusRegion) {
            statusRegion.addEventListener('click', (event) => {
                const pill = event.target.closest('[data-action="completion-filter"]');
                if (pill && statusRegion.contains(pill)) {
                    event.preventDefault();
                    const completed = pill.dataset.completed;
                    if (filterState.activeCompletion === completed) {
                        setCompletionFilter('');
                    } else {
                        setCompletionFilter(completed);
                    }
                }
            });

            // Initial update to show star if all activities are already complete.
            updateCompletionCounts(statusRegion, activityItems);
        }
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Initialize completion status region without a filter bar.
 *
 * Used when filtering is disabled but completion status is still shown.
 *
 * @param {HTMLElement} statusRegion - Completion status region element
 * @returns {void}
 */
const initCompletionStatusOnly = (statusRegion) => {
    try {
        const activityContainer = statusRegion.parentElement.querySelector('.minimoodlewall-activities');
        if (!activityContainer) {
            return;
        }

        const activityItems = Array.from(activityContainer.querySelectorAll('li[data-id]'));
        if (!activityItems.length) {
            return;
        }

        /**
         * Apply completion filter and update UI with height animation.
         *
         * @returns {number} Count of visible items after filtering
         */
        const applyAllFilters = () => {
            let visibleCount;

            animateContainerHeight(activityContainer, () => {
                if (filterState.activeCompletion) {
                    visibleCount = applyCombinedFilter(activityItems);
                } else {
                    activityItems.forEach((item) => {
                        item.hidden = false;
                        item.classList.remove('is-filtered-out');
                        item.style.removeProperty('display');
                    });
                    visibleCount = activityItems.length;
                }
            });

            updateCompletionCounts(statusRegion, activityItems);
            toggleNoActivitiesMessage(statusRegion, visibleCount === 0);

            return visibleCount;
        };

        /**
         * Set or clear the completion filter.
         *
         * @param {string} completed - 'true', 'false', or '' to clear
         * @returns {void}
         */
        const setCompletionFilter = (completed) => {
            if (completed) {
                filterState.activeCompletion = completed;
                notifyFilterChange(true);
            } else {
                filterState.activeCompletion = '';
                notifyFilterChange(false);
            }

            updateCompletionPills(statusRegion, completed);
            applyAllFilters();
        };

        statusRegion.addEventListener('click', (event) => {
            const pill = event.target.closest('[data-action="completion-filter"]');
            if (pill && statusRegion.contains(pill)) {
                event.preventDefault();
                const completed = pill.dataset.completed;
                if (filterState.activeCompletion === completed) {
                    setCompletionFilter('');
                } else {
                    setCompletionFilter(completed);
                }
            }
        });

        // Initial update to show star if all activities are already complete.
        updateCompletionCounts(statusRegion, activityItems);
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Initialize all filter bars and completion status regions in the page.
 *
 * Scans for all elements with [data-region="minimoodlewall-filterbar"]
 * and initializes filtering functionality for each.
 *
 * Also initializes standalone completion status regions (without filter bar).
 *
 * Typically one filter bar per course section, but supports multiple.
 *
 * @returns {void}
 */
export const init = () => {
    // Initialize filter bars (which also handle their associated completion status regions).
    document
        .querySelectorAll('[data-region="minimoodlewall-filterbar"]')
        .forEach((bar) => initFilterBar(bar));

    // Initialize standalone completion status regions (when filtering is disabled).
    document
        .querySelectorAll('[data-region="completion-status"]')
        .forEach((statusRegion) => {
            // Skip if already initialized by a filter bar.
            const parent = statusRegion.parentElement;
            if (!parent || !parent.querySelector('[data-region="minimoodlewall-filterbar"]')) {
                initCompletionStatusOnly(statusRegion);
            }
        });
};