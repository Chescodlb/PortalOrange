<?php
/** Reversible integration tests for work profiles. Run against the local demo only. */
define('CLI_SCRIPT', true);
require __DIR__ . '/../../../config.php';
require_once $CFG->dirroot . '/local/orangeportal/locallib.php';
use local_orangeportal\orange_administration as admin;
use local_orangeportal\orange_scope as scope;
use local_orangeportal\work_profiles as profiles;
if (!preg_match('~^http://(localhost|127\.0\.0\.1):~', $CFG->wwwroot)) { throw new coding_exception('Local demo only.'); }
function profile_check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException($label); }
    echo "PASS $label\n";
}
function profile_denied(callable $fn, string $code): void {
    try { $fn(); } catch (moodle_exception $e) {
        profile_check($e->errorcode === $code, 'Rechazo: ' . $code);
        return;
    }
    throw new RuntimeException('Expected ' . $code);
}
function profile_user(): int {
    $suffix = bin2hex(random_bytes(8));
    return admin::create_user((object)['username' => 'profiletest_' . $suffix, 'firstname' => 'Profile',
        'lastname' => 'Test', 'email' => $suffix . '@localhost.invalid', 'password' => 'Testing!7aB' . $suffix]);
}
function profile_transaction(callable $test): void {
    global $DB;
    $tx = $DB->start_delegated_transaction();
    try { $test(); } finally {
        if (!$tx->is_disposed()) {
            try { $tx->rollback(new RuntimeException('profile-test-rollback')); }
            catch (RuntimeException $e) { if ($e->getMessage() !== 'profile-test-rollback') { throw $e; } }
        }
    }
}
$actor = $DB->get_record('user', ['username' => 'admin.orange'], '*', MUST_EXIST);
$external = $DB->get_record('user', ['username' => 'usuario.externo'], '*', MUST_EXIST);
\core\session\manager::set_user($actor);
$system = context_system::instance();
$courses = array_values(scope::course_ids());
if (count($courses) < 2) { throw new RuntimeException('Two Orange courses are required.'); }
$first = context_course::instance($courses[0]);
$second = context_course::instance($courses[1]);
profile_transaction(function() use ($DB, $actor, $external, $system, $first, $second) {
    $id = profile_user();
    profiles::save($id, 'agent', []);
    profile_check(!has_capability('local/orangeportal:viewreports', $system, $id), 'Agente sin seguimiento ajeno');
    profiles::save($id, 'trainer', [$first->instanceid], array_keys(profiles::categories()));
    profile_check(has_capability('local/orangeportal:manage', $system, $id) &&
        has_capability('moodle/course:manageactivities', $first, $id) &&
        !has_capability('moodle/course:manageactivities', $second, $id), 'Formador edita únicamente cursos autorizados');
    profile_check(!has_capability('moodle/site:config', $system, $id) &&
        !has_capability('moodle/user:create', $system, $id), 'Formador sin administración global');
    // Native resource editing creates derived author roles; consultation must remove them too.
    $map = $DB->get_record('local_orange_container', [], '*', IGNORE_MULTIPLE);
    if ($map) {
        role_assign($DB->get_field('role', 'id', ['shortname' => 'orange_autorrecursos']), $id,
            context_course::instance($map->courseid)->id, 'local_orangeportal', $map->id);
    }
    profiles::save($id, 'readonly', [$first->instanceid]);
    profile_check(profiles::state($id)->workprofile === 'readonly' &&
        profiles::state($id)->profilecourses === [(int)$first->instanceid], 'Persiste perfil y selección de cursos');
    profile_check(has_capability('local/orangeportal:viewreports', $system, $id) &&
        has_capability('moodle/grade:viewall', $first, $id) && !has_capability('moodle/grade:viewall', $second, $id),
        'Formador sin edición consulta notas solo en cursos autorizados');
    profile_check(!has_capability('local/orangeportal:manage', $system, $id) &&
        !has_capability('moodle/course:manageactivities', $first, $id) &&
        !has_capability('moodle/grade:edit', $first, $id) && !has_capability('moodle/grade:export', $first, $id),
        'Cambio a consulta retira escritura, calificación y exportación');
    foreach (profiles::categories() as $category => $name) {
        profile_check(!has_capability('moodle/course:create', context_coursecat::instance($category), $id),
            'Cambio a consulta retira creación en ' . $name);
    }
    profiles::save($id, 'readonly', [$second->instanceid]);
    profile_check(!has_capability('moodle/grade:viewall', $first, $id) &&
        has_capability('moodle/grade:viewall', $second, $id), 'Cambio de cursos revoca ámbito anterior');
    profiles::save($id, 'quality', []);
    profile_check(has_capability('local/orangeportal:viewreports', $system, $id) &&
        !has_capability('moodle/grade:viewall', $second, $id), 'Calidad consulta lecturas sin notas ajenas');
    profile_check(admin::exclusive_account($id), 'Calidad conserva edición de datos de cuenta exclusiva');
    profiles::save($id, 'coordinator', [$first->instanceid]);
    profile_check(has_capability('moodle/grade:viewall', $first, $id), 'Coordinación conserva consulta formativa');
    profiles::save($id, 'keep', []);
    profile_check(profiles::state($id)->workprofile === 'coordinator', 'Conservar no altera asignaciones');
    profile_denied(fn() => profiles::save($id, 'administrator', []), 'workprofileinvalid');
    profile_denied(fn() => profiles::save($id, 'readonly', []), 'workprofileneedcourses');
    profile_denied(fn() => profiles::save($id, 'readonly', [SITEID]), 'outsideorange');
    profile_denied(fn() => profiles::save($id, 'agent', [$first->instanceid]), 'workprofileinvalid');
    profile_denied(fn() => profiles::save($actor->id, 'agent', []), 'orangeprotecteduser');
    profile_denied(fn() => profiles::save($external->id, 'trainer', [$first->instanceid]), 'outsideorange');
    $role = $DB->get_field('role', 'id', ['shortname' => 'orange_seguimiento']);
    assign_capability('moodle/site:config', CAP_ALLOW, $role, $system->id);
    profile_denied(fn() => profiles::save($id, 'readonly', [$first->instanceid]), 'workprofilecustomrole');
    unassign_capability('moodle/site:config', $role, $system->id);
    admin::remove_access($id);
    profile_check(!has_capability('local/orangeportal:viewreports', $system, $id) &&
        !has_capability('moodle/grade:viewall', $first, $id), 'Retirar acceso Orange elimina perfiles gestionados');
    \core\session\manager::set_user($external);
    profile_denied(fn() => profiles::save($actor->id, 'agent', []), 'portalaccessdenied');
});
\core\session\manager::set_user($actor);
// A surviving manual editing role must reject consultation and roll back the entire save.
profile_transaction(function() use ($DB, $first) {
    $id = profile_user();
    $teacher = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
    role_assign($teacher, $id, $first->id);
    profile_denied(fn() => profiles::save($id, 'readonly', [$first->instanceid]), 'workprofileconflict');
});
profile_transaction(function() use ($DB, $first) {
    $id = profile_user();
    $role = create_role('Temporary assignment grader', 'profiletest_grader', 'Temporary test role');
    assign_capability('mod/assign:grade', CAP_ALLOW, $role, context_system::instance()->id);
    role_assign($role, $id, $first->id);
    profile_denied(fn() => profiles::save($id, 'readonly', [$first->instanceid]), 'workprofileconflict');
});
profile_transaction(function() use ($DB, $first, $second) {
    $id = profile_user();
    $role = $DB->get_field('role', 'id', ['shortname' => 'orange_consulta_formativa'], MUST_EXIST);
    role_assign($role, $id, $second->id);
    profile_denied(fn() => profiles::save($id, 'readonly', [$first->instanceid]), 'workprofileconflict');
});
profile_check(!$DB->record_exists_select('user', 'username LIKE ?', ['profiletest_%']), 'Sin cuentas de prueba persistentes');
echo "Pruebas finalizadas; datos temporales restaurados.\n";
