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
 * Initializer for the course editor reactive bridge component.
 *
 * Creates a CourseEditorWatcher BaseComponent registered with the
 * current course editor reactive instance. The component then watches
 * state changes and dispatches custom events for other minimoodlewall
 * modules (pagination, tag filter) to consume.
 *
 * @module     format_minimoodlewall/init_courseeditor_watcher
 * @copyright  2025 MBS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import CourseEditorWatcher from 'format_minimoodlewall/courseeditor_watcher';
import {getCurrentCourseEditor} from 'core_courseformat/courseeditor';

/**
 * Initialize the course editor watcher component.
 *
 * @returns {CourseEditorWatcher|null} The component instance, or null if unavailable
 */
export const init = () => {
    const reactive = getCurrentCourseEditor();
    if (!reactive) {
        return null;
    }

    // Anchor to the wall activity container or filter bar.
    const element = document.querySelector('.minimoodlewall-activities')
        || document.querySelector('[data-region="minimoodlewall-filterbar"]');
    if (!element) {
        return null;
    }

    return new CourseEditorWatcher({element, reactive});
};
