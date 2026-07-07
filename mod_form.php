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
            'enablereplies',
            get_string('enablereplies', 'mod_task')
        );
        $mform->setDefault('enablereplies', 1);
        $mform->addHelpButton('enablereplies', 'enablereplies', 'mod_task');

        $mform->addElement(
            'selectyesno',
            'enablereactions',
            get_string('enablereactions', 'mod_task')
        );
        $mform->setDefault('enablereactions', 1);
        $mform->addHelpButton('enablereactions', 'enablereactions', 'mod_task');

        $mform->addElement(
            'selectyesno',
            'embedoncoursepage',
            get_string('embedoncoursepage', 'mod_task')
        );
        $mform->setDefault('embedoncoursepage', 0);
        $mform->addHelpButton('embedoncoursepage', 'embedoncoursepage', 'mod_task');

        $this->add_grading_elements();

        $this->standard_coursemodule_elements();

        // Core's "Receive a grade" completion checkbox hides itself against the
        // standard modgrade element, which this form does not use, so tie it to
        // the "Graded task" toggle instead.
        $suffix = $this->get_suffix();
        if ($mform->elementExists('completionusegrade' . $suffix)) {
            $mform->hideIf('completionusegrade' . $suffix, 'graded', 'eq', 0);
        }

        $this->add_action_buttons();
    }

    /**
     * Add the participation grading settings.
     *
     * The marks fields only show while "Graded task" is on, and the reply and
     * reaction pairs additionally require their feature to be enabled. As with
     * the react completion condition, the reaction pair is omitted entirely
     * when reactions are turned off site-wide.
     */
    protected function add_grading_elements(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'gradingheader', get_string('gradingheading', 'mod_task'));

        $mform->addElement('selectyesno', 'graded', get_string('graded', 'mod_task'));
        $mform->setDefault('graded', 0);
        $mform->addHelpButton('graded', 'graded', 'mod_task');

        $mform->addElement(
            'text',
            'graderesponsepoints',
            get_string('graderesponsepoints', 'mod_task'),
            ['size' => 4]
        );
        $mform->setType('graderesponsepoints', PARAM_INT);
        $mform->setDefault('graderesponsepoints', 80);
        $mform->addHelpButton('graderesponsepoints', 'graderesponsepoints', 'mod_task');
        $mform->hideIf('graderesponsepoints', 'graded', 'eq', 0);

        $fields = [
            'gradereplypoints' => 10,
            'gradereplycount' => 1,
        ];
        if (get_config('mod_task', 'enablereactions') !== '0') {
            $fields += [
                'gradereactpoints' => 10,
                'gradereactcount' => 1,
            ];
        }
        foreach ($fields as $field => $default) {
            $mform->addElement('text', $field, get_string($field, 'mod_task'), ['size' => 4]);
            $mform->setType($field, PARAM_INT);
            $mform->setDefault($field, $default);
            $mform->hideIf($field, 'graded', 'eq', 0);
            $toggle = strpos($field, 'gradereply') === 0 ? 'enablereplies' : 'enablereactions';
            $mform->hideIf($field, $toggle, 'eq', 0);
        }
        $mform->addHelpButton('gradereplypoints', 'gradereplypoints', 'mod_task');
        if (isset($fields['gradereactpoints'])) {
            $mform->addHelpButton('gradereactpoints', 'gradereactpoints', 'mod_task');
        }
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
     * The reply and react conditions only make sense when the matching activity
     * setting is on, so each is hidden when its feature is disabled. Reactions can
     * also be turned off site-wide, in which case the react condition is omitted
     * entirely (there is no per-activity control to hide it against).
     *
     * @return string[] the names of the added elements
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        $suffix = $this->get_suffix();

        $rules = ['completionrespond', 'completionreply'];
        if (get_config('mod_task', 'enablereactions') !== '0') {
            $rules[] = 'completionreact';
        }

        $elements = [];
        foreach ($rules as $rule) {
            $element = $rule . $suffix;
            $mform->addElement('checkbox', $element, '', get_string($rule, 'mod_task'));
            $mform->setDefault($element, 0);
            $elements[] = $element;
        }

        $mform->hideIf('completionreply' . $suffix, 'enablereplies', 'eq', 0);
        if (in_array('completionreact' . $suffix, $elements, true)) {
            $mform->hideIf('completionreact' . $suffix, 'enablereactions', 'eq', 0);
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
        $suffix = $this->get_suffix();
        // A grade-based completion condition cannot survive without a grade item.
        if (empty($data->graded)) {
            foreach (['completionusegrade', 'completionpassgrade'] as $field) {
                if (isset($data->{$field . $suffix})) {
                    $data->{$field . $suffix} = 0;
                }
            }
        }
        if (!empty($data->completionunlocked)) {
            $autocompletion = !empty($data->{'completion' . $suffix})
                && $data->{'completion' . $suffix} == COMPLETION_TRACKING_AUTOMATIC;
            // A completion condition cannot be required when its feature is off,
            // otherwise the activity could never be completed.
            $disabled = [
                'completionreply' => empty($data->enablereplies),
                'completionreact' => empty($data->enablereactions)
                    || get_config('mod_task', 'enablereactions') === '0',
            ];
            foreach (['completionrespond', 'completionreply', 'completionreact'] as $rule) {
                if (empty($data->{$rule . $suffix}) || !$autocompletion || !empty($disabled[$rule])) {
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

        if (!empty($data['graded'])) {
            $points = ['graderesponsepoints', 'gradereplypoints', 'gradereactpoints'];
            foreach ($points as $field) {
                if (isset($data[$field]) && $data[$field] < 0) {
                    $errors[$field] = get_string('error_gradenegative', 'mod_task');
                }
            }
            foreach (['gradereplycount', 'gradereactcount'] as $field) {
                if (isset($data[$field]) && $data[$field] < 1) {
                    $errors[$field] = get_string('error_gradecountmin', 'mod_task');
                }
            }
            // The grade maximum must be positive: sum the contributions that
            // would actually count with the submitted feature toggles.
            $max = max(0, (int) ($data['graderesponsepoints'] ?? 0));
            if (!empty($data['enablereplies'])) {
                $max += max(0, (int) ($data['gradereplypoints'] ?? 0));
            }
            if (!empty($data['enablereactions']) && get_config('mod_task', 'enablereactions') !== '0') {
                $max += max(0, (int) ($data['gradereactpoints'] ?? 0));
            }
            if ($max <= 0) {
                $errors['graderesponsepoints'] = get_string('error_grademaxzero', 'mod_task');
            }
        }

        return $errors;
    }
}
