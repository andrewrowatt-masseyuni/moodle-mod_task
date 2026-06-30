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
 * Backup task definition for mod_task.
 *
 * @package    mod_task
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/task/backup/moodle2/backup_task_stepslib.php');

/**
 * Backup task for the Task activity.
 *
 * @package    mod_task
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_task_activity_task extends backup_activity_task {
    /**
     * No particular settings for this activity.
     */
    protected function define_my_settings() {
    }

    /**
     * Define the backup steps.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_task_activity_structure_step('task_structure', 'task.xml'));
    }

    /**
     * Encode internal links to the Task activity.
     *
     * @param string $content the content to encode
     * @return string
     */
    public static function encode_content_links($content) {
        global $CFG;
        $base = preg_quote($CFG->wwwroot, '/');

        // Link to the list of Tasks in a course.
        $content = preg_replace(
            '/(' . $base . '\/mod\/task\/index\.php\?id=)([0-9]+)/',
            '$@TASKINDEX*$2@$',
            $content
        );

        // Link to a Task view page.
        $content = preg_replace(
            '/(' . $base . '\/mod\/task\/view\.php\?id=)([0-9]+)/',
            '$@TASKVIEWBYID*$2@$',
            $content
        );

        return $content;
    }
}
