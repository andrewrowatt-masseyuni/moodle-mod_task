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

    $fields = 'id, name, intro, introformat';
    $task = $DB->get_record('task', ['id' => $coursemodule->instance], $fields);
    if (!$task) {
        return false;
    }

    $info = new cached_cm_info();
    $info->name = $task->name;
    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('task', $task, $coursemodule->id, false);
    }
    return $info;
}

/**
 * Add the "x new responses" badge to the activity card on the course page.
 *
 * Runs per user per request, so the count respects the answer-before-you-see
 * gating: students who have not yet responded see no count.
 *
 * @param cm_info $cm the course module
 */
function task_cm_info_view(cm_info $cm) {
    global $USER;

    if (!$cm->uservisible) {
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

    $itemid = (int)array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_task', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, null, 0, $forcedownload, $options);
}
