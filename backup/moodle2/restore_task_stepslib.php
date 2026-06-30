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
 * Restore structure step for mod_task.
 *
 * @package    mod_task
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the structure step to restore a Task activity.
 *
 * @package    mod_task
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_task_activity_structure_step extends restore_activity_structure_step {
    /**
     * Define the restore paths.
     *
     * @return array
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('task', '/activity/task');
        if ($userinfo) {
            $paths[] = new restore_path_element('task_post', '/activity/task/posts/post');
            $paths[] = new restore_path_element('task_reaction', '/activity/task/posts/post/reactions/reaction');
            $paths[] = new restore_path_element('task_lastviewed', '/activity/task/lastvieweds/lastviewed');
            $paths[] = new restore_path_element('task_notifypref', '/activity/task/notifyprefs/notifypref');
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore a Task instance.
     *
     * @param array $data the element data
     */
    protected function process_task($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('task', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restore a post, remapping any parent reply id.
     *
     * @param array $data the element data
     */
    protected function process_task_post($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->taskid = $this->get_new_parentid('task');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (!empty($data->parentid)) {
            // Parents have lower ids and are restored first (backed up id ASC).
            $data->parentid = $this->get_mappingid('task_post', $data->parentid);
        }
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newid = $DB->insert_record('task_post', $data);
        $this->set_mapping('task_post', $oldid, $newid);
    }

    /**
     * Restore a reaction.
     *
     * @param array $data the element data
     */
    protected function process_task_reaction($data) {
        global $DB;

        $data = (object) $data;
        $data->postid = $this->get_new_parentid('task_post');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $DB->insert_record('task_reaction', $data);
    }

    /**
     * Restore a last-viewed marker.
     *
     * @param array $data the element data
     */
    protected function process_task_lastviewed($data) {
        global $DB;

        $data = (object) $data;
        $data->taskid = $this->get_new_parentid('task');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $DB->insert_record('task_lastviewed', $data);
    }

    /**
     * Restore a per-user notification preference.
     *
     * @param array $data the element data
     */
    protected function process_task_notifypref($data) {
        global $DB;

        $data = (object) $data;
        $data->taskid = $this->get_new_parentid('task');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $DB->insert_record('task_notifypref', $data);
    }

    /**
     * Re-attach embedded files after the structure has been restored.
     */
    protected function after_execute() {
        $this->add_related_files('mod_task', 'intro', null);
        $this->add_related_files('mod_task', 'teacherresponse', null);
    }
}
