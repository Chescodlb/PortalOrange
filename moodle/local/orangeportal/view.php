<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
use local_orangeportal\service;
require_login();
$id = required_param('id', PARAM_INT);
$notice = $DB->get_record('local_orange_notice', ['id' => $id], '*', MUST_EXIST);
service::require_view($notice);
$url = orange_link('view.php', ['id' => $id]);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHA);
    if ($action === 'confirm') {
        if (required_param('revision', PARAM_INT) != $notice->revision) {
            throw new invalid_parameter_exception(get_string('noticerevisionchanged', 'local_orangeportal'));
        }
        service::receipt($notice, 'confirmed');
    } else if ($action === 'state') {
        service::change_state($notice, required_param('state', PARAM_ALPHA));
    } else { throw new invalid_parameter_exception(get_string('unknownaction', 'local_orangeportal')); }
    redirect($url, get_string('changessaved', 'local_orangeportal'), null, \core\output\notification::NOTIFY_SUCCESS);
}
orange_page($notice->title, $notice->kind, $url);
$receipt = service::receipt($notice, 'readtime');
echo $OUTPUT->header();
$category = $DB->get_record('local_orange_category', ['id' => $notice->categoryid], '*', MUST_EXIST);
echo html_writer::link(orange_link('index.php', ['section' => $notice->kind]), get_string('backtolist', 'local_orangeportal'));
echo orange_intro($category->name, $notice->title, get_string('noticeversiondate', 'local_orangeportal', (object)['version' => $notice->revision,
    'date' => userdate($notice->timecreated, '%d/%m/%Y')]));
echo html_writer::div(format_text($notice->content, FORMAT_HTML, ['context' => context_system::instance()]), 'orange-notice-content');
if ($receipt && service::can_view($notice, (int)$USER->id, false)) {
    echo html_writer::div(get_string('receiptdates', 'local_orangeportal', (object)['received' => userdate($receipt->received), 'read' => userdate($receipt->readtime)]), 'orange-receipt');
    echo $receipt->confirmed ? $OUTPUT->notification(get_string('confirmedon', 'local_orangeportal', userdate($receipt->confirmed)), 'success') :
        orange_post_button($url, get_string('confirmreading', 'local_orangeportal'), ['action' => 'confirm', 'revision' => $notice->revision]);
} else { echo $OUTPUT->notification(get_string('managementpreview', 'local_orangeportal'), 'info'); }
if ($notice->kind == 'incidents') {
    echo html_writer::tag('h3', get_string('currentstatus', 'local_orangeportal', s(service::states()[$notice->state])));
    if (has_capability('local/orangeportal:changestate', context_system::instance())) {
        $form = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'state']) .
            html_writer::select(service::states(true, $notice->state), 'state', $notice->state, false, ['aria-label' => get_string('newstatus', 'local_orangeportal')]) .
            html_writer::tag('button', get_string('updatestatusforall', 'local_orangeportal'), ['type' => 'submit', 'class' => 'btn btn-primary']);
        echo html_writer::tag('form', $form, ['method' => 'post', 'action' => $url->out(false), 'class' => 'orange-search']);
    }
    $logs = $DB->get_records('local_orange_state_log', ['noticeid' => $id], 'timecreated DESC, id DESC');
    foreach ($logs as $log) {
        $user = core_user::get_user($log->userid);
        echo html_writer::tag('p', s($user ? fullname($user) : get_string('deleteduser', 'local_orangeportal')) . ' · ' .
            s(service::states()[$log->oldstate] . ' → ' . service::states()[$log->newstate]) . ' · ' . userdate($log->timecreated));
    }
}
if (service::manager()) {
    echo html_writer::div(html_writer::link(orange_link('manage.php', ['id' => $id, 'kind' => $notice->kind]),
        get_string('editpublication', 'local_orangeportal'), ['class' => 'btn btn-outline-secondary']), 'orange-toolbar');
}
echo $OUTPUT->footer();
