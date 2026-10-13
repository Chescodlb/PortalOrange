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
        $m->addElement('hidden', 'kind', $kind); $m->setType('kind', PARAM_ALPHA);
        $m->addElement('text', 'title', 'Título', ['size' => 70, 'maxlength' => 255]);
        $m->setType('title', PARAM_TEXT); $m->addRule('title', 'Escribe un título', 'required', null, 'client');
        $m->addElement('select', 'categoryid', 'Categoría', \orange_category_options($kind));
        $m->addRule('categoryid', 'Crea una categoría antes de publicar', 'required');
        $m->addElement('editor', 'body', 'Descripción / contenido', null,
            ['maxfiles' => 0, 'context' => \context_system::instance(), 'subdirs' => 0]);
        $m->setType('body', PARAM_RAW);
        $m->addElement('select', 'impact', 'Impacto', \local_orangeportal\service::impacts());
        $m->addElement('select', 'state', 'Estado compartido', \local_orangeportal\service::states());
        if ($kind === 'notifications') {
            $m->freeze('state'); $m->freeze('impact');
        }
        $m->addElement('header', 'audience', 'Destinatarios');
        $users = [];
        foreach ($DB->get_records_select('user', 'deleted = 0 AND suspended = 0 AND id > 1', [], 'lastname, firstname') as $u) {
            if (!isguestuser($u)) { $users[$u->id] = fullname($u) . ' (' . $u->username . ')'; }
        }
        $cohorts = [];
        foreach ($DB->get_records('cohort', null, 'name') as $c) { $cohorts[$c->id] = $c->name; }
        $groups = [];
        foreach ($DB->get_records_sql('SELECT g.*, c.fullname AS coursename FROM {groups} g JOIN {course} c ON c.id = g.courseid ORDER BY c.fullname, g.name') as $g) {
            $groups[$g->id] = $g->coursename . ' / ' . $g->name;
        }
        foreach (['users' => ['Usuarios individuales', $users], 'cohorts' => ['Colectivos globales (cohortes Moodle)', $cohorts],
                'groups' => ['Grupos de cursos Moodle', $groups]] as $name => [$label, $choices]) {
            $m->addElement('autocomplete', $name, $label, $choices, ['multiple' => true]);
            $m->setType($name, PARAM_INT);
        }
        $m->addElement('static', 'audiencenote', '', 'Se aplica la unión de destinatarios: basta pertenecer a uno de los colectivos seleccionados. La pertenencia se comprueba en cada acceso.');
        $m->addElement('advcheckbox', 'published', 'Publicado', 'Visible para sus destinatarios');
        $m->addElement('static', 'revisionnote', '', 'Si cambias el título o contenido, los destinatarios deberán volver a leer y confirmar la nueva versión.');
        $this->add_action_buttons(true, 'Guardar publicación');
    }
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (empty(trim(strip_tags($data['body']['text'] ?? '')))) { $errors['body'] = 'Escribe el contenido'; }
        if (!empty($data['published']) && empty($data['users']) && empty($data['cohorts']) && empty($data['groups'])) {
            $errors['users'] = 'Selecciona al menos un destinatario individual, cohorte o grupo';
        }
        return $errors;
    }
}
