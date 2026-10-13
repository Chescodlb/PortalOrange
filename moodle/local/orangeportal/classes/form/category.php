<?php
namespace local_orangeportal\form;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
class category extends \moodleform {
    public function definition(): void {
        $m = $this->_form;
        $m->addElement('hidden', 'id', 0); $m->setType('id', PARAM_INT);
        $m->addElement('hidden', 'kind', $this->_customdata['kind']); $m->setType('kind', PARAM_ALPHA);
        $m->addElement('text', 'name', 'Nombre', ['maxlength' => 255]); $m->setType('name', PARAM_TEXT);
        $m->addRule('name', 'Escribe un nombre', 'required', null, 'client');
        $options = \orange_category_options($this->_customdata['kind']);
        foreach ($this->_customdata['excluded'] as $id) { unset($options[$id]); }
        $m->addElement('select', 'parentid', 'Categoría superior', [0 => 'Raíz'] + $options);
        $this->add_action_buttons(true, 'Guardar categoría');
    }
}
