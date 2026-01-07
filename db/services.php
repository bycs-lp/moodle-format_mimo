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
 * Web service definitions for minimoodlewall format.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'format_minimoodlewall_get_tags' => [
        'classname'   => 'format_minimoodlewall\external\get_tags',
        'methodname'  => 'execute',
        'description' => 'Get tags selected for a course',
        'type'        => 'read',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'format_minimoodlewall_assign_tag' => [
        'classname'   => 'format_minimoodlewall\external\assign_tag',
        'methodname'  => 'execute',
        'description' => 'Assign a tag to a course module',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'format_minimoodlewall_store_pending_tag' => [
        'classname'   => 'format_minimoodlewall\external\store_pending_tag',
        'methodname'  => 'execute',
        'description' => 'Store a pending tag in session for assignment after activity creation',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'format_minimoodlewall_get_activity_descriptions' => [
        'classname'   => 'format_minimoodlewall\external\get_activity_descriptions',
        'methodname'  => 'execute',
        'description' => 'Get descriptions for activity types',
        'type'        => 'read',
        'ajax'        => true,
        'loginrequired' => true,
    ],
];
