<?php
defined('MOODLE_INTERNAL') || die();
use local_orangeportal\training_report as training;
use local_orangeportal\report_scope;
use local_orangeportal\reading_report;
use local_orangeportal\report_period;
$t = static fn($key) => get_string($key, 'local_orangeportal');
$draw = static function(array $head, array $rows, string $class = ''): string {
    $table = new html_table(); $table->head = $head; $table->data = $rows;
    $table->attributes['class'] = 'generaltable ' . $class;
    return html_writer::div(html_writer::table($table), 'orange-table-scroll');
};
$exportlink = static fn($type) => $canexport ? html_writer::link(orange_link('export.php',
    ['type' => $type, 'courseid' => $courseid, 'training_search' => $reportparams['training_search'],
        'training_modality' => $reportparams['training_modality']] + report_period::params()), $t('trackingexport'), ['class' => 'btn btn-primary']) : '';
$progress = static function($course, $members) use ($DB): array {
    $completed = $DB->get_records('course_completions', ['course' => $course->id], '', 'userid,timecompleted');
    return array_intersect_key(array_filter($completed, fn($r) => !empty($r->timecompleted)), $members);
};
if (!$courseid) {
    $courses = training::courses();
    $search = $reportparams['training_search']; $modality = $reportparams['training_modality'];
    $rows = []; $modalities = ['' => $t('trackingallmodalities')];
    foreach ($courses as $course) {
        $metadata = training::metadata((int)$course->id);
        if (!empty($metadata['modality'])) { $modalities[$metadata['modality']] = $metadata['modality']; }
        if (!training::matches($course, $search, $modality)) { continue; }
        $members = training::participants($course);
        if (!$members && !report_scope::global_access()) { continue; }
        $done = count($progress($course, $members));
        $grades = [];
        if (has_capability('moodle/grade:viewall', context_course::instance($course->id))) {
            foreach ($DB->get_records_sql("SELECT gg.userid,gg.finalgrade,gi.grademax FROM {grade_grades} gg JOIN {grade_items} gi ON gi.id=gg.itemid
                WHERE gi.courseid=? AND gi.itemtype='course' AND gi.hidden=0 AND gg.hidden=0 AND gg.finalgrade IS NOT NULL", [$course->id]) as $grade) {
                if (isset($members[$grade->userid]) && $grade->grademax > 0) { $grades[] = $grade->finalgrade / $grade->grademax * 100; }
            }
        }
        $rows[] = [html_writer::link($reporturl(['courseid' => $course->id, 'training_tab' => 'summary']), s($course->fullname)),
            s($metadata['code'] ?? '—'), s($metadata['edition'] ?? '—'), $course->startdate ? userdate($course->startdate, '%d/%m/%Y') : '—',
            s($metadata['modality'] ?? '—'), count($members), reading_report::ratio($done, count($members)),
            $grades ? format_float(array_sum($grades) / count($grades), 1) . ' %' : '—'];
    }
    $fields = '';
    foreach (report_period::params() + ['section' => 'courses'] as $key => $value) { $fields .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $key, 'value' => $value]); }
    $fields .= html_writer::tag('label', get_string('search'), ['for' => 'training_search']) .
        html_writer::empty_tag('input', ['type' => 'search', 'name' => 'training_search', 'id' => 'training_search', 'value' => $search, 'class' => 'form-control']) .
        html_writer::tag('label', $t('edition_modality'), ['for' => 'training_modality']) .
        html_writer::select($modalities, 'training_modality', $modality, false, ['id' => 'training_modality', 'class' => 'custom-select']) .
        html_writer::tag('button', get_string('filter'), ['class' => 'btn btn-primary']);
    echo html_writer::tag('form', $fields, ['method' => 'get', 'class' => 'orange-report-filters']);
    echo html_writer::tag('p', $t('trackingeditionperiod'), ['class' => 'text-muted']);
    $total = count($rows); $page = min(max(0, $reportparams['training_page']), max(0, (int)ceil($total / $perpage) - 1));
    echo $draw([get_string('course'), $t('edition_code'), $t('edition_edition'), $t('reportstartdate'), $t('edition_modality'),
        $t('activeparticipants'), $t('reporttrainingfinished'), $t('averagegrade')], array_slice($rows, $page * $perpage, $perpage), 'orange-training-report');
    echo $OUTPUT->paging_bar($total, $page, $perpage, $reporturl(), 'training_page');
    if (!$total) { echo html_writer::tag('p', $t('reportnotraining')); }
    echo $exportlink('training');
    return;
}
$course = training::course($courseid);
$members = training::participants($course);
$metadata = training::metadata($courseid);
$canmanage = training::can_manage($course);
$tab = $reportparams['training_tab'];
if (!in_array($tab, ['summary', 'participants', 'evaluations', 'satisfaction'], true)) { throw new invalid_parameter_exception('Invalid training tab'); }
echo html_writer::link($reporturl(['courseid' => 0, 'training_page' => 0]), $t('backtolist'));
echo $OUTPUT->heading(format_string($course->fullname), 3);
$tabs = '';
foreach (['summary' => 'reportoverview', 'participants' => 'trackingattendance', 'evaluations' => 'trackingevaluations', 'satisfaction' => 'trackingsatisfaction'] as $key => $label) {
    $tabs .= html_writer::link($reporturl(['training_tab' => $key, 'training_page' => 0]), $t($label),
        ['class' => 'orange-admin-link' . ($tab === $key ? ' active' : ''), 'aria-current' => $tab === $key ? 'page' : 'false']);
}
echo html_writer::tag('nav', $tabs, ['class' => 'orange-category-tabs']);
if ($tab === 'summary') {
    $rows = [];
    foreach (training::fields() as $key) { $rows[] = [$t('edition_' . $key), s($metadata[$key] ?? '—')]; }
    $rows[] = [$t('reportstartdate'), $course->startdate ? userdate($course->startdate) : '—'];
    $rows[] = [$t('reportfinishdate'), $course->enddate ? userdate($course->enddate) : '—'];
    $rows[] = [$t('activeparticipants'), count($members)];
    $rows[] = [$t('reporttrainingfinished'), reading_report::ratio(count($progress($course, $members)), count($members))];
    echo $draw([$t('trackingfield'), $t('trackingvalue')], $rows);
    if ($canmanage) {
        echo html_writer::link(new moodle_url('/course/edit.php', ['id' => $courseid]), $t('trackingeditedition'), ['class' => 'btn btn-primary']);
        if (\core_course\management\helper::can_copy_course($courseid)) {
            echo ' ' . html_writer::link(new moodle_url('/backup/copy.php', ['id' => $courseid]), $t('trackingcopyedition'), ['class' => 'btn btn-primary']);
        }
        echo html_writer::tag('p', $t('trackingcopyhelp'), ['class' => 'text-muted']);
    }
} else if ($tab === 'participants') {
    echo html_writer::tag('p', $t('trackingattendancehelp'), ['class' => 'text-muted']);
    $total = count($members); $page = min(max(0, $reportparams['training_page']), max(0, (int)ceil($total / $perpage) - 1));
    $rows = [];
    foreach (array_slice($members, $page * $perpage, $perpage, true) as $u) {
        $usertraining = \local_orangeportal\user_report::training($u->id, true)['rows'][$courseid] ?? null;
        $attendance = $DB->get_record('local_orange_attendance', ['courseid' => $courseid, 'userid' => $u->id]);
        $choice = $attendance->status ?? 'unknown';
        $options = array_combine(['unknown', 'present', 'absent', 'partial'], array_map(fn($k) => $t('attendance_' . $k), ['unknown', 'present', 'absent', 'partial']));
        $control = $t('attendance_' . $choice);
        if ($canmanage) {
            $control = '';
            foreach (['action' => 'attendance', 'participant' => $u->id, 'sesskey' => sesskey()] as $key => $value) {
                $control .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $key, 'value' => $value]);
            }
            $control .= html_writer::tag('label', $t('trackingattendance'), ['for' => 'attendance-' . $u->id, 'class' => 'sr-only']) .
                html_writer::select($options, 'attendance', $choice, false, ['id' => 'attendance-' . $u->id, 'class' => 'custom-select']) .
                html_writer::tag('label', $t('trackingminutes'), ['for' => 'minutes-' . $u->id, 'class' => 'sr-only']) .
                html_writer::empty_tag('input', ['type' => 'number', 'name' => 'minutes', 'min' => 0, 'max' => 1000000,
                    'id' => 'minutes-' . $u->id, 'value' => $attendance->minutes ?? '', 'placeholder' => $t('trackingminutes'), 'class' => 'form-control']) .
                html_writer::tag('button', get_string('savechanges'), ['class' => 'btn btn-primary']);
            $control = html_writer::tag('form', $control, ['method' => 'post', 'action' => $reporturl()->out(false), 'class' => 'orange-attendance-form']);
        }
        $grade = $usertraining->grade ?? null;
        $rows[] = [html_writer::link($reporturl(['section' => 'users', 'report_userid' => $u->id]), s(fullname($u))), s($u->idnumber),
            s(report_scope::user_cohorts($u->id)), $usertraining ? $t($usertraining->status) : '—',
            $grade && $grade->finalgrade !== null ? format_float($grade->finalgrade, 2) . ' / ' . format_float($grade->grademax, 2) : '—', $control,
            $attendance && $attendance->minutes !== null ? format_float($attendance->minutes / 60, 2) : '—'];
    }
    echo $draw([get_string('fullnameuser'), get_string('idnumber'), $t('orangecollectives'), $t('incidentcolumnstate'), get_string('grade', 'grades'),
        $t('trackingattendance'), $t('trackinghours')], $rows);
    echo $OUTPUT->paging_bar($total, $page, $perpage, $reporturl(), 'training_page');
    echo $exportlink('attendance');
} else if ($tab === 'evaluations') {
    echo html_writer::tag('p', $t('trackingattempthelp'), ['class' => 'text-muted']);
    $evaluations = training::evaluations($course);
    if (!$evaluations) { echo html_writer::tag('p', $t('trackingnoevaluations')); }
    foreach ($evaluations as $quiz) {
        echo $OUTPUT->heading(format_string($quiz['name']), 4);
        $rows = [];
        $total = count($quiz['attempts']); $page = min(max(0, $reportparams['training_page']), max(0, (int)ceil($total / $perpage) - 1));
        foreach (array_slice($quiz['attempts'], $page * $perpage, $perpage) as $a) {
            $result = $a->grade === null ? '—' : ($quiz['gradepass'] > 0 ? $t($a->grade >= $quiz['gradepass'] ? 'reportpassed' : 'reportnotpassed') : $t('reportnogradecriterion'));
            $rows[] = [s(fullname($a->user)), $a->attempt, get_string('state' . $a->state, 'quiz'), userdate($a->timestart),
                $a->timefinish ? userdate($a->timefinish) : '—', $a->timefinish ? format_time(max(0, $a->timefinish - $a->timestart)) : '—',
                $a->grade === null ? '—' : format_float($a->grade, 2) . ' / ' . format_float($quiz['max'], 2), $result,
                isset($quiz['grades'][$a->userid]) ? format_float($quiz['grades'][$a->userid]->grade, 2) : '—'];
        }
        echo $draw([get_string('fullnameuser'), $t('reportattempt'), $t('incidentcolumnstate'), $t('reportstartdate'), $t('reportfinishdate'),
            $t('trackingattemptduration'), get_string('grade', 'grades'), $t('reportresult'), $t('trackingfinalgrade')], $rows);
        echo $OUTPUT->paging_bar($total, $page, $perpage, $reporturl(), 'training_page');
        $questions = [];
        foreach ($quiz['questions'] as $q) { $questions[] = [s($q['name']), $q['n'], format_float($q['sum'] / $q['n'], 1) . ' %']; }
        echo html_writer::tag('details', html_writer::tag('summary', $t('trackingquestions')) .
            $draw([$t('trackingquestion'), $t('trackingattemptsgraded'), $t('trackingachievement')], $questions));
    }
    echo $exportlink('evaluations');
} else {
    echo html_writer::tag('p', $t('trackingsurveyperiod'), ['class' => 'text-muted']);
    $surveys = training::satisfaction($course);
    if (!$surveys) { echo html_writer::tag('p', $t('reportnosurveys')); }
    foreach ($surveys as $survey) {
        echo $OUTPUT->heading(format_string($survey['name']), 4);
        if ($survey['restricted']) { echo $OUTPUT->notification($t('trackinganonymousscope'), 'info'); continue; }
        echo html_writer::tag('p', $t('trackingresponses') . ': ' . $survey['responses'] . ($survey['anonymous'] ? ' · ' . $t('reportanonymoussurvey') : ''));
        $rows = [];
        foreach ($survey['questions'] as $q) {
            ksort($q['distribution'], SORT_NUMERIC); $distribution = [];
            foreach ($q['distribution'] as $score => $n) { $distribution[] = $score . ': ' . $n; }
            $rows[] = [s($q['name']), $q['n'], format_float($q['mean'], 2), s(implode(' · ', $distribution))];
        }
        echo $draw([$t('trackingquestion'), $t('trackingresponses'), $t('trackingmean'), $t('trackingdistribution')], $rows);
        $comments = '';
        foreach ($survey['comments'] as $comment) { $comments .= html_writer::tag('li', s($comment['question'] . ': ' . $comment['text'])); }
        if ($comments !== '') { echo html_writer::tag('details', html_writer::tag('summary', $t('trackingcomments')) . html_writer::tag('ul', $comments)); }
    }
    echo $exportlink('satisfaction');
}
