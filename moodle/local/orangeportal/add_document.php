<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once($CFG->libdir . '/formslib.php');
require_login();
\local_orangeportal\experience::require_access();
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
        $m->addElement('select', 'collection', get_string('collectiondestination', 'local_orangeportal'), $this->_customdata['options']);
        $m->setType('collection', PARAM_INT);
        $m->addElement('static', 'instructions', '', get_string('documentinstructions', 'local_orangeportal'));
        $this->add_action_buttons(true, get_string('adddocument', 'local_orangeportal'));
    }
}
$url = orange_link('add_document.php');
orange_page(get_string('newdocument', 'local_orangeportal'), 'library', $url);
$PAGE->set_secondary_navigation(false);
$form = new orange_document_destination_form($url, ['options' => $options]);
if ($form->is_cancelled()) { redirect(orange_link('index.php', ['section' => 'library'])); }
if ($data = $form->get_data()) {
    if (!isset($options[$data->collection])) { throw new invalid_parameter_exception(get_string('collectionnotallowed', 'local_orangeportal')); }
    require_capability('mod/resource:addinstance', context_course::instance($data->collection));
    redirect(new moodle_url('/course/modedit.php', ['add' => 'resource', 'course' => $data->collection, 'section' => 0, 'return' => 0]));
}
echo $OUTPUT->header();
echo orange_intro(get_string('library', 'local_orangeportal'), get_string('newdocument', 'local_orangeportal'), get_string('documentdestinationintro', 'local_orangeportal'));
$form->display();
echo $OUTPUT->footer();
