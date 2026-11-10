<?php
/** Local-only explicit-save consent regression against the existing HTTPS Teams simulation. */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/orangeportal/locallib.php');
require_once($CFG->dirroot . '/course/lib.php');
use local_orangeportal\training_publication as publication;
use local_orangeportal\service;
if (!str_starts_with($CFG->wwwroot, 'http://localhost:') || ($argv[1] ?? '') !== '--endpoint=https://host.docker.internal:4017/teams/invoke') {
    throw new coding_exception('Local simulation only');
}
$CFG->forced_plugin_settings['local_orangeportal']['teamsurl'] = 'https://host.docker.internal:4017/teams/invoke';
foreach (['enabled', 'teamsenabled', 'teams_created', 'teams_updated', 'teams_courses', 'teams_library', 'teams_incidents', 'teams_notifications'] as $key) {
    $CFG->forced_plugin_settings['local_orangeportal'][$key] = 1;
}
$CFG->curlsecurityallowedport = "443\n4017"; $CFG->curlsecurityblockedhosts = '';
function save_check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException($label); } echo "PASS $label\n";
}
function save_jobs(string $kind, int $id): array {
    global $DB;
    return array_filter($DB->get_records('task_adhoc', ['classname' => '\\local_orangeportal\\task\\send_teams'], 'id'),
        fn($j) => ($data = json_decode($j->customdata)) && $data->kind === $kind && (int)$data->id === $id);
}
function save_dispatch(string $kind, int $id): void {
    global $DB;
    $jobs = save_jobs($kind, $id);
    save_check(count($jobs) === 1, "$kind casilla marcada encola exactamente un aviso");
    $job = reset($jobs); \core\task\manager::adhoc_task_from_record($job)->execute();
    $DB->delete_records('task_adhoc', ['id' => $job->id]);
}
$previous = [];
foreach (['teamslastattempt', 'teamslastsuccess', 'teamslastresult'] as $key) { $previous[$key] = get_config('local_orangeportal', $key); }
$courses = []; $notices = [];
\core\session\manager::set_user(get_admin());
try {
    foreach (['library', 'courses'] as $kind) {
        $course = create_course((object)['category' => (int)get_config('local_orangeportal', $kind === 'library' ? 'librarycategory' : 'coursescategory'),
            'fullname' => 'TEST save consent ' . $kind, 'shortname' => 'TEST-SAVE-CONSENT-' . uniqid(), 'summary' => '', 'summaryformat' => FORMAT_HTML, 'visible' => $kind === 'library' ? 1 : 0]);
        $courses[$kind] = (int)$course->id;
    }
    $inline = create_course((object)['category' => (int)get_config('local_orangeportal', 'coursescategory'),
        'fullname' => 'TEST inline published training', 'shortname' => 'TEST-SAVE-CONSENT-' . uniqid(),
        'summary' => '', 'summaryformat' => FORMAT_HTML, 'visible' => 0, 'orangeaudiencepresent' => 1,
        'orangepublished' => 1, 'orangenotifyteams' => 1]);
    $courses['inline'] = (int)$inline->id;
    save_check($DB->get_field('course', 'visible', ['id' => $inline->id]) && publication::record($inline->id)->firstpublished > 0,
        'Creación con Visible marcado publica después de guardar destinatarios');
    save_dispatch('courses', (int)$inline->id);
    $id = $courses['courses'];
    $course = (object)['id' => $id, 'orangeaudiencepresent' => 1, 'orangepublished' => 0, 'orangenotifyteams' => 1];
    update_course($course);
    save_check(!save_jobs('courses', $id), 'Formación en borrador no avisa aunque se intente marcar Teams');
    $course->orangepublished = 1; $course->orangenotifyteams = 0;
    update_course($course);
    save_check(get_course($id)->visible && !save_jobs('courses', $id), 'Publicar desde destinatarios sin casilla Teams no encola avisos');
    $course->orangenotifyteams = 1; $course->fullname = 'TEST save consent training update';
    update_course($course);
    save_dispatch('courses', $id);
    update_course((object)['id' => $id, 'fullname' => 'TEST automatic edit is silent']);
    save_check(!save_jobs('courses', $id), 'Cambios sin consentimiento explícito no generan avisos de formación');
    $course->fullname = 'TEST queued before silent edit'; update_course($course);
    $pending = save_jobs('courses', $id); $pending = reset($pending);
    update_course((object)['id' => $id, 'fullname' => 'TEST silent newer content']);
    \core\task\manager::adhoc_task_from_record($pending)->execute();
    $DB->delete_records('task_adhoc', ['id' => $pending->id]);
    $curl = new curl(); $stats = json_decode($curl->get('https://host.docker.internal:4017/teams/__stats'), true, 512, JSON_THROW_ON_ERROR);
    save_check(count($stats['requests']) === 2, 'Una edición silenciosa posterior no se anuncia mediante una tarea antigua');
    $course->orangepublished = 0; update_course($course);
    save_check(!get_course($id)->visible && !save_jobs('courses', $id), 'Desmarcar Visible retira formación sin avisar');
    $document = create_module((object)['modulename' => 'page', 'course' => $courses['library'], 'section' => 0,
        'visible' => 0, 'orangeaudiencepresent' => 1, 'orangepublished' => 0, 'orangenotifyteams' => 1,
        'name' => 'TEST save document', 'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
        'content' => '<p>TEST</p>', 'contentformat' => FORMAT_HTML, 'display' => 0, 'completion' => 0]);
    $cmid = (int)$document->coursemodule;
    save_check(!$DB->get_field('course_modules', 'visible', ['id' => $cmid]) && !save_jobs('library', $cmid), 'Documento en borrador tampoco avisa');
    $document->page = ['text' => '<p>TEST</p>', 'format' => FORMAT_HTML, 'itemid' => 0];
    $document->introeditor = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0];
    $document->orangepublished = 1; $document->orangenotifyteams = 0; $document->update = $cmid;
    update_module($document);
    save_check($DB->get_field('course_modules', 'visible', ['id' => $cmid]) && !save_jobs('library', $cmid), 'Publicar documento sin marcar Teams no avisa');
    $document->introeditor = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0];
    $document->orangenotifyteams = 1; $document->name = 'TEST save document with announcement';
    update_module($document);
    save_dispatch('library', $cmid);
    foreach (['incidents', 'notifications'] as $kind) {
        $record = (object)['kind' => $kind, 'title' => 'TEST save consent ' . $kind,
            'categoryid' => $DB->get_field('local_orange_category', 'id', ['kind' => $kind], IGNORE_MULTIPLE),
            'content' => '<p>TEST</p>', 'published' => 1, 'client' => '', 'product' => '',
            'state' => \local_orangeportal\notice_options::default_code('states'),
            'impact' => \local_orangeportal\notice_options::default_code('impacts'), 'orangenotifyteams' => 0];
        $record->id = service::save_notice($record, []); $notices[] = $record->id;
        save_check(!save_jobs($kind, $record->id), "$kind publicación visible sin marcar Teams no avisa");
        $record->orangenotifyteams = 1; $record->content = '<p>TEST updated</p>';
        service::save_notice($record, []);
        save_dispatch($kind, $record->id);
        if ($kind === 'incidents') {
            $otherstates = array_values(array_diff(array_keys(service::states(true)), [$record->state]));
            if ($otherstates) {
                $record->state = $otherstates[0]; service::save_notice($record, []);
                save_check(!save_jobs($kind, $record->id), 'Cambiar solo el estado sigue sin generar aviso Teams');
            }
        }
        $record->published = 0; service::save_notice($record, []);
        save_check(!save_jobs($kind, $record->id), "$kind borrador ignora el aviso aunque esté marcado");
    }
    $curl = new curl(); $stats = json_decode($curl->get('https://host.docker.internal:4017/teams/__stats'), true, 512, JSON_THROW_ON_ERROR);
    save_check(count($stats['requests']) === 5, 'El simulador recibe solo cinco POST autorizados: alta y edición de formación, documento, incidencia y comunicado');
} catch (Throwable $e) {
    echo get_class($e) . ": " . $e->getMessage() . "\n" . ($e->debuginfo ?? "") . "\n" . $e->getTraceAsString() . "\n";
    throw $e;
} finally {
    foreach ($courses as $id) {
        foreach (['courses', 'library'] as $kind) {
            foreach (save_jobs($kind, $id) as $job) { $DB->delete_records('task_adhoc', ['id' => $job->id]); }
        }
        delete_course($id, false);
    }
    foreach ($notices as $id) {
        foreach (['incidents', 'notifications'] as $kind) { foreach (save_jobs($kind, $id) as $job) { $DB->delete_records('task_adhoc', ['id' => $job->id]); } }
        foreach (['local_orange_revision', 'local_orange_audience', 'local_orange_receipt', 'local_orange_state_log'] as $table) { $DB->delete_records($table, ['noticeid' => $id]); }
        $DB->delete_records('local_orange_notice', ['id' => $id]);
    }
    foreach ($previous as $key => $value) { if ($value === false) { unset_config($key, 'local_orangeportal'); } else { set_config($key, $value, 'local_orangeportal'); } }
    cache::make('core', 'coursemodinfo')->purge(); course_modinfo::clear_instance_cache();
}
