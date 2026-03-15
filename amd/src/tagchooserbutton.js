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
 * Tag chooser button handler for format_mimo.
 *
 * Handles tag selection in the activity chooser dropdown. Supports both:
 * - Moodle 5.1+ with data-section-id attributes (MDL-86337)
 * - Moodle 5.0 and earlier with data-sectionnum attributes
 *
 * @module     format_mimo/tagchooserbutton
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import Ajax from 'core/ajax';
import {get_string as getString} from 'core/str';
import Templates from 'core/templates';
import Modal from 'core/modal';

// Note: Activity chooser modules are dynamically imported to support older Moodle versions

/**
 * Initialize the tag chooser button handlers.
 */
export const init = () => {
    // Listen for clicks on tag links in the dropdown
    document.addEventListener('click', async(e) => {
        const tagLink = e.target.closest('.format-mimo-tag-link');
        if (!tagLink) {
            return;
        }

        e.preventDefault();

        const tagId = tagLink.dataset.tagId;
        const tagName = tagLink.dataset.tagName;
        const activityType1 = tagLink.dataset.activityType1;
        const activityType2 = tagLink.dataset.activityType2;
        const activityType3 = tagLink.dataset.activityType3;
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
            activityType3,
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
 * @param {string} activityType3 Third activity type (or null)
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
    activityType3,
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
        if (activityType3 && activityType3 !== 'null') {
            typesToFetch.push(activityType3);
            activityTypes.push(activityType3);
        }

        // Fetch descriptions and labels in parallel.
        const promises = [
            getString('newactivity', 'format_mimo', tagName),
            getString('newactivityfor', 'format_mimo', tagName),
            getString('selectactivitytypedesc', 'format_mimo'),
            getString('openallactivities', 'format_mimo'),
        ];

        // Add label fetching for each activity type
        activityTypes.forEach(type => {
            promises.push(getActivityTypeLabel(type));
        });

        // Add description fetching
        if (typesToFetch.length > 0) {
            promises.push(Ajax.call([{
                methodname: 'format_mimo_get_activity_descriptions',
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
                tagname: desc.tagname,
                tagcolor: desc.tagcolor,
            };
        });

        // Get activity type string once
        const activityTypeStr = activityTypes.length > 0 ?
            await getString('activitytype', 'format_mimo') : '';

        // Build activity cards array
        const activitycards = activityTypes.map((type, index) => ({
            activitytype: type,
            label: labels[index],
            description: dataMap[type]?.description || '',
            icon: dataMap[type]?.iconhtml || '',
            purpose: dataMap[type]?.purpose || '',
            type: activityTypeStr,
            tagname: dataMap[type]?.tagname || '',
            tagcolor: dataMap[type]?.tagcolor || '',
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
        const bodyHtml = await Templates.render('format_mimo/activitytype_chooser_modal', context);

        const modal = await Modal.create({
            title: modalTitle,
            body: bodyHtml,
            large: true,
            removeOnClose: true,
        });

        // Handle option selection (updated selector for new template structure)
        modal.getRoot().on('click', '.mimo-activity-card, .mimo-activity-chooser-link', async(e) => {
            // Allow clicks on links inside the description to navigate normally.
            if (e.target.closest('.mimo-activity-card-description a')) {
                return;
            }
            e.preventDefault();
            const activityType = e.currentTarget.dataset.activityType;

            modal.destroy();

            if (activityType === 'chooser') {
                // Open the standard activity chooser
                await openActivityChooser(sectionNum, sectionId, beforeMod, sectionReturnNum, sectionReturnId, tagId);
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
            methodname: 'format_mimo_store_pending_tag',
            args: {tagid: parseInt(tagId, 10)},
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
 * Supports both Moodle 5.1+ (with core_courseformat modules) and 5.0 (with core_course modules).
 * For Moodle 5.0, triggers a click on the hidden button to let core handle the chooser.
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
            methodname: 'format_mimo_store_pending_tag',
            args: {tagid: parseInt(tagId, 10)},
        }])[0];

        // Dynamically import activity chooser modules (available in Moodle 5.1+)
        const Repository = await import('core_courseformat/local/activitychooser/repository');
        const ChooserDialogue = await import('core_courseformat/local/activitychooser/dialogue');

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
        // Moodle 5.0: Use core_course modules instead
        try {
            const Repository = await import('core_course/local/activitychooser/repository');
            const ChooserDialogue = await import('core_course/local/activitychooser/dialogue');

            const courseId = M.cfg.courseId;
            const footerDataPromise = Repository.fetchFooterData(courseId, sectionNum);
            const modulesDataPromise = Repository.activityModules(courseId, sectionNum);

            const footerData = await footerDataPromise;
            const modulesData = await modulesDataPromise;

            // Build module data with section info
            const builtModuleData = modulesData.content_items.map(module => {
                let link = module.link + '&section=' + sectionNum + '&beforemod=' + (beforeMod || 0);
                if (sectionReturnNum) {
                    link += '&sr=' + sectionReturnNum;
                }
                return {...module, link: link};
            });

            // Build template context - simplified version without tab modes
            const templateContext = {
                'default': builtModuleData,
                showAll: true,
                activities: [],
                showActivities: false,
                activitiesFirst: false,
                resources: [],
                showResources: false,
                favourites: [],
                recommended: [],
                recommendedFirst: false,
                recommendedBeginning: false,
                favouritesFirst: false,
                fallback: true,
            };

            // Create modal promise
            const Templates = await import('core/templates');
            const {get_string: getString} = await import('core/str');

            let bodyPromiseResolver;
            const bodyPromise = new Promise(resolve => {
                bodyPromiseResolver = resolve;
            });

            const modalPromise = Modal.create({
                body: bodyPromise,
                title: await getString('addresourceoractivity'),
                footer: footerData.customfootertemplate,
                large: true,
                scrollable: false,
                templateContext: {
                    classes: 'modchooser'
                },
                show: true,
            });

            // Render and resolve body BEFORE calling displayChooser
            const renderedBody = await Templates.render('core_course/activitychooser', templateContext);
            bodyPromiseResolver(renderedBody);

            // Now display the chooser - it will find the rendered elements
            ChooserDialogue.displayChooser(
                modalPromise,
                builtModuleData,
                null, // Favourite manager function
                footerData
            );
        } catch (legacyError) {
            Notification.exception(legacyError);
        }
    }
};
