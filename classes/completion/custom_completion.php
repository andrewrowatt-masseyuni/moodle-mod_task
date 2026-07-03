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

declare(strict_types=1);

namespace mod_task\completion;

use core_completion\activity_custom_completion;
use mod_task\manager;

/**
 * Custom completion rules for mod_task.
 *
 * @package    mod_task
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {
    /**
     * Fetch the completion state for a given custom completion rule.
     *
     * @param string $rule the completion rule
     * @return int COMPLETION_COMPLETE or COMPLETION_INCOMPLETE
     */
    public function get_state(string $rule): int {
        $this->validate_rule($rule);

        $taskid = (int)$this->cm->instance;
        switch ($rule) {
            case 'completionrespond':
                $met = manager::has_responded($taskid, $this->userid);
                break;
            case 'completionreply':
                $met = manager::has_replied($taskid, $this->userid);
                break;
            default:
                $met = manager::has_reacted($taskid, $this->userid);
                break;
        }

        return $met ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Fetch the list of custom completion rules that this module defines.
     *
     * @return string[]
     */
    public static function get_defined_custom_rules(): array {
        return ['completionrespond', 'completionreply', 'completionreact'];
    }

    /**
     * Returns an associative array of the descriptions of custom completion rules.
     *
     * @return string[]
     */
    public function get_custom_rule_descriptions(): array {
        return [
            'completionrespond' => get_string('completiondetail:respond', 'task'),
            'completionreply' => get_string('completiondetail:reply', 'task'),
            'completionreact' => get_string('completiondetail:react', 'task'),
        ];
    }

    /**
     * Returns an array of all completion rules, in the order they should be displayed to users.
     *
     * @return string[]
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionrespond',
            'completionreply',
            'completionreact',
        ];
    }
}
