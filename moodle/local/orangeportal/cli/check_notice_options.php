<?php
/** Catalogue regression checks: all temporary data and settings are restored. */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
use local_orangeportal\notice_options as options;
use local_orangeportal\service;
if (!str_starts_with($CFG->wwwroot, 'http://localhost:')) { cli_error('Prueba limitada a local.'); }
$original = [];
foreach (['states', 'impacts'] as $type) { $original[$type] = get_config('local_orangeportal', 'notice_' . $type); }
$tx = $DB->start_delegated_transaction();
$checks = 0;
function option_check(bool $condition, string $label): void {
    global $checks;
    if (!$condition) { throw new RuntimeException($label); }
    $checks++; echo 'PASS ' . $label . PHP_EOL;
}
function option_denied(callable $action, string $code, string $label): void {
    try { $action(); } catch (moodle_exception $e) {
        option_check($e->errorcode === $code, $label . ' (' . $e->errorcode . ')'); return;
    }
    option_check(false, $label);
}
$failure = null;
try {
    $editor = $DB->get_record('user', ['username' => 'editor.orange'], '*', MUST_EXIST);
    $coordinator = $DB->get_record('user', ['username' => 'coordinador.orange'], '*', MUST_EXIST);
    $agent = $DB->get_record('user', ['username' => 'agente.orange'], '*', MUST_EXIST);
    foreach ([$editor, $coordinator] as $user) {
        \core\session\manager::set_user($user);
        option_check(has_capability('local/orangeportal:manageoptions', context_system::instance()), 'Acceso de ' . $user->username);
    }
    option_check(!has_capability('local/orangeportal:manage', context_system::instance()), 'Coordinador no recibe publicación');
    $state = options::save('states', '', 'Estado de prueba', true);
    $impact = options::save('impacts', '', 'Impacto de prueba', true);
    option_check(isset(service::states()[$state]) && isset(service::impacts()[$impact]), 'Crear estado e impacto');
    option_check(strlen($state) <= 20 && ctype_alpha($state), 'Identificadores compatibles con avisos existentes');
    options::save('states', $state, 'Estado renombrado', true);
    option_check(service::states()[$state] === 'Estado renombrado', 'Renombrar conserva identificador');
    option_denied(fn() => options::save('states', '', 'estado renombrado', true), 'duplicateoptionname', 'Nombres duplicados rechazados');
    option_denied(fn() => options::save('states', '', '   ', true), 'invalidoptionname', 'Nombre vacío rechazado');
    \core\session\manager::set_user($editor);
    $categories = $DB->get_records('local_orange_category', ['kind' => 'incidents'], 'id', '*', 0, 1);
    $notice = (object)['kind' => 'incidents', 'categoryid' => reset($categories)->id,
        'title' => 'Prueba temporal de catálogo', 'content' => '<p>Prueba</p>',
        'state' => $state, 'impact' => $impact, 'published' => 1];
    $targets = ['user' => [$agent->id]];
    $id = service::save_notice($notice, $targets);
    option_check($DB->record_exists('local_orange_notice', ['id' => $id, 'state' => $state, 'impact' => $impact]), 'Publicación acepta nuevas opciones');
    option_denied(fn() => options::delete('states', $state), 'optioninuse', 'Estado usado no se elimina');
    option_denied(fn() => options::delete('impacts', $impact), 'optioninuse', 'Impacto usado no se elimina');
    options::save('states', $state, 'Estado renombrado', false);
    options::save('impacts', $impact, 'Impacto de prueba', false);
    option_check(!isset(service::states(true)[$state]) && isset(service::states()[$state]), 'Desactivación conserva filtros e histórico');
    option_check(isset(service::states(true, $state)[$state]), 'Edición conserva opción desactivada actual');
    $notice->title .= ' editada';
    service::save_notice($notice, $targets);
    option_check($DB->get_field('local_orange_notice', 'state', ['id' => $id]) === $state, 'Guardar aviso conserva opción inactiva actual');
    $newnotice = clone $notice; unset($newnotice->id, $newnotice->revision);
    option_denied(fn() => service::save_notice($newnotice, $targets), 'inactiveoptionselection', 'Nuevo aviso no acepta opción inactiva');
    \core\session\manager::set_user($agent);
    service::change_state($notice, 'open');
    option_denied(fn() => service::change_state($notice, $state), 'inactiveoptionselection', 'No reasigna estado inactivo usando aviso antiguo');
    \core\session\manager::set_user($coordinator);
    option_denied(fn() => options::delete('states', $state), 'optioninuse', 'Estado solo presente en histórico queda protegido');
    $unused = options::save('states', '', 'Opción sin uso', true);
    options::delete('states', $unused);
    option_check(!isset(service::states()[$unused]), 'Eliminar opción sin referencias');
    foreach (options::records('states') as $key => $record) {
        if ($key !== 'open') { options::save('states', $key, $record['name'], false); }
    }
    option_denied(fn() => options::save('states', 'open', 'Abierta', false), 'lastactiveoption', 'No desactiva la última opción activa');
    $last = options::save('impacts', '', 'Último impacto de prueba', true);
    foreach (options::records('impacts') as $key => $record) {
        if ($key !== $last) { options::save('impacts', $key, $record['name'], false); }
    }
    option_check(options::default_code('impacts') === $last, 'Valor inicial usa primera opción activa');
    option_denied(fn() => options::delete('impacts', $last), 'lastactiveoption', 'No elimina la última opción activa');
    \core\session\manager::set_user($agent);
    option_denied(fn() => options::save('states', '', 'Sin permiso', true), 'nopermissions', 'Agente no crea opciones');
    option_denied(fn() => options::delete('states', $state), 'nopermissions', 'Agente no elimina opciones');
    echo 'Comprobaciones: ' . $checks . '. Se revierten datos y configuración.' . PHP_EOL;
} catch (Throwable $e) { $failure = $e; }
try { $tx->rollback(new RuntimeException('orange_options_rollback')); } catch (Throwable $e) {
    if ($e->getMessage() !== 'orange_options_rollback') { $failure = $failure ?? $e; }
}
foreach ($original as $type => $value) {
    if ($value === false) { unset_config('notice_' . $type, 'local_orangeportal'); }
    else { set_config('notice_' . $type, $value, 'local_orangeportal'); }
}
if ($failure) { cli_error($failure->getMessage()); }
