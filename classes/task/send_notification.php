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

use mod_task\manager;

/**
 * Adhoc task: notify participants of a new response or reply.
 *
 * Each enrolled participant is notified (or not) according to their own
 * per-user notification preference for the Task. The author's name is resolved
 * per-recipient so an anonymous post never reveals its author to a peer.
 *
 * @package    mod_task
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_notification extends \core\task\adhoc_task {
    /**
     * Send the notification to every participant whose preference matches.
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
        if (!$task) {
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

        $isreply = ((int)$post->parentid !== 0);
        $isanonymous = (bool)$post->anonymous;
        // For the "replies to my response only" preference we need to know whose
        // response thread this reply belongs to.
        $rootauthorid = $isreply ? manager::thread_root_author((int)$post->id) : 0;

        // Candidate recipients are the enrolled participants: anyone who can
        // respond or reply. Staff (who see all responses) determine both the
        // default preference and whether a recipient may see the real name
        // behind an anonymous post.
        $recipients = get_enrolled_users($context, ['mod/task:respond', 'mod/task:reply'], 0, 'u.*', null, 0, 0, true);
        $staff = get_users_by_capability($context, 'mod/task:viewallresponses', 'u.id');
        $staffids = [];
        foreach ($staff as $staffuser) {
            $staffids[(int)$staffuser->id] = true;
        }

        // A reply from a teacher (staff) is the one notification a muted student
        // still receives, but only on their own response thread.
        $isteacherreply = $isreply && isset($staffids[(int)$post->userid]);

        $taskname = format_string($task->name, true, ['context' => $context]);
        $url = new \moodle_url('/mod/task/view.php', ['id' => $cm->id]);
        $subject = get_string('newresponsesubject', 'mod_task', $taskname);
        $realname = fullname($author);
        $anonname = get_string('anonymous', 'mod_task');
        $bodykey = $isreply ? 'newreplybody' : 'newresponsebody';

        foreach ($recipients as $recipient) {
            $recipientid = (int)$recipient->id;
            if ($recipientid === (int)$post->userid) {
                continue;
            }

            $isstaff = isset($staffids[$recipientid]);
            $preference = manager::effective_notification_preference((int)$task->id, $recipientid, $isstaff);
            if (!manager::should_notify_for_post($preference, $isreply, $rootauthorid, $recipientid, $isteacherreply)) {
                continue;
            }

            // Peers never see the real name behind an anonymous post; staff do.
            $authorname = ($isanonymous && !$isstaff) ? $anonname : $realname;
            $body = get_string($bodykey, 'mod_task', (object) ['author' => $authorname, 'taskname' => $taskname]);
            // HTML variant: the task name is a direct link to the Task module.
            $bodyhtml = get_string($bodykey, 'mod_task', (object) [
                'author' => s($authorname),
                'taskname' => \html_writer::link($url, $taskname),
            ]);

            $message = new \core\message\message();
            $message->component = 'mod_task';
            $message->name = 'newresponse';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $recipient;
            $message->courseid = $course->id;
            $message->subject = $subject;
            $message->fullmessage = $body;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = \html_writer::tag('p', $bodyhtml);
            $message->smallmessage = $body;
            $message->notification = 1;
            $message->contexturl = $url->out(false);
            $message->contexturlname = $taskname;

            message_send($message);
        }
    }
}
