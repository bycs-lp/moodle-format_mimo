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
 * Section overview card actions: delete and drag-and-drop reorder.
 *
 * Delete: confirmation modal → core_courseformat_update_course section_delete.
 * Drag-drop: uses core DragDrop delegate (BaseComponent/DragDrop from core/reactive),
 * whole card as drag surface, interactive children protected via draggable="false".
 * On drop, calls core_courseformat_update_course section_move_after.
 *
 * @module     format_mimo/section_overview_actions
 * @copyright  2025 MBS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {BaseComponent, DragDrop, Reactive} from 'core/reactive';
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {get_string as getString} from 'core/str';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';

/**
 * Persist a section move to the server.
 *
 * @param {number} sectionId The dragged section's DB id
 * @param {number} targetSectionId The section to insert after
 */
const persistSectionMove = (sectionId, targetSectionId) => {
    Ajax.call([{
        methodname: 'core_courseformat_update_course',
        args: {
            action: 'section_move_after',
            courseid: M.cfg.courseId,
            ids: [sectionId],
            targetsectionid: targetSectionId,
        },
    }])[0].catch((error) => {
        Notification.exception(error);
        // window.location.reload();
    });
};

/**
 * Delete a section via the server.
 *
 * @param {number} sectionId The section's DB id
 * @returns {Promise}
 */
const deleteSection = (sectionId) => {
    return Ajax.call([{
        methodname: 'core_courseformat_update_course',
        args: {
            action: 'section_delete',
            courseid: M.cfg.courseId,
            ids: [sectionId],
        },
    }])[0];
};

// ─── Delete handler ───────────────────────────────────────────────────────────

/**
 * Set up section delete buttons via event delegation.
 *
 * @param {HTMLElement} container The overview grid container
 */
const setupDeleteActions = (container) => {
    container.addEventListener('click', async(e) => {
        const btn = e.target.closest('[data-action="delete-section"]');
        if (!btn) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();

        const sectionId = parseInt(btn.dataset.sectionId, 10);
        const sectionName = btn.dataset.sectionName || '';
        const activityCount = parseInt(btn.dataset.activityCount, 10) || 0;

        // Choose confirmation message based on whether the section has activities.
        let bodyText;
        if (activityCount > 0) {
            bodyText = await getString(
                'confirmdeletesection_notempty',
                'format_mimo',
                {name: sectionName, count: activityCount}
            );
        } else {
            bodyText = await getString('confirmdeletesection', 'format_mimo', sectionName);
        }

        const titleText = await getString('deletesection', 'format_mimo');

        const modal = await ModalSaveCancel.create({
            title: titleText,
            body: bodyText,
            buttons: {
                save: titleText,
            },
        });

        modal.getRoot().on(ModalEvents.save, async() => {
            const card = container.querySelector(
                `.mimo-overview-card[data-section-id="${sectionId}"]`
            );

            try {
                await deleteSection(sectionId);
                if (card) {
                    card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.95)';
                    setTimeout(() => card.remove(), 300);
                }
            } catch (error) {
                Notification.exception(error);
                // window.location.reload();
            }
        });

        modal.show();
    });
};

// ─── Drag-and-drop handler ────────────────────────────────────────────────────

/**
 * Create a minimal Reactive instance for the overview grid.
 * This keeps DnD self-contained (same pattern as wall_state for activities).
 *
 * @returns {Reactive}
 */
/**
 * Dispatch overview state changed event.
 *
 * @param {object} detail event detail payload
 * @param {EventTarget} target element to dispatch on
 */
const dispatchOverviewStateChanged = (detail, target) => {
    target = target ?? document;
    target.dispatchEvent(new CustomEvent('format_mimo_overview:stateChanged', {
        bubbles: true,
        detail,
    }));
};

const createOverviewState = () => {
    const initial = {sections: []};
    return new Reactive({
        name: 'format_mimo_overview',
        eventName: 'format_mimo_overview:stateChanged',
        eventDispatch: dispatchOverviewStateChanged,
        state: initial,
        mutations: {},
    });
};

/**
 * Draggable and droppable component for a single overview section card.
 *
 * The whole card is the drag surface. Interactive children (buttons, links,
 * inplace editable) have draggable="false" in the template so the browser
 * won't initiate drag from those elements.
 *
 * @extends BaseComponent
 */
class SectionCardDnd extends BaseComponent {

    /**
     * Component setup.
     *
     * @param {object} descriptor BaseComponent descriptor
     */
    create(descriptor) {
        this.element = descriptor.element;
        this.reactive = descriptor.reactive;
        this.classes = {DRAGOVER: 'drag-over'};
        this.relativeDrag = true;
        this.sectionid = parseInt(this.element.dataset.sectionId, 10) || 0;
    }

    /**
     * Create the DragDrop delegate once state is ready.
     */
    stateReady() {
        if (this.sectionid) {
            this.dragdrop = new DragDrop(this);
        }
    }

    /**
     * Return draggable data for this card.
     *
     * @returns {object|null}
     */
    getDraggableData() {
        return {
            type: 'mimo_section_card',
            sectionid: this.sectionid,
            element: this.element,
        };
    }

    /**
     * Validate whether this element accepts the dragged data.
     *
     * @param {object} dropdata The dragged element's data
     * @returns {boolean}
     */
    validateDropData(dropdata) {
        return dropdata?.type === 'mimo_section_card' && dropdata.sectionid !== this.sectionid;
    }

    /**
     * Handle a valid drop on this element.
     *
     * section_move_after semantics: move the dragged section to directly after
     * the target section. We always pass the drop target's section ID as the
     * "insert after" reference.
     *
     * @param {object} dropdata The dragged element's data
     */
    drop(dropdata) {
        const container = this.element.parentNode;
        const allCards = Array.from(container.querySelectorAll('[data-for="mimo-overview-card"]'));
        const draggedCard = dropdata.element;
        const draggedIndex = allCards.indexOf(draggedCard);
        const targetIndex = allCards.indexOf(this.element);

        if (draggedIndex === -1 || targetIndex === -1) {
            return;
        }

        // Determine which section to place after.
        // section_move_after places the section directly after targetsectionid.
        let afterSectionId;
        if (draggedIndex < targetIndex) {
            // Moving forward: place after the drop target.
            afterSectionId = this.sectionid;
        } else {
            // Moving backward: place after the card before the drop target.
            const prevCard = allCards[targetIndex - 1];
            if (prevCard) {
                afterSectionId = parseInt(prevCard.dataset.sectionId, 10);
            } else {
                // Dropping at the very start — place after section 0 (use DB id from grid).
                afterSectionId = parseInt(container.dataset.section0id, 10) || 0;
            }
        }

        // Optimistic DOM reorder.
        if (draggedIndex < targetIndex) {
            container.insertBefore(draggedCard, this.element.nextSibling);
        } else {
            container.insertBefore(draggedCard, this.element);
        }

        // Persist to server.
        persistSectionMove(dropdata.sectionid, afterSectionId);
    }
}

// ─── Init ─────────────────────────────────────────────────────────────────────

/**
 * Initialise section overview actions (delete + drag-and-drop).
 */
export const init = () => {
    if (!document.body.classList.contains('editing')) {
        return;
    }

    const grid = document.querySelector('[data-region="mimo-overview-grid"]');
    if (!grid) {
        return;
    }

    // Set up delete buttons.
    setupDeleteActions(grid);

    // Set up drag-and-drop on each section card.
    const overviewState = createOverviewState();
    grid.querySelectorAll('[data-for="mimo-overview-card"]').forEach((card) => {
        new SectionCardDnd({element: card, reactive: overviewState});
    });
};
