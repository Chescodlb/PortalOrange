<?php
defined('MOODLE_INTERNAL') || die();
use local_orangeportal\reading_report;
use local_orangeportal\report_scope;
use local_orangeportal\training_report;
$stats = reading_report::user_stats($since);
$critical = reading_report::critical_pending();
$totals = ['incidents' => [0, 0], 'notifications' => [0, 0]];
foreach ($stats as $row) {
    foreach ($totals as $kind => &$values) { $values[0] += (int)$row->{$kind . '_total'}; $values[1] += (int)$row->{$kind . '_read'}; }
    unset($values);
}
echo $OUTPUT->heading(get_string('reportoverview', 'local_orangeportal'), 3);
echo html_writer::tag('p', get_string('trackingcurrentgroups', 'local_orangeportal'), ['class' => 'text-muted']);
echo html_writer::start_div('orange-tracking-overview');
foreach ($totals as $kind => [$total, $read]) {
    echo html_writer::div(html_writer::tag('h4', get_string($kind, 'local_orangeportal')) .
        html_writer::tag('p', get_string('reportunreadcount', 'local_orangeportal') . ': ' . reading_report::ratio($total - $read, $total)) .
        html_writer::tag('p', get_string('reportreadcount', 'local_orangeportal') . ': ' . reading_report::ratio($read, $total)) .
        html_writer::link($reporturl(['section' => $kind]), get_string('view'), ['class' => 'btn btn-primary']), 'orange-tracking-card');
}
$pending = array_sum(array_map(fn($r) => (int)$r->pending, $critical));
echo html_writer::div(html_writer::tag('h4', get_string('reportcriticalunread', 'local_orangeportal')) .
    html_writer::tag('p', (string)$pending) . html_writer::tag('p', get_string('reportcriticalexplanation', 'local_orangeportal')),
    'orange-tracking-card');
echo html_writer::div(html_writer::tag('h4', get_string('reportusers', 'local_orangeportal')) .
    html_writer::tag('p', (string)count(report_scope::users())) .
    html_writer::link($reporturl(['section' => 'users']), get_string('view'), ['class' => 'btn btn-primary']), 'orange-tracking-card');
$editions = 0; $participants = 0; $finished = 0;
foreach (training_report::courses() as $course) {
    $members = training_report::participants($course);
    if (!$members && !report_scope::global_access()) { continue; }
    $editions++; $participants += count($members);
    foreach ($DB->get_records('course_completions', ['course' => $course->id]) as $done) {
        if ($done->timecompleted && isset($members[$done->userid])) { $finished++; }
    }
}
echo html_writer::div(html_writer::tag('h4', get_string('reporttraining', 'local_orangeportal')) .
    html_writer::tag('p', get_string('trackingeditions', 'local_orangeportal') . ': ' . $editions) .
    html_writer::tag('p', get_string('reporttrainingfinished', 'local_orangeportal') . ': ' . reading_report::ratio($finished, $participants)) .
    html_writer::link($reporturl(['section' => 'courses']), get_string('view'), ['class' => 'btn btn-primary']), 'orange-tracking-card');
echo html_writer::end_div();
