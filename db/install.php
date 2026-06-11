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
 * Post-installation script for format_mimo.
 *
 * @package    format_mimo
 * @copyright  2025 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Post installation procedure.
 */
function xmldb_format_mimo_install() {
    // Initialize default tags.
    \format_mimo\tag_manager::initialize_default_tags();
    // Initialize default activity profiles (primaryschool, secondaryschool, foreignlanguage).
    \format_mimo\profile_manager::initialize_default_profiles();
    // Initialize default description tags.
    \format_mimo\description_tag_manager::initialize_default_description_tags();
    // Initialize default activity descriptions for all activity types.
    \format_mimo\activity_description_manager::initialize_default_activity_descriptions();
    // Initialize default completion overrides per module type.
    \format_mimo\completion_defaults_manager::initialize_default_completion_defaults();
}
