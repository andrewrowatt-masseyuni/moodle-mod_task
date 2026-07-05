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
 * Settings form for mod_task.
 *
 * @package    mod_task
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * The Task activity settings form.
 *
 * @package    mod_task
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_task_mod_form extends moodleform_mod {
    /**
     * Define the form.
     */
    public function definition() {
        $mform = $this->_form;
        $options = task_get_editor_options($this->context);

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('taskname', 'mod_task'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement(
            'select',
            'tasktype',
            get_string('tasktype', 'mod_task'),
            \mod_task\manager::get_task_type_options()
        );
        $mform->setType('tasktype', PARAM_ALPHANUMEXT);
        $mform->setDefault('tasktype', \mod_task\manager::default_task_type());
        $mform->addHelpButton('tasktype', 'tasktype', 'mod_task');

        // The task the student responds to is the standard activity description,
        // relabelled. It is always visible to everyone who can view the activity.
        $this->standard_intro_elements(get_string('taskdescription', 'mod_task'));
        $mform->addHelpButton('introeditor', 'taskdescription', 'mod_task');

        // Optional teacher response, revealed once the student has posted.
        $mform->addElement(
            'editor',
            'teacherresponse_editor',
            get_string('teacherresponse', 'mod_task'),
            null,
            $options
        );
        $mform->setType('teacherresponse_editor', PARAM_RAW);
        $mform->addHelpButton('teacherresponse_editor', 'teacherresponse', 'mod_task');

        $mform->addElement(
            'selectyesno',
            'teacherresponseismodelanswer',
            get_string('ismodelanswer', 'mod_task')
        );
        $mform->setDefault('teacherresponseismodelanswer', 0);
        $mform->addHelpButton('teacherresponseismodelanswer', 'ismodelanswer', 'mod_task');

        $mform->addElement(
            'selectyesno',
            'anonymousposts',
            get_string('anonymousposts', 'mod_task')
        );
        $mform->setDefault('anonymousposts', 1);
        $mform->addHelpButton('anonymousposts', 'anonymousposts', 'mod_task');

        $mform->addElement(
            'selectyesno',
            'embedoncoursepage',
            get_string('embedoncoursepage', 'mod_task')
        );
        $mform->setDefault('embedoncoursepage', 0);
        $mform->addHelpButton('embedoncoursepage', 'embedoncoursepage', 'mod_task');

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Prepare the rich-text editor fields for display.
     *
     * @param array $defaultvalues passed by reference
     */
    public function data_preprocessing(&$defaultvalues) {
        $options = task_get_editor_options($this->context);

        // The task description is the standard intro field, prepared for us by core.
        if ($this->current && !empty($this->current->id)) {
            $defaultvalues = (array) file_prepare_standard_editor(
                (object) $defaultvalues,
                'teacherresponse',
                $options,
                $this->context,
                'mod_task',
                'teacherresponse',
                0
            );
        } else {
            $defaultvalues['teacherresponse_editor'] = [
                'text' => '',
                'format' => editors_get_preferred_format(),
                'itemid' => file_get_submitted_draft_itemid('teacherresponse_editor'),
            ];
        }
    }

    /**
     * Add the custom completion rule checkboxes: respond, reply, react.
     *
     * @return string[] the names of the added elements
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        $suffix = $this->get_suffix();

        $elements = [];
        foreach (['completionrespond', 'completionreply', 'completionreact'] as $rule) {
            $element = $rule . $suffix;
            $mform->addElement('checkbox', $element, '', get_string($rule, 'mod_task'));
            $mform->setDefault($element, 0);
            $elements[] = $element;
        }
        return $elements;
    }

    /**
     * Whether at least one custom completion rule is enabled.
     *
     * @param array $data submitted form data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        $suffix = $this->get_suffix();
        return !empty($data['completionrespond' . $suffix])
            || !empty($data['completionreply' . $suffix])
            || !empty($data['completionreact' . $suffix]);
    }

    /**
     * Persist 0 for any completion rule checkbox left unticked.
     *
     * @param stdClass $data submitted form data passed by reference
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        if (!empty($data->completionunlocked)) {
            $suffix = $this->get_suffix();
            $autocompletion = !empty($data->{'completion' . $suffix})
                && $data->{'completion' . $suffix} == COMPLETION_TRACKING_AUTOMATIC;
            foreach (['completionrespond', 'completionreply', 'completionreact'] as $rule) {
                if (empty($data->{$rule . $suffix}) || !$autocompletion) {
                    $data->{$rule . $suffix} = 0;
                }
            }
        }
    }

    /**
     * Validate the form: the task description must not be empty.
     *
     * @param array $data submitted data
     * @param array $files submitted files
     * @return array errors keyed by element name
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $description = $data['introeditor']['text'] ?? '';
        if (trim(html_to_text($description)) === '' && stripos($description, '<img') === false) {
            $errors['introeditor'] = get_string('required');
        }

        if (!array_key_exists($data['tasktype'] ?? '', \mod_task\manager::get_task_type_options())) {
            $errors['tasktype'] = get_string('required');
        }

        return $errors;
    }
}
