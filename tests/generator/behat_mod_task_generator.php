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
 * Behat data generator for mod_task.
 *
 * @package    mod_task
 * @category   test
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_task_generator extends behat_generator_base {
    /**
     * Get the entities Behat can create with the generator step.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'responses' => [
                'singular' => 'response',
                'datagenerator' => 'response',
                'required' => ['task', 'user'],
                'switchids' => ['task' => 'taskid', 'user' => 'userid'],
            ],
            'replies' => [
                'singular' => 'reply',
                'datagenerator' => 'reply',
                'required' => ['task', 'user', 'parent'],
                'switchids' => ['task' => 'taskid', 'user' => 'userid', 'parent' => 'parentid'],
            ],
            'reactions' => [
                'singular' => 'reaction',
                'datagenerator' => 'reaction',
                'required' => ['user', 'post'],
                'switchids' => ['user' => 'userid', 'post' => 'postid'],
            ],
        ];
    }

    /**
     * Resolve a Task id from an activity idnumber or name.
     *
     * @param string $idnumberorname the Task idnumber or name
     * @return int the Task instance id
     */
    protected function get_task_id(string $idnumberorname): int {
        return $this->get_cm_by_activity_name('task', $idnumberorname)->instance;
    }

    /**
     * Resolve a post id from its content, for the "parent" column of replies.
     *
     * @param string $content the exact content of the post
     * @return int the post id
     */
    protected function get_parent_id(string $content): int {
        return $this->get_post_id($content);
    }

    /**
     * Resolve a post id from its content.
     *
     * @param string $content the exact content of the post
     * @return int the post id
     */
    protected function get_post_id(string $content): int {
        global $DB;

        $select = $DB->sql_compare_text('content') . ' = ' . $DB->sql_compare_text(':content');
        $posts = $DB->get_records_select('task_post', $select, ['content' => $content], 'id ASC', 'id');
        if (!$posts) {
            throw new Exception('No Task post found with content "' . $content . '"');
        }
        return (int) array_key_first($posts);
    }
}
