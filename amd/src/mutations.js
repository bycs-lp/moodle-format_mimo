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
 * Format mimo mutations.
 *
 * Adds custom mutations to the course editor for the "Done" activity state.
 * All functions are declared as class attributes so that addMutations can
 * discover them (required because multiple plugins may add mutations).
 *
 * @module     format_mimo/mutations
 * @copyright  2026 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getCurrentCourseEditor} from 'core_courseformat/courseeditor';
import DefaultMutations from 'core_courseformat/local/courseeditor/mutations';
import CourseActions from 'core_courseformat/local/content/actions';
import Fragment from 'core/fragment';
import Templates from 'core/templates';
import Config from 'core/config';

/**
 * Reload a cmitem from the server and replace the DOM node.
 *
 * This follows the same pattern as core content.js _reloadCm to ensure
 * the full cmitem (including visibility dropdown) is re-rendered correctly.
 *
 * @param {number} cmId Course module id
 */
const reloadCmItem = async(cmId) => {
    const cmitem = document.querySelector(`li.activity[data-id="${cmId}"]`);
    if (!cmitem) {
        return;
    }
    const promise = Fragment.loadFragment(
        'core_courseformat',
        'cmitem',
        Config.courseContextId,
        {
            id: cmId,
            courseid: Config.courseId,
        }
    );
    promise.then((html, js) => {
        if (!document.contains(cmitem)) {
            return false;
        }
        Templates.replaceNode(cmitem, html, js);
        return true;
    }).catch(() => {
        // Silently ignore errors.
    });
};

class MimoMutations extends DefaultMutations {

    /**
     * Mark course modules as done.
     *
     * @param {StateManager} stateManager the current state manager
     * @param {array} cmIds the list of cm ids
     */
    cmDone = async function(stateManager, cmIds) {
        const course = stateManager.get('course');
        this.cmLock(stateManager, cmIds, true);
        const updates = await this._callEditWebservice('cm_done', course.id, cmIds);
        this.bulkReset(stateManager);
        stateManager.processUpdates(updates);
        this.cmLock(stateManager, cmIds, false);
        // Reload each cmitem from the server so the visibility dropdown icon updates.
        cmIds.forEach(id => reloadCmItem(id));
    };

    /**
     * Unmark course modules as done.
     *
     * @param {StateManager} stateManager the current state manager
     * @param {array} cmIds the list of cm ids
     */
    cmUndone = async function(stateManager, cmIds) {
        const course = stateManager.get('course');
        this.cmLock(stateManager, cmIds, true);
        const updates = await this._callEditWebservice('cm_undone', course.id, cmIds);
        this.bulkReset(stateManager);
        stateManager.processUpdates(updates);
        this.cmLock(stateManager, cmIds, false);
        // Reload each cmitem from the server so the visibility dropdown icon updates.
        cmIds.forEach(id => reloadCmItem(id));
    };

    /**
     * Show course modules — force reload to clear done styling.
     *
     * When switching from done→show, cm.visible doesn't change (both are visible)
     * so core's _reloadCm watcher won't fire. We force a reload after the action.
     *
     * @param {StateManager} stateManager the current state manager
     * @param {array} cmIds the list of cm ids
     */
    cmShow = async function(stateManager, cmIds) {
        await this._cmBasicAction(stateManager, 'cm_show', cmIds);
        cmIds.forEach(id => reloadCmItem(id));
    };

    /**
     * Hide course modules — force reload to clear done styling.
     *
     * @param {StateManager} stateManager the current state manager
     * @param {array} cmIds the list of cm ids
     */
    cmHide = async function(stateManager, cmIds) {
        await this._cmBasicAction(stateManager, 'cm_hide', cmIds);
        cmIds.forEach(id => reloadCmItem(id));
    };

    /**
     * Stealth course modules — force reload to clear done styling.
     *
     * @param {StateManager} stateManager the current state manager
     * @param {array} cmIds the list of cm ids
     */
    cmStealth = async function(stateManager, cmIds) {
        await this._cmBasicAction(stateManager, 'cm_stealth', cmIds);
        cmIds.forEach(id => reloadCmItem(id));
    };
}

export const init = () => {
    const courseEditor = getCurrentCourseEditor();
    courseEditor.addMutations(new MimoMutations());
    CourseActions.addActions({
        cmDone: 'cmDone',
        cmUndone: 'cmUndone',
    });
};
