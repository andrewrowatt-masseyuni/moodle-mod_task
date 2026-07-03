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

/**
 * Upgrade steps for mod_task.
 *
 * @package    mod_task
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Run the mod_task upgrade.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool
 */
function xmldb_task_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026063001) {
        // The standalone task description is folded into the standard activity
        // description (intro). Migrate any existing content, then drop the old
        // fields and their file area.
        $table = new xmldb_table('task');
        $descfield = new xmldb_field('taskdescription');
        $formatfield = new xmldb_field('taskdescriptionformat');

        if ($dbman->field_exists($table, $descfield)) {
            $fs = get_file_storage();
            $tasks = $DB->get_records(
                'task',
                null,
                '',
                'id, course, intro, introformat, taskdescription, taskdescriptionformat'
            );
            foreach ($tasks as $task) {
                $cm = get_coursemodule_from_instance('task', $task->id, $task->course, false, IGNORE_MISSING);
                if (!$cm) {
                    continue;
                }
                $context = context_module::instance($cm->id);

                $hasdescription = trim(html_to_text((string)$task->taskdescription)) !== '';
                $introempty = trim(html_to_text((string)$task->intro)) === '';

                // Only adopt the old description where the intro has nothing of its own.
                if ($introempty && $hasdescription) {
                    foreach ($fs->get_area_files($context->id, 'mod_task', 'taskdescription', 0, 'id', false) as $file) {
                        if (
                            !$fs->file_exists(
                                $context->id,
                                'mod_task',
                                'intro',
                                0,
                                $file->get_filepath(),
                                $file->get_filename()
                            )
                        ) {
                            $fs->create_file_from_storedfile(['filearea' => 'intro'], $file);
                        }
                    }
                    $DB->set_field('task', 'intro', $task->taskdescription, ['id' => $task->id]);
                    $DB->set_field('task', 'introformat', $task->taskdescriptionformat, ['id' => $task->id]);
                }

                // The taskdescription file area is being abandoned.
                $fs->delete_area_files($context->id, 'mod_task', 'taskdescription');
            }

            $dbman->drop_field($table, $descfield);
        }

        if ($dbman->field_exists($table, $formatfield)) {
            $dbman->drop_field($table, $formatfield);
        }

        upgrade_mod_savepoint(true, 2026063001, 'task');
    }

    if ($oldversion < 2026070100) {
        // Per-activity "Notify me of new responses" is replaced by a per-user
        // notification preference. Create the new table and drop the old flag.
        $table = new xmldb_table('task_notifypref');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('taskid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('preference', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '2');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('taskid_fk', XMLDB_KEY_FOREIGN, ['taskid'], 'task', ['id']);
        $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('task_user_unique', XMLDB_INDEX_UNIQUE, ['taskid', 'userid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $tasktable = new xmldb_table('task');
        $notifyfield = new xmldb_field('notifyteacher');
        if ($dbman->field_exists($tasktable, $notifyfield)) {
            $dbman->drop_field($tasktable, $notifyfield);
        }

        upgrade_mod_savepoint(true, 2026070100, 'task');
    }

    if ($oldversion < 2026070102) {
        // Per-activity "Task type" controls which CSS classes decorate the
        // description panel (see the mod_task/tasktypes site setting).
        $table = new xmldb_table('task');
        $field = new xmldb_field('tasktype', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, 'explore', 'name');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026070102, 'task');
    }

    if ($oldversion < 2026070103) {
        // Per-activity "Embed on course page" shows the Task's full interactive
        // widget on the course page instead of the normal icon+link+description
        // card, the same way {task:Name} embeds it in a text field.
        $table = new xmldb_table('task');
        $field = new xmldb_field(
            'embedoncoursepage',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'teacherresponseismodelanswer'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026070103, 'task');
    }

    if ($oldversion < 2026070301) {
        // Custom completion rules: require a response, a reply and/or a
        // reaction before the activity is automatically marked complete.
        $table = new xmldb_table('task');
        $fields = [
            new xmldb_field('completionrespond', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'embedoncoursepage'),
            new xmldb_field('completionreply', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'completionrespond'),
            new xmldb_field('completionreact', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'completionreply'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_mod_savepoint(true, 2026070301, 'task');
    }

    return true;
}
