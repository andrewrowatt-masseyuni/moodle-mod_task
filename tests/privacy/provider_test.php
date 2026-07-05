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

namespace mod_task\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use mod_task\manager;

/**
 * Privacy provider tests for mod_task.
 *
 * @package    mod_task
 * @category   test
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_task\privacy\provider
 */
final class provider_test extends provider_testcase {
    /** @var \stdClass the course */
    private $course;

    /** @var \stdClass the first task instance */
    private $task1;

    /** @var \stdClass the second task instance */
    private $task2;

    /** @var \context_module the first task's context */
    private $context1;

    /** @var \context_module the second task's context */
    private $context2;

    /** @var \stdClass first student: posts, reacts, lastviewed and pref in task1 */
    private $student1;

    /** @var \stdClass second student: posts in task1 and task2 */
    private $student2;

    /** @var \stdClass third student: no mod_task data at all */
    private $student3;

    /**
     * Create two tasks and three students with a known spread of data.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        /** @var \mod_task_generator $taskgenerator */
        $taskgenerator = $generator->get_plugin_generator('mod_task');

        $this->course = $generator->create_course();
        $this->task1 = $taskgenerator->create_instance(['course' => $this->course->id]);
        $this->task2 = $taskgenerator->create_instance(['course' => $this->course->id]);
        $this->context1 = \context_module::instance($this->task1->cmid);
        $this->context2 = \context_module::instance($this->task2->cmid);

        $this->student1 = $generator->create_and_enrol($this->course, 'student');
        $this->student2 = $generator->create_and_enrol($this->course, 'student');
        $this->student3 = $generator->create_and_enrol($this->course, 'student');

        // Task 1: student1 responds; student2 responds and replies to student1;
        // student1 reacts to student2's response, views the task and sets a
        // notification preference.
        $response1 = $taskgenerator->create_response([
            'taskid' => $this->task1->id,
            'userid' => $this->student1->id,
            'content' => '<p>Student one response.</p>',
            'anonymous' => 1,
        ]);
        $response2 = $taskgenerator->create_response([
            'taskid' => $this->task1->id,
            'userid' => $this->student2->id,
            'content' => '<p>Student two response.</p>',
        ]);
        $taskgenerator->create_reply([
            'taskid' => $this->task1->id,
            'userid' => $this->student2->id,
            'parentid' => $response1->id,
            'content' => '<p>Student two reply.</p>',
        ]);
        $taskgenerator->create_reaction([
            'postid' => $response2->id,
            'userid' => $this->student1->id,
            'emoji' => 'thumbsup',
        ]);
        manager::mark_viewed((int)$this->task1->id, (int)$this->student1->id);
        manager::set_notification_preference((int)$this->task1->id, (int)$this->student1->id, manager::NOTIFY_ALL);

        // Task 2: only student2 responds.
        $taskgenerator->create_response([
            'taskid' => $this->task2->id,
            'userid' => $this->student2->id,
            'content' => '<p>Student two in task two.</p>',
        ]);
    }

    /**
     * All four tables are declared in the metadata.
     */
    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('mod_task'));
        $tables = [];
        foreach ($collection->get_collection() as $type) {
            $tables[] = $type->get_name();
        }
        $this->assertEqualsCanonicalizing(
            ['task_post', 'task_reaction', 'task_lastviewed', 'task_notifypref'],
            $tables
        );
    }

    /**
     * Each user's contexts reflect where they actually have data.
     */
    public function test_get_contexts_for_userid(): void {
        $contextids = provider::get_contexts_for_userid($this->student1->id)->get_contextids();
        $this->assertEqualsCanonicalizing([$this->context1->id], $contextids);

        $contextids = provider::get_contexts_for_userid($this->student2->id)->get_contextids();
        $this->assertEqualsCanonicalizing([$this->context1->id, $this->context2->id], $contextids);

        $this->assertEmpty(provider::get_contexts_for_userid($this->student3->id)->get_contextids());
    }

    /**
     * A reaction alone (no posts) is enough to surface a context.
     */
    public function test_get_contexts_for_userid_reaction_only(): void {
        /** @var \mod_task_generator $taskgenerator */
        $taskgenerator = $this->getDataGenerator()->get_plugin_generator('mod_task');
        $post = $taskgenerator->create_response([
            'taskid' => $this->task2->id,
            'userid' => $this->student1->id,
        ]);
        // Student3 reacts in task2 without posting anything anywhere.
        $taskgenerator->create_reaction(['postid' => $post->id, 'userid' => $this->student3->id]);

        $contextids = provider::get_contexts_for_userid($this->student3->id)->get_contextids();
        $this->assertEqualsCanonicalizing([$this->context2->id], $contextids);
    }

    /**
     * All users with data in a context are found, and no-one else.
     */
    public function test_get_users_in_context(): void {
        $userlist = new userlist($this->context1, 'mod_task');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing(
            [$this->student1->id, $this->student2->id],
            $userlist->get_userids()
        );

        $userlist = new userlist($this->context2, 'mod_task');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$this->student2->id], $userlist->get_userids());
    }

    /**
     * Export includes the user's posts, reactions, last-viewed time and preference.
     */
    public function test_export_user_data(): void {
        $contextlist = new approved_contextlist($this->student1, 'mod_task', [$this->context1->id]);
        provider::export_user_data($contextlist);

        $writer = writer::with_context($this->context1);
        $this->assertTrue($writer->has_any_data());

        global $DB;
        $post = $DB->get_record('task_post', ['taskid' => $this->task1->id, 'userid' => $this->student1->id]);
        $exportedpost = $writer->get_data(['posts', $post->id]);
        $this->assertEquals('<p>Student one response.</p>', $exportedpost->content);
        $this->assertEquals(get_string('yes'), $exportedpost->anonymous);

        $reaction = $DB->get_record('task_reaction', ['userid' => $this->student1->id]);
        $exportedreaction = $writer->get_data(['reactions', $reaction->id]);
        $this->assertEquals('thumbsup', $exportedreaction->emoji);

        $this->assertNotEmpty($writer->get_data(['lastviewed'])->timeviewed);
        $this->assertEquals(manager::NOTIFY_ALL, $writer->get_data(['notificationpreference'])->preference);

        // Nothing of student2's is exported under student1's request: their own
        // subcontexts cover only student1's two data points above.
        $this->assertEmpty($writer->get_data(['posts', $post->id + 1]));
    }

    /**
     * Deleting a whole context removes every row for that task, and only that task.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        provider::delete_data_for_all_users_in_context($this->context1);

        $this->assertEquals(0, $DB->count_records('task_post', ['taskid' => $this->task1->id]));
        $this->assertEquals(0, $DB->count_records('task_reaction'));
        $this->assertEquals(0, $DB->count_records('task_lastviewed', ['taskid' => $this->task1->id]));
        $this->assertEquals(0, $DB->count_records('task_notifypref', ['taskid' => $this->task1->id]));

        // Task 2 is untouched.
        $this->assertEquals(1, $DB->count_records('task_post', ['taskid' => $this->task2->id]));
    }

    /**
     * Deleting one user's data soft-deletes their posts and removes the rest,
     * leaving other users' data intact.
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $contextlist = new approved_contextlist($this->student1, 'mod_task', [$this->context1->id]);
        provider::delete_data_for_user($contextlist);

        // Student1's post survives as a blanked, soft-deleted row (replies to it
        // authored by others must keep their thread).
        $post = $DB->get_record('task_post', ['taskid' => $this->task1->id, 'userid' => $this->student1->id]);
        $this->assertEquals(1, $post->deleted);
        $this->assertSame('', $post->content);

        $this->assertEquals(0, $DB->count_records('task_reaction', ['userid' => $this->student1->id]));
        $this->assertEquals(0, $DB->count_records('task_lastviewed', ['userid' => $this->student1->id]));
        $this->assertEquals(0, $DB->count_records('task_notifypref', ['userid' => $this->student1->id]));

        // Student2's data in the same context is untouched.
        $posts = $DB->get_records('task_post', ['taskid' => $this->task1->id, 'userid' => $this->student2->id]);
        $this->assertCount(2, $posts);
        foreach ($posts as $post) {
            $this->assertEquals(0, $post->deleted);
            $this->assertNotSame('', $post->content);
        }
    }

    /**
     * Deleting an approved userlist affects exactly those users in that context.
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        $userlist = new approved_userlist($this->context1, 'mod_task', [$this->student2->id]);
        provider::delete_data_for_users($userlist);

        // Student2's task1 posts are blanked and soft-deleted.
        $posts = $DB->get_records('task_post', ['taskid' => $this->task1->id, 'userid' => $this->student2->id]);
        $this->assertCount(2, $posts);
        foreach ($posts as $post) {
            $this->assertEquals(1, $post->deleted);
            $this->assertSame('', $post->content);
        }

        // Student1's post and reaction in task1 are untouched.
        $post = $DB->get_record('task_post', ['taskid' => $this->task1->id, 'userid' => $this->student1->id]);
        $this->assertEquals(0, $post->deleted);
        $this->assertEquals(1, $DB->count_records('task_reaction', ['userid' => $this->student1->id]));

        // Student2's post in task2 (a different context) is untouched.
        $post = $DB->get_record('task_post', ['taskid' => $this->task2->id, 'userid' => $this->student2->id]);
        $this->assertEquals(0, $post->deleted);
    }
}
