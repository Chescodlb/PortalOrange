<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once($CFG->libdir . '/formslib.php');
use local_orangeportal\native_content;
require_login();
\local_orangeportal\experience::require_access();
$options = native_content::library_options();
if (!$options) { throw new moodle_exception('nativecontentdenied', 'local_orangeportal'); }
class orange_document_destination_form extends moodleform {
    public function definition(): void {
        $m = $this->_form;
        $m->addElement('select', 'categoryid', get_string('category'), $this->_customdata['options']);
        $m->setType('categoryid', PARAM_INT);
        $m->addElement('static', 'instructions', '', get_string('nativedocumentinstructions', 'local_orangeportal'));
        $this->add_action_buttons(true, get_string('addresourceoractivity'));
    }
}
$url = orange_link('add_document.php');
orange_page(get_string('newdocument', 'local_orangeportal'), 'library', $url);
$PAGE->set_secondary_navigation(false);
$form = new orange_document_destination_form($url, ['options' => $options]);
$form->set_data((object)['categoryid' => optional_param('category', 0, PARAM_INT)]);
if ($form->is_cancelled()) { redirect(orange_link('index.php', ['section' => 'library'])); }
if ($data = $form->get_data()) {
    $courseid = native_content::library_container((int)$data->categoryid);
    redirect(orange_link('add_activity.php', ['course' => $courseid]));
}
echo $OUTPUT->header();
echo orange_intro('', get_string('newdocument', 'local_orangeportal'), get_string('nativedocumentintro', 'local_orangeportal'));
$form->display();
echo $OUTPUT->footer();
