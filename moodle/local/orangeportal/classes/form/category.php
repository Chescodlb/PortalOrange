<?php
namespace local_orangeportal\form;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
class category extends \moodleform {
    public function definition(): void {
        $m = $this->_form;
        $m->addElement('hidden', 'id', 0); $m->setType('id', PARAM_INT);
        $m->addElement('hidden', 'kind', $this->_customdata['kind']); $m->setType('kind', PARAM_ALPHA);
        $m->addElement('text', 'name', get_string('name'), ['maxlength' => 255]); $m->setType('name', PARAM_TEXT);
        $m->addRule('name', get_string('entername', 'local_orangeportal'), 'required', null, 'client');
        $options = $this->_customdata['options'] ?? ([0 => get_string('categoryroot', 'local_orangeportal')] + \orange_category_options($this->_customdata['kind']));
        $m->addElement('select', 'parentid', get_string('parentcategory', 'local_orangeportal'), $options);
        $this->add_action_buttons(true, get_string('savecategory', 'local_orangeportal'));
    }
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (trim($data['name']) === '') { $errors['name'] = get_string('entername', 'local_orangeportal'); }
        return $errors;
    }
}
