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
}
