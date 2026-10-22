<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
use local_orangeportal\orange_administration as admin;
use local_orangeportal\orange_scope as scope;
require_login(); admin::require_access();
$id = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$url = orange_link('cohorts.php');
if ($id && !isset(scope::cohorts()[$id])) { throw new moodle_exception('outsideorange', 'local_orangeportal'); }
orange_page(get_string('orangecollectives', 'local_orangeportal'), 'administration', $url);
$PAGE->set_primary_active_tab('siteadminnode'); $PAGE->set_secondary_navigation(false);
if ($action === 'delete') {
    if (!$id) { throw new invalid_parameter_exception('Invalid collective'); }
    if (data_submitted() && confirm_sesskey()) {
        admin::delete_cohort($id); redirect($url, get_string('changessaved', 'local_orangeportal'));
    }
    echo $OUTPUT->header(); echo $OUTPUT->confirm(get_string('orangeconfirmdeletecohort', 'local_orangeportal', scope::cohorts()[$id]->name),
        new single_button(orange_link('cohorts.php', ['action' => 'delete', 'id' => $id]), get_string('delete'), 'post'), $url);
    echo $OUTPUT->footer(); exit;
}
$form = null;
if ($id || $action === 'new') {
    $form = new \local_orangeportal\form\orange_cohort(orange_link('cohorts.php', $id ? ['id' => $id] : ['action' => 'new']));
    if ($form->is_cancelled()) { redirect($url); }
    if ($data = $form->get_data()) {
        foreach ($data->courses ?? [] as $courseid) {
            if (!isset(scope::course_ids()[$courseid])) { throw new moodle_exception('outsideorange', 'local_orangeportal'); }
        }
        $tx = $DB->start_delegated_transaction();
        $id = admin::save_cohort($id, $data->name);
        foreach ($data->courses ?? [] as $courseid) { admin::enrol_cohort($id, $courseid); }
        $tx->allow_commit(); redirect($url, get_string('changessaved', 'local_orangeportal'));
    }
    if ($id) { $form->set_data((object)['name' => scope::cohorts()[$id]->name]); }
}
echo $OUTPUT->header();
echo orange_intro('', get_string('orangecollectives', 'local_orangeportal'), get_string('orangecollectivesintro', 'local_orangeportal'));
echo orange_admin_navigation('cohorts');
if ($form) { $form->display(); }
else {
    echo html_writer::link(orange_link('cohorts.php', ['action' => 'new']), get_string('orangecreatecohort', 'local_orangeportal'), ['class' => 'btn btn-primary']);
    $table = new html_table(); $table->head = [get_string('name'), get_string('memberscount', 'cohort'), get_string('actions')];
    foreach (scope::cohorts() as $c) {
        $table->data[] = [s($c->name), $DB->count_records('cohort_members', ['cohortid' => $c->id]),
            html_writer::link(orange_link('users.php', ['cohortid' => $c->id]), get_string('orangeusers', 'local_orangeportal')) . ' · ' .
            html_writer::link(orange_link('cohorts.php', ['id' => $c->id]), get_string('edit')) . ' · ' .
            html_writer::link(orange_link('cohorts.php', ['id' => $c->id, 'action' => 'delete']), get_string('delete'))];
    }
    echo html_writer::div(html_writer::table($table), 'orange-table-scroll');
}
echo $OUTPUT->footer();
