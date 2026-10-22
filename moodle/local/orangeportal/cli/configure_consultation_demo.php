<?php
/** Local-only native role configuration. Production uses the documented Moodle UI steps. */
if (!defined('CLI_SCRIPT')) { define('CLI_SCRIPT', true); }
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
if (!preg_match('~^http://(localhost|127\.0\.0\.1):~', $CFG->wwwroot)) {
    throw new coding_exception('Local demo only. Use Moodle role configuration on the existing campus.');
}
\core\session\manager::set_user(get_admin());
$coordinator = $DB->get_record('user', ['username' => 'coordinador.orange'], '*', MUST_EXIST);
$cohortid = (int)get_config('local_orangeportal', 'audiencecohort');
if (!$cohortid || !get_config('local_orangeportal', 'enabled')) { throw new coding_exception('Configure the Orange audience first.'); }
$quality = $DB->get_record('user', ['username' => 'calidad.orange']);
$password = getenv('ORANGE_DEMO_PASSWORD');
if (!$quality && !$password) { throw new coding_exception('ORANGE_DEMO_PASSWORD is required for the new demo account.'); }
if ($quality && !\local_orangeportal\experience::member((int)$quality->id)) {
    throw new coding_exception('Existing quality account is outside Orange; no changes made.');
}
$backupdir = $CFG->dataroot . '/orange-role-backups';
if (!is_dir($backupdir) && !mkdir($backupdir, 0700, true)) { throw new coding_exception('Cannot create role backup.'); }
$backupfile = $backupdir . '/consultation-' . date('Ymd-His') . '.json';
$backup = ['roles' => $DB->get_records('role'), 'capabilities' => $DB->get_records('role_capabilities'),
    'assignments' => $DB->get_records('role_assignments')];
if (file_put_contents($backupfile, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
    throw new coding_exception('Cannot save role backup.');
}
chmod($backupfile, 0600);
$transaction = $DB->start_delegated_transaction();
$systemid = context_system::instance()->id;
$definitions = [
    'orange_seguimiento' => ['Coordinación Orange', [CONTEXT_SYSTEM],
        ['local/orangeportal:viewreports', 'local/orangeportal:viewallincidents']],
    'orange_calidad' => ['Calidad Orange', [CONTEXT_SYSTEM],
        ['local/orangeportal:viewreports', 'local/orangeportal:viewallincidents']],
    'orange_consulta_formativa' => ['Consulta formativa Orange', [CONTEXT_COURSECAT, CONTEXT_COURSE], [
        'moodle/course:view', 'moodle/course:viewparticipants', 'moodle/site:accessallgroups', 'moodle/grade:viewall',
        'gradereport/grader:view', 'gradereport/user:view', 'mod/quiz:view', 'mod/quiz:viewreports',
        'mod/feedback:view', 'mod/feedback:viewreports', 'report/completion:view', 'report/progress:view',
    ]],
];
$roleids = [];
foreach ($definitions as $shortname => [$name, $levels, $caps]) {
    $id = $DB->get_field('role', 'id', ['shortname' => $shortname]);
    if (!$id) { $id = create_role($name, $shortname, 'Consulta Orange; sin edición ni exportación por defecto.'); }
    $roleids[$shortname] = (int)$id;
    set_role_contextlevels($id, $levels);
    // Reset only these dedicated demo roles, not shared native teacher/student definitions.
    foreach ($DB->get_records('role_capabilities', ['roleid' => $id]) as $record) {
        unassign_capability($record->capability, $id, $record->contextid);
    }
    foreach ($caps as $cap) {
        if (!$DB->record_exists('capabilities', ['name' => $cap])) { throw new coding_exception('Unknown capability: ' . $cap); }
        assign_capability($cap, CAP_ALLOW, $id, $systemid);
    }
}
if (!$quality) {
    $id = user_create_user((object)['username' => 'calidad.orange', 'password' => $password, 'auth' => 'manual',
        'firstname' => 'Calidad', 'lastname' => 'Orange', 'email' => 'calidad.orange@localhost.invalid',
        'confirmed' => 1, 'mnethostid' => $CFG->mnet_localhost_id, 'lang' => 'es']);
    $quality = $DB->get_record('user', ['id' => $id], '*', MUST_EXIST);
}
cohort_add_member($cohortid, $quality->id);
role_assign($roleids['orange_calidad'], $quality->id, $systemid);
role_assign($roleids['orange_seguimiento'], $coordinator->id, $systemid);
$teacher = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
$courses = \local_orangeportal\orange_scope::course_ids();
foreach ($DB->get_records('role_assignments', ['userid' => $coordinator->id, 'roleid' => $teacher]) as $assignment) {
    $context = context::instance_by_id($assignment->contextid);
    if ($context->contextlevel !== CONTEXT_COURSE || !isset($courses[$context->instanceid]) ||
            $assignment->component !== '' || $assignment->itemid) { continue; }
    role_unassign($teacher, $coordinator->id, $context->id);
    role_assign($roleids['orange_consulta_formativa'], $coordinator->id, $context->id);
}
// Quality consumes demo content as a student, but receives no grade/report role in courses.
if ($demo = $DB->get_field('cohort', 'id', ['idnumber' => 'ORANGE-EQUIPO-DEMO'])) { cohort_add_member($demo, $quality->id); }
$transaction->allow_commit();
purge_all_caches();
echo "Coordinación: consulta de lecturas y notas en sus cursos, sin escritura ni exportación.\n";
echo "Calidad: seguimiento de lecturas e incidencias, sin notas ajenas ni exportación.\n";
echo "Cuenta local calidad.orange: ORANGE_DEMO_PASSWORD de .env.\n";
echo "Respaldo de roles: " . $backupfile . "\n";
