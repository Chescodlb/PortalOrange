<?php
/** Local-only, transactional analytics and privacy regression. */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/orangeportal/locallib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
use local_orangeportal\reading_report;
use local_orangeportal\user_report;
use local_orangeportal\service;
if (!preg_match('~^http://(localhost|127\.0\.0\.1):~', $CFG->wwwroot)) { throw new coding_exception('Local demo only'); }
function user_report_check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException($label); }
    echo "PASS $label\n";
}
$trainer = $DB->get_record('user', ['username' => 'formador.orange'], '*', MUST_EXIST);
$agent = $DB->get_record('user', ['username' => 'agente.orange'], '*', MUST_EXIST);
$quality = $DB->get_record('user', ['username' => 'calidad.orange'], '*', MUST_EXIST);
$coordinator = $DB->get_record('user', ['username' => 'coordinador.orange'], '*', MUST_EXIST);
$external = $DB->get_record('user', ['username' => 'usuario.externo'], '*', MUST_EXIST);
$tx = $DB->start_delegated_transaction();
try {
    \core\session\manager::set_user($trainer);
    $now = time(); $since = reading_report::since('month', $now);
    user_report_check(reading_report::since('week', $now) === $now - 7 * DAYSECS &&
        $since === $now - 30 * DAYSECS && reading_report::since('all', $now) === 0, 'Ventanas móviles de 7/30 días e histórico');
    try { reading_report::since('yesterday'); throw new RuntimeException('Invalid period accepted'); }
    catch (invalid_parameter_exception $e) { user_report_check(true, 'Periodo desconocido rechazado'); }
    user_report_check(!str_contains(reading_report::ratio(0, 0), '100') &&
        str_contains(reading_report::ratio(3, 4), '75') && str_contains(reading_report::ratio(3, 4), '3/4'), 'Porcentaje y fracción; sin asignación no inventa porcentaje');
    $category = $DB->get_field('local_orange_category', 'id', ['kind' => 'notifications'], IGNORE_MULTIPLE);
    $ids = [];
    foreach ([40, 15, 3] as $age) {
        $id = service::save_notice((object)['kind' => 'notifications', 'title' => 'Analytics fixture ' . $age,
            'content' => '<p>Analytics</p>', 'categoryid' => $category, 'published' => 1,
            'impact' => $age === 40 ? 'critical' : 'normal', 'state' => 'open'], ['user' => [$agent->id]]);
        $ids[$age] = $id;
        $DB->set_field('local_orange_notice', 'timecreated', $now - $age * DAYSECS, ['id' => $id]);
        $DB->set_field('local_orange_revision', 'timecreated', $now - $age * DAYSECS, ['noticeid' => $id]);
    }
    user_report_check(!reading_report::summary('notifications', 0, 25, $ids[40], $since)['rows'] &&
        count(reading_report::summary('notifications', 0, 25, $ids[15], $since)['rows']) === 1 &&
        !reading_report::summary('notifications', 0, 25, $ids[15], reading_report::since('week', $now))['rows'],
        'Resumen filtra por fecha de versión con límites de semana y mes');
    user_report_check((reading_report::critical_pending($agent->id)[$agent->id]->pending ?? 0) > 0,
        'Crítica antigua sin leer permanece fuera del periodo');
    $notice = $DB->get_record('local_orange_notice', ['id' => $ids[40]], '*', MUST_EXIST);
    $notice->content .= '<p>Actualización</p>';
    service::save_notice($notice, ['user' => [$agent->id]]);
    user_report_check(isset(reading_report::summary('notifications', 0, 25, $ids[40], $since)['rows'][$ids[40]]) &&
        reading_report::data('notifications', $ids[40], $agent->id, 'unread', 0, 25, $since)['total'] === 1,
        'Revisión de contenido vuelve al periodo reciente y requiere nueva lectura');
    $DB->set_field('local_orange_notice', 'timemodified', $now, ['id' => $ids[15]]);
    user_report_check(!reading_report::summary('notifications', 0, 25, $ids[15], reading_report::since('week', $now))['rows'],
        'Cambiar metadatos sin revisión no reinicia el periodo');
    $summary = reading_report::summary('notifications', 0, 25, 0, $since)['rows'];
    $dates = array_map(fn($n) => (int)$n->publicationdate, array_values($summary));
    $sorted = $dates; rsort($sorted);
    user_report_check($dates === $sorted, 'Publicaciones ordenadas por fecha de versión descendente');
    \core\session\manager::set_user($agent);
    service::receipt($DB->get_record('local_orange_notice', ['id' => $ids[3]]), 'readtime');
    \core\session\manager::set_user($trainer);
    $stats = reading_report::user_stats($since, $agent->id)[$agent->id];
    user_report_check((int)$stats->notifications_read === reading_report::data('notifications', 0, $agent->id, 'read', 0, 25, $since)['total'] &&
        (int)$stats->notifications_total === reading_report::data('notifications', 0, $agent->id, '', 0, 25, $since)['total'],
        'Porcentajes de usuario coinciden con su desglose de lecturas');
    $DB->set_field('user', 'idnumber', 'CE-ORANGE-ANALYTICS', ['id' => $agent->id]);
    $centres = [];
    foreach (['Cristalia-Madrid', 'Centro-Valencia'] as $name) {
        $centres[] = cohort_add_cohort((object)['name' => 'TEST ' . $name, 'contextid' => context_system::instance()->id,
            'component' => 'local_orangeportal', 'description' => '', 'descriptionformat' => FORMAT_HTML]);
    }
    cohort_add_member($centres[0], $agent->id);
    $directory = reading_report::directory('CE-ORANGE-ANALYTICS', $centres[0], 'pending', $since);
    user_report_check($directory['total'] === 1 && isset($directory['rows'][$agent->id]), 'Búsqueda por CE y centro por pertenencia a cohorte nativa');
    user_report_check(reading_report::directory('', $centres[1], 'pending', $since)['total'] === 0,
        'Otro centro no incluye usuarios que no pertenecen a su colectivo');
    cohort_add_member($centres[1], $agent->id);
    user_report_check(reading_report::directory('', $centres[0], 'pending', $since)['total'] === 1 &&
        reading_report::directory('', $centres[1], 'pending', $since)['total'] === 1,
        'Un usuario puede pertenecer a varios centros sin duplicarse en cada filtro');
    $directory = reading_report::directory('', 0, 'pending', $since, 0, 2);
    $next = reading_report::directory('', 0, 'pending', $since, 1, 2);
    user_report_check(count($directory['rows']) === 2 && !array_intersect(array_keys($directory['rows']), array_keys($next['rows'])),
        'Usuarios paginados sin duplicados');
    $all = reading_report::directory('', 0, 'pending', $since, 0, 1000)['rows'];
    $pending = array_map(fn($u) => (int)$u->pending, array_values($all)); $sorted = $pending; rsort($sorted);
    user_report_check($pending === $sorted && !isset($all[$external->id]), 'Orden global por pendientes y aislamiento de usuarios Orange');
    $training = user_report::training($agent->id, true);
    user_report_check($training['available'] && $training['total'] > 0, 'Formación puede consultar matrículas activas autorizadas');
    foreach ($training['rows'] as $r) {
        user_report_check(isset(user_report::courses()[$r->course->id]) && is_enrolled(context_course::instance($r->course->id), $agent->id, '', true),
            'Ficha formativa respeta matrícula y ámbito del curso ' . $r->course->id);
    }
    $courseids = array_keys($training['rows']);
    $cid = reset($courseids);
    $completion = $DB->get_record('course_completions', ['course' => $cid, 'userid' => $agent->id]);
    if (!$completion) { $completion = (object)['course' => $cid, 'userid' => $agent->id]; $completion->id = $DB->insert_record('course_completions', $completion); }
    $DB->update_record('course_completions', (object)['id' => $completion->id, 'timestarted' => $now, 'timecompleted' => 0]);
    user_report_check(user_report::training($agent->id)['rows'][$cid]->status === 'reportinprogress', 'Inicio nativo clasifica En curso sin simular asistencia');
    $DB->set_field('course_completions', 'timecompleted', $now, ['id' => $completion->id]);
    user_report_check(user_report::training($agent->id)['rows'][$cid]->status === 'reportfinished', 'Finalización nativa prevalece sobre inicio y nota');
    $item = $DB->get_record('grade_items', ['courseid' => $cid, 'itemtype' => 'course'], '*', MUST_EXIST);
    $grade = $DB->get_record('grade_grades', ['itemid' => $item->id, 'userid' => $agent->id]);
    if (!$grade) { $grade = (object)['itemid' => $item->id, 'userid' => $agent->id]; $grade->id = $DB->insert_record('grade_grades', $grade); }
    $DB->set_field('grade_items', 'hidden', 0, ['id' => $item->id]);
    $DB->update_record('grade_grades', (object)['id' => $grade->id, 'hidden' => 0, 'finalgrade' => 8]);
    user_report_check((float)user_report::training($agent->id, true)['rows'][$cid]->grade->finalgrade === 8.0,
        'Nota total visible se reutiliza de Moodle');
    $DB->set_field('grade_grades', 'hidden', 1, ['id' => $grade->id]);
    user_report_check(user_report::training($agent->id, true)['rows'][$cid]->grade === null, 'Nota oculta no se revela en la ficha');
    user_report_check(!array_intersect(array_keys(user_report::courses()), array_keys(orange_native_courses((int)get_config('local_orangeportal', 'librarycategory')))),
        'La biblioteca queda excluida del denominador de formación');
    \core\session\manager::set_user($quality);
    $qualitytraining = user_report::training($agent->id, true);
    user_report_check(!$qualitytraining['available'] && !$qualitytraining['rows'], 'Calidad no hereda notas, matrículas ni intentos de otros perfiles');
    user_report_check(reading_report::directory('', 0, 'pending', $since)['total'] > 0,
        'Calidad conserva seguimiento de lecturas');
    \core\session\manager::set_user($coordinator);
    foreach (user_report::courses() as $c) {
        user_report_check(has_capability('moodle/course:viewparticipants', context_course::instance($c->id)) &&
            has_capability('report/completion:view', context_course::instance($c->id)), 'Coordinación solo recibe sus cursos autorizados ' . $c->id);
    }
    try { user_report::training($external->id, true); throw new RuntimeException('External user accepted'); }
    catch (moodle_exception $e) { user_report_check($e->errorcode === 'outsideorange', 'Ficha ajena a Orange rechazada en servidor'); }
    \core\session\manager::set_user($agent);
    try { user_report::training($agent->id); throw new RuntimeException('Agent report accepted'); }
    catch (required_capability_exception $e) { user_report_check(true, 'Agente no obtiene seguimiento ajeno ni autoeleva permisos'); }
} finally {
    try { $tx->rollback(new RuntimeException('analytics-rollback')); }
    catch (RuntimeException $e) { if ($e->getMessage() !== 'analytics-rollback') { throw $e; } }
}
echo "Datos temporales restaurados\n";
