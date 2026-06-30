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
 * Lists all Task activities in a course.
 *
 * @package    mod_task
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/task/lib.php');

$id = required_param('id', PARAM_INT); // Course id.

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course);

$coursecontext = context_course::instance($course->id);

$event = \core\event\course_module_instance_list_viewed::create(['context' => $coursecontext]);
$event->add_record_snapshot('course', $course);
$event->trigger();

$PAGE->set_url('/mod/task/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($coursecontext);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_task'));

$tasks = get_all_instances_in_course('task', $course);
if (empty($tasks)) {
    notice(
        get_string('thereareno', 'moodle', get_string('modulenameplural', 'mod_task')),
        new moodle_url('/course/view.php', ['id' => $course->id])
    );
}

$table = new html_table();
$table->head = [get_string('name'), get_string('sectionname', 'format_' . $course->format)];
$table->align = ['left', 'left'];

foreach ($tasks as $task) {
    $linkurl = new moodle_url('/mod/task/view.php', ['id' => $task->coursemodule]);
    $linkattrs = $task->visible ? [] : ['class' => 'dimmed'];
    $table->data[] = [
        html_writer::link($linkurl, format_string($task->name), $linkattrs),
        get_section_name($course, $task->section),
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
