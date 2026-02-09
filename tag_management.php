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
 * Tag management interface for minimoodlewall course format.
 *
 * @package    format_minimoodlewall
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use format_minimoodlewall\tag_manager;
use format_minimoodlewall\tagset_manager;
use format_minimoodlewall\design_manager;
use format_minimoodlewall\form\tag_form;
use format_minimoodlewall\form\tagset_form;

admin_externalpage_setup('format_minimoodlewall_tags');

$action = optional_param('action', '', PARAM_ALPHA);
$tagid = optional_param('tagid', 0, PARAM_INT);
$tagsetid = optional_param('tagsetid', 0, PARAM_INT);

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url('/course/format/minimoodlewall/tag_management.php');
$PAGE->set_title(get_string('tagmanagement', 'format_minimoodlewall'));
$PAGE->set_heading(get_string('tagmanagement', 'format_minimoodlewall'));

// Handle create/edit tagset.
if ($action === 'createtagset' || $action === 'edittagset') {
    $tagset = null;
    if ($action === 'edittagset' && $tagsetid) {
        $tagset = tagset_manager::get_tagset($tagsetid);
        if ($tagset) {
            $tagset->tagsetid = $tagset->id;
        }
    }

    $formurl = new moodle_url($PAGE->url, ['action' => $action, 'tagsetid' => $tagsetid]);
    $mform = new tagset_form($formurl);

    if ($tagset) {
        $mform->set_data($tagset);
    }

    if ($mform->is_cancelled()) {
        redirect($PAGE->url);
    } else if ($data = $mform->get_data()) {
        if (!empty($data->tagsetid)) {
            tagset_manager::update_tagset($data->tagsetid, [
                'name' => $data->name,
                'description' => $data->description ?? '',
            ]);
            $message = get_string('edittagset', 'format_minimoodlewall');
        } else {
            tagset_manager::create_tagset($data->name, $data->description ?? null);
            $message = get_string('createtagset', 'format_minimoodlewall');
        }
        redirect($PAGE->url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading($action === 'createtagset' ?
        get_string('createtagset', 'format_minimoodlewall') :
        get_string('edittagset', 'format_minimoodlewall'));
    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

// Handle create/edit tag.
if ($action === 'createtag' || $action === 'edittag') {
    $tag = null;
    if ($action === 'edittag' && $tagid) {
        $tag = tag_manager::get_tag($tagid);
        if ($tag) {
            $tag->tagid = $tag->id;
            $tagsetid = $tag->tagsetid;
        }
    }

    if (!$tagsetid) {
        redirect($PAGE->url, get_string('error_required_tagset', 'format_minimoodlewall'), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    // Pass the current URL with action parameter to the form.
    $formurl = new moodle_url($PAGE->url, ['action' => $action, 'tagid' => $tagid, 'tagsetid' => $tagsetid]);
    $mform = new tag_form($formurl, ['context' => $context, 'tagid' => $tag->id ?? 0]);

    // Prepare form data with design-specific image drafts.
    $formdata = [];
    $designs = design_manager::get_all_designs();
    $designids = array_keys($designs);

    if ($tag) {
        $formdata = (array) $tag;
        $formdata['tagsetid'] = $tagsetid;
        // Prepare draft areas for each design's images.
        foreach ($designs as $design) {
            $formdata['cardimage_design_' . $design->id] = design_manager::prepare_cardimage_draft($tag->id, $design->id);
            $formdata['filterimage_design_' . $design->id] = design_manager::prepare_filterimage_draft($tag->id, $design->id);
        }
        $formdata['designids'] = implode(',', $designids);
    } else {
        $formdata['tagsetid'] = $tagsetid;
        // New tag - prepare empty draft areas.
        foreach ($designs as $design) {
            $formdata['cardimage_design_' . $design->id] = 0;
            $formdata['filterimage_design_' . $design->id] = 0;
        }
        $formdata['designids'] = implode(',', $designids);
    }
    $mform->set_data($formdata);

    if ($mform->is_cancelled()) {
        redirect($PAGE->url);
    } else if ($data = $mform->get_data()) {
        if (!empty($data->tagid)) {
            // Update existing tag.
            tag_manager::update_tag(
                $data->tagid,
                [
                    'name' => $data->name,
                    'tagsetid' => $data->tagsetid,
                    'activitytype1' => $data->activitytype1,
                    'activitytype2' => $data->activitytype2,
                    'activitytype3' => $data->activitytype3,
                    'bgcolor' => $data->bgcolor,
                    'imgplacement' => $data->imgplacement,
                ]
            );
            $currenttagid = $data->tagid;
            $message = get_string('edittag', 'format_minimoodlewall');
        } else {
            // Create new tag.
            $currenttagid = tag_manager::create_tag(
                $data->tagsetid,
                $data->name,
                null,
                null,
                $data->activitytype1,
                $data->activitytype2,
                $data->activitytype3,
                $data->bgcolor,
                $data->imgplacement
            );
            $message = get_string('createtag', 'format_minimoodlewall');
        }

        // Save design-specific images.
        $saveddesignids = !empty($data->designids) ? explode(',', $data->designids) : [];
        foreach ($saveddesignids as $designid) {
            $cardfield = 'cardimage_design_' . $designid;
            $filterfield = 'filterimage_design_' . $designid;

            if (isset($data->$cardfield)) {
                design_manager::save_cardimage_from_draft($currenttagid, (int)$designid, (int)$data->$cardfield);
            }
            if (isset($data->$filterfield)) {
                design_manager::save_filterimage_from_draft($currenttagid, (int)$designid, (int)$data->$filterfield);
            }
        }

        redirect($PAGE->url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading($action === 'createtag' ?
        get_string('createtag', 'format_minimoodlewall') :
        get_string('edittag', 'format_minimoodlewall'));
    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

// Handle delete tagset.
if ($action === 'deletetagset' && confirm_sesskey()) {
    if ($tagsetid) {
        tagset_manager::delete_tagset($tagsetid);
        redirect(
            $PAGE->url,
            get_string('deletetagset', 'format_minimoodlewall'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Handle delete tag.
if ($action === 'deletetag' && confirm_sesskey()) {
    if ($tagid) {
        tag_manager::delete_tag($tagid);
        redirect(
            $PAGE->url,
            get_string('deletetag', 'format_minimoodlewall'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('tagmanagement', 'format_minimoodlewall'));

// Initialize delete confirmation modal.
$PAGE->requires->js_call_amd('format_minimoodlewall/tag_delete_confirm', 'init');

// Build template context with tagsets and their tags.
$tagsets = tagset_manager::get_all_tagsets();

$templatecontext = [
    'createtagseturl' => (new moodle_url($PAGE->url, ['action' => 'createtagset']))->out(false),
    'createtagsettext' => get_string('createtagset', 'format_minimoodlewall'),
    'notagsetstext' => get_string('notagsets', 'format_minimoodlewall'),
    'notagstext' => get_string('notags', 'format_minimoodlewall'),
    'hastagsets' => !empty($tagsets),
    'tableheaders' => [
        'cardimage' => get_string('cardimage', 'format_minimoodlewall'),
        'name' => get_string('tagname', 'format_minimoodlewall'),
        'bgcolor' => get_string('tagbgcolor', 'format_minimoodlewall'),
        'activitytype1' => get_string('activitytype1', 'format_minimoodlewall'),
        'activitytype2' => get_string('activitytype2', 'format_minimoodlewall'),
        'activitytype3' => get_string('activitytype3', 'format_minimoodlewall'),
        'actions' => get_string('actions'),
    ],
    'tagsets' => [],
];

foreach ($tagsets as $tagset) {
    $tags = tag_manager::get_tags_by_tagset($tagset->id);

    $tagsetdata = [
        'id' => $tagset->id,
        'name' => format_string($tagset->name),
        'description' => format_string($tagset->description ?? ''),
        'hastags' => !empty($tags),
        'createtagurl' => (new moodle_url($PAGE->url, [
            'action' => 'createtag',
            'tagsetid' => $tagset->id,
        ]))->out(false),
        'edittagseturl' => (new moodle_url($PAGE->url, [
            'action' => 'edittagset',
            'tagsetid' => $tagset->id,
        ]))->out(false),
        'deletetagseturl' => (new moodle_url($PAGE->url, [
            'action' => 'deletetagset',
            'tagsetid' => $tagset->id,
            'sesskey' => sesskey(),
        ]))->out(false),
        'edittagsettitle' => get_string('edittagset', 'format_minimoodlewall'),
        'deletetagsettitle' => get_string('deletetagset', 'format_minimoodlewall'),
        'tags' => [],
    ];

    foreach ($tags as $tag) {
        $cardimgurl = tag_manager::get_cardimage_url($tag);
        $accentcolor = tag_manager::get_tag_accent_color($tag);

        $tagsetdata['tags'][] = [
            'id' => $tag->id,
            'name' => format_string($tag->name),
            'cardimageurl' => $cardimgurl ? $cardimgurl->out(false) : null,
            'bgcolor' => $accentcolor,
            'activitytype1' => $tag->activitytype1,
            'activitytype2' => $tag->activitytype2 ?: '-',
            'activitytype3' => $tag->activitytype3 ?: '-',
            'editurl' => (new moodle_url($PAGE->url, [
                'action' => 'edittag',
                'tagid' => $tag->id,
                'tagsetid' => $tagset->id,
            ]))->out(false),
            'deleteurl' => (new moodle_url($PAGE->url, [
                'action' => 'deletetag',
                'tagid' => $tag->id,
                'sesskey' => sesskey(),
            ]))->out(false),
            'edittitle' => get_string('edittag', 'format_minimoodlewall'),
            'deletetitle' => get_string('deletetag', 'format_minimoodlewall'),
        ];
    }

    $templatecontext['tagsets'][] = $tagsetdata;
}

echo $OUTPUT->render_from_template('format_minimoodlewall/tag_management', $templatecontext);

echo $OUTPUT->footer();
