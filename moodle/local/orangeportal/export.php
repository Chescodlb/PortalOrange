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
    $columns = ['publication' => get_string('publication', 'local_orangeportal'), 'user' => get_string('fullnameuser'),
        'received' => get_string('received', 'local_orangeportal'), 'read' => get_string('read', 'local_orangeportal'), 'confirmed' => get_string('confirmed', 'local_orangeportal')];
    foreach ($DB->get_records_select('local_orange_notice', 'published = 1 AND deleted = 0') as $n) {
        foreach ($users as $u) {
            if (!\local_orangeportal\service::assigned($n->id, $u->id)) { continue; }
            $r = \local_orangeportal\service::get_receipt($n, $u->id);
            $date = fn($v) => $v ? userdate($v, '%Y-%m-%d %H:%M') : '';
            $rows[] = ['publication' => $n->title, 'user' => fullname($u), 'received' => $date($r->received ?? 0),
                'read' => $date($r->readtime ?? 0), 'confirmed' => $n->kind === 'notifications' ? $date($r->confirmed ?? 0) : ''];
        }
    }
} else if ($type === 'training') {
    $columns = ['course' => get_string('course'), 'user' => get_string('fullnameuser'), 'grade' => get_string('grade', 'grades'), 'completed' => get_string('completed')];
    foreach (orange_native_courses((int)get_config('local_orangeportal', 'coursescategory')) as $course) {
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
