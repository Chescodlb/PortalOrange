<?php
/** Verify native quiz grading and portal alerts using a rolled-back attempt. */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->libdir . '/gradelib.php');
if (!str_starts_with($CFG->wwwroot, 'http://localhost:')) { cli_error('Prueba limitada a local.'); }
$admin = get_admin();
$agent = $DB->get_record('user', ['username' => 'agente.orange'], '*', MUST_EXIST);
$course = $DB->get_record('course', ['shortname' => 'ORANGE-FORMACION-INICIAL'], '*', MUST_EXIST);
$quiz = $DB->get_record('quiz', ['course' => $course->id], '*', MUST_EXIST);
$tx = $DB->start_delegated_transaction();
function orange_training_assert(bool $pass, string $label): void {
    if (!$pass) { throw new RuntimeException($label); }
    echo "PASS $label\n";
}
try {
    \core\session\manager::set_user($agent);
    $quizobj = \mod_quiz\quiz_settings::create($quiz->id, $agent->id);
    $attempts = quiz_get_user_attempts($quiz->id, $agent->id, 'all');
    $number = count($attempts) + 1;
    $attempt = quiz_prepare_and_start_new_attempt($quizobj, $number, false, false, [], [], $agent->id);
    $attemptobj = \mod_quiz\quiz_attempt::create($attempt->id);
    $attemptobj->process_submitted_actions(time(), false, [1 => ['answer' => 1]]);
    $attemptobj->process_finish(time(), false);
    $grades = grade_get_grades($course->id, 'mod', 'quiz', $quiz->id, $agent->id);
    $grade = $grades->items[0]->grades[$agent->id]->grade ?? null;
    orange_training_assert((float)$grade === 100.0, 'Cuestionario nativo corrige y registra 100/100');
    $cm = get_coursemodule_from_instance('quiz', $quiz->id);
    $completion = $DB->get_record('course_modules_completion', ['coursemoduleid' => $cm->id, 'userid' => $agent->id]);
    orange_training_assert($completion && $completion->completionstate == COMPLETION_COMPLETE_PASS,
        'Aprobado actualiza finalización nativa');
    // Moodle buffers events until the outer transaction commits. Call the same observer
    // with a real grade event to check it without persisting this test attempt.
    $item = grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'mod', 'itemmodule' => 'quiz', 'iteminstance' => $quiz->id]);
    $gg = grade_grade::fetch(['itemid' => $item->id, 'userid' => $agent->id]); $gg->load_grade_item();
    $event = \core\event\user_graded::create_from_grade($gg);
    $before = $DB->count_records('local_orange_notice', ['kind' => 'notifications']);
    \local_orangeportal\observer::user_graded($event);
    orange_training_assert($DB->count_records('local_orange_notice', ['kind' => 'notifications']) === $before + 1,
        'Calificación visible genera una alerta individual');
    $notice = $DB->get_record_sql('SELECT * FROM {local_orange_notice} WHERE kind = ? ORDER BY id DESC LIMIT 1', ['notifications']);
    orange_training_assert(\local_orangeportal\service::assigned((int)$notice->id, (int)$agent->id), 'Alerta asignada al alumno');
    $outsider = $DB->get_record('user', ['username' => 'agente.otro'], '*', MUST_EXIST);
    orange_training_assert(!\local_orangeportal\service::assigned((int)$notice->id, (int)$outsider->id), 'Alerta no visible a otro alumno');
    $item->hidden = 1;
    $gg->grade_item = $item;
    \local_orangeportal\observer::user_graded(\core\event\user_graded::create_from_grade($gg));
    orange_training_assert($DB->count_records('local_orange_notice', ['kind' => 'notifications']) === $before + 1,
        'Calificación oculta no genera aviso');
    $feedback = $DB->get_record('feedback', ['course' => $course->id], '*', MUST_EXIST);
    orange_training_assert($DB->record_exists('feedback_item', ['feedback' => $feedback->id, 'required' => 1]) && !$feedback->publish_stats,
        'Encuesta nativa preparada sin publicar resultados al alumno');
    echo "Todas las comprobaciones de formación han pasado; se revierte el intento.\n";
    $tx->rollback(new RuntimeException('orange_training_rollback'));
} catch (Throwable $e) {
    if ($e->getMessage() !== 'orange_training_rollback') {
        try { $tx->rollback($e); } catch (Throwable $rolled) { fwrite(STDERR, $rolled->getMessage() . "\n"); exit(1); }
    }
} finally { \core\session\manager::set_user($admin); }
