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
 * Tag chooser button handler for format_minimoodlewall.
 *
 * Handles tag selection in the activity chooser dropdown. Supports both:
 * - Moodle 5.1+ with data-section-id attributes (MDL-86337)
 * - Moodle 5.0 and earlier with data-sectionnum attributes
 *
 * @module     format_minimoodlewall/tagchooserbutton
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import Ajax from 'core/ajax';
import {get_string as getString} from 'core/str';
import Templates from 'core/templates';
import * as Repository from 'core_courseformat/local/activitychooser/repository';
import * as ChooserDialogue from 'core_courseformat/local/activitychooser/dialogue';
import Modal from 'core/modal';

/**
 * Initialize the tag chooser button handlers.
 */
export const init = () => {
    // Listen for clicks on tag links in the dropdown
    document.addEventListener('click', async(e) => {
        const tagLink = e.target.closest('.format-minimoodlewall-tag-link');
        if (!tagLink) {
            return;
        }

        e.preventDefault();

        const tagId = tagLink.dataset.tagId;
        const tagName = tagLink.dataset.tagName;
        const activityType1 = tagLink.dataset.activityType1;
        const activityType2 = tagLink.dataset.activityType2;
        const sectionNum = tagLink.dataset.sectionnum;
        const sectionId = tagLink.dataset.sectionId;
        const beforeMod = tagLink.dataset.beforemod;
        const sectionReturnNum = tagLink.dataset.sectionreturnnum;
        const sectionReturnId = tagLink.dataset.sectionreturnid;

        // Show modal with 3 options
        await showActivityTypeModal(
            tagId,
            tagName,
            activityType1,
            activityType2,
            sectionNum,
            sectionId,
            beforeMod,
            sectionReturnNum,
            sectionReturnId
        );
    });
};

/**
 * Show a modal with activity type selection options.
 *
 * @param {string} tagId The tag ID
 * @param {string} tagName The tag name
 * @param {string} activityType1 First activity type
 * @param {string} activityType2 Second activity type (or null)
 * @param {string} sectionNum Section number
 * @param {string} sectionId Section ID (Moodle 5.1+)
 * @param {string} beforeMod Module ID to insert before (optional)
 * @param {string} sectionReturnNum Section return number (optional)
 * @param {string} sectionReturnId Section return ID (Moodle 5.1+, optional)
 */
const showActivityTypeModal = async(
    tagId,
    tagName,
    activityType1,
    activityType2,
    sectionNum,
    sectionId,
    beforeMod,
    sectionReturnNum,
    sectionReturnId
) => {
    try {
        // Collect activity types to fetch descriptions for.
        const typesToFetch = [];
        const activityTypes = [];

        if (activityType1 && activityType1 !== 'null') {
            typesToFetch.push(activityType1);
            activityTypes.push(activityType1);
        }
        if (activityType2 && activityType2 !== 'null') {
            typesToFetch.push(activityType2);
            activityTypes.push(activityType2);
        }

        // Fetch descriptions and labels in parallel.
        const promises = [
            getString('newactivity', 'format_minimoodlewall', tagName),
            getString('newactivityfor', 'format_minimoodlewall', tagName),
            getString('selectactivitytypedesc', 'format_minimoodlewall'),
            getString('openallactivities', 'format_minimoodlewall'),
        ];

        // Add label fetching for each activity type
        activityTypes.forEach(type => {
            promises.push(getActivityTypeLabel(type));
        });

        // Add description fetching
        if (typesToFetch.length > 0) {
            promises.push(Ajax.call([{
                methodname: 'format_minimoodlewall_get_activity_descriptions',
                args: {activitytypes: typesToFetch},
            }])[0]);
        }

        const results = await Promise.all(promises);

        const modalTitle = results[0];
        const selectActivityTypeStr = results[1];
        const selectActivityTypeDescStr = results[2];
        const activityOrResourceStr = results[3];
        const labels = results.slice(4, 4 + activityTypes.length);
        const descriptions = typesToFetch.length > 0 ? results[results.length - 1] : [];

        // Map descriptions, icons, and purposes by activity type.
        const dataMap = {};
        descriptions.forEach(desc => {
            dataMap[desc.activitytype] = {
                description: desc.description,
                iconhtml: desc.iconhtml,
                purpose: desc.purpose,
            };
        });

        // Get activity type string once
        const activityTypeStr = activityTypes.length > 0 ?
            await getString('activitytype', 'format_minimoodlewall') : '';

        // Build activity cards array
        const activitycards = activityTypes.map((type, index) => ({
            activitytype: type,
            label: labels[index],
            description: dataMap[type]?.description || '',
            icon: dataMap[type]?.iconhtml || '',
            purpose: dataMap[type]?.purpose || '',
            type: activityTypeStr,
        }));

        // Determine column class based on number of cards
        let colclass = 'col-12';
        if (activitycards.length === 2) {
            colclass = 'col-6';
        } else if (activitycards.length === 3) {
            colclass = 'col-4';
        }

        // Prepare template context
        const context = {
            selectactivitytype: selectActivityTypeStr,
            selectactivitytypedesc: selectActivityTypeDescStr,
            activityorresource: activityOrResourceStr,
            activitycards: activitycards,
            colclass: colclass,
        };

        // Render template
        const bodyHtml = await Templates.render('format_minimoodlewall/activitytype_chooser_modal', context);

        const modal = await Modal.create({
            title: modalTitle,
            body: bodyHtml,
            large: true,
            removeOnClose: true,
        });

        // Handle option selection (updated selector for new template structure)
        modal.getRoot().on('click', '.mmw-activity-card, .mmw-activity-chooser-link', (e) => {
            e.preventDefault();
            const activityType = e.currentTarget.dataset.activityType;

            modal.destroy();

            if (activityType === 'chooser') {
                // Open the standard activity chooser
                openActivityChooser(sectionNum, sectionId, beforeMod, sectionReturnNum, sectionReturnId, tagId);
            } else {
                // Navigate directly to the activity creation page
                navigateToActivityCreation(activityType, sectionNum, beforeMod, sectionReturnNum, tagId);
            }
        });

        modal.show();

    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Get a human-readable label for an activity type.
 *
 * @param {string} activityType The activity type (e.g., 'assign', 'resource')
 * @return {Promise<string>} Promise resolving to the label
 */
const getActivityTypeLabel = async(activityType) => {
    try {
        return await getString('modulename', 'mod_' + activityType);
    } catch (error) {
        // Fallback to capitalized activity type if string not found
        return activityType.charAt(0).toUpperCase() + activityType.slice(1);
    }
};

/**
 * Navigate to the activity creation page.
 *
 * @param {string} activityType The activity module type
 * @param {string} sectionNum Section number
 * @param {string} beforeMod Module ID to insert before (optional)
 * @param {string} sectionReturnNum Section return number (optional)
 * @param {string} tagId The tag ID to assign
 */
const navigateToActivityCreation = async(activityType, sectionNum, beforeMod, sectionReturnNum, tagId) => {
    try {
        // Store tag ID in session via AJAX call
        await Ajax.call([{
            methodname: 'format_minimoodlewall_store_pending_tag',
            args: {tagid: parseInt(tagId)},
        }])[0];

        // Build URL for modedit.php
        const url = new URL(M.cfg.wwwroot + '/course/modedit.php');
        url.searchParams.set('add', activityType);
        url.searchParams.set('type', '');
        url.searchParams.set('course', M.cfg.courseId);
        url.searchParams.set('section', sectionNum);
        if (beforeMod) {
            url.searchParams.set('beforemod', beforeMod);
        }
        if (sectionReturnNum) {
            url.searchParams.set('sr', sectionReturnNum);
        }

        window.location.href = url.toString();
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Open the standard Moodle activity chooser.
 *
 * Supports both Moodle 5.1+ (sectionid) and 5.0 and earlier (section parameter).
 *
 * @param {string} sectionNum Section number
 * @param {string} sectionId Section ID (Moodle 5.1+)
 * @param {string} beforeMod Module ID to insert before (optional)
 * @param {string} sectionReturnNum Section return number (optional)
 * @param {string} sectionReturnId Section return ID (Moodle 5.1+, optional)
 * @param {string} tagId The tag ID to assign (stored for later)
 */
const openActivityChooser = async(sectionNum, sectionId, beforeMod, sectionReturnNum, sectionReturnId, tagId) => {
    try {
        // Store tag ID in session via AJAX call
        await Ajax.call([{
            methodname: 'format_minimoodlewall_store_pending_tag',
            args: {tagid: parseInt(tagId)},
        }])[0];

        // Open the core activity chooser modal
        const courseId = M.cfg.courseId;
        const footerDataPromise = Repository.getModalFooterData(courseId, sectionNum);

        let modulesDataPromise;
        if (sectionId && sectionId !== '') {
            // Moodle 5.1+ with section ID
            modulesDataPromise = Repository.getSectionModulesData(
                courseId,
                sectionId,
                sectionReturnNum,
                beforeMod
            );
        } else {
            // Moodle 5.0 and earlier with section number
            modulesDataPromise = Repository.getModulesData(
                courseId,
                sectionNum,
                sectionReturnNum,
                beforeMod
            );
        }

        ChooserDialogue.displayActivityChooserModal(footerDataPromise, modulesDataPromise);
    } catch (error) {
        Notification.exception(error);
    }
};
