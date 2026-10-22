<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_login();
\local_orangeportal\experience::require_access();
$rootid = (int)get_config('local_orangeportal', 'librarycategory');
$options = []; $allowed = []; $types = [];
foreach (orange_native_courses($rootid) as $course) {
    $context = context_course::instance($course->id);
    if (!has_capability('moodle/course:manageactivities', $context)) { continue; }
    foreach (['resource' => 'documentfile', 'page' => 'documentpage'] as $module => $label) {
        if ($DB->record_exists('modules', ['name' => $module, 'visible' => 1]) &&
                has_capability('mod/' . $module . ':addinstance', $context) && course_allowed_module($course, $module)) {
            $options[$course->id] = $course->fullname;
            $allowed[$course->id][$module] = true;
            $types[$module] = get_string($label, 'local_orangeportal');
        }
    }
}
if (!$options) { throw new moodle_exception('nodocumentdestination', 'local_orangeportal'); }
class orange_document_destination_form extends moodleform {
    public function definition(): void {
        $m = $this->_form;
        $m->addElement('select', 'collection', get_string('collectiondestination', 'local_orangeportal'), $this->_customdata['options']);
        $m->setType('collection', PARAM_INT);
        $m->addElement('select', 'documenttype', get_string('documenttype', 'local_orangeportal'), $this->_customdata['types']);
        $m->setType('documenttype', PARAM_ALPHA);
        $m->addElement('static', 'instructions', '', get_string('documentinstructions', 'local_orangeportal'));
        $this->add_action_buttons(true, get_string('adddocument', 'local_orangeportal'));
    }
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (empty($this->_customdata['allowed'][$data['collection'] ?? 0][$data['documenttype'] ?? ''])) {
            $errors['documenttype'] = get_string('documenttypenotallowed', 'local_orangeportal');
        }
        return $errors;
    }
}
$url = orange_link('add_document.php');
orange_page(get_string('newdocument', 'local_orangeportal'), 'library', $url);
$PAGE->set_secondary_navigation(false);
$form = new orange_document_destination_form($url, ['options' => $options, 'types' => $types, 'allowed' => $allowed]);
if ($form->is_cancelled()) { redirect(orange_link('index.php', ['section' => 'library'])); }
if ($data = $form->get_data()) {
    if (!isset($options[$data->collection])) { throw new invalid_parameter_exception(get_string('collectionnotallowed', 'local_orangeportal')); }
    if (empty($allowed[$data->collection][$data->documenttype])) { throw new invalid_parameter_exception(get_string('documenttypenotallowed', 'local_orangeportal')); }
    require_capability('moodle/course:manageactivities', context_course::instance($data->collection));
    require_capability('mod/' . $data->documenttype . ':addinstance', context_course::instance($data->collection));
    redirect(new moodle_url('/course/modedit.php', ['add' => $data->documenttype, 'course' => $data->collection, 'section' => 0, 'return' => 0]));
}
echo $OUTPUT->header();
echo orange_intro(get_string('library', 'local_orangeportal'), get_string('newdocument', 'local_orangeportal'), get_string('documentdestinationintro', 'local_orangeportal'));
$form->display();
echo $OUTPUT->footer();
