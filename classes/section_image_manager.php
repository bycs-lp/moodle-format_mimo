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
 * Section image manager for format_mimo.
 *
 * Handles upload, retrieval, and deletion of overview card images
 * for course sections in multi-section mode.
 *
 * @package    format_mimo
 * @copyright  2026 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mimo;

use moodle_url;

/**
 * Manages section-level overview card images.
 *
 * Images are stored in the Moodle File API under course context with
 * the section ID as itemid. No database table is needed — file existence
 * is the source of truth.
 *
 * @package    format_mimo
 * @copyright  2026 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section_image_manager {
    /** File area for section overview card images. */
    public const FILEAREA = 'sectionimage';

    /** Component name. */
    public const COMPONENT = 'format_mimo';

    /** Filemanager options for section image uploads. */
    private const FILEMANAGER_OPTIONS = [
        'subdirs' => 0,
        'maxfiles' => 1,
        'maxbytes' => 0,
        'accepted_types' => ['.jpg', '.png', '.webp', '.svg'],
    ];

    /**
     * Get the filemanager options for section image uploads.
     *
     * @return array
     */
    public static function get_filemanager_options(): array {
        return self::FILEMANAGER_OPTIONS;
    }

    /**
     * Prepare a draft area for the section image filepicker.
     *
     * @param int $courseid Course ID
     * @param int $sectionid Section ID (course_sections.id)
     * @return int Draft item ID populated with existing file (if any)
     */
    public static function prepare_draft(int $courseid, int $sectionid): int {
        $context = \core\context\course::instance($courseid);
        $draftitemid = file_get_submitted_draft_itemid('sectionimagefile');
        file_prepare_draft_area(
            $draftitemid,
            $context->id,
            self::COMPONENT,
            self::FILEAREA,
            $sectionid,
            self::FILEMANAGER_OPTIONS
        );
        return $draftitemid;
    }

    /**
     * Save a section image from a draft area.
     *
     * @param int $courseid Course ID
     * @param int $sectionid Section ID (course_sections.id)
     * @param int $draftitemid Draft area identifier
     */
    public static function save_image(int $courseid, int $sectionid, int $draftitemid): void {
        $context = \core\context\course::instance($courseid);
        file_save_draft_area_files(
            $draftitemid,
            $context->id,
            self::COMPONENT,
            self::FILEAREA,
            $sectionid,
            self::FILEMANAGER_OPTIONS
        );
    }

    /**
     * Check whether a section has an uploaded image.
     *
     * @param int $courseid Course ID
     * @param int $sectionid Section ID (course_sections.id)
     * @return bool
     */
    public static function has_image(int $courseid, int $sectionid): bool {
        return (bool) self::get_stored_file($courseid, $sectionid);
    }

    /**
     * Get the pluginfile URL for a section's overview image.
     *
     * @param int $courseid Course ID
     * @param int $sectionid Section ID (course_sections.id)
     * @return moodle_url|null URL or null if no image exists
     */
    public static function get_image_url(int $courseid, int $sectionid): ?moodle_url {
        $file = self::get_stored_file($courseid, $sectionid);
        if (!$file) {
            return null;
        }
        return moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        );
    }

    /**
     * Get pluginfile URLs for all section images in a course in a single file-storage lookup.
     *
     * Intended for overview rendering where every section may need its image URL.
     * Returns a map keyed by section ID (course_sections.id → itemid). Sections
     * without an uploaded image are absent from the map.
     *
     * @param int $courseid Course ID
     * @return array<int, moodle_url> Map of sectionid => URL.
     */
    public static function get_image_urls_for_course(int $courseid): array {
        $context = \core\context\course::instance($courseid);
        $files = get_file_storage()->get_area_files(
            $context->id,
            self::COMPONENT,
            self::FILEAREA,
            false,
            '',
            false
        );

        $urls = [];
        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $urls[(int) $file->get_itemid()] = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            );
        }
        return $urls;
    }

    /**
     * Delete the section image for a specific section.
     *
     * @param int $courseid Course ID
     * @param int $sectionid Section ID (course_sections.id)
     */
    public static function delete_image(int $courseid, int $sectionid): void {
        $context = \core\context\course::instance($courseid);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, self::COMPONENT, self::FILEAREA, $sectionid);
    }

    /**
     * Delete all section images for a course.
     *
     * Used during course deletion cleanup.
     *
     * @param int $courseid Course ID
     */
    public static function delete_all_for_course(int $courseid): void {
        $context = \core\context\course::instance($courseid);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, self::COMPONENT, self::FILEAREA);
    }

    /**
     * Get the stored file object for a section image.
     *
     * @param int $courseid Course ID
     * @param int $sectionid Section ID (course_sections.id)
     * @return \stored_file|null
     */
    private static function get_stored_file(int $courseid, int $sectionid): ?\stored_file {
        $context = \core\context\course::instance($courseid);
        $files = get_file_storage()->get_area_files(
            $context->id,
            self::COMPONENT,
            self::FILEAREA,
            $sectionid,
            '',
            false
        );
        return !empty($files) ? reset($files) : null;
    }
}
