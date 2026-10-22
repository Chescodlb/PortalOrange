<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
use local_orangeportal\orange_administration as admin;
use local_orangeportal\orange_scope as scope;
require_login();
admin::require_access();
$id = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$q = optional_param('q', '', PARAM_TEXT);
$cohortid = optional_param('cohortid', 0, PARAM_INT);
$url = orange_link('users.php');
if ($cohortid && !isset(scope::cohorts()[$cohortid])) { throw new moodle_exception('outsideorange', 'local_orangeportal'); }
$target = $id ? scope::user($id) : null;
orange_page(get_string('orangeusers', 'local_orangeportal'), 'administration', $url);
$PAGE->set_primary_active_tab('siteadminnode');
$PAGE->set_secondary_navigation(false);
if ($action === 'remove') {
    if (!$target) { throw new invalid_parameter_exception('Invalid target'); }
    if (data_submitted() && confirm_sesskey()) {
        admin::remove_access($id);
        redirect($url, get_string('orangerevoked', 'local_orangeportal'));
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(get_string('orangeconfirmrevoke', 'local_orangeportal', fullname($target)),
        new single_button(orange_link('users.php', ['id' => $id, 'action' => 'remove']), get_string('continue'), 'post'), $url);
    echo $OUTPUT->footer(); exit;
}
$form = null;
if ($target || $action === 'new') {
    $editable = !$target || admin::exclusive_account($id);
    $form = new \local_orangeportal\form\orange_user(orange_link('users.php', $target ? ['id' => $id] : ['action' => 'new']),
        ['new' => !$target, 'editable' => $editable]);
    if ($form->is_cancelled()) { redirect($url); }
    if ($data = $form->get_data()) {
        $tx = $DB->start_delegated_transaction();
        // Validate every destination before creating or updating the account.
        foreach ($data->cohorts ?? [] as $cid) {
            if (!isset(scope::cohorts()[$cid])) { throw new moodle_exception('outsideorange', 'local_orangeportal'); }
        }
        try {
            if (!$target) { $id = admin::create_user($data); }
            else if ($editable) { admin::update_user($id, $data); }
            admin::set_cohorts($id, $data->cohorts ?? []);
            $tx->allow_commit();
        } catch (moodle_exception $e) { $tx->rollback($e); }
        redirect($url, get_string('changessaved', 'local_orangeportal'));
    }
    if ($target) {
        $data = clone $target;
        $data->cohorts = array_values(array_intersect(array_keys(scope::cohorts()),
            $DB->get_fieldset_select('cohort_members', 'cohortid', 'userid = ?', [$id])));
        $form->set_data($data);
    }
}
echo $OUTPUT->header();
echo orange_intro('', get_string('orangeusers', 'local_orangeportal'), get_string('orangeusersintro', 'local_orangeportal'));
echo orange_admin_navigation('users');
if ($form) {
    if ($target) { echo $OUTPUT->heading(s(fullname($target)), 3); }
    $form->display();
} else {
    echo html_writer::link(orange_link('users.php', ['action' => 'new']), get_string('orangecreateuser', 'local_orangeportal'), ['class' => 'btn btn-primary']);
    if (has_capability('local/orangeportal:exportreports', context_system::instance())) {
        echo ' ' . html_writer::link(orange_link('export.php', ['type' => 'users']), get_string('orangeexport', 'local_orangeportal'), ['class' => 'btn btn-outline-secondary']);
    }
    echo html_writer::tag('form', html_writer::tag('label', get_string('search'), ['for' => 'orange-users-search']) .
        html_writer::empty_tag('input', ['id' => 'orange-users-search', 'type' => 'search', 'name' => 'q', 'value' => $q, 'class' => 'form-control']) .
        ($cohortid ? html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'cohortid', 'value' => $cohortid]) : '') .
        html_writer::tag('button', get_string('search'), ['class' => 'btn btn-primary']),
        ['method' => 'get', 'class' => 'orange-search', 'action' => $url->out(false)]);
    $table = new html_table();
    $table->head = [get_string('fullnameuser'), get_string('username'), get_string('email'), get_string('orangecollectives', 'local_orangeportal'), get_string('actions')];
    foreach (scope::users() as $u) {
        if ($q !== '' && core_text::strpos(core_text::strtolower(fullname($u) . ' ' . $u->username . ' ' . $u->email), core_text::strtolower($q)) === false) { continue; }
        if ($cohortid && !$DB->record_exists('cohort_members', ['cohortid' => $cohortid, 'userid' => $u->id])) { continue; }
        $cohorts = [];
        foreach (scope::cohorts() as $c) {
            if ($DB->record_exists('cohort_members', ['userid' => $u->id, 'cohortid' => $c->id])) { $cohorts[] = $c->name; }
        }
        $protected = is_siteadmin($u->id) || has_capability('local/orangeportal:administer', context_system::instance(), $u->id);
        $actions = $protected ? get_string('orangeprotected', 'local_orangeportal') :
            html_writer::link(orange_link('users.php', ['id' => $u->id]), get_string('edit')) . ' · ' .
            html_writer::link(orange_link('users.php', ['id' => $u->id, 'action' => 'remove']), get_string('orangerevoke', 'local_orangeportal'));
        $table->data[] = [s(fullname($u)), s($u->username), s($u->email), s(implode(', ', $cohorts)), $actions];
    }
    echo html_writer::div(html_writer::table($table), 'orange-table-scroll');
}
echo $OUTPUT->footer();
