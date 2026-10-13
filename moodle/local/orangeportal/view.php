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
            throw new invalid_parameter_exception('El aviso ha cambiado. Recarga antes de confirmar.');
        }
        service::receipt($notice, 'confirmed');
    } else if ($action === 'state') {
        service::change_state($notice, required_param('state', PARAM_ALPHA));
    } else { throw new invalid_parameter_exception('Acción desconocida'); }
    redirect($url, 'Cambio guardado', null, \core\output\notification::NOTIFY_SUCCESS);
}
orange_page($notice->title, $notice->kind, $url);
$receipt = service::receipt($notice, 'readtime');
echo $OUTPUT->header();
$category = $DB->get_record('local_orange_category', ['id' => $notice->categoryid], '*', MUST_EXIST);
echo html_writer::link(orange_link('index.php', ['section' => $notice->kind]), '← Volver al listado');
echo orange_intro($category->name, $notice->title, 'Versión ' . $notice->revision . ' · Publicado el ' . userdate($notice->timecreated, '%d/%m/%Y'));
echo html_writer::div(format_text($notice->content, FORMAT_HTML, ['context' => context_system::instance()]), 'orange-notice-content');
if ($receipt) {
    echo html_writer::div('Recibido: ' . userdate($receipt->received) . ' · Leído: ' . userdate($receipt->readtime), 'orange-receipt');
    echo $receipt->confirmed ? $OUTPUT->notification('Lectura confirmada el ' . userdate($receipt->confirmed), 'success') :
        orange_post_button($url, 'Confirmar que he leído este aviso', ['action' => 'confirm', 'revision' => $notice->revision]);
} else { echo $OUTPUT->notification('Vista de gestión: esta consulta no registra una lectura de destinatario.', 'info'); }
if ($notice->kind == 'incidents') {
    echo html_writer::tag('h3', 'Estado: ' . service::states()[$notice->state]);
    if (has_capability('local/orangeportal:changestate', context_system::instance())) {
        $form = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'state']) .
            html_writer::select(service::states(), 'state', $notice->state, false, ['aria-label' => 'Nuevo estado']) .
            html_writer::tag('button', 'Actualizar estado para todos', ['type' => 'submit', 'class' => 'btn btn-primary']);
        echo html_writer::tag('form', $form, ['method' => 'post', 'action' => $url->out(false), 'class' => 'orange-search']);
    }
    $logs = $DB->get_records('local_orange_state_log', ['noticeid' => $id], 'timecreated DESC, id DESC');
    foreach ($logs as $log) {
        $user = core_user::get_user($log->userid);
        echo html_writer::tag('p', s($user ? fullname($user) : 'Usuario eliminado') . ' · ' .
            s(service::states()[$log->oldstate] . ' → ' . service::states()[$log->newstate]) . ' · ' . userdate($log->timecreated));
    }
}
if (service::manager()) {
    echo html_writer::div(html_writer::link(orange_link('manage.php', ['id' => $id, 'kind' => $notice->kind]),
        'Editar publicación', ['class' => 'btn btn-outline-secondary']), 'orange-toolbar');
}
echo $OUTPUT->footer();
