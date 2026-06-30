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

namespace mod_task\task;

/**
 * Adhoc task: notify staff of a new student response or reply.
 *
 * @package    mod_task
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_notification extends \core\task\adhoc_task {
    /**
     * Send the notification to every staff member who has opted in.
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $postid = (int)($data->postid ?? 0);

        $post = $DB->get_record('task_post', ['id' => $postid]);
        if (!$post || $post->deleted) {
            return;
        }
        $task = $DB->get_record('task', ['id' => $post->taskid]);
        if (!$task || empty($task->notifyteacher)) {
            return;
        }
        $cm = get_coursemodule_from_instance('task', $task->id, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        $context = \context_module::instance($cm->id);
        $course = $DB->get_record('course', ['id' => $task->course]);
        $author = \core_user::get_user((int)$post->userid);
        if (!$author) {
            return;
        }

        $taskname = format_string($task->name, true, ['context' => $context]);
        $url = new \moodle_url('/mod/task/view.php', ['id' => $cm->id]);
        $a = (object) ['author' => fullname($author), 'taskname' => $taskname];
        $subject = get_string('newresponsesubject', 'mod_task', $taskname);
        $body = get_string($post->parentid ? 'newreplybody' : 'newresponsebody', 'mod_task', $a);

        $recipients = get_users_by_capability($context, 'mod/task:receivenotification');
        foreach ($recipients as $recipient) {
            if ((int)$recipient->id === (int)$post->userid) {
                continue;
            }

            $message = new \core\message\message();
            $message->component = 'mod_task';
            $message->name = 'newresponse';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $recipient;
            $message->courseid = $course->id;
            $message->subject = $subject;
            $message->fullmessage = $body;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = \html_writer::tag('p', s($body));
            $message->smallmessage = $body;
            $message->notification = 1;
            $message->contexturl = $url->out(false);
            $message->contexturlname = $taskname;

            message_send($message);
        }
    }
}
