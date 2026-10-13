<?php
/** Local regression checks. All test mutations are rolled back. */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/cohort/lib.php');
use local_orangeportal\service;
if (!str_starts_with($CFG->wwwroot, 'http://localhost:')) { cli_error('Prueba limitada a local.'); }
$admin = get_admin();
$agent = $DB->get_record('user', ['username' => 'agente.orange'], '*', MUST_EXIST);
$outsider = $DB->get_record('user', ['username' => 'agente.otro'], '*', MUST_EXIST);
$publisher = $DB->get_record('user', ['username' => 'editor.orange'], '*', MUST_EXIST);
$cohort = $DB->get_record('cohort', ['idnumber' => 'ORANGE-EQUIPO-DEMO'], '*', MUST_EXIST);
$category = $DB->get_record('local_orange_category', ['kind' => 'incidents', 'name' => 'Centrex'], '*', MUST_EXIST);
function orange_assert(bool $pass, string $label): void {
    if (!$pass) { throw new RuntimeException($label); }
    echo "PASS $label\n";
}
function orange_denied(callable $callback, string $label): void {
    try { $callback(); } catch (required_capability_exception | invalid_parameter_exception $e) {
        orange_assert(true, $label); return;
    }
    throw new RuntimeException('Debe denegarse: ' . $label);
}
$tx = $DB->start_delegated_transaction();
try {
    \core\session\manager::set_user($admin);
    $data = (object)['kind' => 'incidents', 'categoryid' => $category->id, 'title' => 'Prueba transaccional',
        'content' => '<p>Primera versión</p>', 'state' => 'open', 'impact' => 'normal', 'published' => 1];
    $id = service::save_notice($data, ['cohort' => [$cohort->id]]);
    $notice = $DB->get_record('local_orange_notice', ['id' => $id], '*', MUST_EXIST);
    orange_assert(service::receipt($notice, 'readtime') === null, 'Vista del administrador no falsea lecturas');
    \core\session\manager::set_user($outsider);
    orange_assert(!service::can_view($notice), 'Cohorte ajena no concede acceso');
    orange_denied(fn() => service::receipt($notice, 'confirmed'), 'No se puede confirmar una incidencia ajena');
    orange_denied(fn() => service::change_state($notice, 'closed'), 'No se puede cambiar una incidencia ajena');
    orange_denied(fn() => service::save_notice(clone $data, ['user' => [$outsider->id]]), 'Agente no puede publicar');
    \core\session\manager::set_user($agent);
    $r = service::receipt($notice, 'received');
    orange_assert($r->received > 0 && !$r->readtime && !$r->confirmed, 'Recibido no implica lectura ni confirmación');
    $r = service::receipt($notice, 'readtime');
    orange_assert($r->readtime > 0 && !$r->confirmed, 'Leer no confirma automáticamente');
    $r = service::receipt($notice, 'confirmed');
    $again = service::receipt($notice, 'confirmed');
    orange_assert($r->confirmed > 0 && $r->confirmed == $again->confirmed && $r->id == $again->id,
        'Confirmación idempotente conserva su primera fecha');
    service::change_state($notice, 'progress');
    orange_assert($DB->get_field('local_orange_notice', 'state', ['id' => $id]) === 'progress', 'Cambio compartido de estado');
    orange_assert($DB->record_exists('local_orange_state_log', ['noticeid' => $id, 'userid' => $agent->id,
        'oldstate' => 'open', 'newstate' => 'progress']), 'Autor y transición auditados');
    cohort_remove_member($cohort->id, $agent->id);
    orange_assert(!service::can_view($notice), 'Quitar del colectivo revoca el acceso');
    cohort_add_member($cohort->id, $agent->id);
    \core\session\manager::set_user($publisher);
    orange_assert(service::manager() && !has_capability('moodle/site:config', context_system::instance()),
        'Publicador tiene capacidad específica sin administración completa');
    $update = clone $notice; $update->state = 'progress'; $update->content = '<p>Segunda versión</p>';
    service::save_notice($update, ['cohort' => [$cohort->id]]);
    $newnotice = $DB->get_record('local_orange_notice', ['id' => $id], '*', MUST_EXIST);
    orange_assert($newnotice->revision == 2 && $DB->count_records('local_orange_revision', ['noticeid' => $id]) == 2,
        'Editar contenido conserva versiones');
    orange_assert($DB->record_exists('local_orange_receipt', ['noticeid' => $id, 'userid' => $agent->id, 'revision' => 1]),
        'Editar contenido conserva confirmaciones históricas');
    orange_assert(service::get_receipt($newnotice, (int)$agent->id) === null, 'Nueva versión exige nueva lectura');
    \core\session\manager::set_user($agent);
    orange_denied(fn() => service::receipt($notice, 'confirmed'), 'No se puede confirmar una versión obsoleta');
    service::receipt($newnotice, 'confirmed');
    orange_assert(service::get_receipt($newnotice, (int)$agent->id)->confirmed > 0, 'Nueva versión confirmable');
    \core\session\manager::set_user($publisher);
    $draft = clone $data; unset($draft->id); $draft->published = 0;
    $draftid = service::save_notice($draft, ['cohort' => [$cohort->id]]);
    $draftnotice = $DB->get_record('local_orange_notice', ['id' => $draftid], '*', MUST_EXIST);
    \core\session\manager::set_user($agent);
    orange_assert(!service::can_view($draftnotice), 'Un borrador no se muestra al destinatario');
    orange_assert(!has_capability('local/orangeportal:viewreports', context_system::instance()), 'Agente sin informes globales');
    echo "Todas las comprobaciones de acceso han pasado; se revierte la prueba.\n";
    $tx->rollback(new RuntimeException('orange_test_rollback'));
} catch (Throwable $e) {
    if ($e->getMessage() !== 'orange_test_rollback') {
        try { $tx->rollback($e); } catch (Throwable $rolled) { fwrite(STDERR, $rolled->getMessage() . "\n"); exit(1); }
    }
} finally { \core\session\manager::set_user($admin); }
