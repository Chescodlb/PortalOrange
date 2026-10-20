<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once($CFG->libdir . '/adminlib.php');
require_login();
\local_orangeportal\experience::require_access();
require_capability('local/orangeportal:viewreports', context_system::instance());
admin_externalpage_setup('orangeportalreports');
$PAGE->set_heading(get_string('reportstitle', 'local_orangeportal'));
$PAGE->set_title(get_string('reportstitle', 'local_orangeportal') . ' · Orange');
$courseid = optional_param('courseid', 0, PARAM_INT);
$noticeid = optional_param('noticeid', 0, PARAM_INT);
echo $OUTPUT->header();
echo orange_intro(get_string('administration'), get_string('reportstitle', 'local_orangeportal'), get_string('reportsintro', 'local_orangeportal'));
echo orange_admin_navigation('reports');
$courses = orange_native_courses((int)get_config('local_orangeportal', 'coursescategory'));
$table = new html_table(); $table->head = [get_string('course'), get_string('activeparticipants', 'local_orangeportal'), get_string('completions', 'local_orangeportal'), get_string('averagegrade', 'local_orangeportal'), get_string('moodlereports', 'local_orangeportal')];
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
    $links = html_writer::link(new moodle_url('/grade/report/grader/index.php', ['id' => $course->id]), get_string('grades')) . ' · ' .
        html_writer::link(new moodle_url('/report/completion/index.php', ['course' => $course->id]), get_string('completionreport', 'local_orangeportal'));
    $modinfo = get_fast_modinfo($course);
    foreach ($modinfo->get_cms() as $cm) {
        if ($cm->modname == 'quiz' && has_capability('mod/quiz:viewreports', $cm->context)) {
            $links .= ' · ' . html_writer::link(new moodle_url('/mod/quiz/report.php', ['id' => $cm->id]), get_string('quizreport', 'local_orangeportal', s($cm->name)));
        } else if ($cm->modname == 'feedback' && has_capability('mod/feedback:viewreports', $cm->context)) {
            $links .= ' · ' . html_writer::link(new moodle_url('/mod/feedback/analysis.php', ['id' => $cm->id]), get_string('surveyreport', 'local_orangeportal', s($cm->name)));
        }
    }
    $table->data[] = [html_writer::link(orange_link('reports.php', ['courseid' => $course->id]), s($course->fullname)),
        count($participants), $completed, $grades ? round(array_sum($grades) / count($grades), 1) : get_string('nogrades', 'local_orangeportal'), $links];
}
echo html_writer::tag('h3', get_string('reporttraining', 'local_orangeportal')); echo html_writer::table($table);
echo html_writer::link(orange_link('reports.php'), get_string('allcourses', 'local_orangeportal'));
echo html_writer::tag('h3', get_string('receiptreport', 'local_orangeportal'));
echo html_writer::tag('p', get_string('receiptexplanation', 'local_orangeportal'));
$table = new html_table(); $table->head = [get_string('publication', 'local_orangeportal'), get_string('currentrecipient', 'local_orangeportal'), get_string('received', 'local_orangeportal'), get_string('read', 'local_orangeportal'), get_string('confirmed', 'local_orangeportal')];
$users = $DB->get_records_select('user', 'deleted = 0 AND suspended = 0 AND id > 1');
$notices = $noticeid ? [$DB->get_record('local_orange_notice', ['id' => $noticeid], '*', MUST_EXIST)] :
    $DB->get_records('local_orange_notice', ['published' => 1], 'timecreated DESC');
foreach ($notices as $notice) {
    foreach ($users as $u) {
        if (!\local_orangeportal\service::assigned((int)$notice->id, (int)$u->id)) { continue; }
        $r = \local_orangeportal\service::get_receipt($notice, (int)$u->id);
        $table->data[] = [html_writer::link(orange_link('reports.php', ['noticeid' => $notice->id]), s($notice->title)),
            s(fullname($u)), $r && $r->received ? userdate($r->received) : get_string('pending', 'local_orangeportal'),
            $r && $r->readtime ? userdate($r->readtime) : get_string('pending', 'local_orangeportal'),
            $notice->kind === 'incidents' ? get_string('incidentnotapplicable', 'local_orangeportal') :
                ($r && $r->confirmed ? userdate($r->confirmed) : get_string('pending', 'local_orangeportal'))];
    }
}
echo html_writer::table($table);
if ($noticeid) {
    $table = new html_table(); $table->head = [get_string('version', 'local_orangeportal'), get_string('recipient', 'local_orangeportal'), get_string('received', 'local_orangeportal'), get_string('read', 'local_orangeportal'), get_string('confirmed', 'local_orangeportal')];
    foreach ($DB->get_records('local_orange_receipt', ['noticeid' => $noticeid], 'revision DESC, userid') as $r) {
        $u = core_user::get_user($r->userid);
        $table->data[] = [$r->revision, s($u ? fullname($u) : get_string('deleteduser', 'local_orangeportal')),
            $r->received ? userdate($r->received) : get_string('pending', 'local_orangeportal'), $r->readtime ? userdate($r->readtime) : get_string('pending', 'local_orangeportal'),
            $notice->kind === 'incidents' ? get_string('incidentnotapplicable', 'local_orangeportal') : ($r->confirmed ? userdate($r->confirmed) : get_string('pending', 'local_orangeportal'))];
    }
    echo html_writer::tag('h3', get_string('confirmationhistory', 'local_orangeportal')); echo html_writer::table($table);
    foreach ($DB->get_records('local_orange_revision', ['noticeid' => $noticeid], 'revision DESC') as $revision) {
        echo html_writer::tag('details', html_writer::tag('summary', get_string('version', 'local_orangeportal') . ' ' . $revision->revision . ': ' . s($revision->title)) .
            format_text($revision->content, FORMAT_HTML, ['context' => context_system::instance()]));
    }
}
echo $OUTPUT->footer();
