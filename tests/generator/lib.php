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
 * mod_task data generator.
 *
 * @package    mod_task
 * @category   test
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * mod_task data generator class.
 *
 * @package    mod_task
 * @category   test
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_task_generator extends testing_module_generator {
    /**
     * Create a Task instance.
     *
     * @param array|stdClass|null $record the instance settings
     * @param array|null $options generator options
     * @return stdClass the created instance record
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object) (array) $record;

        $defaults = [
            'intro' => '<p>Default task description.</p>',
            'introformat' => FORMAT_HTML,
            'teacherresponse' => '',
            'teacherresponseformat' => FORMAT_HTML,
            'teacherresponseismodelanswer' => 0,
        ];
        foreach ($defaults as $field => $value) {
            if (!isset($record->$field)) {
                $record->$field = $value;
            }
        }

        return parent::create_instance($record, (array) $options);
    }

    /**
     * Create a response or reply in a Task.
     *
     * @param array|stdClass $record fields: taskid, userid, content, anonymous, parentid
     * @return stdClass the created post record
     */
    public function create_response($record): stdClass {
        global $DB;

        $record = (array) $record;
        if (empty($record['taskid'])) {
            throw new coding_exception('taskid is required to create a Task response');
        }
        if (empty($record['userid'])) {
            throw new coding_exception('userid is required to create a Task response');
        }

        $now = time();
        $post = (object) [
            'taskid' => $record['taskid'],
            'parentid' => $record['parentid'] ?? 0,
            'userid' => $record['userid'],
            'content' => $record['content'] ?? '<p>Test response.</p>',
            'anonymous' => empty($record['anonymous']) ? 0 : 1,
            'edited' => 0,
            'deleted' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $post->id = $DB->insert_record('task_post', $post);

        return $post;
    }
}
