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

namespace mod_task;

/**
 * Unit tests for mod_task's course-module info hooks.
 *
 * @package    mod_task
 * @category   test
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \task_get_coursemodule_info
 * @covers     \task_cm_info_dynamic
 * @covers     \task_cm_info_view
 * @covers     \task_grade_max
 * @covers     \task_grade_item_update
 * @covers     \task_grade_item_delete
 * @covers     \task_get_user_grades
 * @covers     \task_update_grades
 */
final class lib_test extends \advanced_testcase {
    /**
     * Reset after every test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Create a course and a Task instance.
     *
     * @param array $taskoverrides overrides for the Task settings
     * @return array
     */
    protected function setup_course_task(array $taskoverrides = []): array {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $task = $gen->get_plugin_generator('mod_task')->create_instance(['course' => $course->id] + $taskoverrides);
        $cm = get_coursemodule_from_instance('task', $task->id);
        return compact('course', 'task', 'cm');
    }

    public function test_embed_on_course_page_suppresses_link_and_sets_content(): void {
        $this->setAdminUser();
        ['course' => $course, 'cm' => $cm] = $this->setup_course_task(['embedoncoursepage' => 1]);

        $modinfo = get_fast_modinfo($course);
        $modcm = $modinfo->get_cm($cm->id);

        $this->assertNull($modcm->url);
        $this->assertTrue($modcm->uservisible);
        $this->assertTrue($modcm->has_custom_cmlist_item());

        $content = $modcm->get_formatted_content();
        $this->assertStringContainsString('data-region="mod_task"', $content);
        $this->assertStringContainsString('data-cmid="' . $modcm->id . '"', $content);
    }

    public function test_default_task_keeps_normal_card(): void {
        $this->setAdminUser();
        ['course' => $course, 'cm' => $cm] = $this->setup_course_task();

        $modinfo = get_fast_modinfo($course);
        $modcm = $modinfo->get_cm($cm->id);

        $this->assertNotNull($modcm->url);
        $this->assertFalse($modcm->has_custom_cmlist_item());
    }

    public function test_embed_hidden_when_not_uservisible(): void {
        $this->setAdminUser();
        ['course' => $course, 'cm' => $cm] = $this->setup_course_task(['embedoncoursepage' => 1]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        set_coursemodule_visible($cm->id, 0);

        $this->setUser($student);
        $modinfo = get_fast_modinfo($course);
        $modcm = $modinfo->get_cm($cm->id);

        $this->assertFalse($modcm->uservisible);
        $this->assertSame('', $modcm->get_formatted_content());
    }

    public function test_completion_rules_reported_inactive_when_features_disabled(): void {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $task = $gen->get_plugin_generator('mod_task')->create_instance([
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionrespond' => 1,
            'completionreply' => 1,
            'completionreact' => 1,
            'enablereplies' => 0,
            'enablereactions' => 0,
        ]);
        $cm = get_coursemodule_from_instance('task', $task->id);

        $info = task_get_coursemodule_info($cm);
        $rules = $info->customdata['customcompletionrules'];

        // Respond stays active; reply and react are reported inactive because
        // their features are turned off, so neither is required nor shown.
        $this->assertEquals(1, $rules['completionrespond']);
        $this->assertEquals(0, $rules['completionreply']);
        $this->assertEquals(0, $rules['completionreact']);
    }

    /**
     * Fetch the gradebook item for a Task, or false if there is none.
     *
     * @param stdClass $task the task record
     * @return \grade_item|false
     */
    protected function get_grade_item(\stdClass $task) {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        return \grade_item::fetch([
            'courseid' => $task->course,
            'itemtype' => 'mod',
            'itemmodule' => 'task',
            'iteminstance' => $task->id,
            'itemnumber' => 0,
        ]);
    }

    /**
     * Fetch a user's final grade for a Task, or null if there is none.
     *
     * @param stdClass $task the task record
     * @param int $userid the user id
     * @return float|null
     */
    protected function get_final_grade(\stdClass $task, int $userid): ?float {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $grades = grade_get_grades($task->course, 'mod', 'task', $task->id, $userid);
        $grade = $grades->items[0]->grades[$userid]->grade ?? null;
        return $grade === null ? null : (float) $grade;
    }

    public function test_graded_task_creates_grade_item(): void {
        ['task' => $task] = $this->setup_course_task([
            'graded' => 1,
            'graderesponsepoints' => 80,
            'gradereplypoints' => 20,
            'gradereplycount' => 2,
            'gradereactpoints' => 10,
            'gradereactcount' => 1,
        ]);

        $item = $this->get_grade_item($task);
        $this->assertNotFalse($item);
        $this->assertEquals(GRADE_TYPE_VALUE, $item->gradetype);
        $this->assertEquals(110.0, (float) $item->grademax);
        $this->assertEquals(0.0, (float) $item->grademin);
        $this->assertSame($task->name, $item->itemname);
    }

    public function test_ungraded_task_creates_no_grade_item(): void {
        ['task' => $task] = $this->setup_course_task(['graded' => 0]);

        $this->assertFalse($this->get_grade_item($task));
    }

    public function test_grade_max_excludes_disabled_features(): void {
        ['task' => $task] = $this->setup_course_task([
            'graded' => 1,
            'graderesponsepoints' => 80,
            'gradereplypoints' => 20,
            'gradereactpoints' => 10,
            'enablereplies' => 0,
            'enablereactions' => 0,
        ]);
        $this->assertEquals(80.0, task_grade_max($task));
        $this->assertEquals(80.0, (float) $this->get_grade_item($task)->grademax);

        // Reactions disabled site-wide are excluded even when the activity enables them.
        set_config('enablereactions', '0', 'mod_task');
        ['task' => $task2] = $this->setup_course_task([
            'graded' => 1,
            'graderesponsepoints' => 80,
            'gradereplypoints' => 20,
            'gradereactpoints' => 10,
        ]);
        $this->assertEquals(100.0, task_grade_max($task2));
    }

    public function test_update_grades_caps_and_prorates(): void {
        $gen = $this->getDataGenerator();
        ['course' => $course, 'task' => $task] = $this->setup_course_task([
            'graded' => 1,
            'graderesponsepoints' => 80,
            'gradereplypoints' => 20,
            'gradereplycount' => 2,
            'gradereactpoints' => 10,
            'gradereactcount' => 2,
        ]);
        $student1 = $gen->create_and_enrol($course, 'student');
        $student2 = $gen->create_and_enrol($course, 'student');
        $taskgen = $gen->get_plugin_generator('mod_task');

        $peerpost = $taskgen->create_response([
            'taskid' => $task->id,
            'userid' => $student2->id,
            'content' => '<p>Peer response.</p>',
        ]);
        $this->assertEquals(80.0, $this->get_final_grade($task, (int) $student2->id));

        // Response (80) + one of two required replies (10) + one of two required
        // posts reacted to (5) = 95.
        $taskgen->create_response([
            'taskid' => $task->id,
            'userid' => $student1->id,
            'content' => '<p>My response.</p>',
        ]);
        $taskgen->create_reply([
            'taskid' => $task->id,
            'userid' => $student1->id,
            'parentid' => $peerpost->id,
            'content' => '<p>My reply.</p>',
        ]);
        $taskgen->create_reaction(['postid' => $peerpost->id, 'userid' => $student1->id]);
        $this->assertEquals(95.0, $this->get_final_grade($task, (int) $student1->id));

        // Further replies beyond the required number stay capped at the reply marks.
        $taskgen->create_reply([
            'taskid' => $task->id,
            'userid' => $student1->id,
            'parentid' => $peerpost->id,
            'content' => '<p>Another reply.</p>',
        ]);
        $taskgen->create_reply([
            'taskid' => $task->id,
            'userid' => $student1->id,
            'parentid' => $peerpost->id,
            'content' => '<p>A third reply.</p>',
        ]);
        $this->assertEquals(105.0, $this->get_final_grade($task, (int) $student1->id));
    }

    public function test_update_instance_toggling_graded_off_deletes_item(): void {
        ['task' => $task, 'cm' => $cm] = $this->setup_course_task(['graded' => 1]);
        $this->assertNotFalse($this->get_grade_item($task));

        $data = clone $task;
        $data->instance = $task->id;
        $data->coursemodule = $cm->id;
        $data->graded = 0;
        task_update_instance($data, null);

        $this->assertFalse($this->get_grade_item($task));
    }

    public function test_delete_instance_deletes_grade_item(): void {
        ['task' => $task] = $this->setup_course_task(['graded' => 1]);
        $this->assertNotFalse($this->get_grade_item($task));

        task_delete_instance($task->id);

        $this->assertFalse($this->get_grade_item($task));
    }
}
