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

namespace mod_task\output;

/**
 * Renders the Task placeholder that the JS controller hydrates.
 *
 * Used by both the activity view page and the {task:Name} filter so an embedded
 * Task behaves exactly like the activity page. The placeholder carries the
 * Task's own course module id and context id, so all gating and capability
 * checks run against the Task module regardless of where it is embedded.
 *
 * @package    mod_task
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class embed {
    /** @var bool Whether the controller JS has been requested this request. */
    protected static bool $jsrequested = false;

    /** @var int Counter for unique placeholder element ids. */
    protected static int $counter = 0;

    /**
     * Render a Task placeholder and request the controller JS for the page.
     *
     * The JS is requested once per request. Because each Snap partial-render
     * fragment is a fresh request, the controller is re-requested for every
     * AJAX-loaded section and runs against any placeholders it brings in.
     *
     * @param \renderer_base $output the page output renderer
     * @param int $cmid the Task course module id
     * @param int $contextid the Task module context id
     * @param bool $showdescription whether the Task shell should render the
     *        description (intro). False on the activity page, where the theme
     *        already renders the activity intro; true for {task:Name} embeds.
     * @param bool $showheading whether the Task shell should render the Task
     *        name as a heading. False on the activity page, where Moodle
     *        already renders the activity name; true for embeds.
     * @return string the placeholder HTML
     */
    public static function placeholder(
        \renderer_base $output,
        int $cmid,
        int $contextid,
        bool $showdescription = true,
        bool $showheading = true
    ): string {
        global $PAGE;

        if (!self::$jsrequested) {
            self::$jsrequested = true;
            $PAGE->requires->js_call_amd('mod_task/view', 'init');
        }

        self::$counter++;
        return $output->render_from_template('mod_task/embed_placeholder', [
            'uid' => 'mod_task_' . $cmid . '_' . $contextid . '_' . self::$counter,
            'cmid' => $cmid,
            'contextid' => $contextid,
            'showdescription' => $showdescription ? 1 : 0,
            'showheading' => $showheading ? 1 : 0,
        ]);
    }
}
