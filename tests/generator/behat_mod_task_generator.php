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
}
