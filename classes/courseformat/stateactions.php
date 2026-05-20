<?php
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
 * State actions extension for format_mimo.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo\courseformat;

use core_courseformat\stateactions as core_stateactions;
use core_courseformat\stateupdates;
use format_mimo\done_manager;
use format_mimo\tag_manager;
use moodle_exception;
use stdClass;

/**
 * Custom state actions for mimo so duplicated modules keep their tags.
 */
class stateactions extends core_stateactions {
    /**
     * Duplicate course modules and copy mimo tags to the clones.
     *
     * @param stateupdates $updates the affected course elements track
     * @param stdClass $course the course object
     * @param int[] $ids course modules ids to duplicate
     * @param int|null $targetsectionid optional target section id destination
     * @param int|null $targetcmid optional target before cm id destination
     * @throws moodle_exception
     */
    public function cm_duplicate(
        stateupdates $updates,
        stdClass $course,
        array $ids = [],
        ?int $targetsectionid = null,
        ?int $targetcmid = null
    ): void {
        // Use the core validator to ensure the caller can duplicate all requested modules.
        $this->validate_cms(
            $course,
            $ids,
            __FUNCTION__,
            ['moodle/backup:backuptargetimport', 'moodle/restore:restoretargetimport'],
            false
        );

        // Grab the current course snapshot so we can inspect existing sections/modules.
        $modinfo = get_fast_modinfo($course);
        $cms = $this->get_cm_info($modinfo, $ids);

        // Bail early if any cm would violate module permissions in this course context.
        foreach ($cms as $cm) {
            if (!course_allowed_module($course, $cm->modname)) {
                throw new moodle_exception('No permission to create that activity');
            }
        }

        $targetsection = null;
        if (!empty($targetsectionid)) {
            // Validate and fetch the explicit destination section (if provided).
            $this->validate_sections($course, [$targetsectionid], __FUNCTION__);
            $targetsection = $modinfo->get_section_info_by_id($targetsectionid, MUST_EXIST);
        }

        $beforecm = null;
        if (!empty($targetcmid)) {
            // When inserting before another cm, align both the section target and ordering reference.
            $this->validate_cms($course, [$targetcmid], __FUNCTION__);
            $beforecm = $modinfo->get_cm($targetcmid);
            $targetsection = $modinfo->get_section_info_by_id($beforecm->section, MUST_EXIST);
        }

        $affectedcmids = [];
        $duplicatedpairs = [];
        foreach ($cms as $cm) {
            // Duplicate_module already handles file/data copying; keep track of old-to-new ids.
            if ($newcm = duplicate_module($course, $cm)) {
                $duplicatedpairs[$cm->id] = $newcm->id;
                if ($targetsection) {
                    // Honor the explicit destination, optionally positioning before a sibling.
                    moveto_module($newcm, $targetsection, $beforecm);
                } else {
                    $affectedcmids[] = $newcm->id;
                }
            }
        }

        // Reuse mimo tags on every freshly duplicated cm.
        foreach ($duplicatedpairs as $sourceid => $duplicateid) {
            $this->copy_cm_tag($sourceid, $duplicateid);
        }

        // Ask course format state to refresh the specific scope we mutated.
        if ($targetsection) {
            $this->section_state($updates, $course, [$targetsection->id]);
        } else {
            $this->cm_state($updates, $course, $affectedcmids);
        }
    }

    /**
     * Copy tag assignment from the source course module to its duplicate.
     *
     * @param int $sourcecmid Original cm id
     * @param int $duplicatedcmid New cm id
     */
    protected function copy_cm_tag(int $sourcecmid, int $duplicatedcmid): void {
        if ($sourcecmid === $duplicatedcmid) {
            return;
        }

        // Read the existing assignment once and short-circuit if the source had no tag.
        $tag = tag_manager::get_cm_tag($sourcecmid);
        if (!$tag || empty($tag->id)) {
            return;
        }

        // Persist the mapping for the duplicate; tag_manager abstracts cache updates.
        tag_manager::assign_tag_to_cm($duplicatedcmid, (int)$tag->id);
    }

    /**
     * Show course modules and clear done flag.
     *
     * @param stateupdates $updates the affected course elements track
     * @param stdClass $course the course object
     * @param int[] $ids cm ids
     * @param int|null $targetsectionid not used
     * @param int|null $targetcmid not used
     */
    public function cm_show(
        stateupdates $updates,
        stdClass $course,
        array $ids = [],
        ?int $targetsectionid = null,
        ?int $targetcmid = null
    ): void {
        parent::cm_show($updates, $course, $ids, $targetsectionid, $targetcmid);
        // Clear done flag only after parent has validated capabilities and applied the visibility change.
        foreach ($ids as $cmid) {
            done_manager::unset_done($cmid);
        }
    }

    /**
     * Hide course modules and clear done flag.
     *
     * @param stateupdates $updates the affected course elements track
     * @param stdClass $course the course object
     * @param int[] $ids cm ids
     * @param int|null $targetsectionid not used
     * @param int|null $targetcmid not used
     */
    public function cm_hide(
        stateupdates $updates,
        stdClass $course,
        array $ids = [],
        ?int $targetsectionid = null,
        ?int $targetcmid = null
    ): void {
        parent::cm_hide($updates, $course, $ids, $targetsectionid, $targetcmid);
        // Clear done flag only after parent has validated capabilities and applied the visibility change.
        foreach ($ids as $cmid) {
            done_manager::unset_done($cmid);
        }
    }

    /**
     * Stealth course modules and clear done flag.
     *
     * @param stateupdates $updates the affected course elements track
     * @param stdClass $course the course object
     * @param int[] $ids cm ids
     * @param int|null $targetsectionid not used
     * @param int|null $targetcmid not used
     */
    public function cm_stealth(
        stateupdates $updates,
        stdClass $course,
        array $ids = [],
        ?int $targetsectionid = null,
        ?int $targetcmid = null
    ): void {
        parent::cm_stealth($updates, $course, $ids, $targetsectionid, $targetcmid);
        // Clear done flag only after parent has validated capabilities and applied the visibility change.
        foreach ($ids as $cmid) {
            done_manager::unset_done($cmid);
        }
    }

    /**
     * Mark course modules as done.
     *
     * @param stateupdates $updates the affected course elements track
     * @param stdClass $course the course object
     * @param int[] $ids cm ids
     * @param int|null $targetsectionid not used
     * @param int|null $targetcmid not used
     */
    public function cm_done(
        stateupdates $updates,
        stdClass $course,
        array $ids = [],
        ?int $targetsectionid = null,
        ?int $targetcmid = null
    ): void {
        $this->validate_cms(
            $course,
            $ids,
            __FUNCTION__,
            ['moodle/course:activityvisibility']
        );

        foreach ($ids as $cmid) {
            // Ensure the activity is visible (shown) when marking as done.
            set_coursemodule_visible($cmid, true, 1, false);
            done_manager::set_done($cmid);
        }

        rebuild_course_cache($course->id, false, true);
        $this->cm_state($updates, $course, $ids);
    }

    /**
     * Unmark course modules as done.
     *
     * @param stateupdates $updates the affected course elements track
     * @param stdClass $course the course object
     * @param int[] $ids cm ids
     * @param int|null $targetsectionid not used
     * @param int|null $targetcmid not used
     */
    public function cm_undone(
        stateupdates $updates,
        stdClass $course,
        array $ids = [],
        ?int $targetsectionid = null,
        ?int $targetcmid = null
    ): void {
        $this->validate_cms(
            $course,
            $ids,
            __FUNCTION__,
            ['moodle/course:activityvisibility']
        );

        foreach ($ids as $cmid) {
            done_manager::unset_done($cmid);
        }

        rebuild_course_cache($course->id, false, true);
        $this->cm_state($updates, $course, $ids);
    }
}
