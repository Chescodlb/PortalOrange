<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once($CFG->libdir . '/adminlib.php');
use local_orangeportal\reading_report;
require_login();
\local_orangeportal\experience::require_access();
require_capability('local/orangeportal:viewreports', context_system::instance());
admin_externalpage_setup('orangeportalreports');
$PAGE->set_secondary_navigation(false);
$PAGE->set_heading(get_string('reportstitle', 'local_orangeportal'));
$PAGE->set_title(get_string('reportstitle', 'local_orangeportal') . ' · Orange');
$perpage = 25;
$courseid = optional_param('courseid', 0, PARAM_INT);
$period = optional_param('period', 'month', PARAM_ALPHA);
$since = reading_report::since($period);
$reportparams = ['period' => $period, 'courseid' => $courseid, 'training_page' => optional_param('training_page', 0, PARAM_INT)];
foreach (['incidents', 'notifications'] as $kind) {
    foreach (['notice', 'user', 'page', 'summary_page', 'history_page', 'revision'] as $key) {
        $reportparams[$kind . '_' . $key] = optional_param($kind . '_' . $key, 0, PARAM_INT);
    }
    $reportparams[$kind . '_reading'] = optional_param($kind . '_reading', '', PARAM_ALPHA);
}
// Keep existing links to a publication report working.
if ($legacyid = optional_param('noticeid', 0, PARAM_INT)) {
    $legacy = $DB->get_record('local_orange_notice', ['id' => $legacyid], '*', MUST_EXIST);
    $reportparams[$legacy->kind . '_notice'] = $legacyid;
}
$section = optional_param('section', $courseid ? 'courses' : 'incidents', PARAM_ALPHA);
if (!empty($legacy)) { $section = $legacy->kind; }
if (!in_array($section, ['incidents', 'notifications', 'courses', 'users'], true)) { throw new invalid_parameter_exception('Unknown report section'); }
$reportparams['section'] = $section;
if ($section === 'users') {
    foreach (['report_userid', 'user_page', 'user_cohort'] as $key) { $reportparams[$key] = optional_param($key, 0, PARAM_INT); }
    foreach (['user_search'] as $key) { $reportparams[$key] = optional_param($key, '', PARAM_TEXT); }
    foreach (['user_tab' => 'summary', 'user_sort' => 'pending', 'user_reading' => ''] as $key => $default) {
        $reportparams[$key] = optional_param($key, $default, PARAM_ALPHA);
    }
}

$reporturl = static function(array $changes = [], string $anchor = '') use (&$reportparams): moodle_url {
    $url = orange_link('reports.php', array_merge($reportparams, $changes));
    if ($anchor) { $url->set_anchor($anchor); }
    return $url;
};
$panel = static function(string $id, string $title, int $total, string $content, bool $open): string {
    $summary = html_writer::tag('summary', html_writer::span(s($title)) .
        html_writer::span(get_string('reportrowcount', 'local_orangeportal', $total), 'orange-report-count'));
    $attrs = ['id' => $id, 'class' => 'orange-report-panel'];
    if ($open) { $attrs['open'] = 'open'; }
    return html_writer::tag('details', $summary . html_writer::div($content, 'orange-report-content'), $attrs);
};
$filterform = static function(string $kind, string $fields) use (&$reportparams): string {
    $hidden = '';
    foreach ($reportparams as $key => $value) {
        // Reset this panel's page and history when changing its filters; preserve the other panels.
        if (str_starts_with($key, $kind . '_')) { continue; }
        $hidden .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $key, 'value' => $value]);
    }
    $button = html_writer::tag('button', get_string('filter'), ['class' => 'btn btn-primary', 'type' => 'submit']);
    return html_writer::tag('form', $hidden . $fields . $button, ['method' => 'get',
        'action' => orange_link('reports.php')->out(false) . '#report-' . $kind, 'class' => 'orange-report-filters']);
};
$date = static fn($value) => $value ? userdate($value) : get_string('pending', 'local_orangeportal');
$canexport = has_capability('local/orangeportal:exportreports', context_system::instance());
echo $OUTPUT->header();
echo orange_intro(get_string('administration'), get_string('reportstitle', 'local_orangeportal'), get_string('reportsintro', 'local_orangeportal'));
echo orange_admin_navigation('reports');
$tabs = '';
foreach (['incidents', 'notifications', 'courses', 'users'] as $tab) {
    $attrs = ['class' => 'orange-admin-link' . ($section === $tab ? ' active' : '')];
    if ($section === $tab) { $attrs['aria-current'] = 'page'; }
    $tabs .= html_writer::link($reporturl(['section' => $tab]),
        get_string($tab === 'courses' ? 'reporttraining' : ($tab === 'users' ? 'reportusers' : $tab), 'local_orangeportal'), $attrs);
}
echo html_writer::tag('nav', $tabs, ['class' => 'orange-category-tabs',
    'aria-label' => get_string('reportsection', 'local_orangeportal')]);
if ($section !== 'courses') {
    $periodfields = html_writer::tag('label', get_string('reportperiod', 'local_orangeportal'), ['for' => 'report-period']) .
        html_writer::select(['week' => get_string('reportweek', 'local_orangeportal'),
            'month' => get_string('reportmonth', 'local_orangeportal'), 'all' => get_string('reportalltime', 'local_orangeportal')],
            'period', $period, false, ['id' => 'report-period', 'class' => 'custom-select']);
    $periodhidden = '';
    foreach ($reportparams as $key => $value) {
        if ($key !== 'period' && !str_ends_with($key, '_page')) {
            $periodhidden .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $key, 'value' => $value]);
        }
    }
    echo html_writer::tag('form', $periodhidden . $periodfields . html_writer::tag('button', get_string('filter'),
        ['class' => 'btn btn-primary']), ['method' => 'get', 'class' => 'orange-report-filters']);
    echo html_writer::tag('p', get_string('reportperiodexplanation', 'local_orangeportal'), ['class' => 'text-muted']);
}
if ($section === 'users') {
    require(__DIR__ . '/report_users.php');
    echo $OUTPUT->footer();
    exit;
}
if ($section === 'courses') {

$courses = array_filter(orange_native_courses((int)get_config('local_orangeportal', 'coursescategory')),
    static fn($course) => (!$courseid || $courseid == $course->id) &&
        has_capability('moodle/grade:viewall', context_course::instance($course->id)));
$totalcourses = count($courses);
$trainingpage = min(max(0, $reportparams['training_page']), max(0, (int)ceil($totalcourses / $perpage) - 1));
$courses = array_slice($courses, $trainingpage * $perpage, $perpage, true);
$table = new html_table(); $table->head = [get_string('course'), get_string('activeparticipants', 'local_orangeportal'), get_string('completions', 'local_orangeportal'), get_string('averagegrade', 'local_orangeportal'), get_string('moodlereports', 'local_orangeportal')];
foreach ($courses as $course) {
    if ($courseid && $courseid != $course->id) { continue; }
    $context = context_course::instance($course->id);
    if (!has_capability('moodle/grade:viewall', $context)) { continue; }
    [$enrolledsql, $enrolparams] = get_enrolled_sql($context, '', 0, true);
    $participants = $DB->get_records_sql("SELECT u.id FROM {user} u JOIN ($enrolledsql) eu ON eu.id = u.id
        WHERE u.deleted = 0 AND u.suspended = 0", $enrolparams);
    $completed = 0; $grades = [];
    $participants = array_filter($participants, fn($p) => \local_orangeportal\experience::member((int)$p->id));
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
    $table->data[] = [html_writer::link($reporturl(['courseid' => $course->id, 'training_page' => 0], 'report-training'), s($course->fullname)),
        count($participants), $completed, $grades ? round(array_sum($grades) / count($grades), 1) : get_string('nogrades', 'local_orangeportal'), $links];
}
$training = html_writer::div(html_writer::table($table), 'orange-table-scroll') .
    $OUTPUT->paging_bar($totalcourses, $trainingpage, $perpage, $reporturl([], 'report-training'), 'training_page');
if ($courseid) { $training .= html_writer::link($reporturl(['courseid' => 0, 'training_page' => 0], 'report-training'), get_string('allcourses', 'local_orangeportal')); }
if ($canexport && $totalcourses) {
    $training .= html_writer::link(orange_link('export.php', ['type' => 'training', 'courseid' => $courseid]),
        get_string('orangetrainingexport', 'local_orangeportal'), ['class' => 'btn btn-outline-secondary']);
}
if (!$totalcourses) { $training .= html_writer::tag('p', get_string('reportnotraining', 'local_orangeportal')); }
echo $OUTPUT->heading(get_string('reporttraining', 'local_orangeportal'), 3);
echo html_writer::div($training, 'orange-training-report');
echo $OUTPUT->footer();
exit;
}
$kind = $section;
$selectedid = $reportparams[$kind . '_notice'];
if (!$selectedid) {
    $data = reading_report::summary($kind, $reportparams[$kind . '_summary_page'], $perpage, 0, $since);
    $table = new html_table();
    $table->attributes['class'] = 'generaltable orange-reading-summary';
    $table->head = [get_string('publication', 'local_orangeportal'), get_string('reportdate', 'local_orangeportal'),
        get_string('reportrecipients', 'local_orangeportal'), get_string('reportunreadcount', 'local_orangeportal'),
        get_string('reportreadcount', 'local_orangeportal')];
    foreach ($data['rows'] as $notice) {
        $url = $reporturl([$kind . '_notice' => $notice->id, $kind . '_user' => 0, $kind . '_reading' => '',
            $kind . '_page' => 0, $kind . '_history_page' => 0]);
        $table->data[] = [html_writer::link($url, s($notice->title)), userdate($notice->publicationdate, get_string('strftimedatetime', 'langconfig')),
            $notice->recipients, reading_report::ratio($notice->unreadcount, $notice->recipients),
            reading_report::ratio($notice->readcount, $notice->recipients)];
    }
    echo $OUTPUT->heading(get_string($kind, 'local_orangeportal'), 3);
    echo html_writer::tag('p', get_string('reportselectnotice', 'local_orangeportal'));
    echo $data['total'] ? html_writer::div(html_writer::table($table), 'orange-table-scroll') :
        html_writer::tag('p', get_string('reportnoresults', 'local_orangeportal'));
    echo $OUTPUT->paging_bar($data['total'], $data['page'], $perpage, $reporturl(), $kind . '_summary_page');
    echo $OUTPUT->footer();
    exit;
}
$summary = reading_report::summary($kind, 0, $perpage, $selectedid);
if (!$summary['rows']) { throw new invalid_parameter_exception(get_string('reportnoresults', 'local_orangeportal')); }
$selectednotice = reset($summary['rows']);
echo html_writer::link($reporturl([$kind . '_notice' => 0]), get_string('backtolist', 'local_orangeportal'));
echo $OUTPUT->heading(s($selectednotice->title), 3);
if ($kind === 'incidents' && \local_orangeportal\incident_workflow::all()) {
    echo html_writer::link(orange_link('incident_tracking.php', ['id' => $selectedid]),
        get_string('incidentstatehistory', 'local_orangeportal'), ['class' => 'btn btn-outline-secondary']);
}
echo html_writer::tag('p', get_string('reportreadingsummary', 'local_orangeportal', (object)[
    'total' => $selectednotice->recipients,
    'read' => reading_report::ratio($selectednotice->readcount, $selectednotice->recipients),
    'unread' => reading_report::ratio($selectednotice->unreadcount, $selectednotice->recipients)]));
$users = \local_orangeportal\orange_scope::users();
    $selectedid = $reportparams[$kind . '_notice'];
    $selecteduser = $reportparams[$kind . '_user'];
    $reading = $reportparams[$kind . '_reading'];
    $notices = $DB->get_records('local_orange_notice', ['id' => $selectedid, 'kind' => $kind, 'published' => 1, 'deleted' => 0], 'id', 'id,title,revision');
    $fields = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $kind . '_notice', 'value' => $selectedid]);
    $useroptions = [0 => get_string('reportallrecipients', 'local_orangeportal')];
    foreach ($users as $user) { $useroptions[$user->id] = fullname($user); }
    $choices = ['user' => [get_string('recipient', 'local_orangeportal'), $useroptions, $selecteduser],
        'reading' => [get_string('updates_reading', 'local_orangeportal'), [
            '' => get_string('updates_allreading', 'local_orangeportal'), 'read' => get_string('read', 'local_orangeportal'),
            'unread' => get_string('updates_unread', 'local_orangeportal')], $reading]];
    foreach ($choices as $key => [$label, $fieldchoices, $value]) {
        $name = $kind . '_' . $key;
        $fields .= html_writer::div(html_writer::tag('label', s($label), ['for' => $name]) .
            html_writer::select($fieldchoices, $name, $value, false, ['id' => $name, 'class' => 'custom-select']), 'orange-filter-field');
    }
    $content = $filterform($kind, $fields);
    $data = reading_report::data($kind, $selectedid, $selecteduser, $reading, $reportparams[$kind . '_page'], $perpage);
    $table = new html_table();
    $table->attributes['class'] = 'generaltable orange-reading-report';
    $table->head = [get_string('currentrecipient', 'local_orangeportal'),
        get_string('received', 'local_orangeportal'), get_string('read', 'local_orangeportal')];
    foreach ($data['rows'] as $row) {
        $table->data[] = [s(fullname($row)), $date($row->received), $date($row->readtime)];
    }
    $content .= html_writer::tag('p', get_string('receiptexplanation', 'local_orangeportal'));
    $content .= $data['total'] ? html_writer::div(html_writer::table($table), 'orange-table-scroll') :
        html_writer::tag('p', get_string('reportnoresults', 'local_orangeportal'));
    $content .= $OUTPUT->paging_bar($data['total'], $data['page'], $perpage, $reporturl([], 'report-' . $kind), $kind . '_page');
    if ($canexport) {
        $content .= html_writer::link(orange_link('export.php', ['type' => 'readings', 'kind' => $kind,
            'noticeid' => $selectedid, 'userid' => $selecteduser, 'reading' => $reading]),
            get_string('orangereadingexport', 'local_orangeportal'), ['class' => 'btn btn-outline-secondary']);
    }
    $reset = [$kind . '_notice' => $selectedid, $kind . '_user' => 0, $kind . '_reading' => '',
        $kind . '_page' => 0, $kind . '_history_page' => 0, $kind . '_revision' => 0];
    $content .= html_writer::link($reporturl($reset, 'report-' . $kind), get_string('reportclearfilters', 'local_orangeportal'), ['class' => 'ml-3']);
    if ($selectedid && isset($notices[$selectedid])) {
        $notice = $notices[$selectedid];
        $conditions = ['noticeid' => $selectedid];
        if ($selecteduser) { $conditions['userid'] = $selecteduser; }
        $total = $DB->count_records('local_orange_receipt', $conditions);
        $page = min(max(0, $reportparams[$kind . '_history_page']), max(0, (int)ceil($total / $perpage) - 1));
        $history = new html_table();
        $history->head = [get_string('version', 'local_orangeportal'), get_string('recipient', 'local_orangeportal'),
            get_string('received', 'local_orangeportal'), get_string('read', 'local_orangeportal')];
        foreach ($DB->get_records('local_orange_receipt', $conditions, 'revision DESC,userid,id', '*', $page * $perpage, $perpage) as $receipt) {
            $user = core_user::get_user($receipt->userid);
            $history->data[] = [$receipt->revision, s($user ? fullname($user) : get_string('deleteduser', 'local_orangeportal')),
                $date($receipt->received), $date($receipt->readtime ?: $receipt->confirmed)];
        }
        $historical = html_writer::div(html_writer::table($history), 'orange-table-scroll') .
            $OUTPUT->paging_bar($total, $page, $perpage, $reporturl([], 'report-' . $kind), $kind . '_history_page');
        $versions = $DB->get_records('local_orange_revision', ['noticeid' => $selectedid], 'revision DESC', 'id,revision,title');
        $versionchoices = [];
        foreach ($versions as $version) { $versionchoices[$version->revision] = get_string('version', 'local_orangeportal') . ' ' . $version->revision; }
        $versionnumber = $reportparams[$kind . '_revision'] ?: (int)$notice->revision;
        $versionfields = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $kind . '_notice', 'value' => $selectedid]) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $kind . '_user', 'value' => $selecteduser]) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $kind . '_reading', 'value' => $reading]) .
            html_writer::tag('label', get_string('version', 'local_orangeportal'), ['for' => $kind . '_revision']) .
            html_writer::select($versionchoices, $kind . '_revision', $versionnumber, false, ['id' => $kind . '_revision', 'class' => 'custom-select']);
        $historical .= $filterform($kind, $versionfields);
        foreach ($versions as $version) {
            if ((int)$version->revision !== $versionnumber) { continue; }
            $historical .= html_writer::tag('h4', s($version->title)) . format_text($DB->get_field('local_orange_revision', 'content', ['id' => $version->id]), FORMAT_HTML, ['context' => context_system::instance()]);
        }
        $content .= $panel('report-history-' . $kind, get_string('confirmationhistory', 'local_orangeportal'), $total, $historical, (bool)$reportparams[$kind . '_history_page'] || (bool)$reportparams[$kind . '_revision']);
    }
    echo html_writer::div($content, 'orange-reading-detail');
echo $OUTPUT->footer();
