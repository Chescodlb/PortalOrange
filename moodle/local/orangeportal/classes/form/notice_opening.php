<?php
namespace local_orangeportal\form;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/** Choose the agent entry point after resources have been created. */
class notice_opening extends \moodleform {
    public function definition(): void {
        $m = $this->_form;
        $notice = $this->_customdata['notice'];
        $m->addElement('header', 'opening', get_string('noticeopening', 'local_orangeportal'));
        foreach (['revision' => $notice->revision, 'previous' => $notice->openactivity] as $name => $value) {
            $m->addElement('hidden', $name, $value);
            $m->setType($name, PARAM_INT);
        }
        $m->addElement('select', 'openactivity', get_string('noticeopening', 'local_orangeportal'),
            \local_orangeportal\native_content::opening_options($notice));
        $m->setType('openactivity', PARAM_INT);
        $m->setDefault('openactivity', $notice->openactivity);
        $m->addElement('static', 'openingnote', '', get_string('noticeopeninghelp', 'local_orangeportal'));
        $this->add_action_buttons(false, get_string('noticeopeningsave', 'local_orangeportal'));
    }
}
