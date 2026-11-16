<?php
namespace local_orangeportal\form;
defined('MOODLE_INTERNAL') || die();
require_once($GLOBALS['CFG']->libdir . '/formslib.php');
class orange_user extends \moodleform {
    public function definition(): void {
        $m = $this->_form;
        $new = $this->_customdata['new'];
        if ($new) {
            $m->addElement('text', 'username', get_string('username'));
            $m->setType('username', PARAM_RAW_TRIMMED);
            $m->addRule('username', null, 'required');
            $m->addElement('passwordunmask', 'password', get_string('newpassword'));
            $m->setType('password', PARAM_RAW);
            $m->addRule('password', null, 'required');
        }
        if ($this->_customdata['editable']) {
            foreach (['firstname', 'lastname', 'email'] as $field) {
                $m->addElement('text', $field, get_string($field));
                $m->setType($field, $field === 'email' ? PARAM_EMAIL : PARAM_NOTAGS);
                $m->addRule($field, null, 'required');
            }
        } else {
            $m->addElement('static', 'shared', '', get_string('orangesharedaccount', 'local_orangeportal'));
        }
        $options = [];
        foreach (\local_orangeportal\orange_scope::cohorts() as $c) { $options[$c->id] = $c->name; }
        $m->addElement('autocomplete', 'cohorts', get_string('orangecollectives', 'local_orangeportal'), $options, ['multiple' => true]);
        $m->setType('cohorts', PARAM_INT);
        $profiles = \local_orangeportal\work_profiles::options();
        if (!$new) { $profiles = ['keep' => get_string('workprofile_keep', 'local_orangeportal')] + $profiles; }
        $m->addElement('select', 'workprofile', get_string('workprofile', 'local_orangeportal'), $profiles);
        $m->setType('workprofile', PARAM_ALPHA);
        $m->setDefault('workprofile', $new ? 'agent' : 'keep');
        $m->addElement('static', 'workprofilehint', '', get_string('workprofilehint', 'local_orangeportal'));
        $courses = [];
        foreach (\local_orangeportal\work_profiles::courses() as $course) {
            $courses[$course->id] = $course->fullname . ' (' . $course->shortname . ')';
        }
        $m->addElement('autocomplete', 'profilecourses', get_string('workprofilecourses', 'local_orangeportal'),
            $courses, ['multiple' => true]);
        $m->setType('profilecourses', PARAM_INT);
        $m->hideIf('profilecourses', 'workprofile', 'in', ['keep', 'agent', 'quality']);
        $m->addElement('autocomplete', 'profilecategories', get_string('workprofilecategories', 'local_orangeportal'),
            \local_orangeportal\work_profiles::categories(), ['multiple' => true]);
        $m->setType('profilecategories', PARAM_INT);
        $m->hideIf('profilecategories', 'workprofile', 'neq', 'trainer');
        $m->addElement('static', 'workprofilecategorieshint', '', get_string('workprofilecategorieshint', 'local_orangeportal'));
        $m->hideIf('workprofilecategorieshint', 'workprofile', 'neq', 'trainer');
        $this->add_action_buttons(true, get_string('savechanges'));
    }
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if ($this->_customdata['editable'] && !validate_email($data['email'] ?? '')) { $errors['email'] = get_string('invalidemail'); }
        if ($this->_customdata['new']) {
            $error = '';
            if (!check_password_policy($data['password'] ?? '', $error)) { $errors['password'] = $error; }
        }
        if (in_array($data['workprofile'] ?? '', ['readonly', 'coordinator'], true) && empty($data['profilecourses'])) {
            $errors['profilecourses'] = get_string('workprofileneedcourses', 'local_orangeportal');
        }
        return $errors;
    }
}
