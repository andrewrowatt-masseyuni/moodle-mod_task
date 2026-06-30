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
 * Task activity header.
 *
 * Renders the activity intro through the shared mod_task/description panel, so
 * the description (with its "Task description" heading) looks identical on the
 * activity page and in the live Task shell (mod_task/task). Moodle uses
 * mod_{modname}\output\activity_header in place of the core header whenever the
 * class exists (see \moodle_page::magic_get_activityheader()), so this is the
 * supported way to customise the Task page header without touching core.
 *
 * @package    mod_task
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_header extends \core\output\activity_header {
    /**
     * Export the header, rendering the intro through the shared description panel.
     *
     * @param \core\output\renderer_base $output the page output renderer
     * @return array the template context
     */
    public function export_for_template(\core\output\renderer_base $output): array {
        global $USER;

        $data = parent::export_for_template($output);

        // The description is only present when the activity has an intro and the
        // page layout does not suppress it; only then is the panel meaningful.
        if (!empty($data['description'])) {
            $description = $output->render_from_template('mod_task/description', [
                'taskdescription' => $data['description'],
            ]);

            // Participants get the per-user notification settings panel directly
            // above the task description, mirroring the live Task shell. Use the
            // header's own page reference (the global $PAGE->cm is not reliably
            // populated at the point the header is exported).
            $notification = '';
            $cm = $this->page->cm;
            if (!empty($cm) && $cm->modname === 'task') {
                $context = \context_module::instance($cm->id);
                if (has_capability('mod/task:respond', $context)) {
                    $notification = $output->render_from_template('mod_task/notification_settings', [
                        'cmid' => (int)$cm->id,
                        'options' => \mod_task\manager::notification_options(
                            $context,
                            (int)$cm->instance,
                            (int)$USER->id
                        ),
                    ]);
                }
            }

            $data['description'] = $notification . $description;
        }

        return $data;
    }
}
