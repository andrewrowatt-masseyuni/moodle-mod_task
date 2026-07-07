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

$string['allresponsesheading'] = 'All responses';
$string['anonymous'] = 'Anonymous';
$string['anonymousbadge'] = 'Anonymous';
$string['anonymousposts'] = 'Enable students to post anonymous responses and replies';
$string['anonymousposts_help'] = 'If enabled, student posts are displayed as anonymous to other students. Teachers always see full names. Teacher interactions are never anonymous.';
$string['befirsttorespond'] = 'Be the first to respond.';
$string['completiondetail:react'] = 'Make a reaction';
$string['completiondetail:reply'] = 'Post a reply';
$string['completiondetail:respond'] = 'Add a response';
$string['completionreact'] = 'Make a reaction';
$string['completionreactdesc'] = 'Student must react to another participant\'s response or reply';
$string['completionreply'] = 'Post a reply';
$string['completionreplydesc'] = 'Student must reply to another participant\'s response or reply';
$string['completionrespond'] = 'Add a response';
$string['completionresponddesc'] = 'Student must add a response';
$string['deleteconfirm'] = 'Are you sure you want to delete this post?';
$string['deleted_post'] = 'This post has been deleted.';
$string['edited'] = 'edited';
$string['embedoncoursepage'] = 'Embed on course page';
$string['embedoncoursepage_help'] = 'If set to Yes, this Task\'s full interactive widget is shown directly on the course page, the same way it would appear if embedded in a Label using {task:Task name}, instead of the usual icon, name and description. For the cleanest layout, avoid also enabling activity completion tracking, restricted access conditions, or "show activity dates" on this Task — core adds extra chrome around embedded activities when any of those are active.';
$string['emojis'] = 'Reaction emoji';
$string['emojis_desc'] = 'Comma-separated list of shortcode:emoji pairs offered as reactions, e.g. <code>thumbsup:👍,heart:❤️</code>.';
$string['enablereactions'] = 'Enable reactions';
$string['enablereactions_desc'] = 'Enable emoji-based reactions to tasks responses and replies.';
$string['enablereactions_help'] = 'Enable emoji-based reactions to tasks responses and replies.';
$string['enablereplies'] = 'Enable replies';
$string['enablereplies_help'] = 'Enable students to reply to other student task responses and replies. Note that students can always reply to their own response.';
$string['error_alreadyresponded'] = 'You have already posted your response to this task. You can reply to responses, but you cannot post another response.';
$string['error_cannotrespondyet'] = 'You cannot do that yet.';
$string['error_emptypost'] = 'Your post cannot be empty.';
$string['error_gradecountmin'] = 'The number required for full marks must be at least 1.';
$string['error_grademaxzero'] = 'A graded task must offer at least some marks: set a marks contribution greater than zero for an enabled activity.';
$string['error_gradenegative'] = 'Marks cannot be negative.';
$string['error_invalidtask'] = 'Invalid Task.';
$string['error_reactionsdisabled'] = 'Reactions are not enabled for this task.';
$string['error_repliesdisabled'] = 'Replies to other participants are not enabled for this task.';
$string['eventpostdeleted'] = 'Task post deleted';
$string['eventpostreacted'] = 'Task post reaction toggled';
$string['eventpostupdated'] = 'Task post updated';
$string['eventreplycreated'] = 'Task reply created';
$string['eventresponsecreated'] = 'Task response created';
$string['graded'] = 'Graded task';
$string['graded_help'] = 'If set to Yes, a gradebook item is created and each student\'s grade is calculated automatically from their participation: marks for posting a response, plus marks for replies and reactions where those are enabled. The maximum grade is the sum of the enabled contributions. Note that if reactions are later disabled site-wide, the maximum grade of existing graded tasks is only refreshed the next time each task\'s settings are saved.';
$string['gradereactcount'] = 'Reactions needed for full marks';
$string['gradereactpoints'] = 'Marks for reactions';
$string['gradereactpoints_help'] = 'The maximum marks a student can earn by reacting to other participants\' posts, spread evenly across the number of reactions needed. For example, 10 marks with 2 needed awards 5 marks per post reacted to, up to 10 marks. Multiple reactions on the same post count once.';
$string['gradereplycount'] = 'Replies needed for full marks';
$string['gradereplypoints'] = 'Marks for replies';
$string['gradereplypoints_help'] = 'The maximum marks a student can earn by replying to other participants\' posts, spread evenly across the number of replies needed. For example, 20 marks with 2 needed awards 10 marks per reply, up to 20 marks. Replies within the student\'s own response thread do not count.';
$string['graderesponsepoints'] = 'Marks for posting a response';
$string['graderesponsepoints_help'] = 'The marks awarded when the student posts their response. This is all-or-nothing: the student earns the full marks by posting.';
$string['gradingheading'] = 'Grading';
$string['hideresponse'] = 'Hide response';
$string['hideyourresponse'] = 'Hide your response';
$string['ismodelanswer'] = 'Teacher response is a model answer';
$string['ismodelanswer_help'] = 'If set to Yes, the teacher response is displayed with a "Model answer" badge once the student can see it.';
$string['messageprovider:newresponse'] = 'Notification of new Task responses';
$string['modelanswer'] = 'Model answer';
$string['modulename'] = 'Task';
$string['modulename_help'] = 'The Task activity lets a teacher set a task for students to respond to. Students must post their own response before they can see the teacher\'s response (optionally flagged as a model answer) and the responses of their peers. Students may respond anonymously, react to responses with emoji, and reply to one another. A Task can be embedded in a label or book chapter with the {task:Task name} syntax.';
$string['modulenameplural'] = 'Tasks';
$string['mustrespondfirst'] = 'Post your response to see the teacher response and other students\' responses.';
$string['newest'] = 'Newest';
$string['newreplybody'] = '{$a->author} posted a new reply in the Task "{$a->taskname}".';
$string['newresponsebody'] = '{$a->author} posted a new response in the Task "{$a->taskname}".';
$string['newresponsesubject'] = 'New response in {$a}';
$string['nootherresponses'] = 'No other responses yet.';
$string['noresponses'] = 'No responses yet.';
$string['notificationpreferences'] = 'Notification preferences';
$string['notificationsettings'] = 'Notification settings';
$string['notifypref_all'] = 'All new responses and replies';
$string['notifypref_myreplies'] = 'All new replies to my response only';
$string['notifypref_none'] = 'Mute notifications (except for Teacher replies)';
$string['notifypref_responses'] = 'All new responses';
$string['now'] = 'now';
$string['nresponses'] = '{$a} responses';
$string['oldest'] = 'Oldest';
$string['onenewresponse'] = '1 new response';
$string['oneresponse'] = '1 response';
$string['otherresponsesheading'] = 'Other responses';
$string['pluginadministration'] = 'Task administration';
$string['pluginname'] = 'Task';
$string['post'] = 'Post';
$string['privacy:metadata:core_grades'] = 'Participation grades calculated for the user are stored in the gradebook.';
$string['privacy:metadata:task_lastviewed'] = 'When the user last viewed each Task, used for the new-response indicator.';
$string['privacy:metadata:task_lastviewed:taskid'] = 'The Task that was viewed.';
$string['privacy:metadata:task_lastviewed:timeviewed'] = 'When the Task was last viewed.';
$string['privacy:metadata:task_lastviewed:userid'] = 'The user who viewed it.';
$string['privacy:metadata:task_notifypref'] = 'Each user\'s notification preference for a Task.';
$string['privacy:metadata:task_notifypref:preference'] = 'The chosen notification preference.';
$string['privacy:metadata:task_notifypref:taskid'] = 'The Task the preference applies to.';
$string['privacy:metadata:task_notifypref:timemodified'] = 'When the preference was last changed.';
$string['privacy:metadata:task_notifypref:userid'] = 'The user the preference belongs to.';
$string['privacy:metadata:task_post'] = 'Responses and replies posted by the user in Task activities.';
$string['privacy:metadata:task_post:anonymous'] = 'Whether the post was made anonymously to peers.';
$string['privacy:metadata:task_post:content'] = 'The content of the post.';
$string['privacy:metadata:task_post:deleted'] = 'Whether the post has been deleted.';
$string['privacy:metadata:task_post:edited'] = 'Whether the post has been edited.';
$string['privacy:metadata:task_post:parentid'] = 'The post this reply was made to (0 for a top-level response).';
$string['privacy:metadata:task_post:taskid'] = 'The Task the post belongs to.';
$string['privacy:metadata:task_post:timecreated'] = 'When the post was created.';
$string['privacy:metadata:task_post:timemodified'] = 'When the post was last edited or deleted.';
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
$string['showcardbadge'] = 'Show new-response badge';
$string['showcardbadge_desc'] = 'Show an "x new responses" badge on the course page activity card.';
$string['showresponse'] = 'Show response';
$string['showyourresponse'] = 'Show your response';
$string['task:addinstance'] = 'Add a new Task';
$string['task:manageresponses'] = 'Edit and delete any response or reply';
$string['task:react'] = 'React to a response or reply';
$string['task:reply'] = 'Reply to a response';
$string['task:respond'] = 'Post a response to a task';
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
$string['teacherresponsestaffnote'] = 'Note: You automatically see this but it will not be visible to a student until they respond to the task.';
$string['totalreactions'] = 'reactions';
$string['viewtask'] = 'View Task';
$string['writeresponse'] = 'Write your response here...';
$string['xnewresponses'] = '{$a} new responses';
$string['you'] = 'You';
$string['yourresponseheading'] = 'Your response';
