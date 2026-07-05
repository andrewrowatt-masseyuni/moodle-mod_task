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
 * Library of interface functions and constants for mod_task.
 *
 * @package    mod_task
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Declare which optional module features the Task activity supports.
 *
 * @param string $feature one of the FEATURE_* constants
 * @return mixed true/false for booleans, a value for others, or null when unknown
 */
function task_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_GROUPS:
            return false;
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_COLLABORATION;
        default:
            return null;
    }
}

/**
 * Editor options for the task description and teacher response fields.
 *
 * @param context $context the module context
 * @return array editor options
 */
function task_get_editor_options(context $context): array {
    global $CFG;
    return [
        'subdirs' => 1,
        'maxbytes' => $CFG->maxbytes,
        'maxfiles' => EDITOR_UNLIMITED_FILES,
        'changeformat' => 1,
        'context' => $context,
        'noclean' => 0,
        'trusttext' => 0,
    ];
}

/**
 * Add a new Task instance.
 *
 * @param stdClass $data form data (with $data->coursemodule set by core)
 * @param mod_task_mod_form|null $mform the form
 * @return int the new instance id
 */
function task_add_instance($data, $mform = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = time();
    $data->teacherresponseismodelanswer = empty($data->teacherresponseismodelanswer) ? 0 : 1;
    $data->embedoncoursepage = empty($data->embedoncoursepage) ? 0 : 1;
    $data->anonymousposts = empty($data->anonymousposts) ? 0 : 1;
    if (!array_key_exists($data->tasktype ?? '', \mod_task\manager::get_task_type_options())) {
        $data->tasktype = \mod_task\manager::default_task_type();
    }

    $data->id = $DB->insert_record('task', $data);

    // Now that the instance (and its module context) exist, persist the teacher
    // response and its embedded files. The task description rides on the standard
    // intro field, which core saves for us.
    if ($mform) {
        $context = context_module::instance($data->coursemodule);
        $options = task_get_editor_options($context);
        $data = file_postupdate_standard_editor(
            $data,
            'teacherresponse',
            $options,
            $context,
            'mod_task',
            'teacherresponse',
            0
        );
        $DB->update_record('task', $data);
    }

    return $data->id;
}

/**
 * Update an existing Task instance.
 *
 * @param stdClass $data form data (with $data->instance set)
 * @param mod_task_mod_form|null $mform the form
 * @return bool
 */
function task_update_instance($data, $mform = null) {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();
    $data->teacherresponseismodelanswer = empty($data->teacherresponseismodelanswer) ? 0 : 1;
    $data->embedoncoursepage = empty($data->embedoncoursepage) ? 0 : 1;
    $data->anonymousposts = empty($data->anonymousposts) ? 0 : 1;
    if (!array_key_exists($data->tasktype ?? '', \mod_task\manager::get_task_type_options())) {
        $data->tasktype = \mod_task\manager::default_task_type();
    }

    if ($mform) {
        $context = context_module::instance($data->coursemodule);
        $options = task_get_editor_options($context);
        $data = file_postupdate_standard_editor(
            $data,
            'teacherresponse',
            $options,
            $context,
            'mod_task',
            'teacherresponse',
            0
        );
    }

    return $DB->update_record('task', $data);
}

/**
 * Delete a Task instance and all of its data.
 *
 * @param int $id the instance id
 * @return bool
 */
function task_delete_instance($id) {
    global $DB;

    $task = $DB->get_record('task', ['id' => $id]);
    if (!$task) {
        return false;
    }

    $postids = $DB->get_fieldset_select('task_post', 'id', 'taskid = :taskid', ['taskid' => $id]);
    if ($postids) {
        [$insql, $params] = $DB->get_in_or_equal($postids, SQL_PARAMS_NAMED, 'p');
        $DB->delete_records_select('task_reaction', "postid $insql", $params);
    }
    $DB->delete_records('task_post', ['taskid' => $id]);
    $DB->delete_records('task_lastviewed', ['taskid' => $id]);
    $DB->delete_records('task_notifypref', ['taskid' => $id]);
    $DB->delete_records('task', ['id' => $id]);

    return true;
}

/**
 * Provide cached course-module information (name and, optionally, description).
 *
 * @param stdClass $coursemodule the course module record
 * @return cached_cm_info|false
 */
function task_get_coursemodule_info($coursemodule) {
    global $DB;

    $fields = 'id, name, intro, introformat, embedoncoursepage, '
        . 'completionrespond, completionreply, completionreact';
    $task = $DB->get_record('task', ['id' => $coursemodule->instance], $fields);
    if (!$task) {
        return false;
    }

    $info = new cached_cm_info();
    $info->name = $task->name;
    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('task', $task, $coursemodule->id, false);
    }
    $info->customdata = ['embedoncoursepage' => (bool) $task->embedoncoursepage];

    // Populate the custom completion rules as key => value pairs, but only if
    // completion is enabled for this module.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completionrespond'] = $task->completionrespond;
        $info->customdata['customcompletionrules']['completionreply'] = $task->completionreply;
        $info->customdata['customcompletionrules']['completionreact'] = $task->completionreact;
    }

    return $info;
}

/**
 * Human-readable descriptions of the active custom completion rules,
 * shown in the course editor and activity chooser.
 *
 * @param cm_info $cm the course module
 * @return array of description strings, one per active rule
 */
function mod_task_get_completion_active_rule_descriptions($cm) {
    if (empty($cm->customdata['customcompletionrules']) || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }

    $descriptions = [];
    foreach ($cm->customdata['customcompletionrules'] as $key => $val) {
        if (empty($val)) {
            continue;
        }
        switch ($key) {
            case 'completionrespond':
                $descriptions[] = get_string('completionresponddesc', 'task');
                break;
            case 'completionreply':
                $descriptions[] = get_string('completionreplydesc', 'task');
                break;
            case 'completionreact':
                $descriptions[] = get_string('completionreactdesc', 'task');
                break;
        }
    }
    return $descriptions;
}

/**
 * Suppress the Task's own view link when it should embed inline instead.
 *
 * Must run from the _cm_info_dynamic hook: cm_info::set_no_view_link() throws
 * if called later, from _cm_info_view (see cm_info::check_not_view_only()).
 *
 * @param cm_info $cm the course module
 */
function task_cm_info_dynamic(cm_info $cm) {
    if (!empty($cm->customdata['embedoncoursepage'])) {
        $cm->set_no_view_link();
    }
}

/**
 * On the course page: either embed the Task's interactive widget inline
 * (when "Embed on course page" is on), or add the "x new responses" badge.
 *
 * Runs per user per request, so the badge count respects the
 * answer-before-you-see gating: students who have not yet responded see no
 * count.
 *
 * @param cm_info $cm the course module
 */
function task_cm_info_view(cm_info $cm) {
    global $USER, $PAGE;

    if (!$cm->uservisible) {
        return;
    }

    if (!empty($cm->customdata['embedoncoursepage'])) {
        $context = context_module::instance($cm->id);
        // Use $PAGE->get_renderer() rather than $OUTPUT: early in the course-page
        // lifecycle $OUTPUT can still be the bootstrap renderer, not a renderer_base.
        $cm->set_content(
            \mod_task\output\embed::placeholder($PAGE->get_renderer('core'), $cm->id, $context->id, true),
            true
        );
        $cm->set_custom_cmlist_item(true);
        return;
    }

    if (get_config('mod_task', 'showcardbadge') === '0') {
        return;
    }

    $count = \mod_task\manager::count_new_responses($cm, (int)$USER->id);
    if ($count <= 0) {
        return;
    }

    $label = ($count == 1)
        ? get_string('onenewresponse', 'mod_task')
        : get_string('xnewresponses', 'mod_task', $count);
    $cm->set_after_link(' ' . html_writer::span($label, 'badge badge-secondary'));
}

/**
 * Serve files belonging to a Task instance (description, teacher response, intro).
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module
 * @param context $context the module context
 * @param string $filearea the file area
 * @param array $args the remaining file path arguments
 * @param bool $forcedownload whether to force download
 * @param array $options additional options
 * @return bool false when the file cannot be served
 */
function task_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, true, $cm);

    if (!has_capability('mod/task:view', $context)) {
        return false;
    }

    $allowedareas = ['intro', 'teacherresponse'];
    if (!in_array($filearea, $allowedareas, true)) {
        return false;
    }

    // The teacher response is only ever served to a user who is allowed to see it.
    if (
        $filearea === 'teacherresponse'
            && !\mod_task\manager::can_see_responses($context, (int)$cm->instance)
    ) {
        return false;
    }

    // Intro URLs are rewritten with a null itemid (see format_module_intro()),
    // so they carry no itemid path segment; teacherresponse URLs carry /0/.
    $itemid = ($filearea === 'intro') ? 0 : (int)array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_task', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, null, 0, $forcedownload, $options);
}
