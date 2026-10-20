<?php
// Explicit local demo migration; native categories keep their IDs and contents.
define('CLI_SCRIPT', true);
require __DIR__ . '/../../../config.php';
if (!preg_match('~^http://(localhost|127\\.0\\.0\\.1):~', $CFG->wwwroot)) {
    throw new coding_exception('Local demo only. Manage categories through Moodle on other sites.');
}
\core\session\manager::set_user(get_admin());
$rootid = (int)get_config('local_orangeportal', 'librarycategory');
$legacy = $DB->get_record('course_categories', ['parent' => $rootid, 'name' => 'RPV/CENTREX']);
if ($legacy) {
    if ($DB->record_exists('course_categories', ['parent' => $rootid, 'name' => 'RPV'])) {
        throw new coding_exception('RPV already exists. Review both branches before renaming.');
    }
    \core_course_category::get($legacy->id, MUST_EXIST, true)->update(['name' => 'RPV']);
    echo "RPV/CENTREX renamed to RPV; category IDs and contents preserved.\n";
}
if (!$DB->record_exists('course_categories', ['parent' => $rootid, 'name' => 'CENTREX'])) {
    \core_course_category::create(['name' => 'CENTREX', 'parent' => $rootid,
        'idnumber' => 'orange_' . substr(hash('sha256', 'library/CENTREX'), 0, 32)]);
    echo "Empty CENTREX branch created. Existing documents remain under RPV.\n";
}
$rpv = $DB->get_record('course_categories', ['parent' => $rootid, 'name' => 'RPV'], '*', MUST_EXIST);
$centrex = $DB->get_record('course_categories', ['parent' => $rootid, 'name' => 'CENTREX'], '*', MUST_EXIST);
// The agreed initial arrangement puts all existing content under RPV, leaving CENTREX available for later.
foreach ($DB->get_records('course_categories', ['parent' => $centrex->id]) as $child) {
    \core_course_category::get($child->id, MUST_EXIST, true)->change_parent($rpv->id);
    echo "Moved category {$child->id} to RPV.\n";
}
require_once $CFG->dirroot . '/course/lib.php';
$courseids = $DB->get_fieldset_select('course', 'id', 'category = ?', [$centrex->id]);
if ($courseids) { move_courses($courseids, $rpv->id); }
