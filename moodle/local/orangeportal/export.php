<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
use local_orangeportal\orange_scope as scope;
require_login(); \local_orangeportal\experience::require_access();
require_capability('local/orangeportal:viewreports', context_system::instance());
require_capability('local/orangeportal:exportreports', context_system::instance());
$type = required_param('type', PARAM_ALPHA);
$users = scope::users();
$rows = []; $columns = [];
if ($type === 'users') {
    require_capability('local/orangeportal:administer', context_system::instance());
    $columns = ['name' => get_string('fullnameuser'), 'username' => get_string('username'), 'email' => get_string('email')];
    foreach ($users as $u) { $rows[] = ['name' => fullname($u), 'username' => $u->username, 'email' => $u->email]; }
} else if ($type === 'readings') {
    $kind = optional_param('kind', '', PARAM_ALPHA);
    $data = \local_orangeportal\reading_report::data($kind, optional_param('noticeid', 0, PARAM_INT),
        optional_param('userid', 0, PARAM_INT), optional_param('reading', '', PARAM_ALPHA), 0, 0);
    $columns = ['publication' => get_string('publication', 'local_orangeportal'), 'user' => get_string('fullnameuser'),
        'received' => get_string('received', 'local_orangeportal'), 'read' => get_string('read', 'local_orangeportal')];
    $date = fn($v) => $v ? userdate($v, '%Y-%m-%d %H:%M') : '';
    foreach ($data['rows'] as $row) {
        $rows[] = ['publication' => $row->title, 'user' => fullname($row),
            'received' => $date($row->received), 'read' => $date($row->readtime)];
    }
} else if ($type === 'training') {
    $columns = ['course' => get_string('course'), 'user' => get_string('fullnameuser'), 'grade' => get_string('grade', 'grades'), 'completed' => get_string('completed')];
    $courseid = optional_param('courseid', 0, PARAM_INT);
    foreach (orange_native_courses((int)get_config('local_orangeportal', 'coursescategory')) as $course) {
        if ($courseid && (int)$course->id !== $courseid) { continue; }
        $context = context_course::instance($course->id);
        if (!has_capability('moodle/grade:viewall', $context)) { continue; }
        foreach ($users as $u) {
            if (!is_enrolled($context, $u, '', true)) { continue; }
            $grade = $DB->get_record_sql('SELECT gg.id, gg.finalgrade FROM {grade_grades} gg JOIN {grade_items} gi ON gi.id=gg.itemid
                WHERE gi.courseid=? AND gi.itemtype=? AND gg.userid=? AND gg.hidden=0 AND gi.hidden=0', [$course->id, 'course', $u->id]);
            $done = $DB->get_field('course_completions', 'timecompleted', ['course' => $course->id, 'userid' => $u->id]);
            $rows[] = ['course' => $course->fullname, 'user' => fullname($u), 'grade' => $grade->finalgrade ?? '',
                'completed' => $done ? userdate($done, '%Y-%m-%d %H:%M') : ''];
        }
    }
} else { throw new invalid_parameter_exception('Invalid report'); }
// Keep user-entered spreadsheet cells as text, never executable formulas.
foreach ($rows as &$row) {
    foreach ($row as &$value) { if (preg_match('/^[\s]*[=+@-]/u', (string)$value)) { $value = "'" . $value; } }
    unset($value);
}
unset($row);
\core\dataformat::download_data('orange-' . $type . '-' . userdate(time(), '%Y%m%d'), 'csv', $columns, new ArrayIterator($rows));
