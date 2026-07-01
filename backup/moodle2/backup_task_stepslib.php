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
 * Backup structure step for mod_task.
 *
 * @package    mod_task
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the complete Task structure for backup, with file and id annotations.
 *
 * @package    mod_task
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_task_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the backup structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $task = new backup_nested_element('task', ['id'], [
            'name', 'tasktype', 'intro', 'introformat',
            'teacherresponse', 'teacherresponseformat', 'teacherresponseismodelanswer',
            'timecreated', 'timemodified',
        ]);

        $posts = new backup_nested_element('posts');
        $post = new backup_nested_element('post', ['id'], [
            'parentid', 'userid', 'content', 'anonymous', 'edited', 'deleted',
            'timecreated', 'timemodified',
        ]);

        $reactions = new backup_nested_element('reactions');
        $reaction = new backup_nested_element('reaction', ['id'], ['userid', 'emoji', 'timecreated']);

        $lastvieweds = new backup_nested_element('lastvieweds');
        $lastviewed = new backup_nested_element('lastviewed', ['id'], ['userid', 'timeviewed']);

        $notifyprefs = new backup_nested_element('notifyprefs');
        $notifypref = new backup_nested_element('notifypref', ['id'], ['userid', 'preference', 'timemodified']);

        $task->add_child($posts);
        $posts->add_child($post);
        $post->add_child($reactions);
        $reactions->add_child($reaction);
        $task->add_child($lastvieweds);
        $lastvieweds->add_child($lastviewed);
        $task->add_child($notifyprefs);
        $notifyprefs->add_child($notifypref);

        $task->set_source_table('task', ['id' => backup::VAR_ACTIVITYID]);

        if ($userinfo) {
            // Order by id ascending so a reply's parent post is backed up (and restored) first.
            $post->set_source_table('task_post', ['taskid' => backup::VAR_PARENTID], 'id ASC');
            $reaction->set_source_table('task_reaction', ['postid' => backup::VAR_PARENTID]);
            $lastviewed->set_source_table('task_lastviewed', ['taskid' => backup::VAR_PARENTID]);
            $notifypref->set_source_table('task_notifypref', ['taskid' => backup::VAR_PARENTID]);
        }

        $post->annotate_ids('user', 'userid');
        $reaction->annotate_ids('user', 'userid');
        $lastviewed->annotate_ids('user', 'userid');
        $notifypref->annotate_ids('user', 'userid');

        $task->annotate_files('mod_task', 'intro', null);
        $task->annotate_files('mod_task', 'teacherresponse', null);

        return $this->prepare_activity_structure($task);
    }
}
