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
 * Unit tests for the mod_task manager, especially the response gating.
 *
 * @package    mod_task
 * @category   test
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_task\manager
 */
final class manager_test extends \advanced_testcase {
    /**
     * Reset after every test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Create a course, three users and a Task instance.
     *
     * @param array $taskoverrides overrides for the Task settings
     * @return array
     */
    protected function setup_course_task(array $taskoverrides = []): array {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $student1 = $gen->create_and_enrol($course, 'student');
        $student2 = $gen->create_and_enrol($course, 'student');
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $taskgen = $gen->get_plugin_generator('mod_task');
        $task = $taskgen->create_instance(['course' => $course->id] + $taskoverrides);
        $cm = get_coursemodule_from_instance('task', $task->id);
        $context = \context_module::instance($cm->id);
        return compact('course', 'student1', 'student2', 'teacher', 'task', 'cm', 'context', 'taskgen');
    }

    /**
     * Find a post row in a view payload by id.
     *
     * @param array $view the view payload
     * @param int $postid the post id
     * @return array
     */
    protected function find_post(array $view, int $postid): array {
        foreach ($view['posts'] as $post) {
            if ($post['id'] === $postid) {
                return $post;
            }
        }
        $this->fail("Post $postid not found in view");
    }

    public function test_student_cannot_see_before_responding(): void {
        $data = $this->setup_course_task([
            'teacherresponse' => '<p>The model answer.</p>',
            'teacherresponseismodelanswer' => 1,
        ]);
        $this->setUser($data['student1']);

        $view = manager::get_task_view($data['context'], (int)$data['task']->id);

        $this->assertFalse($view['canseeresponses']);
        $this->assertFalse($view['hasresponded']);
        $this->assertFalse($view['showteacherresponse']);
        $this->assertSame('', $view['teacherresponse']);
        $this->assertSame([], $view['posts']);
        $this->assertNotEmpty($view['taskdescription'], 'Task description is always visible');
    }

    public function test_student_sees_everything_after_responding(): void {
        $data = $this->setup_course_task([
            'teacherresponse' => '<p>The model answer.</p>',
            'teacherresponseismodelanswer' => 1,
        ]);
        // A peer has already posted.
        $data['taskgen']->create_response([
            'taskid' => $data['task']->id,
            'userid' => $data['student2']->id,
            'content' => '<p>Peer response.</p>',
        ]);

        $this->setUser($data['student1']);
        $this->assertFalse(manager::can_see_responses($data['context'], (int)$data['task']->id));

        manager::create_post($data['context'], (int)$data['task']->id, 0, '<p>My answer.</p>', false, (int)$data['student1']->id);

        $view = manager::get_task_view($data['context'], (int)$data['task']->id);
        $this->assertTrue($view['canseeresponses']);
        $this->assertTrue($view['hasresponded']);
        $this->assertTrue($view['showteacherresponse']);
        $this->assertTrue($view['teacherresponseismodelanswer']);
        $this->assertStringContainsString('model answer', $view['teacherresponse']);
        $this->assertCount(2, $view['posts']);
    }

    public function test_teacher_sees_everything_without_responding(): void {
        $data = $this->setup_course_task(['teacherresponse' => '<p>Answer.</p>']);
        $data['taskgen']->create_response([
            'taskid' => $data['task']->id,
            'userid' => $data['student1']->id,
        ]);

        $this->setUser($data['teacher']);
        $view = manager::get_task_view($data['context'], (int)$data['task']->id);

        $this->assertTrue($view['canviewall']);
        $this->assertTrue($view['canseeresponses']);
        $this->assertTrue($view['showteacherresponse']);
        $this->assertCount(1, $view['posts']);
    }

    public function test_student_limited_to_one_response(): void {
        $data = $this->setup_course_task();
        $this->setUser($data['student1']);

        $first = manager::create_post(
            $data['context'],
            (int)$data['task']->id,
            0,
            '<p>My one response.</p>',
            false,
            (int)$data['student1']->id
        );
        $this->assertGreaterThan(0, $first->id);
        $this->assertFalse(
            manager::get_task_view($data['context'], (int)$data['task']->id)['canaddresponse'],
            'The composer is withdrawn once the student has responded'
        );

        // A second top-level response is rejected.
        try {
            manager::create_post(
                $data['context'],
                (int)$data['task']->id,
                0,
                '<p>A second response.</p>',
                false,
                (int)$data['student1']->id
            );
            $this->fail('A second response should be rejected');
        } catch (\moodle_exception $e) {
            $this->assertSame(get_string('error_alreadyresponded', 'mod_task'), $e->getMessage());
        }

        // But replies to the response are unlimited.
        $reply1 = manager::create_post(
            $data['context'],
            (int)$data['task']->id,
            (int)$first->id,
            '<p>Reply one.</p>',
            false,
            (int)$data['student1']->id
        );
        $reply2 = manager::create_post(
            $data['context'],
            (int)$data['task']->id,
            (int)$first->id,
            '<p>Reply two.</p>',
            false,
            (int)$data['student1']->id
        );
        $this->assertGreaterThan(0, $reply1->id);
        $this->assertGreaterThan(0, $reply2->id);
    }

    public function test_staff_may_post_multiple_responses(): void {
        $data = $this->setup_course_task();
        $this->setUser($data['teacher']);

        $one = manager::create_post($data['context'], (int)$data['task']->id, 0, '<p>One.</p>', false, (int)$data['teacher']->id);
        $two = manager::create_post($data['context'], (int)$data['task']->id, 0, '<p>Two.</p>', false, (int)$data['teacher']->id);
        $this->assertGreaterThan(0, $one->id);
        $this->assertGreaterThan(0, $two->id);
        $this->assertTrue(manager::get_task_view($data['context'], (int)$data['task']->id)['canaddresponse']);
    }

    public function test_students_cannot_edit_or_delete_their_post(): void {
        global $DB;

        $data = $this->setup_course_task();
        $post = $data['taskgen']->create_response([
            'taskid' => $data['task']->id,
            'userid' => $data['student1']->id,
        ]);

        // The view offers the student no edit/delete affordance on their own post.
        $this->setUser($data['student1']);
        $row = $this->find_post(manager::get_task_view($data['context'], (int)$data['task']->id), (int)$post->id);
        $this->assertFalse($row['canedit']);
        $this->assertFalse($row['candelete']);

        // And the operations are refused server-side.
        try {
            manager::edit_post($data['context'], (int)$post->id, '<p>Sneaky edit.</p>', (int)$data['student1']->id);
            $this->fail('A student editing their post should be refused');
        } catch (\required_capability_exception $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        try {
            manager::delete_post($data['context'], (int)$post->id, (int)$data['student1']->id);
            $this->fail('A student deleting their post should be refused');
        } catch (\required_capability_exception $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        // The post is untouched.
        $this->assertEquals(0, $DB->get_field('task_post', 'deleted', ['id' => $post->id]));
    }

    public function test_staff_can_edit_and_delete_any_post(): void {
        $data = $this->setup_course_task();
        $post = $data['taskgen']->create_response([
            'taskid' => $data['task']->id,
            'userid' => $data['student1']->id,
        ]);

        $this->setUser($data['teacher']);
        $row = $this->find_post(manager::get_task_view($data['context'], (int)$data['task']->id), (int)$post->id);
        $this->assertTrue($row['canedit']);
        $this->assertTrue($row['candelete']);

        manager::edit_post($data['context'], (int)$post->id, '<p>Moderated.</p>', (int)$data['teacher']->id);
        manager::delete_post($data['context'], (int)$post->id, (int)$data['teacher']->id);

        $row = $this->find_post(manager::get_task_view($data['context'], (int)$data['task']->id), (int)$post->id);
        $this->assertTrue($row['deleted']);
    }

    public function test_anonymous_hidden_from_peers_but_visible_to_teacher(): void {
        $data = $this->setup_course_task();

        $this->setUser($data['student2']);
        $anonpost = manager::create_post(
            $data['context'],
            (int)$data['task']->id,
            0,
            '<p>Anon.</p>',
            true,
            (int)$data['student2']->id
        );
        $this->assertEquals(1, $anonpost->anonymous);

        $this->setUser($data['student1']);
        manager::create_post($data['context'], (int)$data['task']->id, 0, '<p>Mine.</p>', false, (int)$data['student1']->id);
        $view = manager::get_task_view($data['context'], (int)$data['task']->id);
        $peerview = $this->find_post($view, (int)$anonpost->id);
        $this->assertTrue($peerview['isanonymous']);
        $this->assertSame(get_string('anonymous', 'mod_task'), $peerview['authorname']);
        $this->assertSame('', $peerview['profileurl']);
        $this->assertFalse($peerview['showanonymousbadge']);

        $this->setUser($data['teacher']);
        $view = manager::get_task_view($data['context'], (int)$data['task']->id);
        $teacherview = $this->find_post($view, (int)$anonpost->id);
        $this->assertTrue($teacherview['showanonymousbadge']);
        $this->assertStringContainsString(fullname($data['student2']), $teacherview['authorname']);
    }

    public function test_own_posts_labelled_you(): void {
        $data = $this->setup_course_task();

        // A student's own response is labelled "You" when they view it.
        $this->setUser($data['student1']);
        $post = manager::create_post(
            $data['context'],
            (int)$data['task']->id,
            0,
            '<p>Mine.</p>',
            false,
            (int)$data['student1']->id
        );
        $ownview = $this->find_post(manager::get_task_view($data['context'], (int)$data['task']->id), (int)$post->id);
        $this->assertSame(get_string('you', 'mod_task'), $ownview['authorname']);

        // Even an anonymous post reads "You" to its author, who still sees the badge.
        $anonpost = manager::create_post(
            $data['context'],
            (int)$data['task']->id,
            (int)$post->id,
            '<p>Anon reply.</p>',
            true,
            (int)$data['student1']->id
        );
        $anonview = $this->find_post(manager::get_task_view($data['context'], (int)$data['task']->id), (int)$anonpost->id);
        $this->assertSame(get_string('you', 'mod_task'), $anonview['authorname']);
        $this->assertTrue($anonview['showanonymousbadge']);

        // Another user's post keeps its real name, never "You".
        $this->setUser($data['teacher']);
        $teacherview = $this->find_post(manager::get_task_view($data['context'], (int)$data['task']->id), (int)$post->id);
        $this->assertSame(fullname($data['student1']), $teacherview['authorname']);
    }

    public function test_staff_cannot_post_anonymously(): void {
        $data = $this->setup_course_task();
        $this->setUser($data['teacher']);
        $post = manager::create_post(
            $data['context'],
            (int)$data['task']->id,
            0,
            '<p>Staff.</p>',
            true,
            (int)$data['teacher']->id
        );
        $this->assertEquals(0, $post->anonymous);
    }

    public function test_role_label_shown_for_staff_not_students(): void {
        $data = $this->setup_course_task();
        $this->setUser($data['teacher']);
        $teacherpost = manager::create_post(
            $data['context'],
            (int)$data['task']->id,
            0,
            '<p>Staff reply.</p>',
            false,
            (int)$data['teacher']->id
        );
        $studentpost = $data['taskgen']->create_response([
            'taskid' => $data['task']->id,
            'userid' => $data['student1']->id,
        ]);

        $view = manager::get_task_view($data['context'], (int)$data['task']->id);
        $this->assertNotEmpty($this->find_post($view, (int)$teacherpost->id)['authorrole']);
        $this->assertSame('', $this->find_post($view, (int)$studentpost->id)['authorrole']);
    }

    public function test_reactions_toggle(): void {
        $data = $this->setup_course_task();
        $post = $data['taskgen']->create_response([
            'taskid' => $data['task']->id,
            'userid' => $data['student1']->id,
        ]);

        $result = manager::toggle_reaction((int)$post->id, (int)$data['student2']->id, 'thumbsup');
        $this->assertSame('added', $result['action']);

        $reactions = manager::get_reactions([(int)$post->id], (int)$data['student2']->id);
        $this->assertSame(1, $reactions[(int)$post->id]['counts']['thumbsup']);
        $this->assertContains('thumbsup', $reactions[(int)$post->id]['userreactions']);

        $result = manager::toggle_reaction((int)$post->id, (int)$data['student2']->id, 'thumbsup');
        $this->assertSame('removed', $result['action']);
        $reactions = manager::get_reactions([(int)$post->id], (int)$data['student2']->id);
        $this->assertArrayNotHasKey('thumbsup', $reactions[(int)$post->id]['counts']);
    }

    public function test_empty_post_is_rejected(): void {
        $data = $this->setup_course_task();
        $this->setUser($data['student1']);
        $this->expectException(\moodle_exception::class);
        manager::create_post($data['context'], (int)$data['task']->id, 0, '<p></p>', false, (int)$data['student1']->id);
    }

    public function test_count_new_responses_respects_gating(): void {
        $data = $this->setup_course_task();
        $data['taskgen']->create_response([
            'taskid' => $data['task']->id,
            'userid' => $data['student2']->id,
        ]);
        $cminfo = get_fast_modinfo($data['course'])->get_cm((int)$data['cm']->id);

        // A student who has not responded cannot see, so the count is zero.
        $this->assertSame(0, manager::count_new_responses($cminfo, (int)$data['student1']->id));
        // Staff see everything: the peer post counts.
        $this->assertSame(1, manager::count_new_responses($cminfo, (int)$data['teacher']->id));

        // Once the student responds, the peer's post counts; their own does not.
        $this->setUser($data['student1']);
        manager::create_post($data['context'], (int)$data['task']->id, 0, '<p>Mine.</p>', false, (int)$data['student1']->id);
        $this->assertSame(1, manager::count_new_responses($cminfo, (int)$data['student1']->id));

        // Viewing clears the count.
        manager::mark_viewed((int)$data['task']->id, (int)$data['student1']->id);
        $this->assertSame(0, manager::count_new_responses($cminfo, (int)$data['student1']->id));
    }
}
