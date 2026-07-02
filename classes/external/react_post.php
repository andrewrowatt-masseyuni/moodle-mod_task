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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use mod_task\manager;

/**
 * Web service: toggle an emoji reaction on a post.
 *
 * @package    mod_task
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class react_post extends external_api {
    /**
     * Parameters definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'postid' => new external_value(PARAM_INT, 'Post id'),
            'emoji' => new external_value(PARAM_ALPHANUMEXT, 'Emoji shortcode'),
        ]);
    }

    /**
     * Toggle the reaction and return the updated reaction state for the post.
     *
     * @param int $postid the post id
     * @param string $emoji the emoji shortcode
     * @return array
     */
    public static function execute(int $postid, string $emoji): array {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'postid' => $postid,
            'emoji' => $emoji,
        ]);

        $post = $DB->get_record('task_post', ['id' => $params['postid']], '*', MUST_EXIST);
        if ($post->deleted) {
            throw new \moodle_exception('error_invalidtask', 'mod_task');
        }
        $cm = get_coursemodule_from_instance('task', (int)$post->taskid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/task:react', $context);

        // A student must be able to see responses (i.e. have responded) before reacting.
        if (!manager::can_see_responses($context, (int)$post->taskid)) {
            throw new \moodle_exception('error_cannotrespondyet', 'mod_task');
        }

        $result = manager::toggle_reaction((int)$post->id, (int)$USER->id, $params['emoji']);
        \mod_task\event\post_reacted::create_for_reaction(
            (int)$post->id,
            $context,
            $params['emoji'],
            $result['action']
        )->trigger();

        $reactions = manager::get_reactions([(int)$post->id], (int)$USER->id)[(int)$post->id];
        $counts = [];
        foreach ($reactions['counts'] as $shortcode => $count) {
            $counts[] = ['emoji' => $shortcode, 'count' => (int)$count];
        }

        return [
            'postid' => (int)$post->id,
            'action' => $result['action'],
            'counts' => $counts,
            'userreactions' => array_values($reactions['userreactions']),
        ];
    }

    /**
     * Return value definition.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns() {
        return helper::react_result_structure();
    }
}
