<?php
namespace local_orangeportal\form;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

class notice_option extends \moodleform {
    public function definition(): void {
        $m = $this->_form;
        $m->addElement('hidden', 'type', $this->_customdata['type']); $m->setType('type', PARAM_ALPHA);
        $m->addElement('hidden', 'code', $this->_customdata['code']); $m->setType('code', PARAM_ALPHA);
        $m->addElement('text', 'name', get_string('optionname', 'local_orangeportal'), ['maxlength' => 100]);
        $m->setType('name', PARAM_TEXT);
        $m->addRule('name', get_string('required'), 'required', null, 'client');
        $m->addElement('advcheckbox', 'active', get_string('optionactive', 'local_orangeportal'));
        $m->setDefault('active', 1);
        if ($this->_customdata['type'] === 'states') {
            $m->addElement('advcheckbox', 'closed', get_string('incidentclosingstate', 'local_orangeportal'));
            $m->addElement('static', 'closedhelp', '', get_string('incidentclosingstatehelp', 'local_orangeportal'));
        }
        $this->add_action_buttons(true, get_string('savechanges'));
    }
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $name = trim($data['name']);
        if ($name === '') { $errors['name'] = get_string('invalidoptionname', 'local_orangeportal'); }
        $records = \local_orangeportal\notice_options::records($this->_customdata['type']);
        foreach ($records as $key => $record) {
            if ($key !== $data['code'] && \core_text::strtolower($record['name']) === \core_text::strtolower($name)) {
                $errors['name'] = get_string('duplicateoptionname', 'local_orangeportal');
            }
        }
        $others = array_filter($records, fn($record, $key) => $key !== $data['code'] && $record['active'], ARRAY_FILTER_USE_BOTH);
        if (!$data['active'] && !$others) { $errors['active'] = get_string('lastactiveoption', 'local_orangeportal'); }
        return $errors;
    }
}
