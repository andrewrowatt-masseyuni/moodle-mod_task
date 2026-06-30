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
 * Web service: reply to a response or reply.
 *
 * @package    mod_task
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_reply extends external_api {
    /**
     * Parameters definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Task course module id'),
            'parentid' => new external_value(PARAM_INT, 'Parent post id'),
            'content' => new external_value(PARAM_RAW, 'Reply HTML content'),
            'anonymous' => new external_value(PARAM_BOOL, 'Post anonymously to peers', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Create the reply and return the refreshed view.
     *
     * @param int $cmid the course module id
     * @param int $parentid the parent post id
     * @param string $content the reply HTML
     * @param bool $anonymous whether to be anonymous to peers
     * @return array
     */
    public static function execute(int $cmid, int $parentid, string $content, bool $anonymous): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'parentid' => $parentid,
            'content' => $content,
            'anonymous' => $anonymous,
        ]);

        $cm = get_coursemodule_from_id('task', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        manager::create_post(
            $context,
            (int)$cm->instance,
            $params['parentid'],
            $params['content'],
            $params['anonymous'],
            (int)$USER->id
        );

        return manager::get_task_view($context, (int)$cm->instance);
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
