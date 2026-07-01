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
 * Admin setting for the "shortname|name|CSS classes" Task types textarea.
 *
 * @package    mod_task
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_tasktypes extends \admin_setting_configtextarea {
    /**
     * Validate that every non-blank line is "shortname|name|CSS classes", with a
     * unique, space-free shortname and a non-empty name.
     *
     * @param string $data the submitted textarea content
     * @return true|string true on success, or an error message
     */
    public function validate($data) {
        if (trim((string)$data) === '') {
            return get_string('tasktypes_error_empty', 'mod_task');
        }

        $shortnames = [];
        $badlines = [];
        foreach (preg_split('/\r\n|\r|\n/', $data) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = explode('|', $line);
            if (count($parts) !== 3) {
                $badlines[] = $line;
                continue;
            }

            [$shortname, $name, ] = array_map('trim', $parts);
            if ($shortname === '' || $name === '' || preg_match('/\s/', $shortname)) {
                $badlines[] = $line;
                continue;
            }

            $lower = \core_text::strtolower($shortname);
            if (isset($shortnames[$lower])) {
                $badlines[] = $line;
                continue;
            }
            $shortnames[$lower] = true;
        }

        if ($badlines) {
            return get_string('tasktypes_error_format', 'mod_task', join(', ', $badlines));
        }
        if (empty($shortnames)) {
            return get_string('tasktypes_error_empty', 'mod_task');
        }

        return true;
    }
}
