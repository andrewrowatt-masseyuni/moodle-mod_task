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

namespace mod_task\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Unit tests for the mod_task react_post web service, especially the
 * mod/task:react capability.
 *
 * @package    mod_task
 * @category   test
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_task\external\react_post
 */
final class react_post_test extends \externallib_advanced_testcase {
    /**
     * Create a course, two students, a teacher, a Task and a first response.
     *
     * @return array
     */
    protected function setup_course_task(): array {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $student1 = $gen->create_and_enrol($course, 'student');
        $student2 = $gen->create_and_enrol($course, 'student');
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $taskgen = $gen->get_plugin_generator('mod_task');
        $task = $taskgen->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('task', $task->id);
        $context = \context_module::instance($cm->id);
        $post = $taskgen->create_response([
            'taskid' => $task->id,
            'userid' => $student1->id,
        ]);
        return compact('course', 'student1', 'student2', 'teacher', 'task', 'cm', 'context', 'taskgen', 'post');
    }

    public function test_student_can_react_after_responding(): void {
        $data = $this->setup_course_task();
        $data['taskgen']->create_response([
            'taskid' => $data['task']->id,
            'userid' => $data['student2']->id,
        ]);

        $this->setUser($data['student2']);
        $result = react_post::execute((int)$data['post']->id, 'thumbsup');
        $result = \core_external\external_api::clean_returnvalue(react_post::execute_returns(), $result);

        $this->assertSame('added', $result['action']);
        $this->assertContains('thumbsup', $result['userreactions']);
    }

    public function test_teacher_can_react_without_responding(): void {
        $data = $this->setup_course_task();

        $this->setUser($data['teacher']);
        $result = react_post::execute((int)$data['post']->id, 'thumbsup');
        $result = \core_external\external_api::clean_returnvalue(react_post::execute_returns(), $result);

        $this->assertSame('added', $result['action']);
    }

    public function test_react_requires_react_capability(): void {
        global $DB;

        $data = $this->setup_course_task();
        $data['taskgen']->create_response([
            'taskid' => $data['task']->id,
            'userid' => $data['student2']->id,
        ]);

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        assign_capability('mod/task:react', CAP_PROHIBIT, $roleid, $data['context']->id, true);

        $this->setUser($data['student2']);
        $this->expectException(\required_capability_exception::class);
        react_post::execute((int)$data['post']->id, 'thumbsup');
    }

    public function test_react_requires_seeing_responses_first(): void {
        $data = $this->setup_course_task();

        // Student2 has not responded, so the gating refuses the reaction even
        // though they hold mod/task:react.
        $this->setUser($data['student2']);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error_cannotrespondyet', 'mod_task'));
        react_post::execute((int)$data['post']->id, 'thumbsup');
    }
}
