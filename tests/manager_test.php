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
        manager::reset_caches();
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
        $this->assertFalse($view['showteacherresponsenote']);
        $this->assertFalse($view['showallresponsesheading']);
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
        $this->assertFalse($view['showteacherresponsenote'], 'Students do not get the staff visibility note');
        $this->assertFalse($view['showallresponsesheading'], 'Students keep the "Other responses" heading');
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
        $this->assertTrue($view['showteacherresponsenote'], 'Staff are told students cannot see this yet');
        $this->assertTrue($view['showallresponsesheading'], 'Staff without mod/task:respond see "All responses"');
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

    public function test_staff_may_post_multiple_responses_when_granted_respond(): void {
        global $DB;

        $data = $this->setup_course_task();

        // Teachers cannot respond by default; grant it to verify that staff
        // (who can view all responses) are not limited to one response.
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('mod/task:respond', CAP_ALLOW, $roleid, $data['context']->id, true);

        $this->setUser($data['teacher']);

        $one = manager::create_post($data['context'], (int)$data['task']->id, 0, '<p>One.</p>', false, (int)$data['teacher']->id);
        $two = manager::create_post($data['context'], (int)$data['task']->id, 0, '<p>Two.</p>', false, (int)$data['teacher']->id);
        $this->assertGreaterThan(0, $one->id);
        $this->assertGreaterThan(0, $two->id);
        $this->assertTrue(manager::get_task_view($data['context'], (int)$data['task']->id)['canaddresponse']);
    }

    public function test_teacher_cannot_post_a_response_by_default(): void {
        $data = $this->setup_course_task();
        $this->setUser($data['teacher']);

        $this->expectException(\required_capability_exception::class);
        manager::create_post($data['context'], (int)$data['task']->id, 0, '<p>One.</p>', false, (int)$data['teacher']->id);
    }

    public function test_teacher_can_reply_by_default(): void {
        $data = $this->setup_course_task();
        $response = $data['taskgen']->create_response([
            'taskid' => $data['task']->id,
            'userid' => $data['student1']->id,
        ]);

        $this->setUser($data['teacher']);
        $reply = manager::create_post(
            $data['context'],
            (int)$data['task']->id,
            (int)$response->id,
            '<p>Teacher reply.</p>',
            false,
            (int)$data['teacher']->id
        );
        $this->assertGreaterThan(0, $reply->id);
    }

    public function test_reply_requires_reply_capability(): void {
        global $DB;

        $data = $this->setup_course_task();
        $response = $data['taskgen']->create_response([
            'taskid' => $data['task']->id,
            'userid' => $data['student2']->id,
        ]);

        // A student stripped of mod/task:reply keeps mod/task:respond but may
        // no longer post replies.
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        assign_capability('mod/task:reply', CAP_PROHIBIT, $roleid, $data['context']->id, true);

        $this->setUser($data['student1']);
        $own = manager::create_post(
            $data['context'],
            (int)$data['task']->id,
            0,
            '<p>Still allowed to respond.</p>',
            false,
            (int)$data['student1']->id
        );
        $this->assertGreaterThan(0, $own->id);

        $view = manager::get_task_view($data['context'], (int)$data['task']->id);
        $this->assertFalse($this->find_post($view, (int)$response->id)['canreply']);

        $this->expectException(\required_capability_exception::class);
        manager::create_post(
            $data['context'],
            (int)$data['task']->id,
            (int)$response->id,
            '<p>Not allowed to reply.</p>',
            false,
            (int)$data['student1']->id
        );
    }

    public function test_canreact_follows_react_capability(): void {
        global $DB;

        $data = $this->setup_course_task();

        $this->setUser($data['student1']);
        $this->assertTrue(manager::get_task_view($data['context'], (int)$data['task']->id)['canreact']);

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        assign_capability('mod/task:react', CAP_PROHIBIT, $roleid, $data['context']->id, true);

        $this->assertFalse(manager::get_task_view($data['context'], (int)$data['task']->id)['canreact']);
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
        global $DB;

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
        $row = $this->find_post(manager::get_task_view($data['context'], (int)$data['task']->id), (int)$post->id);
        $this->assertStringContainsString('Moderated.', $row['content']);
        $this->assertTrue($row['edited']);

        // A deleted post is soft-deleted in the database and no longer shown.
        manager::delete_post($data['context'], (int)$post->id, (int)$data['teacher']->id);
        $this->assertEquals(1, $DB->get_field('task_post', 'deleted', ['id' => $post->id]));
        $view = manager::get_task_view($data['context'], (int)$data['task']->id);
        $this->assertSame([], array_filter($view['posts'], fn($p) => $p['id'] === (int)$post->id));
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

    public function test_view_flags_the_viewers_own_posts(): void {
        $data = $this->setup_course_task();

        // A peer responds first, then the viewer posts their own response.
        $peerpost = $data['taskgen']->create_response([
            'taskid' => $data['task']->id,
            'userid' => $data['student2']->id,
        ]);
        $this->setUser($data['student1']);
        $ownpost = manager::create_post(
            $data['context'],
            (int)$data['task']->id,
            0,
            '<p>Mine.</p>',
            false,
            (int)$data['student1']->id
        );

        // The ismine flag drives the split between the "Your response" panel and
        // the "Other responses" area in the JS shell.
        $view = manager::get_task_view($data['context'], (int)$data['task']->id);
        $this->assertTrue($this->find_post($view, (int)$ownpost->id)['ismine']);
        $this->assertFalse($this->find_post($view, (int)$peerpost->id)['ismine']);

        // The same post is "not mine" to a different viewer.
        $this->setUser($data['teacher']);
        $teacherview = manager::get_task_view($data['context'], (int)$data['task']->id);
        $this->assertFalse($this->find_post($teacherview, (int)$ownpost->id)['ismine']);
    }

    public function test_staff_cannot_post_anonymously(): void {
        $data = $this->setup_course_task();
        $response = $data['taskgen']->create_response([
            'taskid' => $data['task']->id,
            'userid' => $data['student1']->id,
        ]);

        // Staff post replies under their own name even when they ask to be anonymous.
        $this->setUser($data['teacher']);
        $post = manager::create_post(
            $data['context'],
            (int)$data['task']->id,
            (int)$response->id,
            '<p>Staff.</p>',
            true,
            (int)$data['teacher']->id
        );
        $this->assertEquals(0, $post->anonymous);
    }

    public function test_anonymous_posts_disabled(): void {
        $data = $this->setup_course_task(['anonymousposts' => 0]);

        // The anonymous option is withheld from the composer UI.
        $this->setUser($data['student1']);
        $view = manager::get_task_view($data['context'], (int)$data['task']->id);
        $this->assertFalse($view['cananonymous']);

        // A forged anonymous request is stored as a named post.
        $post = manager::create_post(
            $data['context'],
            (int)$data['task']->id,
            0,
            '<p>Named.</p>',
            true,
            (int)$data['student1']->id
        );
        $this->assertEquals(0, $post->anonymous);
    }

    public function test_anonymous_posts_enabled_by_default(): void {
        $data = $this->setup_course_task();

        $this->setUser($data['student1']);
        $view = manager::get_task_view($data['context'], (int)$data['task']->id);
        $this->assertTrue($view['cananonymous']);
    }

    public function test_role_label_shown_for_staff_not_students(): void {
        $data = $this->setup_course_task();
        $studentpost = $data['taskgen']->create_response([
            'taskid' => $data['task']->id,
            'userid' => $data['student1']->id,
        ]);

        $this->setUser($data['teacher']);
        $teacherpost = manager::create_post(
            $data['context'],
            (int)$data['task']->id,
            (int)$studentpost->id,
            '<p>Staff reply.</p>',
            false,
            (int)$data['teacher']->id
        );

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

    public function test_notification_preference_defaults_by_role(): void {
        $data = $this->setup_course_task();

        // Staff default to all responses and replies; students to replies to their own response.
        $this->assertSame(
            manager::NOTIFY_ALL,
            manager::get_notification_preference($data['context'], (int)$data['task']->id, (int)$data['teacher']->id)
        );
        $this->assertSame(
            manager::NOTIFY_MYREPLIES,
            manager::get_notification_preference($data['context'], (int)$data['task']->id, (int)$data['student1']->id)
        );
    }

    public function test_notification_preference_is_stored_and_returned(): void {
        global $DB;

        $data = $this->setup_course_task();

        manager::set_notification_preference((int)$data['task']->id, (int)$data['student1']->id, manager::NOTIFY_NONE);
        $this->assertSame(
            manager::NOTIFY_NONE,
            manager::get_notification_preference($data['context'], (int)$data['task']->id, (int)$data['student1']->id)
        );

        // Updating overwrites the previous choice rather than inserting a duplicate.
        manager::set_notification_preference((int)$data['task']->id, (int)$data['student1']->id, manager::NOTIFY_ALL);
        $this->assertSame(
            manager::NOTIFY_ALL,
            manager::get_notification_preference($data['context'], (int)$data['task']->id, (int)$data['student1']->id)
        );
        $this->assertEquals(
            1,
            $DB->count_records('task_notifypref', [
                'taskid' => (int)$data['task']->id,
                'userid' => (int)$data['student1']->id,
            ])
        );
    }

    public function test_set_notification_preference_rejects_invalid_value(): void {
        $data = $this->setup_course_task();
        $this->expectException(\invalid_parameter_exception::class);
        manager::set_notification_preference((int)$data['task']->id, (int)$data['student1']->id, 99);
    }

    public function test_notification_options_marks_current(): void {
        $data = $this->setup_course_task();
        manager::set_notification_preference((int)$data['task']->id, (int)$data['student1']->id, manager::NOTIFY_RESPONSES);

        $options = manager::notification_options($data['context'], (int)$data['task']->id, (int)$data['student1']->id);
        $this->assertCount(4, $options);

        $active = array_values(array_filter($options, fn($o) => $o['active']));
        $this->assertCount(1, $active);
        $this->assertSame(manager::NOTIFY_RESPONSES, $active[0]['value']);
    }

    public function test_should_notify_for_post_matrix(): void {
        // A new top-level response (rootauthorid is irrelevant).
        $this->assertFalse(manager::should_notify_for_post(manager::NOTIFY_NONE, false, 0, 5));
        $this->assertTrue(manager::should_notify_for_post(manager::NOTIFY_RESPONSES, false, 0, 5));
        $this->assertTrue(manager::should_notify_for_post(manager::NOTIFY_ALL, false, 0, 5));
        $this->assertFalse(manager::should_notify_for_post(manager::NOTIFY_MYREPLIES, false, 0, 5));

        // A new reply: only "all" and "my replies" (when the thread root is mine) match.
        $this->assertFalse(manager::should_notify_for_post(manager::NOTIFY_NONE, true, 5, 5));
        $this->assertFalse(manager::should_notify_for_post(manager::NOTIFY_RESPONSES, true, 5, 5));
        $this->assertTrue(manager::should_notify_for_post(manager::NOTIFY_ALL, true, 99, 5));
        $this->assertTrue(manager::should_notify_for_post(manager::NOTIFY_MYREPLIES, true, 5, 5));
        $this->assertFalse(manager::should_notify_for_post(manager::NOTIFY_MYREPLIES, true, 99, 5));

        // A teacher's reply to your own response reaches you whatever your setting,
        // even when muted; a teacher's reply elsewhere, or a peer's reply, does not.
        $this->assertTrue(manager::should_notify_for_post(manager::NOTIFY_NONE, true, 5, 5, true));
        $this->assertFalse(manager::should_notify_for_post(manager::NOTIFY_NONE, true, 99, 5, true));
        $this->assertFalse(manager::should_notify_for_post(manager::NOTIFY_NONE, true, 5, 5, false));
        // The override never applies to a top-level response.
        $this->assertFalse(manager::should_notify_for_post(manager::NOTIFY_NONE, false, 0, 5, true));
    }

    public function test_thread_root_author_walks_to_top_level(): void {
        $data = $this->setup_course_task();

        $response = $data['taskgen']->create_response([
            'taskid' => $data['task']->id,
            'userid' => $data['student1']->id,
        ]);
        $reply = $data['taskgen']->create_response([
            'taskid' => $data['task']->id,
            'userid' => $data['student2']->id,
            'parentid' => $response->id,
        ]);
        $nestedreply = $data['taskgen']->create_response([
            'taskid' => $data['task']->id,
            'userid' => $data['teacher']->id,
            'parentid' => $reply->id,
        ]);

        // Every post in the thread resolves to the top-level response's author.
        $this->assertSame((int)$data['student1']->id, manager::thread_root_author((int)$response->id));
        $this->assertSame((int)$data['student1']->id, manager::thread_root_author((int)$reply->id));
        $this->assertSame((int)$data['student1']->id, manager::thread_root_author((int)$nestedreply->id));
    }

    public function test_response_notifies_only_those_who_opted_in(): void {
        $data = $this->setup_course_task();
        $this->setUser($data['student1']);
        $sink = $this->redirectMessages();

        // The author (student1) posts a top-level response.
        $response = manager::create_post(
            $data['context'],
            (int)$data['task']->id,
            0,
            '<p>My response.</p>',
            false,
            (int)$data['student1']->id
        );
        $this->run_send_notification((int)$response->id);

        // Teacher (default all) is notified; student2 (default my-replies) is not.
        // The author is never notified of their own post.
        $recipients = $this->message_recipient_ids($sink);
        $this->assertSame([(int)$data['teacher']->id], $recipients);
    }

    public function test_reply_notifies_the_response_owner(): void {
        $data = $this->setup_course_task();

        $response = $data['taskgen']->create_response([
            'taskid' => $data['task']->id,
            'userid' => $data['student1']->id,
        ]);

        $this->setUser($data['teacher']);
        $sink = $this->redirectMessages();

        // The teacher replies inside student1's response thread.
        $reply = manager::create_post(
            $data['context'],
            (int)$data['task']->id,
            (int)$response->id,
            '<p>A reply.</p>',
            false,
            (int)$data['teacher']->id
        );
        $this->run_send_notification((int)$reply->id);

        // Only the thread-root owner (student1) is notified; student2 is unrelated.
        $recipients = $this->message_recipient_ids($sink);
        $this->assertSame([(int)$data['student1']->id], $recipients);
    }

    public function test_response_notification_hides_anonymous_author_from_peers(): void {
        $data = $this->setup_course_task();

        // Opt student2 in to all new responses so they receive this one.
        manager::set_notification_preference((int)$data['task']->id, (int)$data['student2']->id, manager::NOTIFY_RESPONSES);

        $sink = $this->redirectMessages();

        $this->setUser($data['student1']);
        $response = manager::create_post(
            $data['context'],
            (int)$data['task']->id,
            0,
            '<p>Anon response.</p>',
            true,
            (int)$data['student1']->id
        );
        $this->run_send_notification((int)$response->id);

        $messages = $sink->get_messages();
        $byuser = [];
        foreach ($messages as $message) {
            $byuser[(int)$message->useridto] = $message->fullmessage;
        }

        // The peer sees "Anonymous"; staff see the real name.
        $this->assertArrayHasKey((int)$data['student2']->id, $byuser);
        $this->assertStringContainsString(get_string('anonymous', 'mod_task'), $byuser[(int)$data['student2']->id]);
        $this->assertStringNotContainsString(fullname($data['student1']), $byuser[(int)$data['student2']->id]);

        $this->assertArrayHasKey((int)$data['teacher']->id, $byuser);
        $this->assertStringContainsString(fullname($data['student1']), $byuser[(int)$data['teacher']->id]);
    }

    public function test_teacher_reply_reaches_muted_student(): void {
        $data = $this->setup_course_task();

        $response = $data['taskgen']->create_response([
            'taskid' => $data['task']->id,
            'userid' => $data['student1']->id,
        ]);

        // Mute student1's notifications for this Task.
        manager::set_notification_preference((int)$data['task']->id, (int)$data['student1']->id, manager::NOTIFY_NONE);

        $this->setUser($data['teacher']);
        $sink = $this->redirectMessages();

        // A teacher replies inside student1's own response thread.
        $reply = manager::create_post(
            $data['context'],
            (int)$data['task']->id,
            (int)$response->id,
            '<p>Teacher feedback.</p>',
            false,
            (int)$data['teacher']->id
        );
        $this->run_send_notification((int)$reply->id);

        // The mute is overridden: the response owner still hears about it.
        $this->assertContains((int)$data['student1']->id, $this->message_recipient_ids($sink));
    }

    public function test_peer_reply_does_not_override_mute(): void {
        $data = $this->setup_course_task();

        $response = $data['taskgen']->create_response([
            'taskid' => $data['task']->id,
            'userid' => $data['student1']->id,
        ]);
        manager::set_notification_preference((int)$data['task']->id, (int)$data['student1']->id, manager::NOTIFY_NONE);

        $this->setUser($data['student2']);
        $sink = $this->redirectMessages();

        // A peer (not a teacher) replies in student1's thread.
        $reply = manager::create_post(
            $data['context'],
            (int)$data['task']->id,
            (int)$response->id,
            '<p>Peer reply.</p>',
            false,
            (int)$data['student2']->id
        );
        $this->run_send_notification((int)$reply->id);

        // Only a teacher's reply overrides the mute; a peer's does not.
        $this->assertNotContains((int)$data['student1']->id, $this->message_recipient_ids($sink));
    }

    public function test_parse_tasktypes_config_skips_malformed_lines(): void {
        $config = "explore|Explore|c4lv-mu-explore\n"
            . "\n"
            . "not-enough-parts\n"
            . "has space|Bad shortname|css\n"
            . "|Empty shortname|css\n"
            . "watch|Watch|c4lv-mu-watch c4lv-mu-watch1";

        $types = manager::parse_tasktypes_config($config);

        $this->assertSame(['explore', 'watch'], array_keys($types));
        $this->assertSame('Explore', $types['explore']['name']);
        $this->assertSame('c4lv-mu-explore', $types['explore']['cssclasses']);
        $this->assertSame('Watch', $types['watch']['name']);
        $this->assertSame('c4lv-mu-watch c4lv-mu-watch1', $types['watch']['cssclasses']);
    }

    public function test_get_task_types_falls_back_to_default_when_unset(): void {
        set_config('tasktypes', '', 'mod_task');

        $options = manager::get_task_type_options();

        $this->assertSame(['explore', 'watch', 'read', 'write'], array_keys($options));
        $this->assertSame('explore', manager::default_task_type());
    }

    public function test_get_task_types_reads_site_config(): void {
        set_config('tasktypes', "custom|Custom Type|my-css-class", 'mod_task');

        $this->assertSame(['custom' => 'Custom Type'], manager::get_task_type_options());
        $this->assertSame('my-css-class', manager::get_task_type_css('custom'));
        $this->assertSame('', manager::get_task_type_css('explore'));
        $this->assertSame('custom', manager::default_task_type());
    }

    public function test_view_exposes_tasktype_css_classes(): void {
        $data = $this->setup_course_task(['tasktype' => 'watch']);
        $this->setUser($data['student1']);

        $view = manager::get_task_view($data['context'], (int)$data['task']->id);

        $this->assertSame('c4lv-mu-watch c4lv-mu-watch1', $view['taskdescriptioncssclasses']);
    }

    /**
     * Run the notification adhoc task for a given post.
     *
     * @param int $postid the post id
     */
    protected function run_send_notification(int $postid): void {
        $task = new \mod_task\task\send_notification();
        $task->set_custom_data(['postid' => $postid]);
        $task->execute();
    }

    /**
     * Collect the sorted recipient user ids from a message sink.
     *
     * @param \phpunit_message_sink $sink the message sink
     * @return int[] the recipient ids, sorted ascending
     */
    protected function message_recipient_ids(\phpunit_message_sink $sink): array {
        $ids = [];
        foreach ($sink->get_messages() as $message) {
            $ids[] = (int)$message->useridto;
        }
        sort($ids);
        return $ids;
    }
}
