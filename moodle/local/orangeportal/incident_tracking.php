<?php
require __DIR__ . '/../../config.php';
require __DIR__ . '/locallib.php';
use local_orangeportal\service;
use local_orangeportal\incident_workflow;
require_login();
\local_orangeportal\experience::require_access();
require_capability('local/orangeportal:viewallincidents', context_system::instance());
$id = optional_param('id', 0, PARAM_INT);
$url = orange_link('incident_tracking.php', $id ? ['id' => $id] : []);
orange_page(get_string('incidenttracking', 'local_orangeportal'), 'incidents', $url);
$PAGE->set_primary_active_tab('siteadminnode');
echo $OUTPUT->header();
echo orange_intro(get_string('administration'), get_string('incidenttracking', 'local_orangeportal'), get_string('incidenttrackingintro', 'local_orangeportal'));
echo orange_admin_navigation('incidenttracking');
echo html_writer::link(orange_link('index.php', ['section' => 'incidents']), get_string('backtolist', 'local_orangeportal'));
if (!$id) {
    $table = new html_table();
    $table->head = array_map(fn($key) => get_string($key, 'local_orangeportal'),
        ['incidentidentification', 'incidentopened', 'incidentcloseddate', 'incidentcolumnstate', 'incidentclient', 'incidentproduct', 'incidentassigned', 'incidentreadusers']);
    $users = $DB->get_records_select('user', 'id > 1 AND deleted = 0 AND suspended = 0');
    foreach ($DB->get_records('local_orange_notice', ['kind' => 'incidents'], 'timecreated DESC, id DESC') as $n) {
        $assigned = 0; $read = 0;
        foreach ($users as $u) {
            if (!service::assigned((int)$n->id, (int)$u->id)) { continue; }
            $assigned++;
            $r = service::get_receipt($n, (int)$u->id);
            if ($r && $r->readtime) { $read++; }
        }
        $title = html_writer::link(orange_link('incident_tracking.php', ['id' => $n->id]), s($n->title));
        if ($n->deleted) { $title .= ' · ' . get_string('incidentarchived', 'local_orangeportal'); }
        $table->data[] = [$title, userdate($n->timecreated, '%d/%m/%Y %H:%M'), $n->timeclosed ? userdate($n->timeclosed, '%d/%m/%Y %H:%M') : '—',
            s(service::states()[$n->state] ?? $n->state), s($n->client ?: '—'), s($n->product ?: '—'), $assigned, $read];
    }
    echo html_writer::div(html_writer::table($table), 'orange-table-scroll');
} else {
    $n = $DB->get_record('local_orange_notice', ['id' => $id, 'kind' => 'incidents'], '*', MUST_EXIST);
    echo $OUTPUT->heading(s($n->title), 3);
    echo html_writer::tag('p', s(get_string('incidentclient', 'local_orangeportal') . ': ' . ($n->client ?: '—') . ' · ' .
        get_string('incidentproduct', 'local_orangeportal') . ': ' . ($n->product ?: '—')));
    echo html_writer::tag('p', s(get_string('incidentcolumnstate', 'local_orangeportal') . ': ' . (service::states()[$n->state] ?? $n->state)));
    if ($n->timeclosed) {
        $closer = $n->closedby ? core_user::get_user($n->closedby) : false;
        echo html_writer::tag('p', s(get_string('incidentclosureinfo', 'local_orangeportal', (object)[
            'date' => userdate($n->timeclosed, '%d/%m/%Y %H:%M'), 'user' => $closer ? fullname($closer) : '—'])));
    }
    if ($n->deleted) { echo $OUTPUT->notification(get_string('incidentarchived', 'local_orangeportal'), 'info'); }
    if (!$n->deleted && service::manager()) {
        echo html_writer::link(orange_link('manage.php', ['kind' => 'incidents', 'id' => $id]), get_string('edit'), ['class' => 'btn btn-outline-secondary']);
    }
    $audience = new html_table();
    $audience->head = array_map(fn($key) => get_string($key, 'local_orangeportal'), ['recipient', 'received', 'read']);
    foreach ($DB->get_records_select('user', 'id > 1 AND deleted = 0 AND suspended = 0', [], 'lastname, firstname') as $u) {
        if (!service::assigned($id, (int)$u->id)) { continue; }
        $r = service::get_receipt($n, (int)$u->id);
        $audience->data[] = [s(fullname($u)), $r && $r->received ? userdate($r->received, '%d/%m/%Y %H:%M') : get_string('pending', 'local_orangeportal'),
            $r && $r->readtime ? userdate($r->readtime, '%d/%m/%Y %H:%M') : get_string('pending', 'local_orangeportal')];
    }
    echo $OUTPUT->heading(get_string('incidentrecipienttracking', 'local_orangeportal'), 4);
    echo html_writer::div(html_writer::table($audience), 'orange-table-scroll');
    echo $OUTPUT->heading(get_string('incidentstatehistory', 'local_orangeportal'), 4);
    foreach ($DB->get_records('local_orange_state_log', ['noticeid' => $id], 'timecreated DESC, id DESC') as $log) {
        $user = core_user::get_user($log->userid);
        echo html_writer::tag('p', s(userdate($log->timecreated) . ' · ' . ($user ? fullname($user) : '—') . ' · ' .
            (service::states()[$log->oldstate] ?? $log->oldstate) . ' → ' . (service::states()[$log->newstate] ?? $log->newstate)));
    }
    echo $OUTPUT->heading(get_string('incidentreadinghistory', 'local_orangeportal'), 4);
    $history = new html_table();
    $history->head = array_map(fn($key) => get_string($key, 'local_orangeportal'), ['version', 'recipient', 'received', 'read']);
    foreach ($DB->get_records('local_orange_receipt', ['noticeid' => $id], 'revision DESC, userid') as $r) {
        $user = core_user::get_user($r->userid);
        $history->data[] = [$r->revision, s($user ? fullname($user) : '—'), $r->received ? userdate($r->received, '%d/%m/%Y %H:%M') : '—', $r->readtime ? userdate($r->readtime, '%d/%m/%Y %H:%M') : '—'];
    }
    echo html_writer::div(html_writer::table($history), 'orange-table-scroll');
}
echo $OUTPUT->footer();
