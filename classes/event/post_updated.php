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

namespace mod_task\event;

/**
 * Event fired when a post is edited.
 *
 * @package    mod_task
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class post_updated extends \core\event\base {
    /**
     * Build the event from a post record.
     *
     * @param \stdClass $post the post record
     * @param \stdClass $cm the course module
     * @param \context $context the module context
     * @return self
     */
    public static function create_from_post(\stdClass $post, \stdClass $cm, \context $context): self {
        $event = self::create([
            'objectid' => $post->id,
            'context' => $context,
            'other' => ['taskid' => (int)$post->taskid, 'parentid' => (int)$post->parentid],
        ]);
        $event->add_record_snapshot('task_post', $post);
        return $event;
    }

    /**
     * Initialise the event metadata.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'task_post';
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventpostupdated', 'mod_task');
    }

    /**
     * Human-readable description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' updated the post with id '{$this->objectid}' " .
            "in the task with course module id '{$this->contextinstanceid}'.";
    }

    /**
     * URL of the relevant page.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/task/view.php', ['id' => $this->contextinstanceid]);
    }
}
