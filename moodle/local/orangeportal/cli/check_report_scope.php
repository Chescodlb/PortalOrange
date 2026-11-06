<?php
/** Local-only transactional regression: scopes, dates and independent edition attendance. */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/orangeportal/locallib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
use local_orangeportal\report_scope as scope;
use local_orangeportal\training_report as training;
use local_orangeportal\reading_report as reading;
use local_orangeportal\report_period as period;
use local_orangeportal\service;
if (!preg_match('~^http://(localhost|127\.0\.0\.1):~', $CFG->wwwroot)) { throw new coding_exception('Local only'); }
function scope_check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException($label); }
    echo "PASS $label\n";
}
function denied(callable $fn, string $label): void {
    try { $fn(); } catch (moodle_exception $e) { scope_check(true, $label); return; }
    throw new RuntimeException('Unexpected access: ' . $label);
}
$trainer = $DB->get_record('user', ['username' => 'formador.orange'], '*', MUST_EXIST);
$coordinator = $DB->get_record('user', ['username' => 'coordinador.orange'], '*', MUST_EXIST);
$quality = $DB->get_record('user', ['username' => 'calidad.orange'], '*', MUST_EXIST);
$tx = $DB->start_delegated_transaction();
try {
    \core\session\manager::set_user($DB->get_record('user', ['username' => 'admin.orange'], '*', MUST_EXIST));
    $_GET['period'] = 'all';
    $base = (int)get_config('local_orangeportal', 'audiencecohort');
    $cohorts = []; $students = [];
    foreach (['a', 'b', 'c'] as $key) {
        $cohorts[$key] = cohort_add_cohort((object)['name' => 'TEST scope ' . $key, 'contextid' => context_system::instance()->id,
            'component' => 'local_orangeportal', 'description' => '', 'descriptionformat' => FORMAT_HTML]);
        $uid = user_create_user((object)['username' => 'test-scope-' . $key . '-' . strtolower(random_string(8)), 'auth' => 'manual',
            'firstname' => 'TEST', 'lastname' => 'Scope ' . $key, 'email' => $key . '@example.invalid', 'confirmed' => 1,
            'password' => 'Aa1!' . random_string(30)], true, false);
        $students[$key] = $DB->get_record('user', ['id' => $uid]);
        cohort_add_member($base, $uid); cohort_add_member($cohorts[$key], $uid);
    }
    foreach ([$trainer, $coordinator, $quality] as $staff) {
        cohort_add_member($cohorts['a'], $staff->id);
    }
    cohort_add_member($cohorts['b'], $coordinator->id);
    // A learner in two collectives must still be counted once.
    cohort_add_member($cohorts['b'], $students['a']->id);
    $editions = [];
    $manual = enrol_get_plugin('manual');
    $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
    foreach ([0, 60] as $age) {
        $c = create_course((object)['category' => (int)get_config('local_orangeportal', 'coursescategory'),
            'fullname' => 'TEST edition ' . $age, 'shortname' => 'TEST-' . random_string(12),
            'startdate' => time() - $age * DAYSECS, 'enablecompletion' => 1]);
        $enrol = $DB->get_record('enrol', ['courseid' => $c->id, 'enrol' => 'manual']);
        if (!$enrol) { $enrol = $DB->get_record('enrol', ['id' => $manual->add_instance($c)], '*', MUST_EXIST); }
        foreach ($students as $u) { $manual->enrol_user($enrol, $u->id, $studentrole); }
        $manual->enrol_user($enrol, $trainer->id, $DB->get_field('role', 'id', ['shortname' => 'editingteacher']));
        role_assign($DB->get_field('role', 'id', ['shortname' => 'orange_consulta_formativa']), $coordinator->id, context_course::instance($c->id)->id);
        $editions[$age] = $c;
    }
    $category = $DB->get_field('local_orange_category', 'id', ['kind' => 'notifications'], IGNORE_MULTIPLE);
    $notices = [];
    foreach (['a', 'b', 'c'] as $key) {
        $id = service::save_notice((object)['kind' => 'notifications', 'title' => 'TEST scope ' . $key, 'content' => '<p>TEST</p>',
            'categoryid' => $category, 'published' => 1, 'impact' => 'critical', 'state' => 'open'], ['user' => [$students[$key]->id]]);
        $notices[$key] = $id;
    }
    \core\session\manager::set_user($coordinator);
    scope_check(!scope::global_access(), 'Coordinación no recibe seguimiento global por su nombre de rol');
    scope_check(isset(scope::users()[$students['a']->id], scope::users()[$students['b']->id]) &&
        !isset(scope::users()[$students['c']->id]), 'Todos mis colectivos es la unión de mis grupos');
    $directory = reading::directory('', 0, 'pending', 0, 0, 0);
    scope_check(isset($directory['rows'][$students['a']->id]) && $directory['rows'][$students['a']->id]->notifications_total == 1,
        'Pertenecer a varios grupos no duplica usuarios ni asignaciones');
    scope_check(!isset(reading::summary('notifications')['rows'][$notices['c']]), 'Resumen no revela publicaciones de colectivos ajenos');
    denied(fn() => scope::require_user($students['c']->id), 'URL de usuario fuera del ámbito rechazada');
    denied(fn() => reading::data('notifications', $notices['c']), 'Detalle y exportación de publicación ajena rechazados');
    $_GET['report_cohort'] = $cohorts['a'];
    scope_check(isset(scope::users()[$students['a']->id]) && !isset(scope::users()[$students['b']->id]), 'Filtro reduce a un colectivo concreto');
    $_GET['report_cohort'] = $cohorts['c'];
    denied(fn() => scope::users(), 'Manipular el ID del colectivo no amplía permisos');
    unset($_GET['report_cohort']);
    denied(fn() => training::save_attendance($editions[0]->id, $students['a']->id, 'present', 60), 'Coordinación consulta y no modifica asistencia');
    scope_check(!training::evaluations($editions[0]), 'Una edición sin cuestionarios no inventa resultados');
    \core\session\manager::set_user($trainer);
    training::save_attendance($editions[0]->id, $students['a']->id, 'present', 90);
    $a = $DB->get_record('local_orange_attendance', ['courseid' => $editions[0]->id, 'userid' => $students['a']->id]);
    scope_check($a->status === 'present' && $a->minutes == 90 && str_contains($a->collectives, 'TEST scope a'), 'Formación registra asistencia, minutos y colectivo de referencia');
    training::save_attendance($editions[60]->id, $students['a']->id, 'absent', 90);
    scope_check($DB->get_field('local_orange_attendance', 'minutes', ['courseid' => $editions[60]->id, 'userid' => $students['a']->id]) === null,
        'Ausencia no genera horas asistidas');
    scope_check($DB->get_field('local_orange_attendance', 'status', ['id' => $a->id]) === 'present', 'La asistencia de una edición no modifica otra');
    scope_check(!$DB->record_exists('course_completions', ['course' => $editions[0]->id, 'userid' => $students['a']->id]),
        'Registrar asistencia no inventa finalización ni aprobado');
    denied(fn() => training::save_attendance($editions[0]->id, $students['c']->id, 'present', 60), 'Formación no registra usuarios de otro colectivo');
    denied(fn() => training::save_attendance($editions[0]->id, $students['a']->id, 'invented', 60), 'Estado de asistencia inválido rechazado');
    denied(fn() => training::save_attendance($editions[0]->id, $students['a']->id, 'present', -1), 'Minutos negativos rechazados');
    $_GET['period'] = 'week';
    scope_check(isset(training::courses()[$editions[0]->id]) && !isset(training::courses()[$editions[60]->id]), 'Formación filtra por fecha de inicio de edición');
    $_GET['period'] = 'custom'; $_GET['report_from'] = '2026-01-01'; $_GET['report_to'] = '2026-01-31';
    $lower = period::date('report_from'); $upper = period::date('report_to');
    scope_check(period::contains($lower) && period::contains($upper - 1) && !period::contains($upper), 'Rango incluye completos el primer y último día');
    $DB->set_field('local_orange_revision', 'timecreated', $upper - 1, ['noticeid' => $notices['a']]);
    $DB->set_field('local_orange_revision', 'timecreated', $upper, ['noticeid' => $notices['b']]);
    $d = reading::directory('', 0, 'pending', reading::since('custom'), 0, 0)['rows'];
    scope_check($d[$students['a']->id]->notifications_total == 1, 'Directorio respeta ambos extremos del rango');
    scope_check(reading::data('notifications', $notices['a'], 0, '', 0, 0, $lower)['total'] === 1,
        'Detalle y CSV comparten rango de fechas');
    scope_check(isset(reading::critical_pending()[$students['a']->id]), 'Críticas pendientes conservan todo el histórico');
    $_GET['report_from'] = '2026-02-31';
    denied(fn() => reading::since('custom'), 'Fecha inexistente rechazada');
    $_GET['period'] = 'all'; unset($_GET['report_from'], $_GET['report_to']);
    \core\session\manager::set_user($quality);
    scope_check(!training::evaluations($editions[0]) && !local_orangeportal\user_report::training($students['a']->id)['available'],
        'Calidad conserva lecturas sin recibir notas o cursos');
    \core\session\manager::set_user($DB->get_record('user', ['username' => 'admin.orange'], '*', MUST_EXIST));
    // Native survey records exercise aggregation without importing historical spreadsheet identities.
    $feedback = $DB->get_record('feedback', [], '*', IGNORE_MULTIPLE);
    if (!$feedback) { throw new RuntimeException('Demo feedback required'); }
    unset($feedback->id); $feedback->course = $editions[0]->id; $feedback->anonymous = 2; $feedback->name = 'TEST survey';
    $feedback->id = $DB->insert_record('feedback', $feedback);
    $cmid = add_course_module((object)['course' => $editions[0]->id, 'module' => $DB->get_field('modules', 'id', ['name' => 'feedback']),
        'instance' => $feedback->id, 'visible' => 1, 'visibleoncoursepage' => 1, 'added' => time()]);
    course_add_cm_to_section($editions[0]->id, $cmid, 0);
    $itemid = $DB->insert_record('feedback_item', (object)['feedback' => $feedback->id, 'template' => 0, 'name' => 'TEST score',
        'label' => '', 'presentation' => '1|10', 'typ' => 'numeric', 'hasvalue' => 1, 'position' => 1, 'required' => 0]);
    foreach (['a' => 8, 'c' => 2] as $key => $score) {
        $completed = $DB->insert_record('feedback_completed', (object)['feedback' => $feedback->id, 'userid' => $students[$key]->id,
            'timemodified' => time(), 'random_response' => 0, 'anonymous_response' => 2]);
        $DB->insert_record('feedback_value', (object)['course_id' => $editions[0]->id, 'item' => $itemid,
            'completed' => $completed, 'tmp_completed' => 0, 'value' => (string)$score]);
    }
    rebuild_course_cache($editions[0]->id, true);
    \core\session\manager::set_user($trainer);
    $survey = training::satisfaction($editions[0])[0];
    scope_check($survey['responses'] === 1 && $survey['questions'][0]['mean'] == 8 && !$survey['restricted'],
        'Encuesta identificada agrega solo respuestas del colectivo autorizado');
    $DB->set_field('feedback', 'anonymous', 1, ['id' => $feedback->id]);
    scope_check(training::satisfaction($editions[0])[0]['restricted'], 'Encuesta anónima no atribuye respuestas a colectivos parciales');
    \core\session\manager::set_user($DB->get_record('user', ['username' => 'admin.orange'], '*', MUST_EXIST));
    $survey = training::satisfaction($editions[0])[0];
    scope_check(!$survey['restricted'] && $survey['responses'] === 2 && $survey['questions'][0]['mean'] == 5,
        'Encuesta anónima muestra solo agregado de la edición completa');
    require_once($CFG->dirroot . '/question/engine/lib.php');
    $quiz = $DB->get_record('quiz', [], '*', IGNORE_MULTIPLE);
    if (!$quiz) { throw new RuntimeException('Demo quiz required'); }
    unset($quiz->id); $quiz->course = $editions[0]->id; $quiz->name = 'TEST assessment'; $quiz->grade = 10; $quiz->sumgrades = 10;
    $quiz->id = $DB->insert_record('quiz', $quiz);
    $quizcmid = add_course_module((object)['course' => $editions[0]->id, 'module' => $DB->get_field('modules', 'id', ['name' => 'quiz']),
        'instance' => $quiz->id, 'visible' => 1, 'visibleoncoursepage' => 1, 'added' => time()]);
    course_add_cm_to_section($editions[0]->id, $quizcmid, 0);
    $DB->insert_record('grade_items', (object)['courseid' => $editions[0]->id, 'itemtype' => 'mod', 'itemmodule' => 'quiz',
        'iteminstance' => $quiz->id, 'itemnumber' => 0, 'gradepass' => 7, 'grademax' => 10]);
    foreach ([['a', 0, 8], ['c', 0, 2], ['a', 1, 10]] as [$key, $preview, $score]) {
        $usage = question_engine::make_questions_usage_by_activity('mod_quiz', context_module::instance($quizcmid));
        $usage->set_preferred_behaviour('deferredfeedback'); question_engine::save_questions_usage_by_activity($usage);
        $DB->insert_record('quiz_attempts', (object)['quiz' => $quiz->id, 'userid' => $students[$key]->id, 'attempt' => $preview ? 2 : 1,
            'uniqueid' => $usage->get_id(), 'layout' => '0', 'currentpage' => 0, 'preview' => $preview, 'state' => 'finished',
            'timestart' => time() - 600, 'timefinish' => time(), 'timemodified' => time(), 'sumgrades' => $score]);
        if (!$preview) { $DB->insert_record('quiz_grades', (object)['quiz' => $quiz->id, 'userid' => $students[$key]->id, 'grade' => $score, 'timemodified' => time()]); }
    }
    rebuild_course_cache($editions[0]->id, true);
    \core\session\manager::set_user($trainer);
    $evaluation = training::evaluations($editions[0])[0];
    scope_check(count($evaluation['attempts']) === 1 && $evaluation['attempts'][0]->grade == 8 && $evaluation['gradepass'] == 7,
        'Evaluación usa escala y aprobado nativos y excluye previews y colectivos ajenos');
    scope_check(count($evaluation['grades']) === 1 && $evaluation['grades'][$students['a']->id]->grade == 8,
        'Nota final nativa respeta el mismo ámbito de usuarios');
    \core\session\manager::set_user($quality);
    scope_check(!training::evaluations($editions[0]) && !training::satisfaction($editions[0]), 'Calidad no hereda evaluaciones o encuestas por ver lecturas');
    \core\session\manager::set_user($DB->get_record('user', ['username' => 'admin.orange'], '*', MUST_EXIST));
    scope_check(isset(scope::users()[$students['c']->id]), 'Administración general conserva el seguimiento Orange completo');
    scope_check(count(array_intersect_key(training::courses(), array_flip(array_column($editions, 'id')))) === 2,
        'Todo el histórico mantiene las dos ediciones accesibles');
} finally {
    try { $tx->rollback(new RuntimeException('scope-rollback')); }
    catch (RuntimeException $e) { if ($e->getMessage() !== 'scope-rollback') { throw $e; } }
}
echo "Datos temporales restaurados\n";
