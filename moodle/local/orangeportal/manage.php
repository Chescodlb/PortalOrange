<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once($CFG->libdir . '/adminlib.php');
use local_orangeportal\service;
require_login();
require_capability('local/orangeportal:manage', context_system::instance());
$kind = optional_param('kind', 'incidents', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$new = optional_param('new', 0, PARAM_BOOL);
if (!in_array($kind, ['incidents', 'notifications'])) { throw new invalid_parameter_exception(get_string('unknownsection', 'local_orangeportal')); }
admin_externalpage_setup('orangeportalmanage');
$PAGE->set_heading(get_string('publishmanage', 'local_orangeportal'));
$PAGE->set_title(get_string('publishmanage', 'local_orangeportal') . ' · Orange');
$url = orange_link('manage.php', ['kind' => $kind]);
echo '';
$form = null;
if ($id || $new || $_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $id ? $DB->get_record('local_orange_notice', ['id' => $id, 'kind' => $kind], '*', MUST_EXIST) : null;
    $form = new \local_orangeportal\form\notice(null, ['kind' => $kind, 'current' => $current]);
    if ($form->is_cancelled()) { redirect($url); }
    if ($data = $form->get_data()) {
        $record = (object)['id' => $data->id, 'revision' => $data->revision, 'kind' => $data->kind, 'categoryid' => $data->categoryid,
            'title' => $data->title, 'content' => format_text($data->body['text'], $data->body['format'],
                ['context' => context_system::instance(), 'filter' => false]),
            'impact' => $data->impact, 'state' => $data->state, 'published' => (int)$data->published];
        service::save_notice($record, ['user' => $data->users ?? [], 'cohort' => $data->cohorts ?? [], 'group' => $data->groups ?? []]);
        redirect($url, get_string('publicationsaved', 'local_orangeportal'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    if ($id) {
        $record = $DB->get_record('local_orange_notice', ['id' => $id, 'kind' => $kind], '*', MUST_EXIST);
        $record->body = ['text' => $record->content, 'format' => FORMAT_HTML];
        $record->users = []; $record->cohorts = []; $record->groups = [];
        foreach ($DB->get_records('local_orange_audience', ['noticeid' => $id]) as $a) {
            $field = ['user' => 'users', 'cohort' => 'cohorts', 'group' => 'groups'][$a->targettype];
            $record->{$field}[] = $a->targetid;
        }
        $form->set_data($record);
    }
}
echo $OUTPUT->header();
echo orange_intro(get_string('administration'), get_string('publications', 'local_orangeportal'), get_string('publicationsintro', 'local_orangeportal'));
echo orange_admin_navigation($kind, $kind);
if ($form) { $form->display(); } else {
    echo html_writer::link(orange_link('manage.php', ['kind' => $kind, 'new' => 1]), get_string('createpublication', 'local_orangeportal'), ['class' => 'btn btn-primary']);
    $table = new html_table();
    $table->head = [get_string('noticetitle', 'local_orangeportal'), get_string('category'), get_string('publication', 'local_orangeportal'), get_string('incidentcolumnstate', 'local_orangeportal'), get_string('actions')];
    $categories = orange_category_options($kind);
    foreach ($DB->get_records('local_orange_notice', ['kind' => $kind], 'timecreated DESC') as $n) {
        $table->data[] = [s($n->title), s($categories[$n->categoryid] ?? ''), $n->published ? get_string('published', 'local_orangeportal') : get_string('draft', 'local_orangeportal'),
            s(service::states()[$n->state]), html_writer::link(orange_link('manage.php', ['kind' => $kind, 'id' => $n->id]), get_string('edit')) .
            ' · ' . html_writer::link(orange_link('view.php', ['id' => $n->id]), get_string('viewnotice', 'local_orangeportal'))];
    }
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
