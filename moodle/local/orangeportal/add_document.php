<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once($CFG->libdir . '/formslib.php');
require_login();
$rootid = (int)get_config('local_orangeportal', 'librarycategory');
$courses = array_filter(orange_native_courses($rootid), fn($c) =>
    has_capability('moodle/course:manageactivities', context_course::instance($c->id)) &&
    has_capability('mod/resource:addinstance', context_course::instance($c->id)));
$options = [];
foreach ($courses as $course) { $options[$course->id] = $course->fullname; }
if (!$options) { throw new required_capability_exception(context_system::instance(), 'mod/resource:addinstance', 'nopermissions', ''); }
class orange_document_destination_form extends moodleform {
    public function definition(): void {
        $m = $this->_form;
        $m->addElement('select', 'collection', 'Colección documental', $this->_customdata['options']);
        $m->setType('collection', PARAM_INT);
        $m->addElement('static', 'instructions', '', 'El siguiente paso permite escribir el título y subir el documento con el formulario de archivos de Moodle.');
        $this->add_action_buttons(true, 'Añadir documento');
    }
}
$url = orange_link('add_document.php');
orange_page('Nuevo documento', 'library', $url);
$PAGE->set_secondary_navigation(false);
$form = new orange_document_destination_form($url, ['options' => $options]);
if ($form->is_cancelled()) { redirect(orange_link('index.php', ['section' => 'library'])); }
if ($data = $form->get_data()) {
    if (!isset($options[$data->collection])) { throw new invalid_parameter_exception('Colección no permitida'); }
    require_capability('mod/resource:addinstance', context_course::instance($data->collection));
    redirect(new moodle_url('/course/modedit.php', ['add' => 'resource', 'course' => $data->collection, 'section' => 0, 'return' => 0]));
}
echo $OUTPUT->header();
echo orange_intro('BIBLIOTECA', 'Nuevo documento', 'Elige la colección en la que quieres publicar el archivo.');
$form->display();
echo $OUTPUT->footer();
