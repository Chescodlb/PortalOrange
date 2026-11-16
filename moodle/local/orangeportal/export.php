<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
use local_orangeportal\orange_scope as scope;
require_login(); \local_orangeportal\experience::require_access();
require_capability('local/orangeportal:viewreports', context_system::instance());
require_capability('local/orangeportal:exportreports', context_system::instance());
$type = required_param('type', PARAM_ALPHA);
$users = \local_orangeportal\report_scope::users();
$rows = []; $columns = [];
if ($type === 'users') {
    require_capability('local/orangeportal:administer', context_system::instance());
    $columns = ['name' => get_string('fullnameuser'), 'username' => get_string('username'), 'email' => get_string('email')];
    foreach ($users as $u) { $rows[] = ['name' => fullname($u), 'username' => $u->username, 'email' => $u->email]; }
} else if ($type === 'readings') {
    $kind = optional_param('kind', '', PARAM_ALPHA);
    $data = \local_orangeportal\reading_report::data($kind, optional_param('noticeid', 0, PARAM_INT),
        optional_param('userid', 0, PARAM_INT), optional_param('reading', '', PARAM_ALPHA), 0, 0,
        \local_orangeportal\reading_report::since(optional_param('period', 'month', PARAM_ALPHA)),
        (bool)optional_param('criticalunread', 0, PARAM_BOOL));
    $columns = ['publication' => get_string('publication', 'local_orangeportal'), 'user' => get_string('fullnameuser'), 'collectives' => get_string('orangecollectives', 'local_orangeportal'),
        'received' => get_string('received', 'local_orangeportal'), 'read' => get_string('read', 'local_orangeportal')];
    $date = fn($v) => $v ? userdate($v, '%Y-%m-%d %H:%M') : '';
    foreach ($data['rows'] as $row) {
        $rows[] = ['publication' => $row->title, 'user' => fullname($row), 'collectives' => \local_orangeportal\report_scope::user_cohorts($row->userid),
            'received' => $date($row->received), 'read' => $date($row->readtime)];
    }
} else if ($type === 'library') {
    $documentid = optional_param('library_document', 0, PARAM_INT);
    $documents = $documentid ? [$documentid => \local_orangeportal\library_report::document($documentid)] :
        \local_orangeportal\library_report::documents(optional_param('library_search', '', PARAM_TEXT));
    $columns = ['document' => get_string('libraryreportdocument', 'local_orangeportal'), 'user' => get_string('fullnameuser'),
        'ce' => get_string('idnumber'), 'collectives' => get_string('orangecollectives', 'local_orangeportal'),
        'reading' => get_string('updates_reading', 'local_orangeportal'), 'readtime' => get_string('libraryreportfirstread', 'local_orangeportal')];
    foreach ($documents as $document) {
        foreach (\local_orangeportal\library_report::readers($document, optional_param('library_reading', '', PARAM_ALPHA),
                optional_param('library_usersearch', '', PARAM_TEXT)) as $row) {
            $rows[] = ['document' => $document->name, 'user' => fullname($row->user), 'ce' => $row->user->idnumber,
                'collectives' => \local_orangeportal\report_scope::user_cohorts($row->user->id),
                'reading' => get_string($row->readtime ? 'libraryread' : 'libraryunread', 'local_orangeportal'),
                'readtime' => $row->readtime ? userdate($row->readtime, '%Y-%m-%d %H:%M:%S') : ''];
        }
    }
} else if ($type === 'training') {
    $columns = ['course' => get_string('course'), 'user' => get_string('fullnameuser'), 'ce' => get_string('idnumber'),
        'collectives' => get_string('orangecollectives', 'local_orangeportal'),
        'grade' => get_string('grade', 'grades'), 'completed' => get_string('completed')];
    foreach (\local_orangeportal\training_report::fields() as $field) { $columns['edition_' . $field] = get_string('edition_' . $field, 'local_orangeportal'); }
    $courseid = optional_param('courseid', 0, PARAM_INT);
    foreach ($users as $u) {
        foreach (\local_orangeportal\user_report::training($u->id, true)['rows'] as $row) {
            if ($courseid && $courseid != $row->course->id) { continue; }
            if (!\local_orangeportal\training_report::matches($row->course, optional_param('training_search', '', PARAM_TEXT),
                    optional_param('training_modality', '', PARAM_TEXT))) { continue; }
            $record = ['course' => $row->course->fullname, 'user' => fullname($u), 'ce' => $u->idnumber,
                'collectives' => \local_orangeportal\report_scope::user_cohorts($u->id),
                'grade' => $row->grade->finalgrade ?? '',
                'completed' => !empty($row->completion->timecompleted) ? userdate($row->completion->timecompleted, '%Y-%m-%d %H:%M') : ''];
            $metadata = \local_orangeportal\training_report::metadata($row->course->id);
            foreach (\local_orangeportal\training_report::fields() as $field) { $record['edition_' . $field] = $metadata[$field] ?? ''; }
            $rows[] = $record;
        }
    }
} else if (in_array($type, ['attendance', 'evaluations', 'satisfaction'], true)) {
    $course = \local_orangeportal\training_report::course(required_param('courseid', PARAM_INT));
    if ($type === 'attendance') {
        $columns = ['course' => get_string('course'), 'user' => get_string('fullnameuser'), 'ce' => get_string('idnumber'),
            'collectives' => get_string('orangecollectives', 'local_orangeportal'), 'status' => get_string('trackingattendance', 'local_orangeportal'),
            'minutes' => get_string('trackingminutes', 'local_orangeportal'), 'recorded' => get_string('dateto', 'local_orangeportal')];
        foreach (\local_orangeportal\training_report::participants($course) as $u) {
            $a = $DB->get_record('local_orange_attendance', ['courseid' => $course->id, 'userid' => $u->id]);
            $rows[] = ['course' => $course->fullname, 'user' => fullname($u), 'ce' => $u->idnumber,
                'collectives' => $a->collectives ?? \local_orangeportal\report_scope::user_cohorts($u->id),
                'status' => get_string('attendance_' . ($a->status ?? 'unknown'), 'local_orangeportal'),
                'minutes' => $a->minutes ?? '', 'recorded' => $a ? userdate($a->timemodified, '%Y-%m-%d %H:%M') : ''];
        }
    } else if ($type === 'evaluations') {
        $columns = ['quiz' => get_string('trackingevaluations', 'local_orangeportal'), 'user' => get_string('fullnameuser'),
            'ce' => get_string('idnumber'), 'attempt' => get_string('reportattempt', 'local_orangeportal'),
            'state' => get_string('incidentcolumnstate', 'local_orangeportal'), 'started' => get_string('reportstartdate', 'local_orangeportal'),
            'finished' => get_string('reportfinishdate', 'local_orangeportal'), 'grade' => get_string('grade', 'grades'),
            'finalgrade' => get_string('trackingfinalgrade', 'local_orangeportal')];
        foreach (\local_orangeportal\training_report::evaluations($course) as $quiz) {
            foreach ($quiz['attempts'] as $a) {
                $rows[] = ['quiz' => $quiz['name'], 'user' => fullname($a->user), 'ce' => $a->user->idnumber, 'attempt' => $a->attempt,
                    'state' => get_string('state' . $a->state, 'quiz'), 'started' => userdate($a->timestart, '%Y-%m-%d %H:%M'),
                    'finished' => $a->timefinish ? userdate($a->timefinish, '%Y-%m-%d %H:%M') : '', 'grade' => $a->grade ?? '',
                    'finalgrade' => $quiz['grades'][$a->userid]->grade ?? ''];
            }
        }
    } else {
        $columns = ['survey' => get_string('trackingsatisfaction', 'local_orangeportal'), 'question' => get_string('trackingquestion', 'local_orangeportal'),
            'n' => get_string('trackingresponses', 'local_orangeportal'), 'mean' => get_string('trackingmean', 'local_orangeportal')];
        foreach (\local_orangeportal\training_report::satisfaction($course) as $survey) {
            if ($survey['restricted']) { continue; }
            foreach ($survey['questions'] as $q) { $rows[] = ['survey' => $survey['name'], 'question' => $q['name'], 'n' => $q['n'], 'mean' => $q['mean']]; }
        }
    }
} else if ($type === 'reportusers') {
    $columns = ['user' => get_string('fullnameuser'), 'ce' => get_string('idnumber'), 'collectives' => get_string('orangecollectives', 'local_orangeportal'),
        'incidentspending' => get_string('reportincidentunread', 'local_orangeportal'), 'communicationspending' => get_string('reportnotificationunread', 'local_orangeportal'),
        'critical' => get_string('reportcriticalunread', 'local_orangeportal'),
        'finished' => get_string('reporttrainingfinished', 'local_orangeportal'), 'started' => get_string('reportinprogress', 'local_orangeportal')];
    $since = \local_orangeportal\reading_report::since(optional_param('period', 'month', PARAM_ALPHA));
    $stats = \local_orangeportal\reading_report::directory(optional_param('user_search', '', PARAM_TEXT), \local_orangeportal\report_scope::selected(),
        optional_param('user_sort', 'pending', PARAM_ALPHA), $since, 0, 0);
    $critical = \local_orangeportal\reading_report::critical_pending();
    foreach ($stats['rows'] as $u) {
        $training = \local_orangeportal\user_report::training($u->id);
        $rows[] = ['user' => fullname($u), 'ce' => $u->idnumber,
        'collectives' => \local_orangeportal\report_scope::user_cohorts($u->id),
        'incidentspending' => \local_orangeportal\reading_report::ratio($u->incidents_total - $u->incidents_read, $u->incidents_total),
        'communicationspending' => \local_orangeportal\reading_report::ratio($u->notifications_total - $u->notifications_read, $u->notifications_total),
        'critical' => $critical[$u->id]->pending ?? 0,
        'finished' => $training['available'] ? \local_orangeportal\reading_report::ratio($training['completed'], $training['total']) : '',
        'started' => $training['available'] ? $training['started'] : '']; }
} else { throw new invalid_parameter_exception('Invalid report'); }
// Keep user-entered spreadsheet cells as text, never executable formulas.
foreach ($rows as &$row) {
    foreach ($row as &$value) { if (preg_match('/^[\s]*[=+@-]/u', (string)$value)) { $value = "'" . $value; } }
    unset($value);
}
unset($row);
\core\dataformat::download_data('orange-' . $type . '-' . userdate(time(), '%Y%m%d'), 'csv', $columns, new ArrayIterator($rows));
