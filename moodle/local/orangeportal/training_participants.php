<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once($CFG->libdir . '/formslib.php');
require_login();
\local_orangeportal\experience::require_access();
$id = required_param('course', PARAM_INT);
$course = \local_orangeportal\native_content::training_course($id);
require_login($course);
$context = context_course::instance($id);
if (!\local_orangeportal\content_access::can_enrol($id)) {
    throw new required_capability_exception($context, 'local/orangeportal:enrolusers', 'nopermissions', '');
}
$users = array_filter(\local_orangeportal\orange_scope::users(), fn($u) => !$u->suspended &&
    \local_orangeportal\content_access::recipient($id, (int)$u->id));
class orange_training_participants_form extends moodleform {
    public function definition(): void {
        $m = $this->_form;
        $m->addElement('autocomplete', 'users', get_string('users'),
            array_map(fn($u) => fullname($u) . ' (' . $u->username . ')', $this->_customdata['users']), ['multiple' => true]);
        $m->setType('users', PARAM_INT);
        $m->addElement('static', 'hint', '', get_string('trainingparticipantshint', 'local_orangeportal'));
        $this->add_action_buttons(true, get_string('enrolusers', 'enrol_manual'));
    }
}
$url = orange_link('training_participants.php', ['course' => $id]);
orange_page(get_string('participants'), 'courses', $url);
$PAGE->set_course($course); $PAGE->set_context($context); $PAGE->set_secondary_navigation(false);
$PAGE->set_show_course_index(false);
$form = new orange_training_participants_form($url, ['users' => $users]);
if ($form->is_cancelled()) { redirect(\local_orangeportal\training_publication::edit_url($id)); }
if ($data = $form->get_data()) {
    $selected = array_values(array_unique(array_map('intval', (array)($data->users ?? []))));
    foreach ($selected as $userid) { if (!isset($users[$userid])) { throw new moodle_exception('outsideorange', 'local_orangeportal'); } }
    if (!enrol_is_enabled('manual')) { throw new moodle_exception('nativemanualrequired', 'local_orangeportal'); }
    $plugin = enrol_get_plugin('manual');
    $instances = array_filter(enrol_get_instances($id, true), fn($e) => $e->enrol === 'manual');
    $instance = reset($instances);
    if (!$instance) { $instance = $DB->get_record('enrol', ['id' => $plugin->add_instance($course)], '*', MUST_EXIST); }
    $roles = get_archetype_roles('student');
    if (!$roles) { throw new moodle_exception('csvnostudentrole', 'local_orangeportal'); }
    foreach ($selected as $userid) {
        if (!is_enrolled($context, $userid, '', true)) { $plugin->enrol_user($instance, $userid, reset($roles)->id, 0, 0, ENROL_USER_ACTIVE); }
    }
    redirect($url, get_string('changessaved', 'local_orangeportal'), null, \core\output\notification::NOTIFY_SUCCESS);
}
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($course->fullname) . ' · ' . get_string('participants'));
echo html_writer::link(\local_orangeportal\training_publication::edit_url($id), get_string('edit'), ['class' => 'btn btn-primary']);
$form->display();
$table = new html_table(); $table->head = [get_string('fullname'), get_string('username')];
foreach (get_enrolled_users($context, '', 0, 'u.*') as $u) {
    if (!\local_orangeportal\experience::member((int)$u->id)) { continue; }
    $table->data[] = [s(fullname($u)), s($u->username)];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
