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
 * English language strings for mod_task.
 *
 * @package    mod_task
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addanotherresponse'] = 'Add another response';
$string['addresponse'] = 'Add your response';
$string['anonymous'] = 'Anonymous';
$string['anonymousbadge'] = 'Anonymous';
$string['befirsttorespond'] = 'Be the first to respond.';
$string['cancel'] = 'Cancel';
$string['delete'] = 'Delete';
$string['deleteconfirm'] = 'Are you sure you want to delete this post?';
$string['deleted_post'] = 'This post has been deleted.';
$string['edit'] = 'Edit';
$string['edited'] = 'edited';
$string['emojis'] = 'Reaction emoji';
$string['emojis_desc'] = 'Comma-separated list of shortcode:emoji pairs offered as reactions, e.g. <code>thumbsup:👍,heart:❤️</code>.';
$string['error_cannotrespondyet'] = 'You cannot do that yet.';
$string['error_emptypost'] = 'Your post cannot be empty.';
$string['error_invalidtask'] = 'Invalid Task.';
$string['eventpostdeleted'] = 'Task post deleted';
$string['eventpostreacted'] = 'Task post reaction toggled';
$string['eventpostupdated'] = 'Task post updated';
$string['eventreplycreated'] = 'Task reply created';
$string['eventresponsecreated'] = 'Task response created';
$string['ismodelanswer'] = 'Teacher response is a model answer';
$string['ismodelanswer_help'] = 'If set to Yes, the teacher response is displayed with a "Model answer" badge once the student can see it.';
$string['messageprovider:newresponse'] = 'Notification of new Task responses';
$string['modelanswer'] = 'Model answer';
$string['modulename'] = 'Task';
$string['modulename_help'] = 'The Task activity lets a teacher set a task for students to respond to. Students must post their own response before they can see the teacher\'s response (optionally flagged as a model answer) and the responses of their peers. Students may respond anonymously, react to responses with emoji, and reply to one another. A Task can be embedded in a label or book chapter with the {task:Task name} syntax.';
$string['modulenameplural'] = 'Tasks';
$string['mustrespondfirst'] = 'Post your response below to see the teacher response and other students\' responses.';
$string['newest'] = 'Newest';
$string['newreplybody'] = '{$a->author} posted a new reply in the Task "{$a->taskname}".';
$string['newresponsebody'] = '{$a->author} posted a new response in the Task "{$a->taskname}".';
$string['newresponsesubject'] = 'New response in {$a}';
$string['noresponses'] = 'No responses yet.';
$string['notifyteacher'] = 'Notify me of new responses';
$string['notifyteacher_help'] = 'If set to Yes, staff who can receive notifications are notified of each new student response or reply.';
$string['now'] = 'now';
$string['nresponses'] = '{$a} responses';
$string['oldest'] = 'Oldest';
$string['onenewresponse'] = '1 new response';
$string['oneresponse'] = '1 response';
$string['pluginadministration'] = 'Task administration';
$string['pluginname'] = 'Task';
$string['post'] = 'Post';
$string['privacy:metadata:task_lastviewed'] = 'When the user last viewed each Task, used for the new-response indicator.';
$string['privacy:metadata:task_lastviewed:taskid'] = 'The Task that was viewed.';
$string['privacy:metadata:task_lastviewed:timeviewed'] = 'When the Task was last viewed.';
$string['privacy:metadata:task_lastviewed:userid'] = 'The user who viewed it.';
$string['privacy:metadata:task_post'] = 'Responses and replies posted by the user in Task activities.';
$string['privacy:metadata:task_post:anonymous'] = 'Whether the post was made anonymously to peers.';
$string['privacy:metadata:task_post:content'] = 'The content of the post.';
$string['privacy:metadata:task_post:taskid'] = 'The Task the post belongs to.';
$string['privacy:metadata:task_post:timecreated'] = 'When the post was created.';
$string['privacy:metadata:task_post:userid'] = 'The user who wrote the post.';
$string['privacy:metadata:task_reaction'] = 'Emoji reactions the user added to Task posts.';
$string['privacy:metadata:task_reaction:emoji'] = 'The emoji shortcode.';
$string['privacy:metadata:task_reaction:postid'] = 'The post that was reacted to.';
$string['privacy:metadata:task_reaction:timecreated'] = 'When the reaction was added.';
$string['privacy:metadata:task_reaction:userid'] = 'The user who reacted.';
$string['reacttothispost'] = 'React to this post';
$string['relativetime'] = '{$a} ago';
$string['reply'] = 'Reply';
$string['respondanonymously'] = 'Respond anonymously';
$string['respondanonymously_help'] = 'If ticked, your name is hidden from other students. Teachers always see your name.';
$string['responseheading'] = 'Responses';
$string['responses'] = 'responses';
$string['save'] = 'Save';
$string['showcardbadge'] = 'Show new-response badge';
$string['showcardbadge_desc'] = 'Show an "x new responses" badge on the course page activity card.';
$string['task:addinstance'] = 'Add a new Task';
$string['task:manageresponses'] = 'Edit and delete any response or reply';
$string['task:receivenotification'] = 'Receive notification of new responses';
$string['task:respond'] = 'Respond and react in a Task';
$string['task:view'] = 'View Task';
$string['task:viewallresponses'] = 'View all responses without responding first';
$string['taskdescription'] = 'Task description';
$string['taskdescription_help'] = 'The task the student is asked to respond to. This is always visible, including before the student posts a response.';
$string['taskembeddederror'] = 'This Task cannot be embedded here.';
$string['taskname'] = 'Task name';
$string['tasknotfound'] = 'No Task activity named "{$a}" was found in this course.';
$string['teacherresponse'] = 'Teacher response';
$string['teacherresponse_help'] = 'An optional response shown to each student only after they have posted their own response. Leave empty if not required.';
$string['teacherresponseheading'] = 'Teacher response';
$string['totalreactions'] = 'reactions';
$string['viewtask'] = 'View Task';
$string['xnewresponses'] = '{$a} new responses';
