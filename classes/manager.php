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
 * Core data layer for mod_task.
 *
 * Owns the answer-before-you-see gating, response/reply CRUD, emoji reactions
 * and the per-user view payload the JS consumes. All visibility decisions are
 * made here, server-side, so an embedded Task is exactly as safe as the
 * activity page.
 *
 * @package    mod_task
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var string Default emoji set as comma-separated shortcode:unicode pairs. */
    const DEFAULT_EMOJIS = 'thumbsup:👍,heart:❤️,laugh:😂,think:🤔,celebrate:🎉,surprise:😮,thanks:🙏';

    /** @var string Default Task type definitions: one "shortname|name|CSS classes" line per type. */
    const DEFAULT_TASKTYPES = "explore|Explore|c4lv-mu-explore c4lv-mu-explore1\n"
        . "watch|Watch|c4lv-mu-watch c4lv-mu-watch1\n"
        . "read|Read|c4lv-mu-read c4lv-mu-read1\n"
        . "write|Write|c4lv-mu-write c4lv-mu-write1";

    /** @var int Notification preference: mute all notifications. */
    const NOTIFY_NONE = 0;

    /** @var int Notification preference: all new top-level responses. */
    const NOTIFY_RESPONSES = 1;

    /** @var int Notification preference: all new responses and all new replies. */
    const NOTIFY_ALL = 2;

    /** @var int Notification preference: only new replies within the user's own response thread. */
    const NOTIFY_MYREPLIES = 3;

    /** @var array<string,string>|null Per-request cache of the parsed emoji set. */
    private static ?array $emojisetcache = null;

    /** @var array<string,array{name:string,cssclasses:string}>|null Per-request cache of the parsed Task types. */
    private static ?array $tasktypescache = null;

    /**
     * The recognised notification preferences in display order.
     *
     * @return int[] the preference values
     */
    public static function notification_preferences(): array {
        return [self::NOTIFY_NONE, self::NOTIFY_RESPONSES, self::NOTIFY_ALL, self::NOTIFY_MYREPLIES];
    }

    /**
     * Fetch a task record.
     *
     * @param int $taskid the task instance id
     * @return \stdClass
     */
    public static function get_task(int $taskid): \stdClass {
        global $DB;
        return $DB->get_record('task', ['id' => $taskid], '*', MUST_EXIST);
    }

    /**
     * Get the configured emoji set.
     *
     * @return array associative array of shortcode => unicode emoji, in configured order
     */
    public static function get_emoji_set(): array {
        if (self::$emojisetcache !== null) {
            return self::$emojisetcache;
        }

        $config = get_config('mod_task', 'emojis');
        if (empty($config)) {
            $config = self::DEFAULT_EMOJIS;
        }

        $emojis = [];
        foreach (explode(',', $config) as $pair) {
            $parts = explode(':', trim($pair), 2);
            if (count($parts) === 2 && trim($parts[0]) !== '' && trim($parts[1]) !== '') {
                $emojis[trim($parts[0])] = trim($parts[1]);
            }
        }
        return self::$emojisetcache = $emojis;
    }

    /**
     * Reset the per-request config caches (emoji set, Task types).
     *
     * Only needed by tests: PHPUnit does not restart the PHP process between
     * test methods, so a static cache populated in one test would otherwise
     * leak into the next test's assertions after it calls set_config().
     */
    public static function reset_caches(): void {
        self::$emojisetcache = null;
        self::$tasktypescache = null;
    }

    /**
     * Parse the "shortname|name|CSS classes" lines of a Task types definition.
     *
     * Malformed lines (wrong number of parts, an empty shortname/name, or a
     * shortname containing whitespace) are silently skipped: the admin setting
     * itself rejects malformed input at save time (see {@see admin_setting_tasktypes}),
     * so this lenient parse only has to cope with an empty or unset config.
     *
     * @param string $config the raw textarea content
     * @return array<string,array{name:string,cssclasses:string}> keyed by shortname, in file order
     */
    public static function parse_tasktypes_config(string $config): array {
        $types = [];
        foreach (preg_split('/\r\n|\r|\n/', $config) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = explode('|', $line, 3);
            if (count($parts) !== 3) {
                continue;
            }
            [$shortname, $name, $cssclasses] = array_map('trim', $parts);
            if ($shortname === '' || $name === '' || preg_match('/\s/', $shortname)) {
                continue;
            }
            $types[$shortname] = ['name' => $name, 'cssclasses' => $cssclasses];
        }
        return $types;
    }

    /**
     * Get the configured Task types.
     *
     * @return array<string,array{name:string,cssclasses:string}> keyed by shortname, in configured order
     */
    public static function get_task_types(): array {
        if (self::$tasktypescache !== null) {
            return self::$tasktypescache;
        }

        $config = get_config('mod_task', 'tasktypes');
        if (empty($config)) {
            $config = self::DEFAULT_TASKTYPES;
        }

        $types = self::parse_tasktypes_config((string)$config);
        if (empty($types)) {
            $types = self::parse_tasktypes_config(self::DEFAULT_TASKTYPES);
        }
        return self::$tasktypescache = $types;
    }

    /**
     * Task type options for the per-activity dropdown.
     *
     * @return array<string,string> shortname => display name, in configured order
     */
    public static function get_task_type_options(): array {
        $options = [];
        foreach (self::get_task_types() as $shortname => $type) {
            $options[$shortname] = $type['name'];
        }
        return $options;
    }

    /**
     * The default Task type shortname (the first configured type).
     *
     * @return string
     */
    public static function default_task_type(): string {
        $shortnames = array_keys(self::get_task_types());
        return $shortnames[0] ?? 'explore';
    }

    /**
     * The CSS classes for a Task type, to decorate the description panel.
     *
     * @param string $shortname the Task type shortname
     * @return string space-separated CSS classes, or '' if the shortname is not configured
     */
    public static function get_task_type_css(string $shortname): string {
        return self::get_task_types()[$shortname]['cssclasses'] ?? '';
    }

    /**
     * Whether the user has posted a top-level response in this task.
     *
     * @param int $taskid the task instance id
     * @param int $userid the user id
     * @return bool
     */
    public static function has_responded(int $taskid, int $userid): bool {
        global $DB;
        return $DB->record_exists_select(
            'task_post',
            'taskid = :taskid AND userid = :userid AND parentid = 0 AND deleted = 0',
            ['taskid' => $taskid, 'userid' => $userid]
        );
    }

    /**
     * Whether the user may see the teacher response and other students' responses.
     *
     * Staff (mod/task:viewallresponses) always may; everyone else must post a
     * response of their own first.
     *
     * @param \context $context the module context
     * @param int $taskid the task instance id
     * @param int|null $userid the user id, or null for the current user
     * @return bool
     */
    public static function can_see_responses(\context $context, int $taskid, ?int $userid = null): bool {
        global $USER;
        $userid = $userid ?? (int)$USER->id;
        if (has_capability('mod/task:viewallresponses', $context, $userid)) {
            return true;
        }
        return self::has_responded($taskid, $userid);
    }

    /**
     * Sanitise post HTML through Moodle's KSES-based cleaner.
     *
     * @param string $html raw HTML from the editor
     * @return string cleaned HTML
     */
    public static function sanitise(string $html): string {
        return clean_text($html, FORMAT_HTML);
    }

    /**
     * Format an instance rich-text field for output, rewriting embedded file URLs.
     *
     * @param \context $context the module context
     * @param string|null $text the stored text
     * @param int $format the stored text format
     * @param string $filearea the file area (intro|teacherresponse)
     * @return string formatted HTML
     */
    protected static function format_richtext(\context $context, ?string $text, int $format, string $filearea): string {
        $text = (string)$text;
        if ($text === '') {
            return '';
        }
        $text = file_rewrite_pluginfile_urls($text, 'pluginfile.php', $context->id, 'mod_task', $filearea, 0);
        return format_text($text, $format, ['context' => $context]);
    }

    /**
     * Build the gated view payload for the current user.
     *
     * @param \context $context the module context
     * @param int $taskid the task instance id
     * @return array the payload consumed by the JS controller
     */
    public static function get_task_view(\context $context, int $taskid): array {
        global $USER, $PAGE;

        $task = self::get_task($taskid);
        $cm = get_coursemodule_from_instance('task', $taskid, 0, false, MUST_EXIST);
        $renderer = $PAGE->get_renderer('core');

        $canrespond = has_capability('mod/task:respond', $context);
        $canreply = has_capability('mod/task:reply', $context);
        $canreact = has_capability('mod/task:react', $context);
        $canviewall = has_capability('mod/task:viewallresponses', $context);
        $canmanage = has_capability('mod/task:manageresponses', $context);
        $hasresponded = self::has_responded($taskid, (int)$USER->id);
        $cansee = $canviewall || $hasresponded;

        // A student may post exactly one top-level response (replies are unlimited),
        // so the response composer is offered only until they have responded. Staff,
        // who can view all responses, are not limited.
        $canaddresponse = $canrespond && ($canviewall || !$hasresponded);

        // Staff post under their own name; only students may choose to be anonymous.
        $cananonymous = ($canrespond || $canreply) && !$canviewall;

        $emojis = [];
        foreach (self::get_emoji_set() as $shortcode => $unicode) {
            $emojis[] = ['shortcode' => $shortcode, 'unicode' => $unicode];
        }

        $payload = [
            'taskid' => (int)$task->id,
            'contextid' => (int)$context->id,
            'name' => format_string($task->name, true, ['context' => $context]),
            'taskdescription' => self::format_richtext(
                $context,
                $task->intro,
                (int)$task->introformat,
                'intro'
            ),
            'taskdescriptioncssclasses' => self::get_task_type_css($task->tasktype),
            'canrespond' => $canrespond,
            'canaddresponse' => $canaddresponse,
            // The per-user notification preference panel is offered to anyone who
            // can take part; it sits above the task description in the JS shell.
            'shownotificationsettings' => $canrespond || $canreply,
            'notificationsettings' => [
                'cmid' => (int)$cm->id,
                'options' => self::notification_options($context, $taskid, (int)$USER->id),
            ],
            'canviewall' => $canviewall,
            'canmanage' => $canmanage,
            'canreact' => $canreact,
            'cananonymous' => $cananonymous,
            'hasresponded' => $hasresponded,
            'canseeresponses' => $cansee,
            // Staff who cannot respond see everyone's responses, so "Other
            // responses" would be misleading — for them it is "All responses".
            'showallresponsesheading' => $canviewall && !$canrespond,
            'showteacherresponse' => false,
            'showteacherresponsenote' => false,
            'teacherresponse' => '',
            'teacherresponseismodelanswer' => false,
            'emojis' => $emojis,
            'posts' => [],
            'postcount' => 0,
            'currentuserid' => (int)$USER->id,
            'currentuseravatar' => $renderer->user_picture($USER, ['size' => 64, 'link' => false]),
            'currentuserprofileurl' => (isloggedin() && !isguestuser())
                ? (new \moodle_url('/user/profile.php', ['id' => $USER->id]))->out(false)
                : '',
        ];

        if (!$cansee) {
            // Gated: the description and composer only — no teacher response, no peers.
            return $payload;
        }

        // The teacher response (if any) is revealed now.
        $teacherresponse = self::format_richtext(
            $context,
            $task->teacherresponse,
            (int)$task->teacherresponseformat,
            'teacherresponse'
        );
        if ($teacherresponse !== '') {
            $payload['showteacherresponse'] = true;
            $payload['teacherresponse'] = $teacherresponse;
            $payload['teacherresponseismodelanswer'] = (bool)$task->teacherresponseismodelanswer;
            // Staff see the teacher response without responding; remind them
            // that students only see it after posting their own response.
            $payload['showteacherresponsenote'] = $canviewall;
        }

        $payload['posts'] = self::build_posts($context, $taskid, $canviewall, $canmanage, $canreply, $renderer);
        $payload['postcount'] = count($payload['posts']);

        return $payload;
    }

    /**
     * Build the per-post view rows for a task.
     *
     * @param \context $context the module context
     * @param int $taskid the task instance id
     * @param bool $canviewall whether the viewer is staff
     * @param bool $canmanage whether the viewer can moderate
     * @param bool $canreply whether the viewer can reply
     * @param \renderer_base $renderer the core renderer
     * @return array list of post view rows
     */
    protected static function build_posts(
        \context $context,
        int $taskid,
        bool $canviewall,
        bool $canmanage,
        bool $canreply,
        \renderer_base $renderer
    ): array {
        global $DB, $USER;

        $posts = $DB->get_records('task_post', ['taskid' => $taskid, 'deleted' => 0], 'timecreated ASC');
        if (empty($posts)) {
            return [];
        }

        // Batch users, role assignments and reactions.
        $userids = [];
        $postids = [];
        foreach ($posts as $p) {
            $userids[(int)$p->userid] = true;
            $postids[] = (int)$p->id;
        }
        $users = $DB->get_records_list('user', 'id', array_keys($userids));
        $roles = [];
        foreach (array_keys($userids) as $uid) {
            $roles[$uid] = get_user_roles($context, $uid, true);
        }
        $reactions = self::get_reactions($postids, (int)$USER->id);

        $out = [];
        foreach ($posts as $post) {
            $out[] = self::build_post_view(
                $post,
                $context,
                $users,
                $roles,
                $reactions,
                $canviewall,
                $canmanage,
                $canreply,
                $renderer
            );
        }
        return $out;
    }

    /**
     * Build the view row for a single post, applying anonymity for the viewer.
     *
     * @param \stdClass $post the post record
     * @param \context $context the module context
     * @param array $users batched user records keyed by id
     * @param array $roles batched role assignments keyed by user id
     * @param array $reactions batched reaction data keyed by post id
     * @param bool $canviewall whether the viewer is staff (sees real names)
     * @param bool $canmanage whether the viewer can moderate
     * @param bool $canreply whether the viewer can reply
     * @param \renderer_base $renderer the core renderer
     * @return array the post view row
     */
    protected static function build_post_view(
        \stdClass $post,
        \context $context,
        array $users,
        array $roles,
        array $reactions,
        bool $canviewall,
        bool $canmanage,
        bool $canreply,
        \renderer_base $renderer
    ): array {
        global $USER, $DB;

        $authorid = (int)$post->userid;
        $author = $users[$authorid] ?? $DB->get_record('user', ['id' => $authorid]);
        $isown = ($authorid === (int)$USER->id) && !$post->deleted; /* Deleted posts are not owned by anyone */
        $isanon = (bool)$post->anonymous;

        $authorname = '';
        $authorrole = '';
        $profileurl = '';
        $avatar = '';
        $showanonymousbadge = false;

        // Staff and the author always see the real identity; peers see "Anonymous".
        $showrealname = !$isanon || $isown || $canviewall;

        if ($post->deleted) {
            $avatar = self::neutral_avatar($renderer);
        } else if ($showrealname && $author) {
            // The viewer's own posts are labelled "You" rather than their name.
            $authorname = $isown ? get_string('you', 'mod_task') : fullname($author);
            $profileurl = (new \moodle_url('/user/profile.php', ['id' => $author->id]))->out(false);
            $avatar = $renderer->user_picture($author, ['size' => 64, 'link' => false]);
            $authorrole = self::user_role_label($context, $authorid, $roles[$authorid] ?? null);
            $showanonymousbadge = $isanon; // Reveal to staff/author that this is hidden from peers.
        } else {
            $authorname = get_string('anonymous', 'mod_task');
            $avatar = self::neutral_avatar($renderer);
        }

        $reactiondata = $reactions[(int)$post->id] ?? ['counts' => [], 'userreactions' => []];
        $reactioncounts = [];
        foreach ($reactiondata['counts'] as $emoji => $count) {
            $reactioncounts[] = ['emoji' => $emoji, 'count' => (int)$count];
        }

        // Editing and deleting are moderation actions reserved for staff who can
        // manage responses; students cannot edit or delete their own posts.
        $candelete = !$post->deleted && $canmanage;
        $canedit = !$post->deleted && $canmanage;

        return [
            'id' => (int)$post->id,
            'parentid' => (int)$post->parentid,
            'ismine' => $isown,
            'content' => $post->deleted ? '' : format_text(
                $post->content,
                FORMAT_HTML,
                ['context' => $context, 'filter' => false]
            ),
            'deleted' => (bool)$post->deleted,
            'edited' => (bool)$post->edited,
            'timecreated' => (int)$post->timecreated,
            'timecreatediso' => userdate($post->timecreated, get_string('strftimedatetime', 'langconfig')),
            'authorname' => $authorname,
            'authorrole' => $authorrole,
            'isanonymous' => $isanon,
            'showanonymousbadge' => $showanonymousbadge,
            'profileurl' => $profileurl,
            'avatar' => $avatar,
            'reactions' => [
                'counts' => $reactioncounts,
                'userreactions' => array_values($reactiondata['userreactions']),
            ],
            'canedit' => $canedit,
            'candelete' => $candelete,
            'canreply' => $canreply && !$post->deleted,
        ];
    }

    /**
     * Build a neutral placeholder avatar (used for anonymous and deleted posts).
     *
     * @param \renderer_base $renderer the core renderer
     * @return string an img tag
     */
    protected static function neutral_avatar(\renderer_base $renderer): string {
        return \html_writer::empty_tag('img', [
            'class' => 'userpicture',
            'alt' => '',
            'src' => $renderer->image_url('u/f1')->out(false),
            'width' => 48,
            'height' => 48,
        ]);
    }

    /**
     * Create a response or reply.
     *
     * @param \context $context the module context
     * @param int $taskid the task instance id
     * @param int $parentid the parent post id (0 for a top-level response)
     * @param string $content raw HTML from the editor
     * @param bool $anonymous whether the student asked to be anonymous to peers
     * @param int $userid the author user id
     * @return \stdClass the new post record
     */
    public static function create_post(
        \context $context,
        int $taskid,
        int $parentid,
        string $content,
        bool $anonymous,
        int $userid
    ): \stdClass {
        global $DB;

        // Responses and replies are separate permissions: students respond,
        // students and staff reply.
        require_capability($parentid > 0 ? 'mod/task:reply' : 'mod/task:respond', $context);

        $clean = self::sanitise($content);
        if (trim(html_to_text($clean)) === '' && stripos($clean, '<img') === false) {
            throw new \moodle_exception('error_emptypost', 'mod_task');
        }

        // Staff (who can view all responses) are not limited; students may post
        // exactly one top-level response, but any number of replies.
        $canviewall = has_capability('mod/task:viewallresponses', $context, $userid);

        if ($parentid > 0) {
            $parent = $DB->get_record('task_post', ['id' => $parentid], 'id, taskid, deleted');
            if (!$parent || (int)$parent->taskid !== $taskid || $parent->deleted) {
                throw new \invalid_parameter_exception('Invalid parent post');
            }
        } else if (!$canviewall && self::has_responded($taskid, $userid)) {
            throw new \moodle_exception('error_alreadyresponded', 'mod_task');
        }

        // Only students (who cannot view all responses) may post anonymously.
        $anonymous = ($anonymous && !$canviewall) ? 1 : 0;

        $now = time();
        $record = (object) [
            'taskid' => $taskid,
            'parentid' => $parentid,
            'userid' => $userid,
            'content' => $clean,
            'anonymous' => $anonymous,
            'edited' => 0,
            'deleted' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record('task_post', $record);

        $cm = get_coursemodule_from_instance('task', $taskid, 0, false, MUST_EXIST);
        if ($parentid > 0) {
            event\reply_created::create_from_post($record, $cm, $context)->trigger();
        } else {
            event\response_created::create_from_post($record, $cm, $context)->trigger();
        }

        // Notify participants of the new post according to each recipient's own
        // notification preference (resolved when the adhoc task runs).
        $notification = new \mod_task\task\send_notification();
        $notification->set_custom_data(['postid' => (int)$record->id]);
        \core\task\manager::queue_adhoc_task($notification, true);

        return $record;
    }

    /**
     * Edit a post's content.
     *
     * @param \context $context the module context
     * @param int $postid the post id
     * @param string $content raw HTML from the editor
     * @param int $userid the editing user id
     * @return \stdClass the updated post record
     */
    public static function edit_post(\context $context, int $postid, string $content, int $userid): \stdClass {
        global $DB;

        // Editing is a moderation action: only staff who can manage responses,
        // never the student author.
        require_capability('mod/task:manageresponses', $context);

        $post = $DB->get_record('task_post', ['id' => $postid], '*', MUST_EXIST);
        if ($post->deleted) {
            throw new \moodle_exception('error_invalidtask', 'mod_task');
        }

        $clean = self::sanitise($content);
        if (trim(html_to_text($clean)) === '' && stripos($clean, '<img') === false) {
            throw new \moodle_exception('error_emptypost', 'mod_task');
        }

        $post->content = $clean;
        $post->edited = 1;
        $post->timemodified = time();
        $DB->update_record('task_post', $post);

        $cm = get_coursemodule_from_instance('task', (int)$post->taskid, 0, false, MUST_EXIST);
        event\post_updated::create_from_post($post, $cm, $context)->trigger();

        return $post;
    }

    /**
     * Soft-delete a post (keep the row, blank the content).
     *
     * @param \context $context the module context
     * @param int $postid the post id
     * @param int $userid the deleting user id
     */
    public static function delete_post(\context $context, int $postid, int $userid): void {
        global $DB;

        // Deleting is a moderation action: only staff who can manage responses,
        // never the student author.
        require_capability('mod/task:manageresponses', $context);

        $post = $DB->get_record('task_post', ['id' => $postid], '*', MUST_EXIST);
        if ($post->deleted) {
            return;
        }

        $post->deleted = 1;
        $post->content = '';
        $post->timemodified = time();
        $DB->update_record('task_post', $post);

        $cm = get_coursemodule_from_instance('task', (int)$post->taskid, 0, false, MUST_EXIST);
        event\post_deleted::create_from_post($post, $cm, $context)->trigger();
    }

    /**
     * Toggle an emoji reaction on a post.
     *
     * @param int $postid the post id
     * @param int $userid the reacting user id
     * @param string $emoji the emoji shortcode
     * @return array ['action' => 'added'|'removed', 'emoji' => string]
     */
    public static function toggle_reaction(int $postid, int $userid, string $emoji): array {
        global $DB;

        $emojiset = self::get_emoji_set();
        if (!isset($emojiset[$emoji])) {
            throw new \invalid_parameter_exception('Invalid emoji: ' . $emoji);
        }

        $key = ['postid' => $postid, 'userid' => $userid, 'emoji' => $emoji];
        $existing = $DB->get_record('task_reaction', $key);
        if ($existing) {
            $DB->delete_records('task_reaction', ['id' => $existing->id]);
            return ['action' => 'removed', 'emoji' => $emoji];
        }

        $record = (object) ($key + ['timecreated' => time()]);
        try {
            $DB->insert_record('task_reaction', $record);
        } catch (\dml_write_exception $e) {
            // A racing duplicate is an idempotent success thanks to the unique index.
            if (!$DB->record_exists('task_reaction', $key)) {
                throw $e;
            }
        }
        return ['action' => 'added', 'emoji' => $emoji];
    }

    /**
     * Get reaction counts and the current user's reactions for a set of posts.
     *
     * @param int[] $postids the post ids
     * @param int $userid the current user id
     * @return array keyed by post id: ['counts' => emoji=>total, 'userreactions' => shortcode[]]
     */
    public static function get_reactions(array $postids, int $userid): array {
        global $DB;

        $result = [];
        foreach ($postids as $postid) {
            $result[(int)$postid] = ['counts' => [], 'userreactions' => []];
        }
        if (empty($postids)) {
            return $result;
        }

        [$insql, $params] = $DB->get_in_or_equal($postids, SQL_PARAMS_NAMED, 'p');
        $uid = $DB->sql_concat('postid', "'_'", 'emoji');
        $sql = "SELECT $uid AS uid, postid, emoji, COUNT(1) AS total
                  FROM {task_reaction}
                 WHERE postid $insql
              GROUP BY postid, emoji
              ORDER BY postid, total DESC";
        foreach ($DB->get_records_sql($sql, $params) as $row) {
            $result[(int)$row->postid]['counts'][$row->emoji] = (int)$row->total;
        }

        $params['userid'] = $userid;
        $myrows = $DB->get_records_sql(
            "SELECT id, postid, emoji
               FROM {task_reaction}
              WHERE postid $insql AND userid = :userid",
            $params
        );
        foreach ($myrows as $row) {
            $result[(int)$row->postid]['userreactions'][] = $row->emoji;
        }

        return $result;
    }

    /**
     * Count responses/replies by other users newer than the viewer's last visit.
     *
     * Used for the course-card badge; respects gating so a student who has not
     * responded sees nothing.
     *
     * @param \cm_info $cm the course module
     * @param int $userid the viewing user id
     * @return int the number of new posts
     */
    public static function count_new_responses(\cm_info $cm, int $userid): int {
        global $DB;

        $context = \context_module::instance($cm->id);
        if (!self::can_see_responses($context, (int)$cm->instance, $userid)) {
            return 0;
        }

        $lastviewed = (int)$DB->get_field(
            'task_lastviewed',
            'timeviewed',
            ['taskid' => (int)$cm->instance, 'userid' => $userid]
        );

        return $DB->count_records_select(
            'task_post',
            'taskid = :taskid AND userid <> :userid AND deleted = 0 AND timecreated > :lastviewed',
            ['taskid' => (int)$cm->instance, 'userid' => $userid, 'lastviewed' => $lastviewed]
        );
    }

    /**
     * Record that the user has viewed the task now, clearing the new-response badge.
     *
     * @param int $taskid the task instance id
     * @param int $userid the user id
     */
    public static function mark_viewed(int $taskid, int $userid): void {
        global $DB;

        $now = time();
        $existing = $DB->get_record('task_lastviewed', ['taskid' => $taskid, 'userid' => $userid]);
        if ($existing) {
            $DB->set_field('task_lastviewed', 'timeviewed', $now, ['id' => $existing->id]);
        } else {
            $DB->insert_record('task_lastviewed', (object) [
                'taskid' => $taskid,
                'userid' => $userid,
                'timeviewed' => $now,
            ]);
        }
    }

    /**
     * The current user's effective notification preference for a task.
     *
     * Returns the value the user has stored, or the role-based default when they
     * have never chosen one.
     *
     * @param \context $context the module context
     * @param int $taskid the task instance id
     * @param int $userid the user id
     * @return int one of the NOTIFY_* constants
     */
    public static function get_notification_preference(\context $context, int $taskid, int $userid): int {
        global $DB;
        $stored = $DB->get_field('task_notifypref', 'preference', ['taskid' => $taskid, 'userid' => $userid]);
        if ($stored !== false) {
            return (int)$stored;
        }
        return self::default_notification_preference($context, $userid);
    }

    /**
     * The role-based default notification preference.
     *
     * Staff (who can view all responses) default to being notified of all
     * responses and replies; everyone else defaults to replies within their own
     * response thread only.
     *
     * @param \context $context the module context
     * @param int $userid the user id
     * @return int one of the NOTIFY_* constants
     */
    public static function default_notification_preference(\context $context, int $userid): int {
        return has_capability('mod/task:viewallresponses', $context, $userid)
            ? self::NOTIFY_ALL
            : self::NOTIFY_MYREPLIES;
    }

    /**
     * The effective notification preference, given a pre-computed staff flag.
     *
     * A lower-cost variant of {@see get_notification_preference()} for bulk
     * recipient evaluation, where the staff status of each candidate is already
     * known (so no capability check is needed for the default).
     *
     * @param int $taskid the task instance id
     * @param int $userid the user id
     * @param bool $isstaff whether the user can view all responses
     * @return int one of the NOTIFY_* constants
     */
    public static function effective_notification_preference(int $taskid, int $userid, bool $isstaff): int {
        global $DB;
        $stored = $DB->get_field('task_notifypref', 'preference', ['taskid' => $taskid, 'userid' => $userid]);
        if ($stored !== false) {
            return (int)$stored;
        }
        return $isstaff ? self::NOTIFY_ALL : self::NOTIFY_MYREPLIES;
    }

    /**
     * Store the current user's notification preference for a task.
     *
     * @param int $taskid the task instance id
     * @param int $userid the user id
     * @param int $preference one of the NOTIFY_* constants
     */
    public static function set_notification_preference(int $taskid, int $userid, int $preference): void {
        global $DB;

        if (!in_array($preference, self::notification_preferences(), true)) {
            throw new \invalid_parameter_exception('Invalid notification preference: ' . $preference);
        }

        $now = time();
        $existing = $DB->get_record('task_notifypref', ['taskid' => $taskid, 'userid' => $userid]);
        if ($existing) {
            $DB->update_record('task_notifypref', (object) [
                'id' => $existing->id,
                'preference' => $preference,
                'timemodified' => $now,
            ]);
        } else {
            $DB->insert_record('task_notifypref', (object) [
                'taskid' => $taskid,
                'userid' => $userid,
                'preference' => $preference,
                'timemodified' => $now,
            ]);
        }
    }

    /**
     * Build the notification preference button options for the settings panel.
     *
     * @param \context $context the module context
     * @param int $taskid the task instance id
     * @param int $userid the user id
     * @return array list of ['value' => int, 'label' => string, 'active' => bool]
     */
    public static function notification_options(\context $context, int $taskid, int $userid): array {
        $current = self::get_notification_preference($context, $taskid, $userid);
        $labels = [
            self::NOTIFY_NONE => 'notifypref_none',
            self::NOTIFY_RESPONSES => 'notifypref_responses',
            self::NOTIFY_ALL => 'notifypref_all',
            self::NOTIFY_MYREPLIES => 'notifypref_myreplies',
        ];

        $options = [];
        foreach ($labels as $value => $stringkey) {
            $options[] = [
                'value' => $value,
                'label' => get_string($stringkey, 'mod_task'),
                'active' => ($value === $current),
            ];
        }
        return $options;
    }

    /**
     * Whether a user holding a preference should be notified of a given post.
     *
     * A teacher's reply within the user's own response thread always notifies
     * them, whatever their preference (even when muted): a student should never
     * miss teacher feedback on their own response.
     *
     * @param int $preference the recipient's NOTIFY_* preference
     * @param bool $isreply whether the post is a reply (true) or a top-level response (false)
     * @param int $rootauthorid the author of the top-level response in the reply's thread (0 for responses)
     * @param int $recipientid the candidate recipient's user id
     * @param bool $isteacherreply whether this reply was authored by a teacher (staff)
     * @return bool
     */
    public static function should_notify_for_post(
        int $preference,
        bool $isreply,
        int $rootauthorid,
        int $recipientid,
        bool $isteacherreply = false
    ): bool {
        // A teacher replying to your own response always reaches you, muted or not.
        if ($isreply && $isteacherreply && $rootauthorid === $recipientid) {
            return true;
        }
        if ($preference === self::NOTIFY_NONE) {
            return false;
        }
        if (!$isreply) {
            // A new top-level response.
            return $preference === self::NOTIFY_RESPONSES || $preference === self::NOTIFY_ALL;
        }
        // A new reply.
        if ($preference === self::NOTIFY_ALL) {
            return true;
        }
        if ($preference === self::NOTIFY_MYREPLIES) {
            return $rootauthorid === $recipientid;
        }
        return false;
    }

    /**
     * Find the author of the top-level response at the root of a post's thread.
     *
     * @param int $postid the post id to walk up from
     * @return int the root response author's user id, or 0 if it cannot be resolved
     */
    public static function thread_root_author(int $postid): int {
        global $DB;

        $guard = 0;
        $current = $DB->get_record('task_post', ['id' => $postid], 'id, parentid, userid');
        while ($current && (int)$current->parentid !== 0 && $guard++ < 50) {
            $current = $DB->get_record('task_post', ['id' => (int)$current->parentid], 'id, parentid, userid');
        }
        return $current ? (int)$current->userid : 0;
    }

    /**
     * Return a user's primary non-student role label in this context.
     *
     * @param \context $context the module context
     * @param int $userid the user id
     * @param array|null $roles pre-fetched role assignments, or null to look up
     * @return string the role display name, or '' for students/guests
     */
    public static function user_role_label(\context $context, int $userid, ?array $roles = null): string {
        $roles ??= get_user_roles($context, $userid, true);
        foreach ($roles as $r) {
            $archetype = self::role_archetype((int)$r->roleid);
            if ($archetype !== 'student' && $archetype !== '' && $archetype !== 'guest') {
                return role_get_name($r, $context, ROLENAME_ALIAS);
            }
        }
        return '';
    }

    /**
     * Cached lookup of a role archetype.
     *
     * @param int $roleid the role id
     * @return string the archetype
     */
    protected static function role_archetype(int $roleid): string {
        static $cache = [];
        if (!array_key_exists($roleid, $cache)) {
            global $DB;
            $cache[$roleid] = (string)$DB->get_field('role', 'archetype', ['id' => $roleid]);
        }
        return $cache[$roleid];
    }
}
