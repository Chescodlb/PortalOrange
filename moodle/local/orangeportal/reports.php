<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once($CFG->libdir . '/adminlib.php');
require_login();
require_capability('local/orangeportal:viewreports', context_system::instance());
admin_externalpage_setup('orangeportalreports');
$courseid = optional_param('courseid', 0, PARAM_INT);
$noticeid = optional_param('noticeid', 0, PARAM_INT);
echo $OUTPUT->header();
echo orange_intro('ADMINISTRACIÓN', 'Resultados y confirmaciones', 'Seguimiento de formación y trazabilidad de avisos para usuarios autorizados.');
echo orange_admin_navigation('reports');
$courses = orange_native_courses((int)get_config('local_orangeportal', 'coursescategory'));
$table = new html_table(); $table->head = ['Curso', 'Participantes activos', 'Finalizaciones', 'Nota media (%)', 'Informes Moodle'];
foreach ($courses as $course) {
    if ($courseid && $courseid != $course->id) { continue; }
    $context = context_course::instance($course->id);
    if (!has_capability('moodle/grade:viewall', $context)) { continue; }
    [$enrolledsql, $params] = get_enrolled_sql($context, '', 0, true);
    $participants = $DB->get_records_sql("SELECT u.id FROM {user} u JOIN ($enrolledsql) eu ON eu.id = u.id
        WHERE u.deleted = 0 AND u.suspended = 0", $params);
    $completed = 0; $grades = [];
    foreach ($participants as $participant) {
        if ($DB->record_exists_select('course_completions', 'course = ? AND userid = ? AND timecompleted > 0', [$course->id, $participant->id])) { $completed++; }
        $grade = $DB->get_record_sql("SELECT gg.id, gg.finalgrade, gi.grademin, gi.grademax FROM {grade_grades} gg
            JOIN {grade_items} gi ON gi.id = gg.itemid WHERE gi.courseid = ? AND gi.itemtype = 'course'
            AND gg.userid = ? AND gg.hidden = 0 AND gi.hidden = 0", [$course->id, $participant->id]);
        if ($grade && $grade->finalgrade !== null && $grade->grademax > $grade->grademin) {
            $grades[] = 100 * ($grade->finalgrade - $grade->grademin) / ($grade->grademax - $grade->grademin);
        }
    }
    $links = html_writer::link(new moodle_url('/grade/report/grader/index.php', ['id' => $course->id]), 'Calificaciones') . ' · ' .
        html_writer::link(new moodle_url('/report/completion/index.php', ['course' => $course->id]), 'Finalización');
    $modinfo = get_fast_modinfo($course);
    foreach ($modinfo->get_cms() as $cm) {
        if ($cm->modname == 'quiz' && has_capability('mod/quiz:viewreports', $cm->context)) {
            $links .= ' · ' . html_writer::link(new moodle_url('/mod/quiz/report.php', ['id' => $cm->id]), 'Evaluación: ' . s($cm->name));
        } else if ($cm->modname == 'feedback' && has_capability('mod/feedback:viewreports', $cm->context)) {
            $links .= ' · ' . html_writer::link(new moodle_url('/mod/feedback/analysis.php', ['id' => $cm->id]), 'Encuesta: ' . s($cm->name));
        }
    }
    $table->data[] = [html_writer::link(orange_link('reports.php', ['courseid' => $course->id]), s($course->fullname)),
        count($participants), $completed, $grades ? round(array_sum($grades) / count($grades), 1) : 'Sin notas', $links];
}
echo html_writer::tag('h3', 'Formación'); echo html_writer::table($table);
echo html_writer::link(orange_link('reports.php'), 'Ver todos los cursos');
echo html_writer::tag('h3', 'Recepción, lectura y confirmación');
echo html_writer::tag('p', 'Recibido significa mostrado en el listado del portal; leído significa abierto; confirmado requiere pulsar el botón. No acredita recepción en Teams o correo.');
$table = new html_table(); $table->head = ['Publicación', 'Destinatario actual', 'Recibido', 'Leído', 'Confirmado'];
$users = $DB->get_records_select('user', 'deleted = 0 AND suspended = 0 AND id > 1');
$notices = $noticeid ? [$DB->get_record('local_orange_notice', ['id' => $noticeid], '*', MUST_EXIST)] :
    $DB->get_records('local_orange_notice', ['published' => 1], 'timecreated DESC');
foreach ($notices as $notice) {
    foreach ($users as $u) {
        if (!\local_orangeportal\service::assigned((int)$notice->id, (int)$u->id)) { continue; }
        $r = \local_orangeportal\service::get_receipt($notice, (int)$u->id);
        $table->data[] = [html_writer::link(orange_link('reports.php', ['noticeid' => $notice->id]), s($notice->title)),
            s(fullname($u)), $r && $r->received ? userdate($r->received) : 'Pendiente',
            $r && $r->readtime ? userdate($r->readtime) : 'Pendiente', $r && $r->confirmed ? userdate($r->confirmed) : 'Pendiente'];
    }
}
echo html_writer::table($table);
if ($noticeid) {
    $table = new html_table(); $table->head = ['Versión', 'Destinatario', 'Recibido', 'Leído', 'Confirmado'];
    foreach ($DB->get_records('local_orange_receipt', ['noticeid' => $noticeid], 'revision DESC, userid') as $r) {
        $u = core_user::get_user($r->userid);
        $table->data[] = [$r->revision, s($u ? fullname($u) : 'Usuario eliminado'),
            $r->received ? userdate($r->received) : 'Pendiente', $r->readtime ? userdate($r->readtime) : 'Pendiente',
            $r->confirmed ? userdate($r->confirmed) : 'Pendiente'];
    }
    echo html_writer::tag('h3', 'Histórico de confirmaciones por versión'); echo html_writer::table($table);
    foreach ($DB->get_records('local_orange_revision', ['noticeid' => $noticeid], 'revision DESC') as $revision) {
        echo html_writer::tag('details', html_writer::tag('summary', 'Versión ' . $revision->revision . ': ' . s($revision->title)) .
            format_text($revision->content, FORMAT_HTML, ['context' => context_system::instance()]));
    }
}
echo $OUTPUT->footer();
