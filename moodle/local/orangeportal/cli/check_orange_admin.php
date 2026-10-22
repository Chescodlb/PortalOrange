<?php
/** Reversible boundary tests for Orange administration. Local demo only. */
define('CLI_SCRIPT', true);
require __DIR__ . '/../../../config.php';
require_once $CFG->dirroot . '/local/orangeportal/locallib.php';
require_once $CFG->dirroot . '/cohort/lib.php';
use local_orangeportal\orange_administration as admin;
use local_orangeportal\orange_scope as scope;
use local_orangeportal\service;
if (!preg_match('~^http://(localhost|127\\.0\\.0\\.1):~', $CFG->wwwroot)) { throw new coding_exception('Local demo only.'); }
function admin_check(bool $ok, string $label): void { if (!$ok) { throw new RuntimeException($label); } echo "PASS $label\n"; }
function admin_denied(callable $fn, string $label): void {
    try { $fn(); } catch (moodle_exception $e) { admin_check(true, $label); return; }
    throw new RuntimeException($label);
}
$actor = $DB->get_record('user', ['username' => 'admin.orange'], '*', MUST_EXIST);
$trainer = $DB->get_record('user', ['username' => 'formador.orange'], '*', MUST_EXIST);
$external = $DB->get_record('user', ['username' => 'usuario.externo'], '*', MUST_EXIST);
\core\session\manager::set_user($actor);
$tx = $DB->start_delegated_transaction();
try {
    admin::require_access();
    admin_check(!is_siteadmin() && !has_capability('moodle/site:config', context_system::instance()) &&
        !has_capability('moodle/user:create', context_system::instance()) && !has_capability('moodle/user:update', context_system::instance()),
        'Administrador Orange sin configuración del sitio ni gestión global de cuentas');
    $allowed = scope::course_ids();
    admin_check(has_capability('moodle/course:manageactivities', context_course::instance((int)array_key_first($allowed))), 'Gestión de contenidos dentro de Orange');
    $outside = $DB->get_records_select('course_categories', 'id <> ? AND id <> ?',
        [(int)get_config('local_orangeportal', 'librarycategory'), (int)get_config('local_orangeportal', 'coursescategory')]);
    foreach ($outside as $cat) {
        if (array_intersect(array_map('intval', explode('/', $cat->path)), [(int)get_config('local_orangeportal','librarycategory'),(int)get_config('local_orangeportal','coursescategory')])) { continue; }
        admin_check(!has_capability('moodle/category:manage', context_coursecat::instance($cat->id)), 'Sin gestión de categorías ajenas'); break;
    }
    admin_denied(fn() => scope::user($external->id), 'No accede al usuario externo manipulando ID');
    admin_denied(fn() => admin::update_user($trainer->id, (object)['firstname'=>'Alterado','lastname'=>'Alterado','email'=>'other@localhost.invalid']), 'No edita datos globales de cuenta compartida');
    admin_denied(fn() => admin::remove_access($actor->id), 'No puede retirarse ni modificar administradores');
    $data = (object)['username'=>'org_test_' . bin2hex(random_bytes(5)), 'firstname'=>'Prueba', 'lastname'=>'Orange',
        'email'=>bin2hex(random_bytes(5)) . '@localhost.invalid','password'=>'Testing!7aB' . bin2hex(random_bytes(8))];
    $id = admin::create_user($data);
    admin_check(isset(scope::users()[$id]) && admin::owned_account($id) && get_user_preferences('auth_forcepasswordchange', 0, $id),
        'Alta nativa asigna Orange, marca cuenta propia y obliga cambio de contraseña');
    $cohort = admin::save_cohort(0, 'TEST Orange collective');
    admin::set_cohorts($id, [$cohort]);
    admin_check($DB->record_exists('cohort_members', ['cohortid'=>$cohort,'userid'=>$id]), 'Asignación nativa de colectivos Orange');
    admin_denied(fn() => admin::delete_cohort($cohort), 'No elimina colectivos con miembros');
    $foreign = cohort_add_cohort((object)['name'=>'TEST other project','contextid'=>context_system::instance()->id]);
    admin_denied(fn() => admin::set_cohorts($id, [$foreign]), 'No asigna colectivos de otros proyectos');
    admin_denied(fn() => admin::save_cohort($foreign, 'Alterado'), 'No edita colectivos ajenos por ID');
    admin_denied(fn() => admin::delete_cohort($foreign), 'No elimina colectivos ajenos por ID');
    $courseid = (int)array_key_first($allowed);
    admin::enrol_cohort($cohort, $courseid);
    admin_check(is_enrolled(context_course::instance($courseid), $id, '', true), 'Cohorte matricula con API nativa en formación o colección Orange');
    admin_denied(fn() => admin::enrol_cohort($cohort, SITEID), 'No matricula en cursos ajenos');
    admin::update_user($id, (object)['firstname'=>'Actualizado','lastname'=>'Orange','email'=>$data->email]);
    admin_check($DB->get_field('user','firstname',['id'=>$id])==='Actualizado','Edita cuenta exclusiva creada desde Orange');
    cohort_add_member($foreign, $id);
    admin_denied(fn() => admin::update_user($id, (object)['firstname'=>'Compartido','lastname'=>'Orange','email'=>$data->email]), 'Cuenta reutilizada en otro proyecto protege sus datos globales');
    cohort_remove_member($foreign,$id);
    $notice = (object)['kind'=>'notifications','title'=>'TEST scope','content'=>'<p>Test</p>',
        'categoryid'=>$DB->get_field('local_orange_category','id',['kind'=>'notifications'],IGNORE_MULTIPLE),
        'impact'=>'normal','state'=>'open','published'=>1];
    admin_denied(fn()=>service::save_notice($notice,['user'=>[$external->id]]),'Publicación rechaza destinatario externo incluso por backend');
    admin::remove_access($id);
    admin_check(!\local_orangeportal\experience::member($id) && !$DB->get_field('user','deleted',['id'=>$id]) &&
        !$DB->record_exists('cohort_members',['cohortid'=>$cohort,'userid'=>$id]),'Retirada conserva la cuenta y elimina Orange y sus colectivos');
    $empty = admin::save_cohort(0,'TEST empty Orange'); admin::delete_cohort($empty);
    admin_check(!$DB->record_exists('cohort',['id'=>$empty]),'Elimina colectivo vacío con API nativa');
    \core\session\manager::set_user($trainer);
    admin_denied(fn()=>admin::require_access(),'Formador no recibe administración de usuarios ni colectivos');
    \core\session\manager::set_user($external);
    admin_denied(fn()=>admin::require_access(),'Usuario externo sin acceso a administración Orange');
} finally {
    try { $tx->rollback(new RuntimeException('orange-admin-test-rollback')); }
    catch (RuntimeException $e) { if ($e->getMessage()!=='orange-admin-test-rollback') { throw $e; } }
}
echo "Datos temporales restaurados\n";
