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
 * Web service: delete a post.
 *
 * @package    mod_task
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_post extends external_api {
    /**
     * Parameters definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'postid' => new external_value(PARAM_INT, 'Post id'),
        ]);
    }

    /**
     * Delete the post and return the refreshed view.
     *
     * @param int $postid the post id
     * @return array
     */
    public static function execute(int $postid): array {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['postid' => $postid]);

        $post = $DB->get_record('task_post', ['id' => $params['postid']], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('task', (int)$post->taskid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        manager::delete_post($context, (int)$post->id, (int)$USER->id);

        return manager::get_task_view($context, (int)$post->taskid);
    }

    /**
     * Return value definition.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns() {
        return helper::view_structure();
    }
}
