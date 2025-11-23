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
 * Debug bulk actions for minimoodlewall format.
 *
 * @module     format_minimoodlewall/bulk_debug
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* eslint-disable no-console */

import {getCurrentCourseEditor} from 'core_courseformat/courseeditor';

export const init = () => {
    console.log('=== BULK ACTIONS DEBUG ===');

    // Log all elements with data-for="cmitem"
    const cmItems = document.querySelectorAll('[data-for="cmitem"]');
    console.log(`Found ${cmItems.length} cmitem elements`);

    // Monitor clicks
    document.addEventListener('click', (e) => {
        const cmItem = e.target.closest('[data-for="cmitem"]');
        if (cmItem) {
            console.log('Click on cmitem', cmItem.dataset.id, '- action:', cmItem.dataset.action, '- preventDefault:', cmItem.dataset.preventDefault);
        }
        const checkbox = e.target.closest('[data-bulkcheckbox]');
        if (checkbox) {
            console.log('Click on checkbox', checkbox.dataset.id, '- checked:', checkbox.checked, '- action:', checkbox.dataset.action);
        }
    }, true);

    // Monitor bulk state changes
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type === 'attributes') {
                const element = mutation.target;
                if (element.matches('[data-for="cmitem"]') && mutation.attributeName === 'data-action') {
                    console.log('✓ cmitem ID', element.dataset.id, 'got data-action:', element.dataset.action);
                }
                if (element.matches('[data-for="cmitem"]') && mutation.attributeName === 'class') {
                    const isSelected = element.classList.contains('selected');
                    if (isSelected) {
                        console.log('✓ cmitem', element.dataset.id, 'marked as SELECTED');
                    }
                }
            }
        });
    });

    // Observe cmitem elements
    cmItems.forEach(item => {
        observer.observe(item, {
            attributes: true,
            attributeFilter: ['data-action', 'class']
        });
    });

    console.log('Observing', cmItems.length, 'cmitems for bulk mode activation');

    // Check if bulk mode is active
    const courseEditor = document.querySelector('[data-for="course-editor"]');
    if (courseEditor) {
        const isBulkMode = courseEditor.classList.contains('bulkmode') ||
                          courseEditor.dataset.bulkEditing === 'true';
        console.log('Bulk mode active:', isBulkMode);
    }

    // Verify structure
    const cmlist = document.querySelector('[data-for="cmlist"]');
    if (cmlist) {
        console.log('cmlist children:', cmlist.children.length);
    }

    // Check reactive registration
    setTimeout(() => {
        console.log('=== CHECKING REACTIVE AT 1000ms ===');
        try {
            const reactiveEditor = getCurrentCourseEditor();
            if (reactiveEditor) {
                console.log('✓ Reactive editor:', reactiveEditor.name);
                
                // Check registered components
                if (reactiveEditor.components) {
                    const compList = reactiveEditor.components.map(c => c.name).join(', ');
                    console.log('Registered components:', compList);
                    
                    // Find actions component
                    const actionsComp = reactiveEditor.components.find(c => c.name === 'content_actions');
                    console.log('Actions component:', actionsComp ? '✓ FOUND' : '✗ NOT FOUND');
                }
                
                // Check bulk state
                const bulkState = reactiveEditor.get('bulk');
                console.log('Bulk:', `enabled=${bulkState.enabled}, selectedType=${bulkState.selectedType}, selection=[${bulkState.selection}]`);
            }
        } catch (e) {
            console.log('✗ Error:', e.message);
        }
    }, 1000);
};

