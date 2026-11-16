<?php
/** Local-only deployment regression. All data/configuration changes are rolled back. */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/cohort/lib.php');
use local_orangeportal\deployment_config as config;
if (!str_starts_with($CFG->wwwroot, 'http://localhost:')) { throw new coding_exception('Local only'); }
function deploy_check(bool $ok, string $label): void { if (!$ok) { throw new RuntimeException($label); } echo "PASS $label\n"; }
function deploy_denied(callable $fn, string $label): void {
    try { $fn(); } catch (Throwable $e) { deploy_check(true, $label); return; } throw new RuntimeException($label);
}
$oldcfg = clone $CFG; $olduser = clone $USER;
$CFG->forced_plugin_settings['local_orangeportal']['teamsenabled'] = 0;
$token = 'test_deploy_' . bin2hex(random_bytes(4));
$secretfile = tempnam(sys_get_temp_dir(), 'orange-deploy-url-');
file_put_contents($secretfile, 'https://teams.example.invalid/invoke?sig=FAKE_DEPLOY_SECRET');
$tx = $DB->start_delegated_transaction();
try {
    $data = ['format' => 1, 'site_url' => $CFG->wwwroot, 'php_cli' => PHP_BINARY, 'allow_scope_change' => true,
        'portal' => ['enabled' => true, 'library' => ['idnumber' => $token . '_lib', 'name' => 'TEST deploy library', 'create' => true],
            'training' => ['idnumber' => $token . '_training', 'name' => 'TEST deploy training', 'create' => true],
            'audience' => ['idnumber' => $token . '_audience', 'name' => 'TEST deploy audience', 'create' => true],
            'managedcohorts' => [], 'landingsection' => 'library', 'automaticalerts' => true],
        'theme' => ['apply_to_cohort' => true, 'enable_cohort_themes' => true],
        'teams' => ['enabled' => false, 'url_file' => $secretfile, 'types' => ['incidents', 'notifications'], 'actions' => ['created', 'updated']]];
    $count = $DB->count_records('course_categories'); $jobs = $DB->count_records('task_adhoc');
    $plan = config::plan($data, '/tmp');
    deploy_check($DB->count_records('course_categories') === $count, 'Validar no crea categorías ni cambia configuración');
    deploy_check(!str_contains(implode("\n", $plan['lines']), 'FAKE_DEPLOY_SECRET'), 'El plan no imprime la URL ni su firma');
    $bad = $data; $bad['site_url'] = 'https://otro-campus.example.invalid';
    deploy_denied(fn() => config::plan($bad, '/tmp'), 'Rechaza configurar el campus equivocado');
    $bad = $data; $bad['allow_scope_change'] = false;
    deploy_denied(fn() => config::plan($bad, '/tmp'), 'No cambia raíces existentes sin declaración explícita');
    $bad = $data; $bad['portal']['training'] = $bad['portal']['library'];
    deploy_denied(fn() => config::plan($bad, '/tmp'), 'Biblioteca y Formaciones no pueden compartir raíz');
    $bad = $data; $bad['php_cli'] = '/ruta/que/no/existe/php';
    deploy_denied(fn() => config::plan($bad, '/tmp'), 'Rechaza PHP CLI inexistente');
    $bad = $data; $bad['teams']['types'] = ['notifications', 'desconocido'];
    deploy_denied(fn() => config::plan($bad, '/tmp'), 'Rechaza tipos mal escritos sin aplicar cambios');
    $ids = config::apply($data, '/tmp');
    deploy_check($ids['library'] !== $ids['training'] && $DB->count_records('course_categories') === $count + 2,
        'Crea las dos raíces distintas mediante Moodle');
    deploy_check((int)get_config('local_orangeportal', 'audiencecohort') === $ids['audience'] &&
        (int)get_config('local_orangeportal', 'librarycategory') === $ids['library'] &&
        (int)get_config('local_orangeportal', 'coursescategory') === $ids['training'], 'Guarda raíces y cohorte resueltas sin IDs manuales');
    deploy_check($CFG->pathtophp === PHP_BINARY && $DB->get_field('cohort', 'theme', ['id' => $ids['audience']]) === 'orange' &&
        $CFG->theme === $oldcfg->theme, 'Configura PHP y tema de cohorte sin cambiar el tema general');
    deploy_check(get_config('local_orangeportal', 'teamsurl') === trim(file_get_contents($secretfile)) &&
        !get_config('local_orangeportal', 'teams_courses'), 'Configura URL desde archivo privado y los tipos Teams elegidos');
    $again = config::apply($data, '/tmp');
    deploy_check($again === $ids && $DB->count_records('course_categories') === $count + 2, 'Repetir no duplica raíces ni cohorte');
    deploy_check($DB->count_records('task_adhoc') === $jobs, 'Configurar no encola avisos ni ejecuta cron');
    $outside = $DB->get_record('user', ['username' => 'agente.otro'], '*', MUST_EXIST);
    $data['administrators'] = [$outside->username];
    config::apply($data, '/tmp');
    $roleid = $DB->get_field('role', 'id', ['shortname' => 'orange_administrador']);
    deploy_check($DB->record_exists('cohort_members', ['cohortid' => $ids['audience'], 'userid' => $outside->id]) &&
        $DB->record_exists('role_assignments', ['roleid' => $roleid, 'userid' => $outside->id, 'contextid' => context_system::instance()->id]),
        'Asigna administración Orange y audiencia a una cuenta existente sin cambiar contraseña');
    $area = $DB->get_field('role', 'id', ['shortname' => 'orange_admin_area']);
    deploy_check($DB->record_exists('role_assignments', ['roleid' => $area, 'userid' => $outside->id,
        'contextid' => context_coursecat::instance($ids['library'])->id]), 'Sincroniza ámbitos nativos de administración sin pasar por el portal');
    $bad = $data; $bad['administrators'] = [get_admin()->username];
    deploy_denied(fn() => config::plan($bad, '/tmp'), 'No usa un administrador global para simular aislamiento Orange');
    $bad = $data; $bad['catalog_file'] = $secretfile;
    deploy_denied(fn() => config::apply($bad, '/tmp'), 'Catálogo inválido se rechaza antes de cambiar la configuración');
} catch (Throwable $e) {
    echo $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    throw $e;
} finally {
    try { $tx->rollback(new RuntimeException('deployment-test-rollback')); }
    catch (RuntimeException $e) { if ($e->getMessage() !== 'deployment-test-rollback') { throw $e; } }
    foreach (get_object_vars($CFG) as $key => $value) { if (!property_exists($oldcfg, $key)) { unset($CFG->$key); } }
    foreach (get_object_vars($oldcfg) as $key => $value) { $CFG->$key = $value; }
    \core\session\manager::set_user($olduser);
    cache_helper::purge_by_definition('core', 'config');
    cache_helper::purge_by_event('changesincoursecat');
    cache::make('core', 'coursemodinfo')->purge(); course_modinfo::clear_instance_cache();
    unlink($secretfile);
}
echo "Datos y configuración originales restaurados.\n";
