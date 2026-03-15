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
 * Drag and drop for activity cards in editing mode.
 *
 * Uses the core DragDrop delegate (core/reactive) to handle browser DnD API
 * complexity. Each card column gets a CardDnd BaseComponent that implements
 * the DragDrop convention methods. On drop, the DOM is reordered immediately
 * for visual feedback, the wall state is updated, and the new order is
 * persisted to the server via cm_move.
 *
 * @module     format_mimo/activity_dragdrop
 * @copyright  2025 MBS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {BaseComponent, DragDrop} from 'core/reactive';
import {getWallState} from 'format_mimo/local/wall_state/wall_state';
import Ajax from 'core/ajax';
import Notification from 'core/notification';

/**
 * Persist an activity reorder to the server.
 *
 * @param {number} sourceCmid The dragged activity's cmid
 * @param {number|null} targetCmid The cmid to insert before, or null for end
 * @param {number} sectionId The section ID
 */
const persistMove = (sourceCmid, targetCmid, sectionId) => {
    const args = {
        action: 'cm_move',
        courseid: M.cfg.courseId,
        ids: [sourceCmid],
        targetsectionid: sectionId,
    };
    if (targetCmid !== null) {
        args.targetcmid = targetCmid;
    }
    Ajax.call([{
        methodname: 'core_courseformat_update_course',
        args,
    }])[0].catch((error) => {
        Notification.exception(error);
        window.location.reload();
    });
};

/**
 * Draggable and droppable component for a single activity card column.
 *
 * Implements the convention methods expected by core DragDrop:
 * - getDraggableData(): enables dragging, returns card identity
 * - validateDropData(): enables as dropzone, validates incoming drag
 * - drop(): handles the reorder on drop
 *
 * @extends BaseComponent
 */
class CardDnd extends BaseComponent {

    /**
     * Component setup.
     *
     * @param {object} descriptor BaseComponent descriptor
     */
    create(descriptor) {
        this.element = descriptor.element;
        this.reactive = descriptor.reactive;
        // Override DragDrop CSS class to match existing styles.
        this.classes = {DRAGOVER: 'drag-over'};
        this.relativeDrag = true;
        const card = this.element.querySelector('.mimo-card');
        /** @type {number} */
        this.cmid = card ? parseInt(card.dataset.cmid, 10) : 0;
        /** @type {number} */
        this.sectionid = card ? parseInt(card.dataset.sectionid, 10) : 0;
    }

    /**
     * Create the DragDrop delegate once state is ready.
     */
    stateReady() {
        if (this.cmid) {
            this.dragdrop = new DragDrop(this);
        }
    }

    /**
     * Return draggable data for this card.
     * Returning null for hidden cards prevents dragging paginated-out items.
     *
     * @returns {object|null}
     */
    getDraggableData() {
        if (this.element.style.display === 'none' || this.element.hidden) {
            return null;
        }
        return {
            type: 'mimo_card',
            cmid: this.cmid,
            sectionid: this.sectionid,
            col: this.element,
        };
    }

    /**
     * Validate whether this element accepts the dragged data.
     *
     * @param {object} dropdata The dragged element's data
     * @returns {boolean}
     */
    validateDropData(dropdata) {
        if (this.element.style.display === 'none' || this.element.hidden) {
            return false;
        }
        return dropdata?.type === 'mimo_card' && dropdata.cmid !== this.cmid;
    }

    /**
     * Handle a valid drop on this element.
     *
     * @param {object} dropdata The dragged element's data
     */
    drop(dropdata) {
        const container = this.element.parentNode;
        const allCols = Array.from(container.querySelectorAll('.col-12'));
        const draggedCol = dropdata.col;
        const draggedIndex = allCols.indexOf(draggedCol);
        const targetIndex = allCols.indexOf(this.element);

        if (draggedIndex === -1 || targetIndex === -1) {
            return;
        }

        // Compute cm_move targetcmid (insert-before semantics).
        let moveTargetCmid = this.cmid;
        if (draggedIndex < targetIndex) {
            // Moving forward: insert before the element AFTER the drop target.
            const nextCol = allCols[targetIndex + 1];
            if (nextCol) {
                const nextCard = nextCol.querySelector('.mimo-card');
                moveTargetCmid = nextCard ? parseInt(nextCard.dataset.cmid, 10) : null;
            } else {
                moveTargetCmid = null;
            }
        }

        // Reorder DOM immediately for visual feedback.
        if (draggedIndex < targetIndex) {
            container.insertBefore(draggedCol, this.element.nextSibling);
        } else {
            container.insertBefore(draggedCol, this.element);
        }

        // Update wall state with new activity order.
        const items = container.querySelectorAll('li[data-id]');
        const orderedIds = Array.from(items, (item) => Number(item.dataset.id));
        this.reactive.dispatch('reorderActivities', orderedIds);

        // Persist to server.
        persistMove(dropdata.cmid, moveTargetCmid, this.sectionid);
    }
}

/**
 * Setup pagination button hover for automatic page navigation while dragging.
 *
 * When a user hovers on a pagination button for 1 second during a drag
 * operation, the button is clicked to navigate to the next/previous page.
 */
const setupPaginationDragHover = () => {
    const prevButton = document.querySelector('#mimo-prev');
    const nextButton = document.querySelector('#mimo-next');
    let hoverTimeout = null;

    /**
     * Setup hover handler for a single pagination button.
     *
     * @param {HTMLElement|null} button The pagination button
     * @param {Function} callback The action to perform after hover delay
     */
    const setupButtonHover = (button, callback) => {
        if (!button) {
            return;
        }

        button.addEventListener('dragover', (e) => {
            e.preventDefault();
            // Only trigger if a drag is active (core DragDrop adds .dragging to body).
            if (document.body.classList.contains('dragging') && !button.disabled && !hoverTimeout) {
                hoverTimeout = setTimeout(() => {
                    callback();
                    hoverTimeout = null;
                }, 1000);
            }
        });

        button.addEventListener('dragleave', () => {
            if (hoverTimeout) {
                clearTimeout(hoverTimeout);
                hoverTimeout = null;
            }
        });

        button.addEventListener('drop', () => {
            if (hoverTimeout) {
                clearTimeout(hoverTimeout);
                hoverTimeout = null;
            }
        });
    };

    setupButtonHover(prevButton, () => {
        if (prevButton && !prevButton.disabled) {
            prevButton.click();
        }
    });

    setupButtonHover(nextButton, () => {
        if (nextButton && !nextButton.disabled) {
            nextButton.click();
        }
    });
};

/**
 * Initialize drag and drop for activity cards.
 *
 * Creates a CardDnd BaseComponent for each activity card column,
 * using core DragDrop for browser DnD handling.
 */
export const init = () => {
    if (!document.body.classList.contains('editing')) {
        return;
    }

    const container = document.querySelector('.mimo-activities');
    if (!container) {
        return;
    }

    const sectionElement = container.closest('.section-item')
        || container.closest('[data-sectionid]')
        || container;
    const wallState = getWallState(sectionElement);

    // Create a CardDnd component for each card column.
    container.querySelectorAll('.col-12').forEach((col) => {
        if (col.querySelector('.mimo-card')) {
            new CardDnd({element: col, reactive: wallState});
        }
    });

    setupPaginationDragHover();
};
