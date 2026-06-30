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

namespace mod_task\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\helper;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for mod_task.
 *
 * @package    mod_task
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    core_userlist_provider,
    metadata_provider,
    plugin_provider {
    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection the collection to add metadata to
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('task_post', [
            'taskid' => 'privacy:metadata:task_post:taskid',
            'userid' => 'privacy:metadata:task_post:userid',
            'content' => 'privacy:metadata:task_post:content',
            'anonymous' => 'privacy:metadata:task_post:anonymous',
            'timecreated' => 'privacy:metadata:task_post:timecreated',
        ], 'privacy:metadata:task_post');

        $collection->add_database_table('task_reaction', [
            'postid' => 'privacy:metadata:task_reaction:postid',
            'userid' => 'privacy:metadata:task_reaction:userid',
            'emoji' => 'privacy:metadata:task_reaction:emoji',
            'timecreated' => 'privacy:metadata:task_reaction:timecreated',
        ], 'privacy:metadata:task_reaction');

        $collection->add_database_table('task_lastviewed', [
            'taskid' => 'privacy:metadata:task_lastviewed:taskid',
            'userid' => 'privacy:metadata:task_lastviewed:userid',
            'timeviewed' => 'privacy:metadata:task_lastviewed:timeviewed',
        ], 'privacy:metadata:task_lastviewed');

        return $collection;
    }

    /**
     * Get the list of module contexts that contain user data for the given user.
     *
     * @param int $userid the user id
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :modlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {task} t ON t.id = cm.instance
             LEFT JOIN {task_post} p ON p.taskid = t.id AND p.userid = :puserid
             LEFT JOIN {task_lastviewed} lv ON lv.taskid = t.id AND lv.userid = :lvuserid
             LEFT JOIN {task_post} rp ON rp.taskid = t.id
             LEFT JOIN {task_reaction} r ON r.postid = rp.id AND r.userid = :ruserid
                 WHERE p.id IS NOT NULL OR lv.id IS NOT NULL OR r.id IS NOT NULL";
        $params = [
            'modlevel' => CONTEXT_MODULE,
            'modname' => 'task',
            'puserid' => $userid,
            'lvuserid' => $userid,
            'ruserid' => $userid,
        ];
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist the userlist to add users to
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $params = ['cmid' => $context->instanceid, 'modname' => 'task'];

        $sql = "SELECT p.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {task_post} p ON p.taskid = cm.instance
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT lv.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {task_lastviewed} lv ON lv.taskid = cm.instance
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT r.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {task_post} p ON p.taskid = cm.instance
                  JOIN {task_reaction} r ON r.postid = p.id
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all user data for the approved contexts.
     *
     * @param approved_contextlist $contextlist the approved contexts
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('task', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $contextdata = helper::get_context_data($context, $user);
            writer::with_context($context)->export_data([], $contextdata);

            // Posts authored by this user.
            $posts = $DB->get_records('task_post', ['taskid' => $cm->instance, 'userid' => $user->id], 'timecreated ASC');
            foreach ($posts as $post) {
                writer::with_context($context)->export_data(
                    ['posts', $post->id],
                    (object) [
                        'content' => $post->content,
                        'anonymous' => transform::yesno($post->anonymous),
                        'deleted' => transform::yesno($post->deleted),
                        'timecreated' => transform::datetime($post->timecreated),
                    ]
                );
            }

            // Reactions added by this user.
            $sql = "SELECT r.id, r.emoji, r.timecreated, r.postid
                      FROM {task_reaction} r
                      JOIN {task_post} p ON p.id = r.postid
                     WHERE p.taskid = :taskid AND r.userid = :userid";
            $reactions = $DB->get_records_sql($sql, ['taskid' => $cm->instance, 'userid' => $user->id]);
            foreach ($reactions as $reaction) {
                writer::with_context($context)->export_data(
                    ['reactions', $reaction->id],
                    (object) [
                        'postid' => $reaction->postid,
                        'emoji' => $reaction->emoji,
                        'timecreated' => transform::datetime($reaction->timecreated),
                    ]
                );
            }

            // Last viewed.
            if ($lv = $DB->get_record('task_lastviewed', ['taskid' => $cm->instance, 'userid' => $user->id])) {
                writer::with_context($context)->export_data(
                    ['lastviewed'],
                    (object) ['timeviewed' => transform::datetime($lv->timeviewed)]
                );
            }
        }
    }

    /**
     * Delete all user data in a context.
     *
     * @param \context $context the context to delete in
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('task', $context->instanceid);
        if (!$cm) {
            return;
        }

        $postids = $DB->get_fieldset_select('task_post', 'id', 'taskid = :taskid', ['taskid' => $cm->instance]);
        if ($postids) {
            [$insql, $params] = $DB->get_in_or_equal($postids, SQL_PARAMS_NAMED, 'p');
            $DB->delete_records_select('task_reaction', "postid $insql", $params);
        }
        $DB->delete_records('task_post', ['taskid' => $cm->instance]);
        $DB->delete_records('task_lastviewed', ['taskid' => $cm->instance]);
    }

    /**
     * Delete all data for a user across the approved contexts.
     *
     * @param approved_contextlist $contextlist the approved contexts
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('task', $context->instanceid);
            if (!$cm) {
                continue;
            }
            self::delete_user_data($cm->instance, [$userid]);
        }
    }

    /**
     * Delete data for the approved users within a context.
     *
     * @param approved_userlist $userlist the approved users
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('task', $context->instanceid);
        if (!$cm) {
            return;
        }
        self::delete_user_data($cm->instance, $userlist->get_userids());
    }

    /**
     * Remove a set of users' reactions and last-viewed rows, and blank their posts.
     *
     * Posts are soft-deleted (content blanked) rather than removed so reply threads
     * authored by others remain intact.
     *
     * @param int $taskid the task instance id
     * @param int[] $userids the user ids
     */
    protected static function delete_user_data(int $taskid, array $userids): void {
        global $DB;

        if (empty($userids)) {
            return;
        }
        [$userinsql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');

        // Delete reactions by these users in this task.
        $postids = $DB->get_fieldset_select('task_post', 'id', 'taskid = :taskid', ['taskid' => $taskid]);
        if ($postids) {
            [$postinsql, $postparams] = $DB->get_in_or_equal($postids, SQL_PARAMS_NAMED, 'p');
            $DB->delete_records_select(
                'task_reaction',
                "postid $postinsql AND userid $userinsql",
                $postparams + $userparams
            );
        }

        // Soft-delete posts authored by these users.
        $DB->set_field_select(
            'task_post',
            'content',
            '',
            "taskid = :taskid AND userid $userinsql",
            ['taskid' => $taskid] + $userparams
        );
        $DB->set_field_select(
            'task_post',
            'deleted',
            1,
            "taskid = :taskid AND userid $userinsql",
            ['taskid' => $taskid] + $userparams
        );

        // Remove last-viewed rows.
        $DB->delete_records_select(
            'task_lastviewed',
            "taskid = :taskid AND userid $userinsql",
            ['taskid' => $taskid] + $userparams
        );
    }
}
