<?php
/** Local-only regression checks. All fixture data and reading changes are rolled back. */
define('CLI_SCRIPT', true);
require __DIR__ . '/../../../config.php';
require_once $CFG->dirroot . '/local/orangeportal/locallib.php';
use local_orangeportal\personal_updates as updates;
use local_orangeportal\library_browser;
use local_orangeportal\service;
if (!preg_match('~^http://(localhost|127\\.0\\.0\\.1):~', $CFG->wwwroot)) { throw new coding_exception('Local demo only.'); }
function updates_check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException($label); }
    echo "PASS $label\n";
}
$agent = $DB->get_record('user', ['username' => 'agente.orange'], '*', MUST_EXIST);
$trainer = $DB->get_record('user', ['username' => 'formador.orange'], '*', MUST_EXIST);
\core\session\manager::set_user($agent);
$tx = $DB->start_delegated_transaction();
try {
    $joined = time() - 1000;
    $DB->set_field('cohort_members', 'timeadded', $joined, [
        'cohortid' => get_config('local_orangeportal', 'audiencecohort'), 'userid' => $agent->id]);
    updates_check(updates::joined() === $joined, 'Fecha de incorporación tomada de la cohorte Orange');
    $before = updates::counts();
    $ids = [];
    foreach ([['incidents', 'normal', 'open', 1, $agent->id], ['incidents', 'critical', 'open', 1, $agent->id],
            ['notifications', 'normal', 'open', 1, $agent->id], ['incidents', 'normal', 'closed', 1, $agent->id],
            ['notifications', 'normal', 'open', 0, $agent->id], ['incidents', 'normal', 'open', 1, $trainer->id]] as $i => $fixture) {
        [$kind, $impact, $state, $published, $recipient] = $fixture;
        $id = $DB->insert_record('local_orange_notice', (object)['kind' => $kind,
            'categoryid' => $DB->get_field('local_orange_category', 'id', ['kind' => $kind], IGNORE_MULTIPLE),
            'title' => 'TEST updates ' . $i, 'content' => '<p>Fixture</p>', 'impact' => $impact, 'state' => $state,
            'published' => $published, 'revision' => 1, 'authorid' => $trainer->id, 'timecreated' => time(), 'timemodified' => time()]);
        $DB->insert_record('local_orange_audience', (object)['noticeid' => $id, 'targettype' => 'user', 'targetid' => $recipient]);
        $ids[] = $id;
    }
    $after = updates::counts();
    updates_check($after['incidents'] === $before['incidents'] + 1 && $after['critical'] === $before['critical'] + 1 &&
        $after['notifications'] === $before['notifications'] + 1, 'Pendientes personales sin duplicar críticas, cerradas, borradores ni destinatarios ajenos');
    $notice = $DB->get_record('local_orange_notice', ['id' => $ids[0]]);
    service::receipt($notice, 'received');
    updates_check(updates::counts()['incidents'] === $after['incidents'], 'Ver el listado no reduce pendientes');
    service::receipt($notice, 'readtime');
    updates_check(updates::counts()['incidents'] === $before['incidents'], 'Abrir incidencia reduce solo su contador y conserva contenido');
    $notification = $DB->get_record('local_orange_notice', ['id' => $ids[2]]);
    service::receipt($notification, 'readtime');
    updates_check(updates::counts()['notifications'] === $before['notifications'] && !service::get_receipt($notification, $agent->id)->confirmed,
        'Comunicado leído baja contador aunque siga pendiente de confirmación');
    $notification->revision++;
    $DB->update_record('local_orange_notice', $notification);
    updates_check(updates::counts()['notifications'] === $after['notifications'], 'Nueva versión del comunicado vuelve a ser pendiente');
    \core\session\manager::set_user($trainer);
    updates_check(!updates::recipient($notice), 'Formador no cuenta vistas de gestión sin ser destinatario');
    \core\session\manager::set_user($agent);
    $courses = orange_native_courses((int)get_config('local_orangeportal', 'coursescategory'));
    $course = reset($courses);
    if (!$course) { throw new coding_exception('Needs an accessible demo training course.'); }
    $DB->delete_records('user_lastaccess', ['userid' => $agent->id, 'courseid' => $course->id]);
    $DB->set_field('course', 'timecreated', $joined - 1, ['id' => $course->id]);
    $oldcount = updates::counts()['courses'];
    $DB->set_field('course', 'timecreated', $joined + 1, ['id' => $course->id]);
    updates_check(updates::counts()['courses'] === $oldcount + 1, 'Solo formaciones creadas después de incorporarse');
    $DB->insert_record('user_lastaccess', (object)['userid' => $agent->id, 'courseid' => $course->id, 'timeaccess' => time()]);
    updates_check(updates::counts()['courses'] === $oldcount, 'Apertura nativa de formación elimina su novedad');
    $docs = library_browser::documents((int)get_config('local_orangeportal', 'librarycategory'));
    $doc = reset($docs);
    if (!$doc) { throw new coding_exception('Needs an accessible demo document.'); }
    $DB->delete_records('local_orange_doc_read', ['userid' => $agent->id, 'cmid' => $doc->id]);
    $DB->delete_records('course_modules_viewed', ['userid' => $agent->id, 'coursemoduleid' => $doc->id]);
    $DB->set_field('course_modules', 'added', $joined - 1, ['id' => $doc->id]);
    $oldcount = updates::counts()['library'];
    $DB->set_field('course_modules', 'added', $joined + 1, ['id' => $doc->id]);
    updates_check(updates::counts()['library'] === $oldcount + 1, 'Solo documentos añadidos después de incorporarse');
    $DB->insert_record('local_orange_doc_read', (object)['userid' => $agent->id, 'cmid' => $doc->id, 'readtime' => time()]);
    updates_check(updates::counts()['library'] === $oldcount, 'Apertura documental elimina su novedad');
    \core\session\manager::set_user($DB->get_record('user', ['username' => 'usuario.externo'], '*', MUST_EXIST));
    updates_check(array_sum(updates::counts()) === 0, 'Usuario externo sin contadores Orange');
} finally {
    try { $tx->rollback(new RuntimeException('updates-test-rollback')); }
    catch (RuntimeException $e) { if ($e->getMessage() !== 'updates-test-rollback') { throw $e; } }
}
echo "Datos temporales restaurados\n";
