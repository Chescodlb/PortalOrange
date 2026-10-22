<?php
namespace local_orangeportal\form;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
class notice extends \moodleform {
    public function definition(): void {
        global $DB;
        $m = $this->_form;
        $kind = $this->_customdata['kind'];
        $m->addElement('hidden', 'id', 0); $m->setType('id', PARAM_INT);
        $m->addElement('hidden', 'revision', 1); $m->setType('revision', PARAM_INT);
        $m->addElement('hidden', 'kind', $kind); $m->setType('kind', PARAM_ALPHA);
        $m->addElement('text', 'title', get_string('noticetitle', 'local_orangeportal'), ['size' => 70, 'maxlength' => 255]);
        $m->setType('title', PARAM_TEXT); $m->addRule('title', get_string('entertitle', 'local_orangeportal'), 'required', null, 'client');
        if ($kind === 'incidents') {
            foreach (['client', 'product'] as $field) {
                $m->addElement('text', $field, get_string('incident' . $field, 'local_orangeportal'), ['maxlength' => 255]);
                $m->setType($field, PARAM_TEXT);
            }
        }
        $m->addElement('select', 'categoryid', get_string('category'), \orange_category_options($kind));
        $m->addRule('categoryid', get_string('categoryrequired', 'local_orangeportal'), 'required');
        $m->addElement('editor', 'body', get_string('noticecontent', 'local_orangeportal'), null,
            ['maxfiles' => 0, 'context' => \context_system::instance(), 'subdirs' => 0]);
        $m->setType('body', PARAM_RAW);
        $current = $this->_customdata['current'] ?? null;
        $impact = $current->impact ?? \local_orangeportal\notice_options::default_code('impacts');
        $state = $current->state ?? \local_orangeportal\notice_options::default_code('states');
        $m->addElement('select', 'impact', get_string('incidentcolumnimpact', 'local_orangeportal'), \local_orangeportal\service::impacts(true, $impact));
        $m->addElement('select', 'state', get_string('sharedstate', 'local_orangeportal'), \local_orangeportal\service::states(true, $state));
        // Frozen selects export their configured value, not the first option.
        $m->setDefault('impact', $impact);
        $m->setDefault('state', $state);
        if ($kind === 'notifications') {
            // Read-only values must override stale or altered hidden POST fields.
            $m->setConstant('state', $state);
            $m->freeze('state');
        }
        $m->addElement('header', 'audience', get_string('recipients', 'local_orangeportal'));
        $users = [];
        foreach (\local_orangeportal\orange_scope::users() as $u) {
            if (!$u->suspended && !isguestuser($u)) { $users[$u->id] = fullname($u) . ' (' . $u->username . ')'; }
        }
        $cohorts = [];
        foreach (\local_orangeportal\orange_scope::cohorts() as $c) { $cohorts[$c->id] = $c->name; }
        $audience = $DB->get_record('cohort', ['id' => (int)get_config('local_orangeportal', 'audiencecohort')]);
        if ($audience) { $cohorts[$audience->id] = $audience->name; }
        $groups = [];
        foreach (\local_orangeportal\orange_scope::groups() as $g) {
            $groups[$g->id] = $g->coursename . ' / ' . $g->name;
        }
        foreach (['users' => [get_string('individualusers', 'local_orangeportal'), $users], 'cohorts' => [get_string('globalcohorts', 'local_orangeportal'), $cohorts],
                'groups' => [get_string('coursegroups', 'local_orangeportal'), $groups]] as $name => [$label, $choices]) {
            $m->addElement('autocomplete', $name, $label, $choices, ['multiple' => true]);
            $m->setType($name, PARAM_INT);
        }
        $m->addElement('static', 'audiencenote', '', get_string('audiencenote', 'local_orangeportal'));
        $m->addElement('advcheckbox', 'published', get_string('publishlabel', 'local_orangeportal'), get_string('visibleforrecipients', 'local_orangeportal'));
        $m->addElement('static', 'revisionnote', '', get_string('revisionnote', 'local_orangeportal'));
        $this->add_action_buttons(true, get_string('savepublication', 'local_orangeportal'));
    }
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (empty(trim(strip_tags($data['body']['text'] ?? '')))) { $errors['body'] = get_string('entercontent', 'local_orangeportal'); }
        if (!empty($data['published']) && empty($data['users']) && empty($data['cohorts']) && empty($data['groups'])) {
            $errors['users'] = get_string('recipientsrequired', 'local_orangeportal');
        }
        return $errors;
    }
}
