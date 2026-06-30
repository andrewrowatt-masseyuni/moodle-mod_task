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
 * Web service definitions for mod_task.
 *
 * @package    mod_task
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_task_get_task' => [
        'classname' => 'mod_task\external\get_task',
        'description' => 'Load the gated Task view for the current user.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'mod_task_create_response' => [
        'classname' => 'mod_task\external\create_response',
        'description' => 'Create a top-level response in a Task.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => 'mod/task:respond',
    ],
    'mod_task_create_reply' => [
        'classname' => 'mod_task\external\create_reply',
        'description' => 'Reply to a response or reply in a Task.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => 'mod/task:respond',
    ],
    'mod_task_edit_post' => [
        'classname' => 'mod_task\external\edit_post',
        'description' => 'Edit an existing response or reply.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => 'mod/task:respond,mod/task:manageresponses',
    ],
    'mod_task_delete_post' => [
        'classname' => 'mod_task\external\delete_post',
        'description' => 'Delete a response or reply.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => 'mod/task:respond,mod/task:manageresponses',
    ],
    'mod_task_react_post' => [
        'classname' => 'mod_task\external\react_post',
        'description' => 'Toggle an emoji reaction on a post.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => 'mod/task:respond',
    ],
    'mod_task_mark_viewed' => [
        'classname' => 'mod_task\external\mark_viewed',
        'description' => 'Record that the user has viewed the Task, clearing the new-response badge.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'mod_task_set_notification_preference' => [
        'classname' => 'mod_task\external\set_notification_preference',
        'description' => 'Set the current user\'s notification preference for a Task.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => 'mod/task:respond',
    ],
];
