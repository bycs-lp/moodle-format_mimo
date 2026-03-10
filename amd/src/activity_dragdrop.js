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
 * @module     format_minimoodlewall/activity_dragdrop
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {getWallState} from 'format_minimoodlewall/local/wall_state/wall_state';

export const init = () => {
    // Only enable in editing mode.
    if (!document.body.classList.contains('editing')) {
        return;
    }

    const activityCards = document.querySelectorAll('.minimoodlewall-card');
    if (activityCards.length === 0) {
        return;
    }

    const sectionElement = activityCards[0].closest('.section-item') || activityCards[0].closest('[data-sectionid]');
    const wallState = sectionElement ? getWallState(sectionElement) : null;

    let draggedElement = null;

    /**
     * Make all activity cards draggable.
     */
    activityCards.forEach(card => {
        const col = card.closest('.col-12');
        if (!col) {
            return;
        }

        // Make card draggable.
        card.setAttribute('draggable', 'true');
        card.style.cursor = 'move';

        // Drag start.
        card.addEventListener('dragstart', (e) => {
            // Clear any stale highlights from previous drag operations.
            const staleHighlights = document.querySelectorAll('.drag-over');
            staleHighlights.forEach(element => {
                element.classList.remove('drag-over');
            });

            draggedElement = col;
            col.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/html', col.innerHTML);

            // Create a custom drag image with better visibility.
            const dragImage = col.cloneNode(true);
            dragImage.style.position = 'absolute';
            dragImage.style.top = '-9999px';
            dragImage.style.left = '-9999px';
            dragImage.style.width = col.offsetWidth + 'px';
            dragImage.style.height = col.offsetHeight + 'px';
            dragImage.style.maxHeight = col.offsetHeight + 'px';
            dragImage.style.overflow = 'hidden';
            dragImage.style.boxShadow = '0 4px 12px rgba(0, 123, 255, 0.5)';
            dragImage.style.opacity = '0.9';
            dragImage.style.backgroundColor = '#fff';
            dragImage.style.display = 'block';
            document.body.appendChild(dragImage);
            e.dataTransfer.setDragImage(dragImage, col.offsetWidth / 2, col.offsetHeight / 2);

            // Remove the temporary drag image after a short delay.
            setTimeout(() => {
                document.body.removeChild(dragImage);
            }, 0);
        });

        // Drag end - cleanup all highlights.
        card.addEventListener('dragend', () => {
            col.classList.remove('dragging');
            // Clean up any remaining drag-over highlights.
            document.querySelectorAll('.drag-over').forEach(element => {
                element.classList.remove('drag-over');
            });
        });

        // Allow drop.
        col.addEventListener('dragover', (e) => {
            e.preventDefault();
            // Skip if this card is hidden by pagination.
            if (col.style.display === 'none') {
                return;
            }
            e.dataTransfer.dropEffect = 'move';
            // Add highlight on dragover to keep it consistent.
            if (col !== draggedElement) {
                if (!col.classList.contains('drag-over')) {
                    col.classList.add('drag-over');
                }
            }
        });

        // Drag enter - highlight drop zone.
        col.addEventListener('dragenter', (e) => {
            e.preventDefault();
            // Skip if this card is hidden by pagination.
            if (col.style.display === 'none') {
                return;
            }
            if (col !== draggedElement) {
                col.classList.add('drag-over');
            }
        });

        // Drag leave - remove highlight only when leaving the col itself.
        col.addEventListener('dragleave', (e) => {
            // Only remove if we're actually leaving the col (not just entering a child).
            // Check if the relatedTarget (where we're going) is NOT inside this col.
            const leavingElement = !col.contains(e.relatedTarget);
            if (leavingElement) {
                col.classList.remove('drag-over');
            }
        });

        // Drop.
        col.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();

            // Skip if this card is hidden by pagination.
            if (col.style.display === 'none') {
                return;
            }

            // Clean up all drag-over highlights.
            const container = col.closest('.row');
            if (container) {
                const highlights = container.querySelectorAll('.drag-over');
                highlights.forEach(element => {
                    element.classList.remove('drag-over');
                });
            }

            if (draggedElement && col !== draggedElement) {
                // Get fresh references to both cards.
                const draggedCard = draggedElement.querySelector('.minimoodlewall-card');
                const targetCard = col.querySelector('.minimoodlewall-card');

                if (!draggedCard || !targetCard) {
                    return;
                }

                const draggedCmid = parseInt(draggedCard.dataset.cmid, 10);
                const targetCmid = parseInt(targetCard.dataset.cmid, 10);

                // Don't do anything if trying to drop on itself.
                if (draggedCmid === targetCmid) {
                    return;
                }

                // Get the container.
                const container = col.parentNode;

                // Get positions.
                const allCards = Array.from(container.children);
                const draggedIndex = allCards.indexOf(draggedElement);
                const targetIndex = allCards.indexOf(col);

                // Determine the actual target cmid based on direction.
                let actualTargetCmid = targetCmid;
                if (draggedIndex < targetIndex) {
                    // Moving down: insert after target, so use the next element as "before".
                    const nextCol = allCards[targetIndex + 1];
                    if (nextCol) {
                        const nextCard = nextCol.querySelector('.minimoodlewall-card');
                        if (nextCard) {
                            actualTargetCmid = parseInt(nextCard.dataset.cmid, 10);
                        }
                    } else {
                        // Moving to end, use null (append to end).
                        actualTargetCmid = null;
                    }
                }

                // Swap elements in DOM immediately for visual feedback.
                if (draggedIndex < targetIndex) {
                    col.parentNode.insertBefore(draggedElement, col.nextSibling);
                } else {
                    col.parentNode.insertBefore(draggedElement, col);
                }

                // Notify wall state that activity order changed.
                if (wallState) {
                    const allItems = col.parentNode.querySelectorAll('li[data-id]');
                    const orderedIds = Array.from(allItems).map(item => item.dataset.id);
                    wallState.dispatch('reorderActivities', orderedIds);
                }

                // Clean up any remaining highlights after DOM manipulation.
                container.querySelectorAll('.drag-over').forEach(element => {
                    element.classList.remove('drag-over');
                });

                // Save new order to server.
                saveOrder(draggedCmid, actualTargetCmid);
            }
        });
    });

    /**
     * Save the new order to the server.
     *
     * @param {number} sourceCmid The dragged activity ID.
     * @param {number} targetCmid The target activity ID.
     */
    const saveOrder = (sourceCmid, targetCmid) => {
        // Get section ID from the activity card itself.
        const card = document.querySelector(`.minimoodlewall-card[data-cmid="${sourceCmid}"]`);
        const sectionId = card ? parseInt(card.dataset.sectionid, 10) : null;

        if (!sectionId) {
            Notification.addNotification({
                message: 'Could not determine section ID',
                type: 'error'
            });
            return;
        }

        Ajax.call([{
            methodname: 'core_courseformat_update_course',
            args: {
                action: 'cm_move',
                courseid: M.cfg.courseId,
                ids: [sourceCmid],
                targetsectionid: sectionId,
                targetcmid: targetCmid,
            },
        }])[0]
        .then(() => {
            return true;
        })
        .catch((error) => {
            Notification.exception(error);
            // Reload page on error to restore correct order.
            window.location.reload();
            return false;
        });
    };

    /**
     * Setup pagination button hover for automatic page navigation while dragging.
     */
    const setupPaginationDragHover = () => {
        const prevButton = document.querySelector('#minimoodlewall-prev');
        const nextButton = document.querySelector('#minimoodlewall-next');
        let hoverTimeout = null;

        const setupButtonHover = (button, buttonName, callback) => {
            if (!button) {
                return;
            }

            // Need dragover for continuous hovering detection.
            button.addEventListener('dragover', (e) => {
                e.preventDefault();
                // Only trigger if a card is being dragged and no timeout is already running.
                if (draggedElement && !button.disabled && !hoverTimeout) {
                    // Wait 1 second before triggering pagination.
                    hoverTimeout = setTimeout(() => {
                        callback();
                        hoverTimeout = null;
                    }, 1000);
                }
            });

            button.addEventListener('dragleave', () => {
                // Cancel the timeout if user moves away.
                if (hoverTimeout) {
                    clearTimeout(hoverTimeout);
                    hoverTimeout = null;
                }
            });

            button.addEventListener('drop', () => {
                // Cancel timeout on drop.
                if (hoverTimeout) {
                    clearTimeout(hoverTimeout);
                    hoverTimeout = null;
                }
            });
        };

        // Setup previous button.
        setupButtonHover(prevButton, 'Prev', () => {
            if (prevButton && !prevButton.disabled) {
                prevButton.click();
            }
        });

        // Setup next button.
        setupButtonHover(nextButton, 'Next', () => {
            if (nextButton && !nextButton.disabled) {
                nextButton.click();
            }
        });
    };

    // Initialize pagination hover functionality.
    setupPaginationDragHover();
};
