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
 * Style manager for format_minimoodlewall.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_minimoodlewall;

use context_system;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Style manager class for handling style variants.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class style_manager {

    /** Database table for styles. */
    private const TABLE_STYLES = 'format_minimoodlewall_styles';

    /** Database table for tag images. */
    private const TABLE_TAG_IMAGES = 'format_minimoodlewall_tag_images';

    /** File area for style-specific card images. */
    public const FILEAREA_STYLE_CARDIMAGE = 'styletagcard';

    /** File area for style-specific filter images. */
    public const FILEAREA_STYLE_FILTERIMAGE = 'styletagfilter';

    /** Filemanager options for image uploads. */
    private const FILEMANAGER_OPTIONS = [
        'maxbytes' => 1048576, // 1 MB.
        'maxfiles' => 1,
        'accepted_types' => ['.svg', '.png', '.jpg', '.jpeg', '.gif'],
        'subdirs' => 0,
    ];

    /**
     * Get all styles ordered by sortorder.
     *
     * @return array Array of style objects keyed by id
     */
    public static function get_all_styles(): array {
        global $DB;
        return $DB->get_records(self::TABLE_STYLES, null, 'sortorder ASC, id ASC');
    }

    /**
     * Get a single style by ID.
     *
     * @param int $id Style ID
     * @return stdClass|null
     */
    public static function get_style(int $id): ?stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE_STYLES, ['id' => $id]);
        return $record ?: null;
    }

    /**
     * Get a style by its internal name.
     *
     * @param string $name Style name (e.g., 'classic', 'light', 'dark')
     * @return stdClass|null
     */
    public static function get_style_by_name(string $name): ?stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE_STYLES, ['name' => $name]);
        return $record ?: null;
    }

    /**
     * Create a new style.
     *
     * @param string $name Internal identifier
     * @param string $displayname Human-readable name
     * @param int|null $sortorder Sort order (auto-calculated if null)
     * @return int The new style ID
     */
    public static function create_style(string $name, string $displayname, ?int $sortorder = null): int {
        global $DB;

        if ($sortorder === null) {
            $maxorder = $DB->get_field_sql(
                "SELECT MAX(sortorder) FROM {" . self::TABLE_STYLES . "}"
            );
            $sortorder = ($maxorder ?? 0) + 1;
        }

        $now = time();
        $record = new stdClass();
        $record->name = $name;
        $record->displayname = $displayname;
        $record->sortorder = $sortorder;
        $record->timecreated = $now;
        $record->timemodified = $now;

        return $DB->insert_record(self::TABLE_STYLES, $record);
    }

    /**
     * Update an existing style.
     *
     * @param int $id Style ID
     * @param array $data Fields to update
     * @return bool
     */
    public static function update_style(int $id, array $data): bool {
        global $DB;

        $record = new stdClass();
        $record->id = $id;
        $record->timemodified = time();

        foreach ($data as $field => $value) {
            if (in_array($field, ['name', 'displayname', 'sortorder'])) {
                $record->$field = $value;
            }
        }

        return $DB->update_record(self::TABLE_STYLES, $record);
    }

    /**
     * Delete a style and all associated tag images.
     *
     * @param int $id Style ID
     * @return bool
     */
    public static function delete_style(int $id): bool {
        global $DB;

        // Delete associated tag images files.
        $tagimages = $DB->get_records(self::TABLE_TAG_IMAGES, ['styleid' => $id]);
        foreach ($tagimages as $tagimage) {
            self::delete_tag_image_files($tagimage->id);
        }

        // Delete tag image records.
        $DB->delete_records(self::TABLE_TAG_IMAGES, ['styleid' => $id]);

        // Delete the style.
        return $DB->delete_records(self::TABLE_STYLES, ['id' => $id]);
    }

    /**
     * Get styles as options array for select elements.
     *
     * @return array name => displayname
     */
    public static function get_style_options(): array {
        $styles = self::get_all_styles();
        $options = [];
        foreach ($styles as $style) {
            $options[$style->name] = $style->displayname;
        }
        return $options;
    }

    /**
     * Get or create tag_images record for a tag/style combination.
     *
     * @param int $tagid Tag ID
     * @param int $styleid Style ID
     * @return stdClass
     */
    public static function get_or_create_tag_image(int $tagid, int $styleid): stdClass {
        global $DB;

        $record = $DB->get_record(self::TABLE_TAG_IMAGES, [
            'tagid' => $tagid,
            'styleid' => $styleid,
        ]);

        if (!$record) {
            $now = time();
            $record = new stdClass();
            $record->tagid = $tagid;
            $record->styleid = $styleid;
            $record->cardimage = null;
            $record->filterimage = null;
            $record->timecreated = $now;
            $record->timemodified = $now;
            $record->id = $DB->insert_record(self::TABLE_TAG_IMAGES, $record);
        }

        return $record;
    }

    /**
     * Get tag_images record by ID.
     *
     * @param int $id Tag images record ID
     * @return stdClass|null
     */
    public static function get_tag_image(int $id): ?stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE_TAG_IMAGES, ['id' => $id]);
        return $record ?: null;
    }

    /**
     * Get all tag_images records for a tag.
     *
     * @param int $tagid Tag ID
     * @return array Array of tag_images objects keyed by styleid
     */
    public static function get_tag_images_for_tag(int $tagid): array {
        global $DB;
        return $DB->get_records(self::TABLE_TAG_IMAGES, ['tagid' => $tagid], '', '*', 0, 0, 'styleid');
    }

    /**
     * Get tag_images record for a specific tag and style.
     *
     * @param int $tagid Tag ID
     * @param int $styleid Style ID
     * @return stdClass|null
     */
    public static function get_tag_image_for_style(int $tagid, int $styleid): ?stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE_TAG_IMAGES, [
            'tagid' => $tagid,
            'styleid' => $styleid,
        ]);
        return $record ?: null;
    }

    /**
     * Retrieve the shared filemanager options for style image uploads.
     *
     * @return array
     */
    public static function get_image_filemanager_options(): array {
        return self::FILEMANAGER_OPTIONS;
    }

    /**
     * Prepare a draft area for the card image filemanager field.
     *
     * @param int $tagid Tag ID
     * @param int $styleid Style ID
     * @return int Draft item id
     */
    public static function prepare_cardimage_draft(int $tagid, int $styleid): int {
        $tagimage = self::get_tag_image_for_style($tagid, $styleid);
        $itemid = $tagimage ? $tagimage->id : 0;

        $draftitemid = file_get_submitted_draft_itemid("cardimage_style_{$styleid}");
        file_prepare_draft_area(
            $draftitemid,
            context_system::instance()->id,
            'format_minimoodlewall',
            self::FILEAREA_STYLE_CARDIMAGE,
            $itemid,
            self::get_image_filemanager_options()
        );

        return $draftitemid;
    }

    /**
     * Prepare a draft area for the filter image filemanager field.
     *
     * @param int $tagid Tag ID
     * @param int $styleid Style ID
     * @return int Draft item id
     */
    public static function prepare_filterimage_draft(int $tagid, int $styleid): int {
        $tagimage = self::get_tag_image_for_style($tagid, $styleid);
        $itemid = $tagimage ? $tagimage->id : 0;

        $draftitemid = file_get_submitted_draft_itemid("filterimage_style_{$styleid}");
        file_prepare_draft_area(
            $draftitemid,
            context_system::instance()->id,
            'format_minimoodlewall',
            self::FILEAREA_STYLE_FILTERIMAGE,
            $itemid,
            self::get_image_filemanager_options()
        );

        return $draftitemid;
    }

    /**
     * Save card image from draft area.
     *
     * @param int $tagid Tag ID
     * @param int $styleid Style ID
     * @param int $draftitemid Draft area ID
     */
    public static function save_cardimage_from_draft(int $tagid, int $styleid, int $draftitemid): void {
        self::save_image_from_draft($tagid, $styleid, $draftitemid, self::FILEAREA_STYLE_CARDIMAGE, 'cardimage');
    }

    /**
     * Save filter image from draft area.
     *
     * @param int $tagid Tag ID
     * @param int $styleid Style ID
     * @param int $draftitemid Draft area ID
     */
    public static function save_filterimage_from_draft(int $tagid, int $styleid, int $draftitemid): void {
        self::save_image_from_draft($tagid, $styleid, $draftitemid, self::FILEAREA_STYLE_FILTERIMAGE, 'filterimage');
    }

    /**
     * Shared helper to move files from a draft area into storage.
     *
     * @param int $tagid Tag ID
     * @param int $styleid Style ID
     * @param int $draftitemid Draft area ID
     * @param string $filearea File area
     * @param string $dbfield Database field to update
     */
    private static function save_image_from_draft(
        int $tagid,
        int $styleid,
        int $draftitemid,
        string $filearea,
        string $dbfield
    ): void {
        global $DB;

        // Ensure tag_images record exists.
        $tagimage = self::get_or_create_tag_image($tagid, $styleid);

        file_save_draft_area_files(
            $draftitemid,
            context_system::instance()->id,
            'format_minimoodlewall',
            $filearea,
            $tagimage->id,
            self::get_image_filemanager_options()
        );

        // Update filename in database.
        $file = self::get_image_file($tagimage->id, $filearea);
        $filename = $file ? $file->get_filename() : null;

        $DB->set_field(self::TABLE_TAG_IMAGES, $dbfield, $filename, ['id' => $tagimage->id]);
        $DB->set_field(self::TABLE_TAG_IMAGES, 'timemodified', time(), ['id' => $tagimage->id]);
    }

    /**
     * Get card image URL for a tag and style.
     *
     * @param int $tagid Tag ID
     * @param int $styleid Style ID
     * @return moodle_url|null
     */
    public static function get_cardimage_url(int $tagid, int $styleid): ?moodle_url {
        $tagimage = self::get_tag_image_for_style($tagid, $styleid);
        if (!$tagimage) {
            return null;
        }
        return self::get_image_url($tagimage->id, self::FILEAREA_STYLE_CARDIMAGE);
    }

    /**
     * Get filter image URL for a tag and style.
     *
     * @param int $tagid Tag ID
     * @param int $styleid Style ID
     * @return moodle_url|null
     */
    public static function get_filterimage_url(int $tagid, int $styleid): ?moodle_url {
        $tagimage = self::get_tag_image_for_style($tagid, $styleid);
        if (!$tagimage) {
            return null;
        }
        return self::get_image_url($tagimage->id, self::FILEAREA_STYLE_FILTERIMAGE);
    }

    /**
     * Get card image URL for a tag and style name.
     *
     * @param int $tagid Tag ID
     * @param string $stylename Style name (e.g., 'classic')
     * @return moodle_url|null
     */
    public static function get_cardimage_url_by_name(int $tagid, string $stylename): ?moodle_url {
        $style = self::get_style_by_name($stylename);
        if (!$style) {
            return null;
        }
        return self::get_cardimage_url($tagid, $style->id);
    }

    /**
     * Get filter image URL for a tag and style name.
     *
     * @param int $tagid Tag ID
     * @param string $stylename Style name (e.g., 'classic')
     * @return moodle_url|null
     */
    public static function get_filterimage_url_by_name(int $tagid, string $stylename): ?moodle_url {
        $style = self::get_style_by_name($stylename);
        if (!$style) {
            return null;
        }
        return self::get_filterimage_url($tagid, $style->id);
    }

    /**
     * Resolve the pluginfile URL for a stored file.
     *
     * @param int $tagimagesid Tag images record ID
     * @param string $filearea File area
     * @return moodle_url|null
     */
    private static function get_image_url(int $tagimagesid, string $filearea): ?moodle_url {
        $file = self::get_image_file($tagimagesid, $filearea);
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
     * Fetch the stored file object.
     *
     * @param int $tagimagesid Tag images record ID (itemid)
     * @param string $filearea File area
     * @return \stored_file|null
     */
    private static function get_image_file(int $tagimagesid, string $filearea): ?\stored_file {
        $files = get_file_storage()->get_area_files(
            context_system::instance()->id,
            'format_minimoodlewall',
            $filearea,
            $tagimagesid,
            '',
            false
        );

        if (empty($files)) {
            return null;
        }

        return reset($files);
    }

    /**
     * Delete all image files for a tag_images record.
     *
     * @param int $tagimagesid Tag images record ID
     */
    private static function delete_tag_image_files(int $tagimagesid): void {
        $fs = get_file_storage();
        $contextid = context_system::instance()->id;

        $fs->delete_area_files($contextid, 'format_minimoodlewall', self::FILEAREA_STYLE_CARDIMAGE, $tagimagesid);
        $fs->delete_area_files($contextid, 'format_minimoodlewall', self::FILEAREA_STYLE_FILTERIMAGE, $tagimagesid);
    }

    /**
     * Delete all tag_images records and files for a tag.
     *
     * @param int $tagid Tag ID
     */
    public static function delete_tag_images_for_tag(int $tagid): void {
        global $DB;

        $tagimages = $DB->get_records(self::TABLE_TAG_IMAGES, ['tagid' => $tagid]);
        foreach ($tagimages as $tagimage) {
            self::delete_tag_image_files($tagimage->id);
        }

        $DB->delete_records(self::TABLE_TAG_IMAGES, ['tagid' => $tagid]);
    }

    /**
     * Initialize default styles if they don't exist.
     * Called during plugin installation.
     */
    public static function initialize_default_styles(): void {
        $defaults = [
            ['name' => 'classic', 'displayname' => 'Classic', 'sortorder' => 1],
            ['name' => 'light', 'displayname' => 'Light', 'sortorder' => 2],
            ['name' => 'dark', 'displayname' => 'Dark', 'sortorder' => 3],
        ];

        foreach ($defaults as $style) {
            if (!self::get_style_by_name($style['name'])) {
                self::create_style($style['name'], $style['displayname'], $style['sortorder']);
            }
        }
    }
}
