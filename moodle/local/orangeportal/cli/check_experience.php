<?php
/** Local regression checks for cohort-based appearance and portal access. Rolls back test data. */
define('CLI_SCRIPT', true);
require __DIR__ . '/../../../config.php';
require_once $CFG->dirroot . '/cohort/lib.php';
use local_orangeportal\experience;
use local_orangeportal\service;
if (!preg_match('~^http://(localhost|127\\.0\\.0\\.1):~', $CFG->wwwroot)) {
    throw new coding_exception('Local demo test only.');
}
function experience_check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException($label); }
    echo "PASS $label\n";
}
$tx = $DB->start_delegated_transaction();
$originalenabled = get_config('local_orangeportal', 'enabled');
$originalcohort = get_config('local_orangeportal', 'audiencecohort');
try {
    $external = $DB->get_record('user', ['username' => 'usuario.externo'], '*', MUST_EXIST);
    $orange = $DB->get_record('user', ['username' => 'editor.orange'], '*', MUST_EXIST);
    $notice = $DB->get_record('local_orange_notice', ['kind' => 'incidents'], '*', IGNORE_MULTIPLE);
    experience_check(experience::member($orange->id), 'Editor Orange pertenece a la audiencia');
    experience_check(!experience::member($external->id), 'Cuenta externa fuera de la audiencia');
    experience_check(cohort_get_user_cohort_theme($orange->id) === 'orange', 'Selección nativa de tema para Orange');
    experience_check(!cohort_get_user_cohort_theme($external->id), 'Cuenta externa sin tema Orange');
    \core\session\manager::set_user($external);
    $denied = false;
    try { experience::require_access(); } catch (moodle_exception $e) { $denied = $e->errorcode === 'portalaccessdenied'; }
    experience_check($denied, 'Acceso directo denegado a usuario externo');
    $role = $DB->get_field('role', 'id', ['shortname' => 'orange_publicador'], MUST_EXIST);
    role_assign($role, $external->id, context_system::instance()->id);
    reload_all_capabilities();
    experience_check(has_capability('local/orangeportal:manage', context_system::instance()), 'Permiso editorial temporal de prueba');
    experience_check(!experience::member() && !service::can_view($notice), 'El rol por sí solo no habilita el portal ni evita el control de acceso');
    role_unassign($role, $external->id, context_system::instance()->id);
    reload_all_capabilities();
    cohort_add_member((int)$originalcohort, $external->id);
    experience_check(experience::member() && cohort_get_user_cohort_theme($external->id) === 'orange', 'Añadir a cohorte habilita la experiencia');
    experience_check(!has_capability('local/orangeportal:manage', context_system::instance()), 'Pertenecer a la cohorte no concede permisos editoriales');
    cohort_remove_member((int)$originalcohort, $external->id);
    experience_check(!experience::member() && !cohort_get_user_cohort_theme($external->id), 'Quitar de la cohorte retira experiencia y tema para el próximo inicio');
    set_config('audiencecohort', 0, 'local_orangeportal');
    experience_check(!experience::member($orange->id), 'Sin cohorte elegida no se aplica el portal');
    set_config('audiencecohort', $originalcohort, 'local_orangeportal');
    set_config('enabled', 0, 'local_orangeportal');
    experience_check(!experience::member($orange->id), 'Desactivar el portal desactiva navegación y acceso');
    experience_check(get_config('core', 'theme') === 'moove' && !get_config('theme_moove', 'scss'), 'Tema global de la demo sin estilos Orange');
} finally {
    set_config('enabled', $originalenabled, 'local_orangeportal');
    set_config('audiencecohort', $originalcohort, 'local_orangeportal');
    try { $tx->rollback(new RuntimeException('experience-test-rollback')); }
    catch (RuntimeException $e) { if ($e->getMessage() !== 'experience-test-rollback') { throw $e; } }
}
echo "Datos y ajustes de prueba restaurados\n";
