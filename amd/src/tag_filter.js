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
import {BaseComponent} from 'core/reactive';
import {getWallState} from 'format_mimo/local/wall_state/wall_state';
import {get_string as getString} from 'core/str';

/** Duration in milliseconds for height transition animation. */
const HEIGHT_TRANSITION_MS = 300;

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
 * @param {string} activeTag - Active tag id, or '' for no tag filter
 * @param {string} activeCompletion - 'true', 'false', or '' for no filter
 * @returns {number} Count of visible items after filtering
 */
const applyCombinedFilter = (items, activeTag, activeCompletion) => {
    let visibleCount = 0;
    items.forEach((item) => {
        let matchesTag = true;
        let matchesCompletion = true;

        // Check tag filter if active.
        if (activeTag) {
            const itemTag = item.dataset.tagid || '';
            matchesTag = (itemTag === activeTag);
        }

        // Check completion filter if active.
        if (activeCompletion) {
            // Done and overdue activities are excluded from completion filtering.
            if (item.dataset.done === 'true' || item.dataset.overdue === 'true') {
                matchesCompletion = false;
            } else {
                const itemCompleted = item.dataset.completed;
                // Only filter items that have completion tracking.
                if (itemCompleted !== undefined) {
                    matchesCompletion = (itemCompleted === activeCompletion);
                } else {
                    // Items without completion tracking don't match completion filter.
                    matchesCompletion = false;
                }
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
        // The data-filter-value attribute is the pill's identity: which completion value it filters for.
        const isActive = pill.dataset.filterValue === activeCompleted;
        pill.classList.toggle('is-active', isActive);
        pill.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
};

/**
 * Update completion counts displayed in the pills.
 *
 * @param {HTMLElement} statusRegion - Completion status region element
 * @param {HTMLElement[]} items - Array of activity items to count from
 * @param {string} activeTag - Active tag id, or '' (hidden items are skipped
 *                             only while a tag filter is active)
 * @returns {void}
 */
const updateCompletionCounts = (statusRegion, items, activeTag) => {
    let completedCount = 0;
    let incompleteCount = 0;
    let totalWithCompletion = 0;

    items.forEach((item) => {
        // Skip hidden items when a tag filter is active.
        if (activeTag && item.hidden) {
            return;
        }
        // Skip done activities — they don't count toward completion.
        if (item.dataset.done === 'true') {
            return;
        }
        // Skip overdue activities — they don't count toward "to do".
        if (item.dataset.overdue === 'true') {
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

    updatePillLabels(statusRegion, completedCount, incompleteCount);

    // Update the completion stars display.
    updateCompletionStars(statusRegion, completedCount, totalWithCompletion);
};

/**
 * Keep the pill aria-labels in sync with the visible counts so screen
 * readers announce how many activities each filter would show.
 *
 * @param {HTMLElement} statusRegion - Completion status region element
 * @param {number} completedCount - Number of completed activities
 * @param {number} incompleteCount - Number of incomplete activities
 * @returns {void}
 */
const updatePillLabels = (statusRegion, completedCount, incompleteCount) => {
    const completePill = statusRegion.querySelector('.completion-pill--complete');
    const incompletePill = statusRegion.querySelector('.completion-pill--incomplete');
    if (completePill) {
        getString('showcompletedactivities', 'format_mimo', completedCount).then((str) => {
            completePill.setAttribute('aria-label', str);
            return;
        }).catch(() => {
            // Keep the server-rendered label if string loading fails.
        });
    }
    if (incompletePill) {
        getString('showincompleteactivities', 'format_mimo', incompleteCount).then((str) => {
            incompletePill.setAttribute('aria-label', str);
            return;
        }).catch(() => {
            // Keep the server-rendered label if string loading fails.
        });
    }
};

/**
 * Update the completion pills group label to reflect the active tag filter,
 * e.g. "Filter activities in category 'Write' by status".
 *
 * @param {HTMLElement} statusRegion - Completion status region element
 * @param {string} tagName - Active tag name, or empty string for no tag filter
 * @returns {void}
 */
const updateCompletionGroupLabel = (statusRegion, tagName) => {
    const group = statusRegion.querySelector('.completion-status-pills');
    if (!group) {
        return;
    }
    const promise = tagName
        ? getString('completionfilterfortag', 'format_mimo', tagName)
        : getString('completionfilter', 'format_mimo');
    promise.then((str) => {
        group.setAttribute('aria-label', str);
        return;
    }).catch(() => {
        // Keep the previous label if string loading fails.
    });
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
 * Reactive component driving one filter bar and/or completion status region.
 *
 * The wall state reactive owns the filter state (filters.tags,
 * filters.completion). Click handlers only dispatch mutations; the
 * filters:updated watcher performs all DOM rendering idempotently. Activity
 * items are re-queried on every render so cmitem fragment reloads (done
 * toggles, visibility changes) never leave stale node references, and the
 * unfiltered order is restored from state.activityOrder.ids so drag-drop
 * reorders survive a filter round-trip.
 */
class FilterBar extends BaseComponent {

    /**
     * Component setup.
     *
     * @param {object} descriptor
     * @param {HTMLElement|null} descriptor.bar filter bar, or null in
     *     completion-status-only mode
     * @param {HTMLElement} descriptor.container the .mimo-activities container
     * @param {HTMLElement|null} descriptor.statusRegion completion status region
     */
    create(descriptor) {
        this.bar = descriptor.bar;
        this.container = descriptor.container;
        this.statusRegion = descriptor.statusRegion;
        /** @type {string} Last rendered tag id — render cache, never truth. */
        this.renderedTag = '';
    }

    getWatchers() {
        return [
            {watch: 'filters:updated', handler: this._filtersUpdated},
            {watch: 'completion:updated', handler: this._completionUpdated},
        ];
    }

    /**
     * Register click handlers and paint the initial completion counts.
     */
    stateReady() {
        if (this.bar) {
            // Buttons mirror the server-side tag list for this section.
            this.bar.querySelectorAll('[data-action="tag-filter"]').forEach((button) => {
                if (button.dataset.hasactivities === '1') {
                    button.disabled = false;
                    button.classList.remove('is-empty');
                }
            });
            this.addEventListener(this.bar, 'click', this._barClicked);
        }
        if (this.statusRegion) {
            this.addEventListener(this.statusRegion, 'click', this._pillClicked);
            // Initial update to show star if all activities are already complete.
            updateCompletionCounts(this.statusRegion, this._getItems(), '');
        }
    }

    /**
     * Query the current activity items (fresh on every render — cmitem
     * fragment reloads replace nodes).
     *
     * @returns {HTMLElement[]}
     */
    _getItems() {
        return Array.from(this.container.querySelectorAll('li[data-id]'));
    }

    /**
     * Toggle the tag filter from a filter bar click.
     *
     * @param {Event} event
     */
    _barClicked(event) {
        const button = event.target.closest('[data-action="tag-filter"]');
        if (!button || !this.bar.contains(button) || !button.dataset.tagid) {
            return;
        }
        event.preventDefault();
        const current = this.reactive.state.filters.tags[0] ?? '';
        const tagid = button.dataset.tagid;
        this.reactive.dispatch('setTagFilter', current === tagid ? [] : [tagid]);
    }

    /**
     * Toggle the completion filter from a pill click.
     *
     * @param {Event} event
     */
    _pillClicked(event) {
        const pill = event.target.closest('[data-action="completion-filter"]');
        if (!pill || !this.statusRegion.contains(pill)) {
            return;
        }
        event.preventDefault();
        const value = pill.dataset.filterValue;
        const current = this.reactive.state.filters.completion;
        this.reactive.dispatch('setCompletionFilter', current === value ? '' : value);
    }

    /**
     * Recount pills/star after a completion change (data attributes are
     * already updated by courseeditor_watcher).
     *
     * @param {object} detail Watcher event detail
     * @param {object} detail.state Full wall state
     */
    _completionUpdated({state}) {
        if (this.statusRegion) {
            updateCompletionCounts(this.statusRegion, this._getItems(),
                state.filters.tags[0] ?? '');
        }
    }

    /**
     * Render everything from the current filter state.
     *
     * @param {object} detail Watcher event detail
     * @param {object} detail.state Full wall state
     */
    _filtersUpdated({state}) {
        const activeTag = state.filters.tags[0] ?? '';
        const activeCompletion = state.filters.completion;
        const tagChanged = activeTag !== this.renderedTag;
        this.renderedTag = activeTag;

        // Reorder only on tag transitions — moving nodes on unrelated renders
        // would reset focus and CSS animations.
        if (tagChanged) {
            if (activeTag) {
                this._reorderByTag(activeTag);
            } else {
                this._restoreOrder(state.activityOrder.ids);
            }
        }

        const items = this._getItems();
        let visibleCount;
        animateContainerHeight(this.container, () => {
            if (activeTag || activeCompletion) {
                visibleCount = applyCombinedFilter(items, activeTag, activeCompletion);
            } else {
                clearFilterStyles(items);
                visibleCount = items.length;
            }
        });

        const activeButton = (activeTag && this.bar)
            ? this.bar.querySelector(
                `[data-action="tag-filter"][data-tagid="${CSS.escape(activeTag)}"]`)
            : null;

        if (this.bar) {
            updateButtons(this.bar, activeButton);
        }

        if (this.statusRegion) {
            updateCompletionPills(this.statusRegion, activeCompletion);
            const img = activeButton ? activeButton.querySelector('img') : null;
            updateActiveTagImage(this.statusRegion, img ? img.src : '');
            updateCompletionGroupLabel(this.statusRegion,
                activeButton ? activeButton.dataset.tagName || '' : '');
            updateCompletionCounts(this.statusRegion, items, activeTag);
            toggleNoActivitiesMessage(this.statusRegion, visibleCount === 0);
        }

        if (tagChanged) {
            announceFilterStatus(activeButton ? activeButton.dataset.tagName || '' : '',
                visibleCount, items.length);
        }
    }

    /**
     * Move activities matching the tag to the top (stable within groups).
     *
     * @param {string} tagid
     */
    _reorderByTag(tagid) {
        const matching = [];
        const remaining = [];
        this._getItems().forEach((item) => {
            if ((item.dataset.tagid || '') === tagid) {
                matching.push(item);
            } else {
                remaining.push(item);
            }
        });
        const fragment = document.createDocumentFragment();
        matching.concat(remaining).forEach((item) => fragment.appendChild(item));
        this.container.appendChild(fragment);
    }

    /**
     * Restore the unfiltered order from the wall state's activity order
     * (kept current by drag-drop reorders).
     *
     * @param {number[]} ids ordered cm ids
     */
    _restoreOrder(ids) {
        const byId = new Map(this._getItems().map((item) => [Number(item.dataset.id), item]));
        const fragment = document.createDocumentFragment();
        ids.forEach((id) => {
            const item = byId.get(id);
            if (item) {
                fragment.appendChild(item);
                byId.delete(id);
            }
        });
        // Items unknown to the stored order (e.g. freshly created) keep their position at the end.
        byId.forEach((item) => fragment.appendChild(item));
        this.container.appendChild(fragment);
    }
}

/**
 * Initialize all filter bars and completion status regions in the page.
 *
 * Creates one FilterBar component per [data-region="mimo-filterbar"] (which
 * also adopts the sibling completion status region) and one per standalone
 * completion status region (when filtering is disabled).
 *
 * @returns {void}
 */
export const init = () => {
    // Register the one-shot firework click delegation (guarded internally).
    registerFireworkListener();

    /**
     * Create a FilterBar component for one bar/status region pair.
     *
     * @param {HTMLElement|null} bar
     * @param {HTMLElement} container
     * @param {HTMLElement|null} statusRegion
     */
    const createComponent = (bar, container, statusRegion) => {
        try {
            if (!container.querySelector('li[data-id]')) {
                return;
            }
            const sectionElement = container.closest('.section-item') || container;
            new FilterBar({
                element: bar ?? statusRegion,
                reactive: getWallState(sectionElement),
                bar,
                container,
                statusRegion,
            });
        } catch (error) {
            Notification.exception(error);
        }
    };

    // Guard each element against double-initialization: the template {{#js}}
    // block runs once per section render, including re-renders of surviving
    // DOM nodes.
    document.querySelectorAll('[data-region="mimo-filterbar"]').forEach((bar) => {
        if (bar.dataset.mimoFilterInit) {
            return;
        }
        bar.dataset.mimoFilterInit = '1';
        const sibling = bar.nextElementSibling;
        const container = (sibling && sibling.classList.contains('mimo-activities'))
            ? sibling
            : bar.parentElement.querySelector('.mimo-activities');
        if (!container || !bar.querySelector('[data-action="tag-filter"]')) {
            return;
        }
        const statusRegion = bar.parentElement.querySelector('[data-region="completion-status"]');
        if (statusRegion) {
            statusRegion.dataset.mimoFilterInit = '1';
        }
        createComponent(bar, container, statusRegion);
    });

    // Standalone completion status regions (when filtering is disabled).
    document.querySelectorAll('[data-region="completion-status"]').forEach((statusRegion) => {
        if (statusRegion.dataset.mimoFilterInit) {
            return;
        }
        statusRegion.dataset.mimoFilterInit = '1';
        const container = statusRegion.parentElement
            ? statusRegion.parentElement.querySelector('.mimo-activities')
            : null;
        if (!container) {
            return;
        }
        createComponent(null, container, statusRegion);
    });
};