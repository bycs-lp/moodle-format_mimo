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
 * Activity pagination for minimoodlewall format.
 *
 * @module     format_minimoodlewall/activity_pagination
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getCurrentCourseEditor} from 'core_courseformat/courseeditor';

/** Custom event name for filter activation/deactivation coordination. */
const FILTER_EVENT = 'minimoodlewall:filterchange';

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
const announcePaginationStatus = (page, totalPages, startIndex, endIndex, totalItems) => {
    const liveRegion = document.querySelector('[data-region="pagination-status"]');
    if (!liveRegion) {
        return;
    }

    // Convert to 1-indexed for user-friendly display
    const pageNumber = page + 1;
    const firstItem = startIndex + 1;
    const lastItem = Math.min(endIndex, totalItems);

    // Use Moodle string API when available, fallback to English
    liveRegion.textContent =
        `Page ${pageNumber} of ${totalPages}. Showing activities ${firstItem} to ${lastItem} of ${totalItems}.`;
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
 * - Responds to 'minimoodlewall:reorder' for drag-drop updates
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
    const container = document.querySelector('.minimoodlewall-activities');
    if (!container) {
        return;
    }

    const activityCards = Array.from(container.querySelectorAll('.col-12'));
    if (activityCards.length === 0) {
        return;
    }

    const navContainer = document.querySelector('.minimoodlewall-navigation');
    const prevBtn = document.getElementById('minimoodlewall-prev');
    const nextBtn = document.getElementById('minimoodlewall-next');

    let currentPage = 0;
    let isAnimating = false;
    let touchStartX = 0;
    let touchEndX = 0;
    let paginationEnabled = true;

    /**
     * Check if bulk mode is active via reactive state.
     *
     * Strategy:
     * 1. Try to read from reactive course editor state (Moodle 4.0+)
     * 2. Fallback to DOM check for visible bulk checkboxes
     *
     * Bulk mode requires all activities visible for checkbox access,
     * so pagination is disabled when bulk mode is active.
     *
     * @returns {boolean} True if bulk editing mode is currently active
     */
    const isBulkMode = () => {
        try {
            const reactiveEditor = getCurrentCourseEditor();
            if (reactiveEditor) {
                const bulkState = reactiveEditor.get('bulk');
                return bulkState && bulkState.enabled === true;
            }
        } catch (e) {
            // Fallback: check if bulk checkboxes are visible.
            // This is expected when reactive editor isn't available.
            const bulkSelect = document.querySelector('.bulkselect:not(.d-none)');
            return bulkSelect !== null;
        }
        return false;
    };

    /**
     * Show all activities and disable pagination.
     *
     * Called when:
     * - Filter is activated (all activities visible for filtering)
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
            card.style.transition = 'none';
            card.style.opacity = '1';
            card.style.transform = 'translateX(0)';
            card.style.display = 'block';
        });

        // Hide navigation controls.
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
     * Calculate total pages.
     * @returns {number}
     */
    const getTotalPages = () => {
        return Math.ceil(activityCards.length / getItemsPerPage());
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
        cloneCards.forEach((card, index) => {
            card.style.transition = 'none';
            card.style.transform = 'none';
            card.style.opacity = '1';
            if (index >= startIndex && index < endIndex) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });

        document.body.appendChild(clone);
        const height = clone.scrollHeight;
        document.body.removeChild(clone);
        return height;
    };

    /**
     * Show activities for current page without animation (for resizing/initial load).
     */
    const showPageDirect = () => {
        if (!paginationEnabled) {
            return;
        }

        const itemsPerPage = getItemsPerPage();
        const startIndex = currentPage * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;
        const currentCards = Array.from(container.querySelectorAll('.col-12'));

        currentCards.forEach((card, index) => {
            card.style.transition = 'none';
            card.style.opacity = '1';
            card.style.transform = 'translateX(0)';
            if (index >= startIndex && index < endIndex) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
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
        const currentCards = Array.from(container.querySelectorAll('.col-12'));

        // Identify old (currently visible) and new cards.
        const oldCards = currentCards.filter(card => card.style.display !== 'none');
        const newCards = currentCards.filter((card, index) => index >= startIndex && index < endIndex);

        const oldCardPositions = new Map();
        oldCards.forEach((card) => {
            oldCardPositions.set(card, card.getBoundingClientRect());
        });

        // Make new cards visible so we can measure their offset before sliding them in.
        newCards.forEach((card) => {
            card.style.display = 'block';
            card.style.transition = 'none';
            card.style.opacity = '1';
            card.style.transform = 'none';
        });

        // Determine how far below/above the container the next batch currently sits.
        let verticalOffset = 0;
        if (newCards.length) {
            const firstRect = newCards[0].getBoundingClientRect();
            // Get the computed padding-top of the container to align properly
            const containerPaddingTop = parseFloat(getComputedStyle(container).paddingTop) || 0;
            verticalOffset = firstRect.top - containerRect.top - containerPaddingTop;
        }

        const enteringX = direction === 'next' ? slideDistance : -slideDistance;
        const exitingX = -enteringX;

        newCards.forEach((card) => {
            card.style.transform = `translate(${enteringX}px, ${-verticalOffset}px)`;
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
                card.dataset.mmwOffsetX = offsetX.toString();
                card.dataset.mmwOffsetY = offsetY.toString();
                card.style.transition = 'none';
                card.style.transform = `translate(${-offsetX}px, ${-offsetY}px)`;
            } else {
                delete card.dataset.mmwOffsetX;
                delete card.dataset.mmwOffsetY;
            }
        });

        // Trigger reflow.
        void container.offsetHeight;

        // Start animation - move both simultaneously.
        requestAnimationFrame(() => {
            oldCards.forEach((card) => {
                card.style.transition = 'transform 0.4s ease-in-out, opacity 0.4s ease-in-out';
                const offsetX = parseFloat(card.dataset.mmwOffsetX || '0');
                const offsetY = parseFloat(card.dataset.mmwOffsetY || '0');
                const targetX = exitingX - offsetX;
                const targetY = -offsetY;
                card.style.transform = `translate(${targetX}px, ${targetY}px)`;
                card.style.opacity = '0.3';
            });

            newCards.forEach((card) => {
                card.style.transition = 'transform 0.4s ease-in-out, opacity 0.4s ease-in-out';
                card.style.transform = `translate(0px, ${-verticalOffset}px)`;
                card.style.opacity = '1';
            });
        });

        // After animation completes, hide old cards and reset visibility.
        setTimeout(() => {
            currentCards.forEach((card, index) => {
                card.style.transition = '';
                card.style.transform = '';
                card.style.opacity = '1';
                delete card.dataset.mmwOffsetX;
                delete card.dataset.mmwOffsetY;

                if (index < startIndex || index >= endIndex) {
                    card.style.display = 'none';
                } else {
                    card.style.display = 'block';
                }
            });

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
            if (currentPage === 0) {
                prevBtn.style.display = 'none';
            } else {
                prevBtn.style.display = '';
            }
            const disablePrev = (currentPage === 0) || !paginationEnabled;
            prevBtn.disabled = disablePrev;
            prevBtn.setAttribute('aria-disabled', disablePrev ? 'true' : 'false');
        }
        if (nextBtn) {
            if (currentPage >= totalPages - 1) {
                nextBtn.style.display = 'none';
            } else {
                nextBtn.style.display = '';
            }
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
            } else {
                navContainer.classList.add('is-visible');
                navContainer.dataset.hasNext = '1';
            }
        }

        // Announce current page status to screen readers
        if (paginationEnabled && totalPages > 1) {
            const currentCards = Array.from(container.querySelectorAll('.col-12'));
            const itemsPerPage = getItemsPerPage();
            const startIndex = currentPage * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            announcePaginationStatus(currentPage, totalPages, startIndex, endIndex, currentCards.length);
        }
    };

    /**
     * Create navigation controls.
     */
    const createNavigation = () => {
        // Check if navigation already exists in template.
        const existingNav = document.querySelector('.minimoodlewall-navigation');
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

    // React when the filter bar toggles a filter on/off.
    document.addEventListener(FILTER_EVENT, (event) => {
        const filterActive = !!(event && event.detail && event.detail.active);
        if (filterActive) {
            paginationEnabled = false;
            showAllActivities();
        } else {
            paginationEnabled = true;
            enablePagination();
        }
    });

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

    // Watch for bulk mode changes via reactive state.
    try {
        const reactiveEditor = getCurrentCourseEditor();
        if (reactiveEditor) {
            // Add state watcher for bulk mode.
            reactiveEditor.addStateWatch('bulk.enabled', (state) => {
                const bulkEnabled = state?.bulk?.enabled;
                if (bulkEnabled && paginationEnabled) {
                    // Bulk mode activated - disable pagination.
                    paginationEnabled = false;
                    showAllActivities();
                } else if (!bulkEnabled && !paginationEnabled) {
                    // Bulk mode deactivated - enable pagination.
                    paginationEnabled = true;
                    currentPage = 0;
                    enablePagination();
                }
            });
        }
    } catch (e) {
        // Fallback: watch for class changes on bulkselect elements.
        // This is expected when reactive editor state watchers aren't available.
        const observer = new MutationObserver(() => {
            const bulkNow = isBulkMode();
            if (bulkNow && paginationEnabled) {
                paginationEnabled = false;
                showAllActivities();
            } else if (!bulkNow && !paginationEnabled) {
                paginationEnabled = true;
                currentPage = 0;
                enablePagination();
            }
        });

        observer.observe(document.body, {
            attributes: true,
            attributeFilter: ['class'],
            subtree: true,
            childList: true,
        });
    }

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

    // Listen for drag-drop reordering to refresh pagination.
    window.addEventListener('minimoodlewall:reorder', () => {
        if (!paginationEnabled) {
            return;
        }
        isAnimating = false;
        showPageDirect();
    });
};
