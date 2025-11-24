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

const FILTER_EVENT = 'minimoodlewall:filterchange';

export const init = () => {
    const container = document.querySelector('.minimoodlewall-activities');
    if (!container) {
        return;
    }

    const activityCards = Array.from(container.querySelectorAll('.col-12'));
    if (activityCards.length === 0) {
        return;
    }

    let currentPage = 0;
    let isAnimating = false;
    let touchStartX = 0;
    let touchEndX = 0;
    let paginationEnabled = true;
    const animationDuration = 400; // Duration (ms) for slide and height transitions.

    /**
     * Check if bulk mode is active via reactive state.
     * @returns {boolean}
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
            const bulkSelect = document.querySelector('.bulkselect:not(.d-none)');
            return bulkSelect !== null;
        }
        return false;
    };

    /**
     * Show all activities (disable pagination).
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
        const navContainer = document.querySelector('.minimoodlewall-navigation');
        if (navContainer) {
            navContainer.style.display = 'none';
        }
    };

    /**
     * Enable pagination and show current page.
     */
    const enablePagination = () => {
        const navContainer = document.querySelector('.minimoodlewall-navigation');
        if (navContainer) {
            navContainer.style.display = '';
        }
        showPageDirect();
        updateNavigationButtons();
    };

    /**
     * Get items per page based on screen size.
     * @returns {number}
     */
    const getItemsPerPage = () => {
        const width = window.innerWidth;
        if (width >= 1200) { // XL - 4 columns.
            return 8;
        } else if (width >= 992) { // LG - 4 columns.
            return 8;
        } else if (width >= 768) { // MD - 3 columns.
            return 6;
        } else if (width >= 576) { // SM - 2 columns.
            return 4;
        } else { // XS - 1 column.
            return 3;
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
     * @param {number} startIndex
     * @param {number} endIndex
     * @returns {number}
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
     * @param {string} direction - 'next' or 'prev' for animation direction
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
            }, animationDuration + 200);

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

        // Make new cards visible so we can measure their offset before sliding them in.
        newCards.forEach((card) => {
            card.style.display = 'block';
            card.style.transition = 'none';
            card.style.opacity = '1';
            card.style.transform = 'none';
            card.style.zIndex = '10';
        });

        // Determine how far below the container the next batch currently sits and shift it up.
        let verticalOffset = 0;
        if (newCards.length) {
            const firstRect = newCards[0].getBoundingClientRect();
            verticalOffset = firstRect.top - containerRect.top;
        }

        const enteringX = direction === 'next' ? slideDistance : -slideDistance;
        const exitingX = direction === 'next' ? -slideDistance : slideDistance;

        newCards.forEach((card) => {
            card.style.transform = `translate(${enteringX}px, ${-verticalOffset}px)`;
        });

        // Keep old cards at their current position.
        oldCards.forEach((card) => {
            card.style.zIndex = '1';
        });

        // Trigger reflow.
        void container.offsetHeight;

        // Start animation - move both simultaneously.
        requestAnimationFrame(() => {
            oldCards.forEach((card) => {
                card.style.transition = 'transform 0.4s ease-in-out, opacity 0.4s ease-in-out';
                card.style.transform = `translateX(${exitingX}px)`;
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
                card.style.zIndex = '';
                card.style.opacity = '1';

                if (index < startIndex || index >= endIndex) {
                    card.style.display = 'none';
                } else {
                    card.style.display = 'block';
                }
            });

            cardsReset = true;
            finalizeAnimation();
        }, animationDuration);
    };

    /**
     * Update navigation button states.
     */
    const updateNavigationButtons = () => {
        const totalPages = getTotalPages();
        const prevBtn = document.getElementById('minimoodlewall-prev');
        const nextBtn = document.getElementById('minimoodlewall-next');

        if (prevBtn) {
            if (currentPage === 0) {
                prevBtn.style.display = 'none';
            } else {
                prevBtn.style.display = '';
            }
        }
        if (nextBtn) {
            if (currentPage >= totalPages - 1) {
                nextBtn.style.display = 'none';
            } else {
                nextBtn.style.display = '';
            }
        }

        // Hide navigation if only one page.
        const navContainer = document.querySelector('.minimoodlewall-navigation');
        if (navContainer) {
            if (totalPages <= 1) {
                navContainer.classList.remove('is-visible');
            } else {
                navContainer.classList.add('is-visible');
            }
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
    document.getElementById('minimoodlewall-prev')?.addEventListener('click', () => {
        if (currentPage > 0 && !isAnimating) {
            currentPage--;
            showPage('prev');
        }
    });

    document.getElementById('minimoodlewall-next')?.addEventListener('click', () => {
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
     * Handle swipe gesture.
     */
    const handleSwipe = () => {
        const swipeThreshold = 50; // Minimum distance for a swipe.
        const diff = touchStartX - touchEndX;

        if (Math.abs(diff) < swipeThreshold) {
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
