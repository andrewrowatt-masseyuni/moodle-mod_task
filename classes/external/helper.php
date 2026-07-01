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

use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

/**
 * Shared external_description helpers for mod_task.
 *
 * @package    mod_task
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * Description of a post's reaction state.
     *
     * @return external_single_structure
     */
    public static function reactions_structure(): external_single_structure {
        return new external_single_structure([
            'counts' => new external_multiple_structure(
                new external_single_structure([
                    'emoji' => new external_value(PARAM_ALPHANUMEXT, 'Emoji shortcode'),
                    'count' => new external_value(PARAM_INT, 'Reaction count'),
                ])
            ),
            'userreactions' => new external_multiple_structure(
                new external_value(PARAM_ALPHANUMEXT, 'Emoji shortcode the viewer has reacted with')
            ),
        ]);
    }

    /**
     * Description of a single post entry.
     *
     * @return external_single_structure
     */
    public static function post_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Post id'),
            'parentid' => new external_value(PARAM_INT, 'Parent post id (0 for a top-level response)'),
            'ismine' => new external_value(PARAM_BOOL, 'Whether this post was authored by the current viewer'),
            'content' => new external_value(PARAM_RAW, 'Sanitised HTML content'),
            'deleted' => new external_value(PARAM_BOOL, 'Whether the post is deleted'),
            'edited' => new external_value(PARAM_BOOL, 'Whether the post has been edited'),
            'timecreated' => new external_value(PARAM_INT, 'Unix timestamp when created'),
            'timecreatediso' => new external_value(PARAM_TEXT, 'Localised date/time'),
            'authorname' => new external_value(PARAM_TEXT, 'Display name (or "Anonymous")'),
            'authorrole' => new external_value(PARAM_TEXT, 'Author role label, if non-student'),
            'isanonymous' => new external_value(PARAM_BOOL, 'Whether this post was made anonymously'),
            'showanonymousbadge' => new external_value(PARAM_BOOL, 'Show an "Anonymous" badge next to a revealed name'),
            'profileurl' => new external_value(PARAM_URL, 'Author profile URL', VALUE_OPTIONAL),
            'avatar' => new external_value(PARAM_RAW, 'Avatar HTML (img tag)'),
            'reactions' => self::reactions_structure(),
            'canedit' => new external_value(PARAM_BOOL, 'Viewer can edit this post'),
            'candelete' => new external_value(PARAM_BOOL, 'Viewer can delete this post'),
            'canreply' => new external_value(PARAM_BOOL, 'Viewer can reply to this post'),
        ]);
    }

    /**
     * Description of the notification preference panel data.
     *
     * @return external_single_structure
     */
    public static function notification_settings_structure(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Task course module id'),
            'options' => new external_multiple_structure(
                new external_single_structure([
                    'value' => new external_value(PARAM_INT, 'Preference value'),
                    'label' => new external_value(PARAM_TEXT, 'Preference label'),
                    'active' => new external_value(PARAM_BOOL, 'Whether this is the current preference'),
                ])
            ),
        ]);
    }

    /**
     * Description of the gated Task view payload.
     *
     * @return external_single_structure
     */
    public static function view_structure(): external_single_structure {
        return new external_single_structure([
            'taskid' => new external_value(PARAM_INT, 'Task instance id'),
            'contextid' => new external_value(PARAM_INT, 'Module context id'),
            'name' => new external_value(PARAM_TEXT, 'Task name'),
            'taskdescription' => new external_value(PARAM_RAW, 'Formatted task description HTML'),
            'taskdescriptioncssclasses' => new external_value(PARAM_RAW, 'CSS classes for the configured Task type'),
            'canrespond' => new external_value(PARAM_BOOL, 'Viewer can post a response/reply'),
            'canaddresponse' => new external_value(PARAM_BOOL, 'Viewer may still post a top-level response'),
            'shownotificationsettings' => new external_value(PARAM_BOOL, 'Show the notification preference panel'),
            'notificationsettings' => self::notification_settings_structure(),
            'canviewall' => new external_value(PARAM_BOOL, 'Viewer is staff (sees all without responding)'),
            'canmanage' => new external_value(PARAM_BOOL, 'Viewer can moderate posts'),
            'canreact' => new external_value(PARAM_BOOL, 'Viewer can react'),
            'cananonymous' => new external_value(PARAM_BOOL, 'Viewer may choose to respond anonymously'),
            'hasresponded' => new external_value(PARAM_BOOL, 'Viewer has posted a response'),
            'canseeresponses' => new external_value(PARAM_BOOL, 'Viewer may see responses and the teacher response'),
            'showteacherresponse' => new external_value(PARAM_BOOL, 'A teacher response is present and visible'),
            'teacherresponse' => new external_value(PARAM_RAW, 'Formatted teacher response HTML'),
            'teacherresponseismodelanswer' => new external_value(PARAM_BOOL, 'Badge the teacher response as a model answer'),
            'emojis' => new external_multiple_structure(new external_single_structure([
                'shortcode' => new external_value(PARAM_ALPHANUMEXT, 'Emoji shortcode'),
                'unicode' => new external_value(PARAM_RAW, 'Emoji unicode character'),
            ])),
            'posts' => new external_multiple_structure(self::post_structure()),
            'postcount' => new external_value(PARAM_INT, 'Number of posts'),
            'currentuserid' => new external_value(PARAM_INT, 'Current user id'),
            'currentuseravatar' => new external_value(PARAM_RAW, 'Current user avatar HTML (img tag)'),
            'currentuserprofileurl' => new external_value(PARAM_URL, 'Current user profile URL', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Description of the result of toggling a reaction.
     *
     * @return external_single_structure
     */
    public static function react_result_structure(): external_single_structure {
        return new external_single_structure([
            'postid' => new external_value(PARAM_INT, 'Post id'),
            'action' => new external_value(PARAM_ALPHA, 'added or removed'),
            'counts' => new external_multiple_structure(
                new external_single_structure([
                    'emoji' => new external_value(PARAM_ALPHANUMEXT, 'Emoji shortcode'),
                    'count' => new external_value(PARAM_INT, 'Reaction count'),
                ])
            ),
            'userreactions' => new external_multiple_structure(
                new external_value(PARAM_ALPHANUMEXT, 'Emoji shortcode the viewer has reacted with')
            ),
        ]);
    }
}
