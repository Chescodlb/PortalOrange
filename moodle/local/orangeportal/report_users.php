<?php
/** Included by reports.php after authentication, Orange scope and report capability checks. */
defined('MOODLE_INTERNAL') || die();
use local_orangeportal\reading_report;
use local_orangeportal\user_report;
user_report::require_access();
$userid = optional_param('report_userid', 0, PARAM_INT);
$usertab = optional_param('user_tab', 'summary', PARAM_ALPHA);
$userpage = max(0, optional_param('user_page', 0, PARAM_INT));
$search = optional_param('user_search', '', PARAM_TEXT);
$cohort = optional_param('user_cohort', 0, PARAM_INT);
$sort = optional_param('user_sort', 'pending', PARAM_ALPHA);
$reading = optional_param('user_reading', '', PARAM_ALPHA);
$ul = static function(array $changes = []) use ($reporturl, $userid, $usertab, $search, $cohort, $sort, $reading): moodle_url {
    return $reporturl(array_merge(['section' => 'users', 'report_userid' => $userid, 'user_tab' => $usertab,
        'user_search' => $search, 'user_cohort' => $cohort, 'user_sort' => $sort,
        'user_reading' => $reading], $changes));
};
$label = static fn($key) => get_string($key, 'local_orangeportal');
$field = static function(string $name, string $caption, string $control): string {
    return html_writer::div(html_writer::tag('label', s($caption), ['for' => $name]) . $control, 'orange-filter-field');
};
$metric = static fn($read, $total) => reading_report::ratio((int)$read, (int)$total);
$criticals = reading_report::critical_pending($userid);
if (!$userid) {
    $cohortchoices = [0 => $label('reportallcollectives')];
    foreach (\local_orangeportal\orange_scope::cohorts() as $c) { $cohortchoices[$c->id] = $c->name; }
    $fields = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'section', 'value' => 'users']) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'period', 'value' => $period]);
    $fields .= $field('user_search', $label('reportsearchuser'), html_writer::empty_tag('input', [
        'type' => 'search', 'name' => 'user_search', 'id' => 'user_search', 'value' => $search, 'class' => 'form-control']));
    foreach (['user_cohort' => [$label('reportcollectivecentre'), $cohortchoices, $cohort],
        'user_sort' => [$label('reportorder'), ['pending' => $label('reportmostpending'), 'name' => get_string('name')], $sort]] as $name => [$caption, $choices, $value]) {
        $fields .= $field($name, $caption, html_writer::select($choices, $name, $value, false, ['id' => $name, 'class' => 'custom-select']));
    }
    echo html_writer::tag('form', $fields . html_writer::tag('button', get_string('filter'), ['class' => 'btn btn-primary']),
        ['method' => 'get', 'class' => 'orange-report-filters']);
    $data = reading_report::directory($search, $cohort, $sort, $since, $userpage, $perpage);
    $table = new html_table();
    $table->attributes['class'] = 'generaltable orange-user-summary';
    $table->head = [get_string('fullnameuser'), $label('reportincidentunread'), $label('reportincidentread'),
        $label('reportnotificationunread'), $label('reportnotificationread'), $label('reportcriticalunread'),
        $label('reporttrainingfinished'), $label('reportinprogress')];
    foreach ($data['rows'] as $u) {
        $training = user_report::training($u->id);
        $name = html_writer::link($ul(['report_userid' => $u->id, 'user_page' => 0, 'user_tab' => 'summary']), s(fullname($u)));
        if ($u->idnumber !== '') { $name .= html_writer::div(s($u->idnumber), 'small text-muted'); }
        $table->data[] = [$name, $metric($u->incidents_total - $u->incidents_read, $u->incidents_total),
            $metric($u->incidents_read, $u->incidents_total), $metric($u->notifications_total - $u->notifications_read, $u->notifications_total),
            $metric($u->notifications_read, $u->notifications_total), (int)($criticals[$u->id]->pending ?? 0),
            $training['available'] ? $metric($training['completed'], $training['total']) : $label('reporttrainingrestricted'),
            $training['available'] ? $training['started'] : '—'];
    }
    echo $OUTPUT->heading($label('reportusers'), 3);
    echo html_writer::tag('p', $label('reportcriticalexplanation'), ['class' => 'text-muted']);
    echo html_writer::div(html_writer::table($table), 'orange-table-scroll');
    if (!$data['total']) { echo html_writer::tag('p', $label('reportnoresults')); }
    echo $OUTPUT->paging_bar($data['total'], $data['page'], $perpage, $ul(), 'user_page');
    return;
}
user_report::require_access($userid);
$user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
if (!in_array($usertab, ['summary', 'incidents', 'notifications', 'training', 'surveys'], true)) {
    throw new invalid_parameter_exception('Invalid user report tab');
}
echo html_writer::link($ul(['report_userid' => 0, 'user_page' => 0]), $label('backtolist'));
echo $OUTPUT->heading(s(fullname($user)), 3);
if ($user->idnumber !== '') { echo html_writer::tag('p', s($user->idnumber)); }
$memberships = [];
foreach (\local_orangeportal\orange_scope::cohorts() as $c) {
    if ($DB->record_exists('cohort_members', ['cohortid' => $c->id, 'userid' => $userid])) { $memberships[] = $c->name; }
}
if ($memberships) { echo html_writer::tag('p', $label('orangecollectives') . ': ' . s(implode(' · ', $memberships))); }
$tabs = '';
foreach (['summary' => 'reportoverview', 'incidents' => 'incidents', 'notifications' => 'notifications',
    'training' => 'reporttraining', 'surveys' => 'reportsurveys'] as $key => $caption) {
    $attrs = ['class' => 'orange-admin-link' . ($usertab === $key ? ' active' : '')];
    if ($usertab === $key) { $attrs['aria-current'] = 'page'; }
    $tabs .= html_writer::link($ul(['user_tab' => $key, 'user_page' => 0, 'user_reading' => '']), $label($caption), $attrs);
}
echo html_writer::tag('nav', $tabs, ['class' => 'orange-category-tabs orange-user-tabs', 'aria-label' => $label('reportusersections')]);
if ($usertab === 'summary') {
    $stats = reading_report::user_stats($since, $userid)[$userid] ?? null;
    $table = new html_table();
    $table->head = [$label('reportsection'), $label('reportunreadcount'), $label('reportreadcount')];
    foreach (['incidents', 'notifications'] as $kind) {
        $total = (int)($stats->{$kind . '_total'} ?? 0);
        $read = (int)($stats->{$kind . '_read'} ?? 0);
        $table->data[] = [html_writer::link($ul(['user_tab' => $kind, 'user_page' => 0]), $label($kind)),
            $metric($total - $read, $total), $metric($read, $total)];
    }
    echo html_writer::div(html_writer::table($table), 'orange-table-scroll');
    echo html_writer::tag('p', $label('reportcriticalunread') . ': ' . (int)($criticals[$userid]->pending ?? 0));
    echo html_writer::tag('p', $label('reportcriticalexplanation'), ['class' => 'text-muted']);
    $training = user_report::training($userid);
    if ($training['available']) {
        echo html_writer::tag('p', $label('reporttrainingfinished') . ': ' . $metric($training['completed'], $training['total']) .
            ' · ' . $label('reportinprogress') . ': ' . $training['started']);
    } else { echo html_writer::tag('p', $label('reporttrainingrestricted')); }
} else if (in_array($usertab, ['incidents', 'notifications'], true)) {
    $fields = '';
    foreach (['section' => 'users', 'period' => $period, 'report_userid' => $userid, 'user_tab' => $usertab] as $name => $value) {
        $fields .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
    }
    $fields .= $field('user_reading', $label('updates_reading'), html_writer::select([
        '' => $label('updates_allreading'), 'unread' => $label('updates_unread'), 'read' => $label('read')],
        'user_reading', $reading, false, ['id' => 'user_reading', 'class' => 'custom-select']));
    echo html_writer::tag('form', $fields . html_writer::tag('button', get_string('filter'), ['class' => 'btn btn-primary']),
        ['method' => 'get', 'class' => 'orange-report-filters']);
    $data = reading_report::data($usertab, 0, $userid, $reading, $userpage, $perpage, $since);
    $table = new html_table();
    $table->attributes['class'] = 'generaltable orange-user-readings';
    $table->head = [$label('publication'), $label('reportdate'), $label('incidentcolumncategory'), $label('incidentcolumnimpact'), $label('read')];
    if ($usertab === 'incidents') { $table->head[] = $label('incidentcolumnstate'); }
    $categories = $DB->get_records('local_orange_category', ['kind' => $usertab]);
    $impacts = \local_orangeportal\notice_options::choices('impacts');
    $states = \local_orangeportal\notice_options::choices('states');
    foreach ($data['rows'] as $row) {
        $cells = [html_writer::link(orange_link('view.php', ['id' => $row->noticeid]), s($row->title)),
            userdate($row->publicationdate), orange_category_label($row->categoryid, $categories), s($impacts[$row->impact] ?? $row->impact),
            $row->readtime ? userdate($row->readtime) : $label('updates_unread')];
        if ($usertab === 'incidents') { $cells[] = s($states[$row->state] ?? $row->state); }
        $table->data[] = $cells;
    }
    echo html_writer::div(html_writer::table($table), 'orange-table-scroll');
    echo $OUTPUT->paging_bar($data['total'], $data['page'], $perpage, $ul(), 'user_page');
    if (!$data['total']) { echo html_writer::tag('p', $label('reportnoresults')); }
} else {
    $training = user_report::training($userid, true);
    echo html_writer::tag('p', $label('reporttrainingexplanation'), ['class' => 'text-muted']);
    if (!$training['available']) { echo html_writer::tag('p', $label('reporttrainingrestricted')); }
    $page = min($userpage, max(0, (int)ceil(count($training['rows']) / $perpage) - 1));
    foreach (array_slice($training['rows'], $page * $perpage, $perpage) as $row) {
        echo $OUTPUT->heading(s($row->course->fullname), 4);
        if ($usertab === 'surveys') {
            foreach ($row->surveys as $survey) {
                echo html_writer::tag('p', s($survey->name) . ' · ' . ($survey->anonymous ? $label('reportanonymoussurvey') :
                    ($survey->response ? $label('reportfinished') . ' · ' . userdate($survey->response->timemodified) : $label('pending'))));
            }
            if (!$row->surveys) { echo html_writer::tag('p', $label('reportnosurveys')); }
            continue;
        }
        $table = new html_table();
        $table->head = [$label('incidentcolumnstate'), $label('reportfinishdate'), get_string('grade', 'grades'), $label('reportresult')];
        $grade = $row->grade;
        $result = $label('reportnogradecriterion');
        if ($grade && $grade->finalgrade !== null && (float)$grade->gradepass > 0) {
            $result = $label($grade->finalgrade >= $grade->gradepass ? 'reportpassed' : 'reportnotpassed');
        }
        $table->data[] = [$label($row->status), empty($row->completion->timecompleted) ? '—' : userdate($row->completion->timecompleted),
            $grade && $grade->finalgrade !== null ? format_float($grade->finalgrade, 2) . ' / ' . format_float($grade->grademax, 2) : '—', $result];
        echo html_writer::div(html_writer::table($table), 'orange-table-scroll');
        foreach ($row->attempts as $quiz) {
            $attempttable = new html_table();
            $attempttable->head = [$label('reportattempt'), $label('incidentcolumnstate'), $label('reportstartdate'), $label('reportfinishdate')];
            foreach ($quiz->rows as $attempt) {
                $attempttable->data[] = [$attempt->attempt, get_string('state' . $attempt->state, 'quiz'),
                    $attempt->timestart ? userdate($attempt->timestart) : '—', $attempt->timefinish ? userdate($attempt->timefinish) : '—'];
            }
            $link = html_writer::link(new moodle_url('/mod/quiz/report.php', ['id' => $quiz->cmid]), $label('moodlereports'));
            echo html_writer::tag('details', html_writer::tag('summary', s($quiz->name) . ' · ' . $quiz->total) .
                html_writer::div(html_writer::table($attempttable), 'orange-table-scroll') .
                ($quiz->total > 25 ? html_writer::tag('p', $label('reportattemptlimit')) : '') . $link);
        }
    }
    if (!$training['total']) { echo html_writer::tag('p', $label('reportnoresults')); }
    echo $OUTPUT->paging_bar($training['total'], $page, $perpage, $ul(), 'user_page');
}
