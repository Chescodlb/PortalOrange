<?php
if (!empty($_POST['ajax'])) { define('AJAX_SCRIPT', true); }
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
use local_orangeportal\agent_import as importer;
use local_orangeportal\orange_administration as admin;
require_login(); admin::require_access();
$url = orange_link('import_users.php');
$action = optional_param('action', '', PARAM_ALPHA);
$ajax = optional_param('ajax', false, PARAM_BOOL);
$label = static fn($key, $a = null) => get_string($key, 'local_orangeportal', $a);
// Never return database diagnostics or request/file contents to the browser.
$error_message = static fn(Throwable $e) => $e instanceof moodle_exception &&
    str_starts_with($e->errorcode, 'csv') ? $e->getMessage() : get_string('csvimportfailed', 'local_orangeportal');
if (optional_param('template', false, PARAM_BOOL)) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="plantilla-agentes-orange.csv"');
    echo "\xEF\xBB\xBFusername;firstname;lastname;email;password;idnumber;cohorts;courses\n";
    echo "agente001;Nombre;Apellidos;agente001@example.invalid;;CE001;;\n";
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['process', 'cancel'], true)) {
    require_sesskey();
    try {
        $token = required_param('token', PARAM_ALPHANUM);
        if ($action === 'cancel') {
            $created = importer::cancel($token);
            redirect(orange_link('users.php'), $label('csvcancelled', $created));
        }
        $result = importer::process($token);
        if ($ajax) {
            header('Content-Type: application/json'); echo json_encode($result, JSON_THROW_ON_ERROR); exit;
        }
        redirect($url);
    } catch (Throwable $e) {
        $error = $error_message($e);
        if ($ajax) {
            http_response_code(422); header('Content-Type: application/json');
            echo json_encode(['error' => $error], JSON_THROW_ON_ERROR); exit;
        }
    }
}
orange_page($label('orangeimportusers'), 'administration', $url);
$PAGE->set_primary_active_tab('siteadminnode'); $PAGE->set_secondary_navigation(false);
$PAGE->requires->js(new moodle_url('/local/orangeportal/import_users.js'));
$state = importer::pending(); $report = null; $form = null;
if (!$state || $state['cursor'] === $state['total']) {
    $form = new \local_orangeportal\form\agent_csv($url);
    if ($form->is_cancelled()) { redirect(orange_link('users.php')); }
    if ($data = $form->get_data()) {
        try {
            $content = $form->get_file_content('csvfile');
            if ($content === false) { throw new moodle_exception('csvinvalidfile', 'local_orangeportal'); }
            $report = importer::parse($content, $data->delimiter);
            if (!$report['errors']) { importer::stage($report); }
        } catch (Throwable $e) { $error = $error_message($e); }
        finally {
            // Do not leave the uploaded CSV with passwords in Moodle's draft storage.
            get_file_storage()->delete_area_files(context_user::instance($USER->id)->id, 'user', 'draft', (int)$data->csvfile);
        }
        if (!$error && $report && !$report['errors']) { redirect($url); }
        $form = null;
    }
}
echo $OUTPUT->header();
echo orange_intro('', $label('orangeimportusers'), $label('csvintro'));
echo orange_admin_navigation('users');
echo html_writer::link(orange_link('users.php'), $label('backtolist'));
if ($error) { echo $OUTPUT->notification(s($error), 'error'); }
if ($state) {
    $done = $state['cursor'] === $state['total'];
    echo html_writer::tag('p', $label($done ? 'csvfinished' : 'csvprogress', (object)[
        'created' => $state['cursor'], 'total' => $state['total']]), ['id' => 'orange-import-status', 'aria-live' => 'polite']);
    if (!$done) {
        echo html_writer::tag('progress', '', ['id' => 'orange-import-progress', 'value' => $state['cursor'], 'max' => $state['total']]);
        echo html_writer::div('', 'alert alert-danger', ['id' => 'orange-import-error', 'hidden' => 'hidden', 'role' => 'alert']);
        $params = ['token' => $state['token'], 'action' => 'process'];
        echo html_writer::start_tag('div', ['id' => 'orange-import-runner', 'data-auto' => $state['started'] && !$error ? '1' : '0',
            'data-progress' => $label('csvprogress', (object)['created' => '{created}', 'total' => '{total}']),
            'data-finished' => $label('csvfinished', (object)['created' => '{created}', 'total' => '{total}']),
            'data-failed' => $label('csvnetworkerror')]);
        echo html_writer::tag('p', $label('csvpreviewcount', $state['total']));
        echo orange_post_button($url, $label($state['started'] ? 'csvcontinue' : 'csvconfirm'), $params);
        echo orange_post_button($url, get_string('cancel'), ['token' => $state['token'], 'action' => 'cancel']);
        echo html_writer::end_tag('div');
        $report = ['rows' => importer::preview_rows($state), 'errors' => 0, 'total' => $state['total']];
    }
}
if ($report) {
    if ($report['errors']) { echo $OUTPUT->notification($label('csverrorcount', $report['errors']), 'error'); }
    $rows = $report['rows'];
    if ($report['errors']) { $rows = array_filter($rows, static fn($r) => $r->errors); }
    $table = new html_table(); $table->attributes['class'] = 'generaltable orange-agent-import-preview';
    $table->head = [$label('csvrow'), get_string('username'), get_string('fullnameuser'), get_string('email'),
        $label('csvemployeeid'), $label('orangecollectives'), $label('csvcourses'), $label('csvvalidation')];
    foreach (array_slice($rows, 0, 25) as $row) {
        $table->data[] = [$row->line, s($row->username), s($row->firstname . ' ' . $row->lastname), s($row->email),
            s($row->idnumber), s(implode(' · ', $row->cohortnames)), s(implode(' · ', $row->coursenames ?? [])),
            $row->errors ? s(implode(' · ', $row->errors)) : $label('csvvalid')];
    }
    echo html_writer::tag('p', $label('csvpreviewlimit'));
    echo html_writer::div(html_writer::table($table), 'orange-table-scroll');
}
if ($form) {
    echo $OUTPUT->heading($label('csvuploadtitle'), 3);
    echo html_writer::link(new moodle_url($url, ['template' => 1]), $label('csvdownloadtemplate'), ['class' => 'btn btn-outline-secondary']);
    $catalogue = new html_table();
    $catalogue->head = [$label('csvcourseshortname'), $label('csvcourses')];
    foreach (importer::available_courses() as $course) {
        $catalogue->data[] = [s($course->shortname), s($course->fullname)];
    }
    echo html_writer::tag('details', html_writer::tag('summary', $label('csvcoursecatalogue')) .
        ($catalogue->data ? html_writer::div(html_writer::table($catalogue), 'orange-table-scroll') :
        html_writer::tag('p', $label('csvnocourses'))), ['class' => 'my-3 orange-import-courses']);
    $form->display();
} else if (!$state) {
    echo html_writer::link($url, $label('csvnewupload'), ['class' => 'btn btn-primary']);
}
echo $OUTPUT->footer();
