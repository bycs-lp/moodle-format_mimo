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
 * Tag-based filtering for mimo activity cards.
 *
 * @module     format_mimo/tag_filter
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import {getWallState} from 'format_mimo/local/wall_state/wall_state';
import {get_string as getString} from 'core/str';

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
 * These elements follow .mimo-activities and should animate together.
 */
const WRAPPER_SIBLING_SELECTORS = [
    '.mimo-navigation-wrapper',
    '[data-region="completion-status"]',
];

/**
 * Collect sibling elements that should be wrapped together with the container.
 *
 * @param {HTMLElement} container - The .mimo-activities container
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
 * @param {HTMLElement} container - The .mimo-activities container
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
    if (!wrapper.classList.contains('mimo-height-animator')) {
        const originalParent = container.parentElement;

        // Collect siblings to wrap before modifying DOM.
        wrappedSiblings = collectWrappableSiblings(container);

        wrapper = document.createElement('div');
        wrapper.className = 'mimo-height-animator';
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
 * Dispatch filter state to the wall reactive so pagination can react.
 *
 * @param {Reactive} wallState - The wall state reactive instance
 * @param {string} activeTag - Active tag ID, or '' for no tag filter
 * @param {string} activeCompletion - Active completion value, or '' for no filter
 * @returns {void}
 */
const syncFilterState = (wallState, activeTag, activeCompletion) => {
    wallState.dispatch('setTagFilter', activeTag ? [activeTag] : []);
    wallState.dispatch('setCompletionFilter', activeCompletion);
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
const announceFilterStatus = async(tagName, visibleCount, totalCount) => {
    const liveRegion = document.querySelector('[data-region="filter-status"]');
    if (!liveRegion) {
        return;
    }

    if (tagName) {
        liveRegion.textContent = await getString('aria_filter_active', 'format_mimo', {
            visible: visibleCount,
            total: totalCount,
            tagname: tagName,
        });
    } else {
        liveRegion.textContent = await getString('aria_filter_cleared', 'format_mimo', totalCount);
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
 * Update the completion star display.
 *
 * Shows an animated sparkle star only when there is at least one tracked
 * activity and all tracked activities are complete. Visibility is driven
 * by toggling the `is-visible` class on the pre-rendered container.
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

    const allComplete = totalWithCompletion > 0 && completedCount === totalWithCompletion;
    const wasVisible = starsContainer.classList.contains('is-visible');
    starsContainer.classList.toggle('is-visible', allComplete);
    starsContainer.dataset.completedCount = completedCount;
    starsContainer.dataset.totalCount = totalWithCompletion;

    if (allComplete !== wasVisible) {
        // Aria label refresh only when star visibility changed.
        getString('aria_completion_status', 'format_mimo', {
            completed: completedCount,
            total: totalWithCompletion,
        }).then((str) => {
            starsContainer.setAttribute('aria-label', str);
            return;
        }).catch(() => {
            // Fallback silently if string loading fails.
        });
    }
};

/** Glyphs used for firework particles — gold stars and sparkles. */
const FIREWORK_GLYPHS = ['★', '✦', '✧', '✨', '⭐'];

/** Number of particles launched per firework burst. */
const FIREWORK_PARTICLE_COUNT = 42;

/** Duration of the firework animation in milliseconds (must match SCSS). */
const FIREWORK_DURATION_MS = 1400;

/**
 * Launch a firework of star particles from the given container.
 *
 * Particles are rendered as fixed-position elements appended to the document
 * body so they can extend far beyond the small completion-star container and
 * are not clipped by any ancestor with `overflow: hidden`. Each particle gets
 * random trajectory CSS variables (--tx, --ty, --rot) and is removed once the
 * CSS animation finishes.
 *
 * @param {HTMLElement} container - The `.completion-stars` container
 * @returns {void}
 */
const launchStarFirework = (container) => {
    // Trigger a one-shot "punch" animation on the main star.
    container.classList.remove('is-bursting');
    // Force reflow so the class re-add restarts the animation.
    void container.offsetWidth;
    container.classList.add('is-bursting');

    // Anchor the burst at the center of the star on the viewport.
    const rect = container.getBoundingClientRect();
    const cx = rect.left + rect.width / 2;
    const cy = rect.top + rect.height / 2;

    // Scale burst radius with viewport so it looks large everywhere.
    const vmin = Math.min(window.innerWidth, window.innerHeight);
    const maxDistance = Math.max(260, vmin * 0.45);
    const minDistance = maxDistance * 0.45;

    const particles = [];
    for (let i = 0; i < FIREWORK_PARTICLE_COUNT; i++) {
        const particle = document.createElement('span');
        particle.className = 'mimo-star-firework';
        particle.setAttribute('aria-hidden', 'true');
        particle.textContent = FIREWORK_GLYPHS[i % FIREWORK_GLYPHS.length];

        // Random angle (0–360°) and distance for a wide radial burst.
        const angle = Math.random() * Math.PI * 2;
        const distance = minDistance + Math.random() * (maxDistance - minDistance);
        const tx = Math.cos(angle) * distance;
        const ty = Math.sin(angle) * distance;
        const rot = (Math.random() * 720 - 360).toFixed(0);
        // Randomize size a bit so the burst feels organic.
        const scale = (0.8 + Math.random() * 1.4).toFixed(2);

        particle.style.left = `${cx}px`;
        particle.style.top = `${cy}px`;
        particle.style.setProperty('--tx', `${tx.toFixed(1)}px`);
        particle.style.setProperty('--ty', `${ty.toFixed(1)}px`);
        particle.style.setProperty('--rot', `${rot}deg`);
        particle.style.setProperty('--scale', scale);

        // Slight per-particle delay for a more natural burst.
        particle.style.animationDelay = `${(Math.random() * 120).toFixed(0)}ms`;

        document.body.appendChild(particle);
        particles.push(particle);
    }

    // Clean up our own particles after the animation completes.
    setTimeout(() => {
        particles.forEach((el) => el.remove());
        container.classList.remove('is-bursting');
    }, FIREWORK_DURATION_MS + 250);
};

/** Guard to ensure the firework click delegation is registered only once. */
let fireworkListenerRegistered = false;

/**
 * Register a single document-level click delegation that launches a firework
 * whenever a visible completion star is clicked.
 *
 * @returns {void}
 */
const registerFireworkListener = () => {
    if (fireworkListenerRegistered) {
        return;
    }
    fireworkListenerRegistered = true;
    document.addEventListener('click', (event) => {
        const container = event.target.closest('[data-region="completion-stars"]');
        if (!container || !container.classList.contains('is-visible')) {
            return;
        }
        launchStarFirework(container);
    });
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
 * @param {HTMLElement} bar - Filter bar element with [data-region="mimo-filterbar"]
 * @returns {void}
 */
const initFilterBar = (bar) => {
    try {
        const sibling = bar.nextElementSibling;
        let activityContainer = null;
        if (sibling && sibling.classList.contains('mimo-activities')) {
            activityContainer = sibling;
        } else {
            activityContainer = bar.parentElement.querySelector('.mimo-activities');
        }

        if (!activityContainer) {
            return;
        }

        const sectionElement = activityContainer.closest('.section-item') || activityContainer;
        const wallState = getWallState(sectionElement);

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

                syncFilterState(wallState, filterState.activeTag, filterState.activeCompletion);
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

                syncFilterState(wallState, '', filterState.activeCompletion);
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
            } else {
                filterState.activeCompletion = '';
            }
            syncFilterState(wallState, filterState.activeTag, filterState.activeCompletion);

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

        // Listen for reactive completion state changes from the course editor watcher.
        document.addEventListener('mimo:completionchange', () => {
            if (statusRegion) {
                updateCompletionCounts(statusRegion, activityItems);
            }
        });
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
        const activityContainer = statusRegion.parentElement.querySelector('.mimo-activities');
        if (!activityContainer) {
            return;
        }

        const sectionElement = activityContainer.closest('.section-item') || activityContainer;
        const wallState = getWallState(sectionElement);

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
            } else {
                filterState.activeCompletion = '';
            }
            syncFilterState(wallState, filterState.activeTag, filterState.activeCompletion);

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

        // Listen for reactive completion state changes from the course editor watcher.
        document.addEventListener('mimo:completionchange', () => {
            updateCompletionCounts(statusRegion, activityItems);
        });
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Initialize all filter bars and completion status regions in the page.
 *
 * Scans for all elements with [data-region="mimo-filterbar"]
 * and initializes filtering functionality for each.
 *
 * Also initializes standalone completion status regions (without filter bar).
 *
 * Typically one filter bar per course section, but supports multiple.
 *
 * @returns {void}
 */
export const init = () => {
    // Register the one-shot firework click delegation (guarded internally).
    registerFireworkListener();

    // Initialize filter bars (which also handle their associated completion status regions).
    document
        .querySelectorAll('[data-region="mimo-filterbar"]')
        .forEach((bar) => initFilterBar(bar));

    // Initialize standalone completion status regions (when filtering is disabled).
    document
        .querySelectorAll('[data-region="completion-status"]')
        .forEach((statusRegion) => {
            // Skip if already initialized by a filter bar.
            const parent = statusRegion.parentElement;
            if (!parent || !parent.querySelector('[data-region="mimo-filterbar"]')) {
                initCompletionStatusOnly(statusRegion);
            }
        });
};