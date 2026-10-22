<?php
namespace local_orangeportal\form;
defined('MOODLE_INTERNAL') || die();
require_once($GLOBALS['CFG']->libdir . '/formslib.php');
class orange_cohort extends \moodleform {
    public function definition(): void {
        $m = $this->_form;
        $m->addElement('text', 'name', get_string('name')); $m->setType('name', PARAM_NOTAGS); $m->addRule('name', null, 'required');
        $options = [];
        foreach (\local_orangeportal\orange_scope::course_ids() as $id) { $options[$id] = get_course($id)->fullname; }
        $m->addElement('autocomplete', 'courses', get_string('orangeenrolcohort', 'local_orangeportal'), $options, ['multiple' => true]);
        $m->setType('courses', PARAM_INT);
        $m->addElement('static', 'hint', '', get_string('orangeenrolhint', 'local_orangeportal'));
        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
