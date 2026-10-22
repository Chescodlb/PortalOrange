<?php
/** Local permission regression, including combined roles. Fixture assignments are rolled back. */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/orangeportal/locallib.php');
if (!preg_match('~^http://(localhost|127\.0\.0\.1):~', $CFG->wwwroot)) { throw new coding_exception('Local demo only.'); }
function consultation_check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException($label); }
    echo "PASS $label\n";
}
$users = [];
foreach (['coordinador.orange', 'calidad.orange', 'formador.orange', 'agente.orange', 'admin.orange'] as $name) {
    $users[$name] = $DB->get_record('user', ['username' => $name], '*', MUST_EXIST);
}
$system = context_system::instance();
$course = $DB->get_record('course', ['shortname' => 'ORANGE-FORMACION-INICIAL'], '*', MUST_EXIST);
$context = context_course::instance($course->id);
$writes = ['manage', 'manageoptions', 'changestate', 'administer', 'exportreports'];
foreach (['coordinador.orange', 'calidad.orange'] as $name) {
    $u = $users[$name];
    foreach ($writes as $cap) {
        consultation_check(!has_capability('local/orangeportal:' . $cap, $system, $u), $name . ' sin ' . $cap);
    }
    consultation_check(has_capability('local/orangeportal:viewreports', $system, $u) &&
        has_capability('local/orangeportal:viewallincidents', $system, $u), $name . ' consulta lecturas e histórico');
    foreach (['moodle/grade:edit', 'moodle/grade:override', 'moodle/grade:manage', 'moodle/course:manageactivities',
            'enrol/manual:enrol', 'mod/quiz:grade', 'mod/quiz:regrade', 'mod/feedback:edititems', 'moodle/grade:export'] as $cap) {
        consultation_check(!has_capability($cap, $context, $u), $name . ' sin escritura/exportación: ' . $cap);
    }
}
consultation_check(has_capability('moodle/grade:viewall', $context, $users['coordinador.orange']),
    'Coordinación conserva consulta de notas en la formación autorizada');
consultation_check(has_capability('moodle/site:accessallgroups', $context, $users['coordinador.orange']) &&
    !has_capability('moodle/site:accessallgroups', context_course::instance(SITEID), $users['coordinador.orange']),
    'Consulta de grupos limitada a los cursos donde tiene asignado el rol');
consultation_check(!has_capability('moodle/grade:viewall', $context, $users['calidad.orange']),
    'Calidad no recibe notas ajenas');
foreach (['manage', 'manageoptions', 'changestate', 'viewreports', 'viewallincidents'] as $cap) {
    consultation_check(has_capability('local/orangeportal:' . $cap, $system, $users['formador.orange']), 'Formación conserva ' . $cap);
}
consultation_check(has_capability('enrol/manual:enrol', $context, $users['formador.orange']) &&
    !has_capability('moodle/user:create', $system, $users['formador.orange']), 'Formación matricula existentes y no crea cuentas');
consultation_check(!has_capability('local/orangeportal:viewreports', $system, $users['agente.orange']) &&
    !has_capability('moodle/grade:viewall', $context, $users['agente.orange']), 'Agente sin seguimiento de compañeros');
consultation_check(has_capability('local/orangeportal:administer', $system, $users['admin.orange']) &&
    !has_capability('moodle/site:config', $system, $users['admin.orange']), 'Administrador Orange conserva delegación sin sitio');
$tx = $DB->start_delegated_transaction();
try {
    $notice = $DB->get_record('local_orange_notice', ['kind' => 'incidents', 'deleted' => 0], '*', IGNORE_MULTIPLE);
    foreach (['coordinador.orange', 'calidad.orange'] as $name) {
        \core\session\manager::set_user($users[$name]);
        try {
            \local_orangeportal\service::change_state($notice, 'open');
            throw new RuntimeException('Cambio de estado permitido: ' . $name);
        } catch (required_capability_exception $expected) { consultation_check(true, $name . ' cambio de estado rechazado en servidor'); }
        try {
            \local_orangeportal\notice_options::save('impacts', '', 'TEST no autorizado', true);
            throw new RuntimeException('Catálogo editable: ' . $name);
        } catch (required_capability_exception $expected) { consultation_check(true, $name . ' modificación de catálogo rechazada en servidor'); }
    }
    $coordinator = $users['coordinador.orange'];
    role_assign($DB->get_field('role', 'id', ['shortname' => 'orange_publicador']), $coordinator->id, $system->id);
    \core\session\manager::set_user($DB->get_record('user', ['id' => $coordinator->id]));
    reload_all_capabilities();
    consultation_check(has_capability('local/orangeportal:manage', $system) &&
        has_capability('local/orangeportal:changestate', $system) &&
        has_capability('local/orangeportal:manageoptions', $system), 'Roles combinados: Formación conserva escritura sin Prohibir');
} finally {
    try { $tx->rollback(new RuntimeException('consultation-rollback')); }
    catch (RuntimeException $e) { if ($e->getMessage() !== 'consultation-rollback') { throw $e; } }
    \core\session\manager::set_user(get_admin());
}
echo "Asignaciones temporales revertidas\n";
