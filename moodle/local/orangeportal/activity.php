<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
use local_orangeportal\native_content;
require_login();
\local_orangeportal\experience::require_access();
$id = required_param('id', PARAM_INT);
$record = $DB->get_record('course_modules', ['id' => $id, 'deletioninprogress' => 0], '*', MUST_EXIST);
$map = native_content::mapping((int)$record->course);
if ($map && $map->kind === 'notice') {
    $notice = $DB->get_record('local_orange_notice', ['id' => $map->noticeid], '*', MUST_EXIST);
    \local_orangeportal\service::require_view($notice);
    $direct = optional_param('direct', 0, PARAM_BOOL) && confirm_sesskey() &&
        (int)($notice->openactivity ?? 0) === $id;
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$direct) {
        orange_page(get_string('nativeopenactivity', 'local_orangeportal'), $notice->kind, orange_link('activity.php', ['id' => $id]));
        echo $OUTPUT->header(); echo orange_post_button($PAGE->url, get_string('nativeopenactivity', 'local_orangeportal'), ['open' => 1]);
        echo $OUTPUT->footer(); exit;
    }
    require_sesskey(); native_content::enter_notice((int)$record->course);
} else if (!$map) {
    $course = get_course($record->course);
    $roots = [(int)get_config('local_orangeportal', 'librarycategory'), (int)get_config('local_orangeportal', 'coursescategory')];
    $path = $DB->get_field('course_categories', 'path', ['id' => $course->category]);
    if (!array_intersect($roots, array_map('intval', explode('/', $path ?: '')))) { throw new moodle_exception('nativecontentdenied', 'local_orangeportal'); }
}
$cm = get_fast_modinfo($record->course)->get_cm($id);
require_login($record->course, false, $cm);
if (!$cm->uservisible) { throw new moodle_exception('nativecontentdenied', 'local_orangeportal'); }
if ($cm->url) { redirect($cm->url); }
// Text and media areas have no view.php: render their native content with Moodle filters/files.
require_once($CFG->dirroot . '/course/lib.php');
$PAGE->set_url(orange_link('activity.php', ['id' => $id]));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);
// Inline modules use their intro as content: the automatic activity header would repeat it.
$PAGE->activityheader->disable();
$PAGE->set_title($cm->name); $PAGE->set_heading($cm->name);
echo $OUTPUT->header();
if ($map && $map->kind === 'notice') {
    echo html_writer::link(orange_link('view.php', ['id' => $notice->id, 'detail' => 1]),
        get_string('viewnotice', 'local_orangeportal'), ['class' => 'btn btn-primary']);
}
echo $OUTPUT->heading(format_string($cm->name));
echo $cm->content;
if ($map && $map->kind === 'notice') { \local_orangeportal\service::receipt($notice, 'readtime'); }
else {
    $completion = new completion_info(get_course($cm->course)); $completion->set_module_viewed($cm);
}
echo $OUTPUT->footer();
