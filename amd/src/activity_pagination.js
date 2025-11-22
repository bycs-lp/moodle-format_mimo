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
     * Show activities for current page.
     */
    const showPage = () => {
        const itemsPerPage = getItemsPerPage();
        const startIndex = currentPage * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;

        // Re-query activity cards to get fresh list after reordering.
        const currentCards = Array.from(container.querySelectorAll('.col-12'));

        currentCards.forEach((card, index) => {
            if (index >= startIndex && index < endIndex) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });

        updateNavigationButtons();
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
        if (currentPage > 0) {
            currentPage--;
            showPage();
        }
    });

    document.getElementById('minimoodlewall-next')?.addEventListener('click', () => {
        if (currentPage < getTotalPages() - 1) {
            currentPage++;
            showPage();
        }
    });

    showPage();

    // Handle window resize.
    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            // Recalculate and stay on current page if possible.
            const totalPages = getTotalPages();
            if (currentPage >= totalPages) {
                currentPage = Math.max(0, totalPages - 1);
            }
            showPage();
        }, 250);
    });

    // Listen for drag-drop reordering to refresh pagination.
    window.addEventListener('minimoodlewall:reorder', () => {
        showPage();
    });
};
