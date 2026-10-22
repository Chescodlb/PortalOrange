<?php
/** Local incident workflow checks. All publications, receipts, state logs and catalogue changes are restored. */
define('CLI_SCRIPT', true);
require __DIR__ . '/../../../config.php';
require_once $CFG->dirroot . '/local/orangeportal/locallib.php';
use local_orangeportal\service;
use local_orangeportal\notice_options;
use local_orangeportal\incident_workflow;
if (!preg_match('~^http://(localhost|127\\.0\\.0\\.1):~', $CFG->wwwroot)) { throw new coding_exception('Local demo only.'); }
function incident_check(bool $ok, string $label): void { if (!$ok) { throw new RuntimeException($label); } echo "PASS $label\n"; }
function incident_denied(callable $action, string $label): void {
    try { $action(); } catch (moodle_exception $e) { incident_check(true, $label); return; }
    incident_check(false, $label);
}
$users = [];
foreach (['formador.orange', 'coordinador.orange', 'agente.orange', 'editor.orange'] as $name) {
    $users[$name] = $DB->get_record('user', ['username' => $name], '*', MUST_EXIST);
}
$tx = $DB->start_delegated_transaction();
$originalstates = get_config('local_orangeportal', 'notice_states');
try {
    \core\session\manager::set_user($users['formador.orange']);
    incident_check(service::manager() && incident_workflow::all() && has_capability('local/orangeportal:changestate', context_system::instance()), 'Formador publica, consulta todas y cambia estados');
    incident_check(has_capability('moodle/category:manage', context_coursecat::instance((int)get_config('local_orangeportal', 'librarycategory'))), 'Formador hereda gestión de categorías del editor');
    $category = $DB->get_field('local_orange_category', 'id', ['kind' => 'incidents'], IGNORE_MULTIPLE);
    $data = (object)['kind' => 'incidents', 'title' => 'TEST workflow incident', 'content' => '<p>TEST incident</p>',
        'categoryid' => $category, 'impact' => notice_options::default_code('impacts'),
        'state' => notice_options::default_code('states'), 'client' => 'Cliente de prueba', 'product' => 'Producto de prueba', 'published' => 1];
    $progress = isset(service::states(true)['progress']) ? 'progress' :
        notice_options::save('states', '', 'Estado abierto de prueba', true, false);
    $targets = ['user' => [$users['agente.orange']->id], 'cohort' => [], 'group' => []];
    $id = service::save_notice($data, $targets);
    $notice = $DB->get_record('local_orange_notice', ['id' => $id], '*', MUST_EXIST);
    incident_check($notice->client === 'Cliente de prueba' && $notice->product === 'Producto de prueba', 'Cliente y producto guardados');
    \core\session\manager::set_user($users['agente.orange']);
    incident_check(service::can_view($notice), 'Agente asignado consulta una incidencia abierta');
    incident_denied(fn() => service::change_state($notice, $progress), 'Agente no puede cambiar ningún estado');
    incident_denied(fn() => service::receipt($notice, 'confirmed'), 'Incidencia rechaza confirmación manual incluso por POST');
    $receipt = service::receipt($notice, 'readtime');
    incident_check($receipt->readtime > 0 && !$receipt->confirmed, 'Apertura registrada automáticamente sin confirmación');
    \core\session\manager::set_user($users['formador.orange']);
    service::change_state($notice, $progress);
    $notice = $DB->get_record('local_orange_notice', ['id' => $id]);
    \core\session\manager::set_user($users['agente.orange']);
    incident_check(service::can_view($notice), 'Un estado no cerrado sigue siendo visible para el agente');
    \core\session\manager::set_user($users['formador.orange']);
    service::change_state($notice, 'closed');
    $notice = $DB->get_record('local_orange_notice', ['id' => $id]);
    incident_check($notice->timeclosed > 0 && $notice->closedby == $users['formador.orange']->id, 'Cierre con fecha y responsable');
    \core\session\manager::set_user($users['agente.orange']);
    incident_check(!service::can_view($notice) && !isset(service::visible('incidents')[$id]), 'Agente no ve cerradas ni accede por URL');
    \core\session\manager::set_user($users['coordinador.orange']);
    incident_check(incident_workflow::all() && service::can_view($notice), 'Coordinador consulta cerradas aunque no esté asignado');
    incident_denied(fn() => service::change_state($notice, 'open'), 'Coordinador no cambia estados');
    incident_denied(fn() => incident_workflow::delete($id), 'Coordinador no elimina incidencias');
    \core\session\manager::set_user($users['formador.orange']);
    service::change_state($notice, 'open');
    $notice = $DB->get_record('local_orange_notice', ['id' => $id]);
    incident_check(!$notice->timeclosed && !$notice->closedby, 'Reabrir limpia el cierre actual conservando el histórico');
    $custom = notice_options::save('states', '', 'Cierre prueba', true, true);
    service::change_state($notice, $custom);
    $notice = $DB->get_record('local_orange_notice', ['id' => $id]);
    \core\session\manager::set_user($users['agente.orange']);
    incident_check(!service::can_view($notice), 'Un estado de cierre personalizado también se oculta al agente');
    \core\session\manager::set_user($users['formador.orange']);
    incident_denied(fn() => notice_options::save('states', $custom, 'Cierre prueba', true, false), 'No se reclasifica un estado usado para evitar el registro de cierres');
    $openstates = array_filter(notice_options::records('states'),
        fn($r, $code) => $r['active'] && !notice_options::is_closed($code), ARRAY_FILTER_USE_BOTH);
    $lastopen = array_key_first($openstates);
    foreach ($openstates as $code => $record) {
        if ($code !== $lastopen) { notice_options::save('states', $code, $record['name'], false); }
    }
    incident_denied(fn() => notice_options::save('states', $lastopen, $openstates[$lastopen]['name'], false),
        'No se desactiva el último estado abierto activo');
    incident_workflow::delete($id);
    incident_check(!isset(service::visible('incidents')[$id]) && $DB->record_exists('local_orange_state_log', ['noticeid' => $id]), 'Eliminar retira del listado y conserva el seguimiento');
    $notification = (object)['kind' => 'notifications', 'title' => 'TEST notification unchanged', 'content' => '<p>Notification</p>',
        'categoryid' => $DB->get_field('local_orange_category', 'id', ['kind' => 'notifications'], IGNORE_MULTIPLE),
        'impact' => notice_options::default_code('impacts'), 'state' => notice_options::default_code('states'), 'published' => 1];
    $nid = service::save_notice($notification, $targets);
    \core\session\manager::set_user($users['agente.orange']);
    $n = $DB->get_record('local_orange_notice', ['id' => $nid]);
    incident_check(service::receipt($n, 'readtime')->readtime > 0, 'Comunicados registra apertura automáticamente');
    incident_denied(fn() => service::receipt($n, 'confirmed'), 'Comunicados rechaza confirmación manual');
} finally {
    if ($originalstates === false) { unset_config('notice_states', 'local_orangeportal'); }
    else { set_config('notice_states', $originalstates, 'local_orangeportal'); }
    try { $tx->rollback(new RuntimeException('incident-workflow-test-rollback')); }
    catch (RuntimeException $e) { if ($e->getMessage() !== 'incident-workflow-test-rollback') { throw $e; } }
}
echo "Datos temporales restaurados\n";
