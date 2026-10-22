<?php
/** Local demo only. Does not grant site administrator access or alter other demo users. */
define('CLI_SCRIPT', true);
require __DIR__ . '/../../../config.php';
require_once $CFG->dirroot . '/user/lib.php';
require_once $CFG->dirroot . '/cohort/lib.php';
if (!preg_match('~^http://(localhost|127\\.0\\.0\\.1):~', $CFG->wwwroot)) { throw new coding_exception('Local demo only.'); }
\core\session\manager::set_user(get_admin());
$password = getenv('ORANGE_DEMO_PASSWORD');
if (!$password) { throw new coding_exception('ORANGE_DEMO_PASSWORD is required.'); }
\local_orangeportal\orange_administration::install_roles();
$role = $DB->get_field('role', 'id', ['shortname' => 'orange_administrador'], MUST_EXIST);
$user = $DB->get_record('user', ['username' => 'admin.orange', 'mnethostid' => $CFG->mnet_localhost_id]);
if (!$user) {
    $id = user_create_user((object)['username' => 'admin.orange', 'password' => $password, 'auth' => 'manual',
        'firstname' => 'Administración', 'lastname' => 'Orange', 'email' => 'admin.orange@localhost.invalid',
        'confirmed' => 1, 'mnethostid' => $CFG->mnet_localhost_id, 'lang' => 'es']);
    $marker = $DB->get_field('role', 'id', ['shortname' => 'orange_cuenta'], MUST_EXIST);
    role_assign($marker, $id, context_user::instance($id)->id, 'local_orangeportal');
    $user = $DB->get_record('user', ['id' => $id], '*', MUST_EXIST);
} else if (is_siteadmin($user->id) || !\local_orangeportal\orange_administration::owned_account($user->id)) {
    throw new coding_exception('Existing account is not owned by this demo; no changes made.');
}
cohort_add_member((int)get_config('local_orangeportal', 'audiencecohort'), $user->id);
role_assign($role, $user->id, context_system::instance()->id);
\local_orangeportal\orange_administration::sync_areas($user->id);
// Explicitly delegate the previously configured native demo audience, without claiming arbitrary cohorts.
$demo = $DB->get_field('cohort', 'id', ['idnumber' => 'ORANGE-EQUIPO-DEMO']);
$existing = array_filter(array_map('intval', explode(',', get_config('local_orangeportal', 'managedcohorts') ?: '')));
if ($demo) { $existing[] = (int)$demo; }
set_config('managedcohorts', implode(',', array_unique($existing)), 'local_orangeportal');
echo "Preparado admin.orange con ORANGE_DEMO_PASSWORD. Sin administración general de Moodle.\n";
