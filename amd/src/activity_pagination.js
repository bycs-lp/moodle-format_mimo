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
 * Activity pagination for mimo format.
 *
 * @module     format_mimo/activity_pagination
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {BaseComponent} from 'core/reactive';
import {getWallState} from 'format_mimo/local/wall_state/wall_state';
import {get_string as getString} from 'core/str';
import Pending from 'core/pending';

// Animation and timing constants.
/** Duration in milliseconds for slide and height transition animations. */
const ANIMATION_DURATION_MS = 400;
/** Additional buffer time in milliseconds for animation fallback timeout. */
const ANIMATION_FALLBACK_BUFFER_MS = 200;
/** Minimum horizontal distance in pixels required to register a swipe gesture. */
const SWIPE_THRESHOLD_PX = 50;

// Responsive breakpoints and items per page (matching Bootstrap grid).
/** Extra large screens breakpoint (≥1200px). */
const BREAKPOINT_XL = 1200;
/** Large screens breakpoint (≥992px). */
const BREAKPOINT_LG = 992;
/** Medium screens breakpoint (≥768px). */
const BREAKPOINT_MD = 768;
/** Small screens breakpoint (≥576px). */
const BREAKPOINT_SM = 576;

/** Items per page for XL/LG screens (4 columns × 2 rows). */
const ITEMS_PER_PAGE_XL = 8;
/** Items per page for MD screens (3 columns × 2 rows). */
const ITEMS_PER_PAGE_MD = 6;
/** Items per page for SM screens (2 columns × 2 rows). */
const ITEMS_PER_PAGE_SM = 4;
/** Items per page for XS screens (1 column × 3 rows). */
const ITEMS_PER_PAGE_XS = 3;

/**
 * Announce pagination status to screen readers via live region.
 *
 * Accessibility:
 * - Finds sr-only live region with role="status" aria-live="polite"
 * - Announces current page, total pages, and visible items range
 * - Screen readers will speak the message without moving focus
 * - Uses polite live region so announcement waits for user to pause
 *
 * @param {number} page - Current page number (0-indexed)
 * @param {number} totalPages - Total number of pages
 * @param {number} startIndex - First visible activity index (0-indexed)
 * @param {number} endIndex - Last visible activity index (0-indexed)
 * @param {number} totalItems - Total number of activities
 * @returns {void}
 */
const announcePaginationStatus = async(page, totalPages, startIndex, endIndex, totalItems) => {
    const liveRegion = document.querySelector('[data-region="pagination-status"]');
    if (!liveRegion) {
        return;
    }

    // Convert to 1-indexed for user-friendly display
    const pageNumber = page + 1;
    const firstItem = startIndex + 1;
    const lastItem = Math.min(endIndex, totalItems);

    liveRegion.textContent = await getString('aria_pagination_status', 'format_mimo', {
        page: pageNumber,
        totalpages: totalPages,
        first: firstItem,
        last: lastItem,
        total: totalItems,
    });
};

/**
 * Initialize activity pagination with carousel animations.
 *
 * State Management:
 * - Maintains current page index and animation state
 * - Tracks pagination enabled/disabled based on filter and bulk mode states
 * - Monitors touch gesture coordinates for swipe navigation
 *
 * Event Coordination:
 * - Listens to FILTER_EVENT to disable pagination when filters are active
 * - Watches reactive course editor for bulk mode changes
 * - Watches wall state reactive for filter, bulk, and reorder changes
 * - Handles window resize to recalculate page layout
 *
 * Animation Strategy:
 * - Simultaneous height and slide transitions (see ANIMATION_DURATION_MS constant)
 * - Pre-measures target height to prevent layout jumps
 * - Slides old cards out while new cards slide in
 * - Fallback timeout ensures completion if transitionend fails
 *
 * @returns {void}
 */
export const init = () => {
    const container = document.querySelector('.mimo-activities');
    if (!container) {
        return;
    }

    const allActivityCards = Array.from(container.querySelectorAll('.col-12'));
    if (allActivityCards.length === 0) {
        return;
    }

    const navContainer = document.querySelector('.mimo-navigation');
    const prevBtn = document.getElementById('mimo-prev');
    const nextBtn = document.getElementById('mimo-next');

    let currentPage = 0;
    let isAnimating = false;
    let touchStartX = 0;
    let touchEndX = 0;
    let paginationEnabled = true;
    let filterActive = false;

    /**
     * Get the list of visible activity cards (not hidden by filters).
     *
     * When filtering is active, returns only cards without the hidden attribute.
     * When no filter is active, returns all cards.
     *
     * @returns {HTMLElement[]} Array of visible activity card elements
     */
    const getVisibleCards = () => {
        if (!filterActive) {
            return Array.from(container.querySelectorAll('.col-12'));
        }
        // Return cards that are not hidden by filter (no hidden attribute).
        return Array.from(container.querySelectorAll('.col-12:not([hidden])'));
    };

    /**
     * Show all activities and disable pagination (for bulk mode).
     *
     * Called when:
     * - Bulk mode is enabled (all checkboxes need to be accessible)
     *
     * Side effects:
     * - Removes all pagination-related styles
     * - Hides navigation controls
     * - Makes all activity cards visible
     * - Disables navigation buttons
     *
     * @returns {void}
     */
    const showAllActivities = () => {
        const currentCards = Array.from(container.querySelectorAll('.col-12'));
        currentCards.forEach((card) => {
            card.style.transition = '';
            card.style.opacity = '';
            card.style.transform = '';
            card.style.translate = '';
            // Only show cards that aren't filtered out.
            if (!card.hidden) {
                card.style.display = 'block';
            }
        });

        // Hide navigation controls and remove pagination class.
        container.classList.remove('pagination-active');
        if (navContainer) {
            navContainer.classList.remove('is-visible');
            navContainer.dataset.hasNext = '0';
        }
        if (prevBtn) {
            prevBtn.disabled = true;
            prevBtn.setAttribute('aria-disabled', 'true');
        }
        if (nextBtn) {
            nextBtn.disabled = true;
            nextBtn.setAttribute('aria-disabled', 'true');
        }
    };

    /**
     * Recalculate pagination when filter changes.
     *
     * Called when filtering is activated or deactivated.
     * Resets to page 0 and shows the first page of visible items.
     *
     * @returns {void}
     */
    const recalculateForFilter = () => {
        currentPage = 0;
        showPageDirect();
        updateNavigationButtons();
    };

    /**
     * Enable pagination and show current page.
     */
    const enablePagination = () => {
        showPageDirect();
        updateNavigationButtons();
    };

    /**
     * Get items per page based on screen size and grid layout.
     *
     * Responsive breakpoints:
     * - XL (≥1200px): 4 columns × 2 rows = 8 items
     * - LG (≥992px):  4 columns × 2 rows = 8 items
     * - MD (≥768px):  3 columns × 2 rows = 6 items
     * - SM (≥576px):  2 columns × 2 rows = 4 items
     * - XS (<576px):  1 column  × 3 rows = 3 items
     *
     * These values are defined as constants (BREAKPOINT_* and ITEMS_PER_PAGE_*)
     * and match the Bootstrap grid breakpoints and column counts defined in
     * the Mustache templates.
     *
     * @returns {number} Number of activity cards to show per page
     */
    const getItemsPerPage = () => {
        const width = window.innerWidth;
        if (width >= BREAKPOINT_XL) { // XL - 4 columns.
            return ITEMS_PER_PAGE_XL;
        } else if (width >= BREAKPOINT_LG) { // LG - 4 columns.
            return ITEMS_PER_PAGE_XL;
        } else if (width >= BREAKPOINT_MD) { // MD - 3 columns.
            return ITEMS_PER_PAGE_MD;
        } else if (width >= BREAKPOINT_SM) { // SM - 2 columns.
            return ITEMS_PER_PAGE_SM;
        } else { // XS - 1 column.
            return ITEMS_PER_PAGE_XS;
        }
    };

    /**
     * Calculate total pages based on visible cards.
     * @returns {number}
     */
    const getTotalPages = () => {
        const visibleCards = getVisibleCards();
        return Math.ceil(visibleCards.length / getItemsPerPage());
    };

    /**
     * Measure the height the container needs for a given page without affecting the live DOM.
     *
     * Technique:
     * - Clones the activity container (deep copy)
     * - Positions clone off-screen at -9999px
     * - Applies same width but auto height
     * - Shows only cards for target page, hides others
     * - Measures scrollHeight of clone
     * - Removes clone from DOM
     *
     * When filtering is active, indices refer to visible cards only.
     *
     * This prevents layout thrashing and provides accurate height before animation starts.
     *
     * @param {number} startIndex - First card index for the page
     * @param {number} endIndex - One past the last card index for the page
     * @returns {number} Height in pixels needed for this page
     */
    const measurePageHeight = (startIndex, endIndex) => {
        const containerRect = container.getBoundingClientRect();
        const clone = container.cloneNode(true);
        clone.style.position = 'absolute';
        clone.style.visibility = 'hidden';
        clone.style.pointerEvents = 'none';
        clone.style.left = '-9999px';
        clone.style.width = `${containerRect.width}px`;
        clone.style.height = 'auto';
        clone.style.overflow = 'visible';
        clone.style.transform = 'none';
        clone.style.transition = 'none';

        const cloneCards = Array.from(clone.querySelectorAll('.col-12'));

        if (filterActive) {
            // Filter-aware: use visible card indices.
            let visibleIndex = 0;
            cloneCards.forEach((card) => {
                card.style.transition = 'none';
                card.style.translate = 'none';
                card.style.opacity = '1';

                if (card.hidden) {
                    card.style.display = 'none';
                    return;
                }

                if (visibleIndex >= startIndex && visibleIndex < endIndex) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
                visibleIndex++;
            });
        } else {
            cloneCards.forEach((card, index) => {
                card.style.transition = 'none';
                card.style.translate = 'none';
                card.style.opacity = '1';
                if (index >= startIndex && index < endIndex) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        document.body.appendChild(clone);
        const height = clone.scrollHeight;
        document.body.removeChild(clone);
        return height;
    };

    /**
     * Show activities for current page without animation (for resizing/initial load).
     *
     * When filtering is active, paginates only the visible (non-hidden) cards.
     * Hidden cards remain hidden regardless of pagination.
     */
    const showPageDirect = () => {
        if (!paginationEnabled) {
            return;
        }

        const itemsPerPage = getItemsPerPage();
        const startIndex = currentPage * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;

        if (filterActive) {
            // When filter is active, paginate only visible cards.
            const allCards = Array.from(container.querySelectorAll('.col-12'));
            let visibleIndex = 0;

            allCards.forEach((card) => {
                card.style.transition = '';
                card.style.opacity = '';
                card.style.transform = '';
                card.style.translate = '';

                // Skip cards hidden by filter.
                if (card.hidden) {
                    card.style.display = 'none';
                    return;
                }

                // Paginate visible cards.
                if (visibleIndex >= startIndex && visibleIndex < endIndex) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
                visibleIndex++;
            });
        } else {
            // Normal pagination - all cards.
            const currentCards = Array.from(container.querySelectorAll('.col-12'));
            currentCards.forEach((card, index) => {
                card.style.transition = '';
                card.style.opacity = '';
                card.style.transform = '';
                card.style.translate = '';
                if (index >= startIndex && index < endIndex) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
        updateNavigationButtons();
    };

    /**
     * Show activities for current page with carousel animation.
     *
     * Animation phases:
     * 1. Lock container overflow and set explicit height
     * 2. Measure target height for new page (prevents content jump)
     * 3. Start height transition to target height
     * 4. Position new cards off-screen in slide direction
     * 5. Calculate vertical offset to align new cards with container top
     * 6. Simultaneously slide old cards out and new cards in
     * 7. After animation completes, reset all styles and show/hide cards
     *
     * Animation coordination:
     * - Height transition runs independently with transitionend listener
     * - Card slide animations run on a fixed 400ms timeout
     * - finalizeAnimation() only proceeds when both phases complete
     * - Fallback timeout ensures cleanup even if transitionend doesn't fire
     *
     * Note: Re-queries DOM for fresh card list to handle drag-drop reordering
     *
     * @param {string} direction - 'next' or 'prev' determines slide direction (left/right)
     * @returns {void}
     */
    const showPage = (direction = 'next') => {
        if (isAnimating || !paginationEnabled) {
            return;
        }
        isAnimating = true;
        const pending = new Pending('format_mimo/activity_pagination:showPage');

        const itemsPerPage = getItemsPerPage();
        const startIndex = currentPage * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;

        const containerRect = container.getBoundingClientRect();
        container.style.overflow = 'hidden';
        container.style.height = `${containerRect.height}px`;
        const slideDistance = containerRect.width;
        const initialHeight = containerRect.height;

        let targetHeight = measurePageHeight(startIndex, endIndex);
        if (!Number.isFinite(targetHeight) || targetHeight <= 0) {
            targetHeight = initialHeight;
        }

        let heightTransitionDone = Math.abs(targetHeight - initialHeight) < 1;
        let cardsReset = false;
        let heightFallbackId;

        const finalizeAnimation = () => {
            if (!heightTransitionDone || !cardsReset) {
                return;
            }
            container.style.height = '';
            container.style.overflow = '';
            isAnimating = false;
            updateNavigationButtons();
            pending.resolve();
        };

        const startHeightTransition = () => {
            if (heightTransitionDone) {
                return;
            }

            const onHeightTransitionEnd = (event) => {
                if (event.target !== container || event.propertyName !== 'height') {
                    return;
                }
                container.removeEventListener('transitionend', onHeightTransitionEnd);
                clearTimeout(heightFallbackId);
                heightTransitionDone = true;
                finalizeAnimation();
            };

            container.addEventListener('transitionend', onHeightTransitionEnd);
            heightFallbackId = setTimeout(() => {
                container.removeEventListener('transitionend', onHeightTransitionEnd);
                heightTransitionDone = true;
                finalizeAnimation();
            }, ANIMATION_DURATION_MS + ANIMATION_FALLBACK_BUFFER_MS);

            requestAnimationFrame(() => {
                container.style.height = `${targetHeight}px`;
            });
        };

        startHeightTransition();

        // Re-query activity cards to get fresh list after reordering.
        const allCards = Array.from(container.querySelectorAll('.col-12'));

        // Build list of visible cards (for filter-aware pagination).
        let visibleCardsList = [];
        if (filterActive) {
            visibleCardsList = allCards.filter(card => !card.hidden);
        } else {
            visibleCardsList = allCards;
        }

        // Identify old (currently displayed) and new cards based on visible card indices.
        const oldCards = visibleCardsList.filter(card => card.style.display !== 'none');
        const newCards = visibleCardsList.filter((card, index) => index >= startIndex && index < endIndex);

        const oldCardPositions = new Map();
        oldCards.forEach((card) => {
            oldCardPositions.set(card, card.getBoundingClientRect());
        });

        // Make new cards visible so we can measure their offset before sliding them in.
        newCards.forEach((card) => {
            card.style.display = 'block';
            card.style.transition = 'none';
            card.style.opacity = '1';
            card.style.translate = 'none';
        });

        // Determine how far below/above the container the next batch currently sits.
        // Temporarily strip CSS rotation so getBoundingClientRect returns the pure
        // layout position.  Pinnwand/paper cards are rotated via CSS transform which
        // shifts the visual bounding box by a few pixels; without this correction the
        // animated position would be slightly off and snap at the end of the transition.
        let verticalOffset = 0;
        if (newCards.length) {
            const firstCard = newCards[0];
            firstCard.style.transform = 'none';
            const firstRect = firstCard.getBoundingClientRect();
            firstCard.style.transform = '';
            // Get the computed padding-top of the container to align properly
            const containerPaddingTop = parseFloat(getComputedStyle(container).paddingTop) || 0;
            verticalOffset = firstRect.top - containerRect.top - containerPaddingTop;
        }

        const enteringX = direction === 'next' ? slideDistance : -slideDistance;
        const exitingX = -enteringX;

        newCards.forEach((card) => {
            card.style.translate = `${enteringX}px ${-verticalOffset}px`;
        });

        // Keep old cards at their current position.
        oldCards.forEach((card) => {
            const beforeRect = oldCardPositions.get(card);
            if (!beforeRect) {
                return;
            }
            const afterRect = card.getBoundingClientRect();
            const offsetX = afterRect.left - beforeRect.left;
            const offsetY = afterRect.top - beforeRect.top;
            const needsOffset = Math.abs(offsetX) > 1 || Math.abs(offsetY) > 1;
            if (needsOffset) {
                card.dataset.mimoOffsetX = offsetX.toString();
                card.dataset.mimoOffsetY = offsetY.toString();
                card.style.transition = 'none';
                card.style.translate = `${-offsetX}px ${-offsetY}px`;
            } else {
                delete card.dataset.mimoOffsetX;
                delete card.dataset.mimoOffsetY;
            }
        });

        // Trigger reflow.
        void container.offsetHeight;

        // Start animation - move both simultaneously.
        requestAnimationFrame(() => {
            oldCards.forEach((card) => {
                card.style.transition = 'translate 0.4s ease-in-out, opacity 0.4s ease-in-out';
                const offsetX = parseFloat(card.dataset.mimoOffsetX || '0');
                const offsetY = parseFloat(card.dataset.mimoOffsetY || '0');
                const targetX = exitingX - offsetX;
                const targetY = -offsetY;
                card.style.translate = `${targetX}px ${targetY}px`;
                card.style.opacity = '0.3';
            });

            newCards.forEach((card) => {
                card.style.transition = 'translate 0.4s ease-in-out, opacity 0.4s ease-in-out';
                card.style.translate = `0px ${-verticalOffset}px`;
                card.style.opacity = '1';
            });
        });

        // After animation completes, hide old cards and reset visibility.
        setTimeout(() => {
            if (filterActive) {
                // Filter-aware: paginate only visible cards.
                let visibleIndex = 0;
                allCards.forEach((card) => {
                    card.style.transition = '';
                    card.style.transform = '';
                    card.style.translate = '';
                    card.style.opacity = '1';
                    delete card.dataset.mimoOffsetX;
                    delete card.dataset.mimoOffsetY;

                    if (card.hidden) {
                        card.style.display = 'none';
                        return;
                    }

                    if (visibleIndex < startIndex || visibleIndex >= endIndex) {
                        card.style.display = 'none';
                    } else {
                        card.style.display = 'block';
                    }
                    visibleIndex++;
                });
            } else {
                // Normal pagination.
                allCards.forEach((card, index) => {
                    card.style.transition = '';
                    card.style.transform = '';
                    card.style.translate = '';
                    card.style.opacity = '1';
                    delete card.dataset.mimoOffsetX;
                    delete card.dataset.mimoOffsetY;

                    if (index < startIndex || index >= endIndex) {
                        card.style.display = 'none';
                    } else {
                        card.style.display = 'block';
                    }
                });
            }

            cardsReset = true;
            finalizeAnimation();
        }, ANIMATION_DURATION_MS);
    };

    /**
     * Update navigation button states.
     */
    const updateNavigationButtons = () => {
        const totalPages = getTotalPages();

        if (prevBtn) {
            const disablePrev = (currentPage === 0) || !paginationEnabled;
            prevBtn.disabled = disablePrev;
            prevBtn.setAttribute('aria-disabled', disablePrev ? 'true' : 'false');
        }
        if (nextBtn) {
            const disableNext = (currentPage >= totalPages - 1) || !paginationEnabled;
            nextBtn.disabled = disableNext;
            nextBtn.setAttribute('aria-disabled', disableNext ? 'true' : 'false');
        }

        // Hide navigation if only one page.
        if (navContainer) {
            navContainer.classList.remove('is-booting');
            if (!paginationEnabled || totalPages <= 1) {
                navContainer.classList.remove('is-visible');
                navContainer.dataset.hasNext = '0';
                container.classList.remove('pagination-active');
            } else {
                navContainer.classList.add('is-visible');
                navContainer.dataset.hasNext = '1';
                container.classList.add('pagination-active');
            }
        }

        // Announce current page status to screen readers
        if (paginationEnabled && totalPages > 1) {
            const visibleCards = getVisibleCards();
            const itemsPerPage = getItemsPerPage();
            const startIndex = currentPage * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            announcePaginationStatus(currentPage, totalPages, startIndex, endIndex, visibleCards.length);
        }
    };

    /**
     * Create navigation controls.
     */
    const createNavigation = () => {
        // Check if navigation already exists in template.
        const existingNav = document.querySelector('.mimo-navigation');
        if (existingNav) {
            return; // Navigation buttons are in template.
        }
    };

    // Initialize.
    createNavigation();

    // Add event listeners to navigation buttons.
    prevBtn?.addEventListener('click', () => {
        if (currentPage > 0 && !isAnimating) {
            currentPage--;
            showPage('prev');
        }
    });

    nextBtn?.addEventListener('click', () => {
        if (currentPage < getTotalPages() - 1 && !isAnimating) {
            currentPage++;
            showPage('next');
        }
    });

    // --- Reactive wall state integration ---
    // Replace DOM event listeners with reactive watchers via a thin BaseComponent.
    const sectionElement = container.closest('.section-item') || container.closest('[data-sectionid]') || container;
    const wallState = getWallState(sectionElement);

    /**
     * Thin BaseComponent that watches wall state and delegates to closure functions.
     */
    class PaginationWatcher extends BaseComponent {
        // eslint-disable-next-line no-empty-function
        create() {}

        getWatchers() {
            return [
                {watch: 'filters:updated', handler: this._filtersUpdated},
                {watch: 'bulk:updated', handler: this._bulkUpdated},
                {watch: 'activityOrder:updated', handler: this._orderUpdated},
            ];
        }

        /**
         * React when filter state changes.
         *
         * @param {object} detail Watcher event detail
         * @param {object} detail.state Full wall state
         */
        _filtersUpdated({state}) {
            const isActive = state.filters.tags.length > 0 || state.filters.completion !== '';
            filterActive = isActive;
            // Defer so tag_filter DOM updates (hidden attrs) are applied first.
            requestAnimationFrame(() => {
                recalculateForFilter();
            });
        }

        /**
         * React when bulk editing mode changes.
         *
         * @param {object} detail Watcher event detail
         * @param {object} detail.state Full wall state
         */
        _bulkUpdated({state}) {
            const bulkEnabled = !!state.bulk.enabled;
            if (bulkEnabled && paginationEnabled) {
                paginationEnabled = false;
                showAllActivities();
            } else if (!bulkEnabled && !paginationEnabled) {
                paginationEnabled = true;
                currentPage = 0;
                enablePagination();
            }
        }

        /**
         * React when activity order changes (drag-drop reorder).
         */
        _orderUpdated() {
            if (!paginationEnabled) {
                return;
            }
            isAnimating = false;
            showPageDirect();
        }
    }

    // Create the watcher component bound to the wall state reactive.
    new PaginationWatcher({element: container, reactive: wallState});

    // Touch gesture support for swipe navigation.
    container.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
    }, {passive: true});

    container.addEventListener('touchend', (e) => {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    }, {passive: true});

    /**
     * Handle swipe gesture for touch navigation.
     *
     * Gesture detection:
     * - Calculates horizontal distance between touchstart and touchend
     * - Requires minimum threshold movement (see SWIPE_THRESHOLD_PX constant)
     * - Positive difference = swipe left (next page)
     * - Negative difference = swipe right (previous page)
     *
     * Only processes swipe if:
     * - Movement exceeds threshold
     * - Target page exists
     * - Not currently animating
     *
     * @returns {void}
     */
    const handleSwipe = () => {
        const diff = touchStartX - touchEndX;

        if (Math.abs(diff) < SWIPE_THRESHOLD_PX) {
            return; // Not a swipe, ignore.
        }

        if (diff > 0) {
            // Swiped left - go to next page.
            if (currentPage < getTotalPages() - 1 && !isAnimating) {
                currentPage++;
                showPage('next');
            }
        } else {
            // Swiped right - go to previous page.
            if (currentPage > 0 && !isAnimating) {
                currentPage--;
                showPage('prev');
            }
        }
    };

    // Initial setup - start with pagination enabled.
    paginationEnabled = true;
    const itemsPerPage = getItemsPerPage();
    const startIndex = 0;
    const endIndex = startIndex + itemsPerPage;
    const currentCards = Array.from(container.querySelectorAll('.col-12'));
    currentCards.forEach((card, index) => {
        if (index >= startIndex && index < endIndex) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
    updateNavigationButtons();

    // Handle window resize.
    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            if (!paginationEnabled) {
                return;
            }
            // Recalculate and stay on current page if possible.
            const totalPages = getTotalPages();
            if (currentPage >= totalPages) {
                currentPage = Math.max(0, totalPages - 1);
            }
            showPageDirect();
        }, 250);
    });

};
