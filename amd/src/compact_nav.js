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
 * Compact secondary navigation — clones items from the hidden secondary nav bar
 * into a dropdown in the header actions area.
 *
 * @module     format_mimo/compact_nav
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Collect nav items from the secondary navigation bar (which is hidden via CSS).
 *
 * Reads both the visible nav links and any overflow items inside the "More" dropdown.
 *
 * @returns {Array<{text: string, href: string, isActive: boolean}>}
 */
const collectNavItems = () => {
    const items = [];
    const navBar = document.querySelector('.secondary-navigation');
    if (!navBar) {
        return items;
    }

    // Visible nav links (excluding the "More" dropdown toggle).
    navBar.querySelectorAll(':scope .nav > li:not(.dropdownmoremenu) > a.nav-link').forEach(link => {
        const text = link.textContent.trim();
        const href = link.getAttribute('href');
        if (text && href && href !== '#') {
            items.push({text, href, isActive: link.classList.contains('active')});
        }
    });

    // Overflow items inside the "More" dropdown.
    navBar.querySelectorAll(':scope .dropdownmoremenu .dropdown-menu a.dropdown-item').forEach(link => {
        const text = link.textContent.trim();
        const href = link.getAttribute('href');
        if (text && href && href !== '#') {
            items.push({text, href, isActive: link.classList.contains('active')});
        }
    });

    return items;
};

/**
 * Initialize the compact navigation dropdown.
 *
 * Reads items from the hidden secondary nav bar, populates the header dropdown,
 * and removes the dropdown entirely if no items are found.
 */
export const init = () => {
    const dropdown = document.querySelector('[data-region="mimo-secondarynav-dropdown"]');
    if (!dropdown) {
        return;
    }

    const items = collectNavItems();

    if (items.length === 0) {
        // No nav items — remove the entire dropdown wrapper.
        const wrapper = dropdown.closest('.mimo-compact-nav');
        if (wrapper) {
            wrapper.remove();
        }
        return;
    }

    // Build dropdown items.
    const fragment = document.createDocumentFragment();
    items.forEach(item => {
        const li = document.createElement('li');
        const a = document.createElement('a');
        a.className = 'dropdown-item' + (item.isActive ? ' active' : '');
        a.href = item.href;
        a.textContent = item.text;
        if (item.isActive) {
            a.setAttribute('aria-current', 'true');
        }
        li.appendChild(a);
        fragment.appendChild(li);
    });

    dropdown.appendChild(fragment);
};
