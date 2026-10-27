<?php
namespace local_orangeportal\form;
defined('MOODLE_INTERNAL') || die();
require_once($GLOBALS['CFG']->libdir . '/formslib.php');
class agent_csv extends \moodleform {
    public function definition(): void {
        $m = $this->_form;
        $m->addElement('filepicker', 'csvfile', get_string('file'), null, [
            'accepted_types' => ['.csv'], 'maxbytes' => \local_orangeportal\agent_import::MAX_BYTES]);
        $m->addRule('csvfile', null, 'required', null, 'client');
        $m->addHelpButton('csvfile', 'orangeimportformat', 'local_orangeportal');
        $m->addElement('select', 'delimiter', get_string('csvdelimiter', 'local_orangeportal'), [
            'semicolon' => get_string('csvsemicolon', 'local_orangeportal'), 'comma' => get_string('csvcomma', 'local_orangeportal')]);
        $m->setDefault('delimiter', 'semicolon');
        $this->add_action_buttons(true, get_string('csvvalidate', 'local_orangeportal'));
    }
}
