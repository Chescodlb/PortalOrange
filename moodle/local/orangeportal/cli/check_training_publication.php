<?php
/** Real publication/queue/HTTPS regression using the local Postman service only. */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/orangeportal/locallib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/edit_form.php');
use local_orangeportal\training_publication as publication;
if (!str_starts_with($CFG->wwwroot, 'http://localhost:') || ($argv[1] ?? '') !== '--endpoint=https://host.docker.internal:4017/teams/invoke') {
    throw new coding_exception('Local simulation only');
}
$endpoint = 'https://host.docker.internal:4017/teams/invoke';
$CFG->forced_plugin_settings['local_orangeportal']['teamsurl'] = $endpoint;
foreach (['enabled', 'teamsenabled', 'teams_courses', 'teams_created', 'teams_updated'] as $setting) {
    $CFG->forced_plugin_settings['local_orangeportal'][$setting] = 1;
}
$CFG->curlsecurityallowedport = "443\n4017"; $CFG->curlsecurityblockedhosts = '';
function publish_check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException($label); } echo "PASS $label\n";
}
function publish_jobs(int $id): array {
    global $DB;
    return array_filter($DB->get_records('task_adhoc', ['classname' => '\\local_orangeportal\\task\\send_teams'], 'id'),
        fn($job) => ($data = json_decode($job->customdata)) && $data->kind === 'courses' && (int)$data->id === $id);
}
function publish_stats(): array {
    $curl = new curl();
    return json_decode($curl->get('https://host.docker.internal:4017/teams/__stats', [],
        ['CURLOPT_SSL_VERIFYPEER' => true, 'CURLOPT_SSL_VERIFYHOST' => 2]), true, 512, JSON_THROW_ON_ERROR);
}
$status = [];
foreach (['teamslastattempt', 'teamslastsuccess', 'teamslastresult'] as $key) { $status[$key] = get_config('local_orangeportal', $key); }
$trainer = $DB->get_record('user', ['username' => 'formador.orange'], '*', MUST_EXIST);
$agent = $DB->get_record('user', ['username' => 'agente.orange'], '*', MUST_EXIST);
$id = 0;
try {
    \core\session\manager::set_user($trainer);
    $root = \local_orangeportal\native_categories::root('courses');
    $PAGE->set_context(context_coursecat::instance($root->id));
    $return = orange_link('index.php', ['section' => 'courses']);
    $PAGE->set_url('/course/edit.php', ['category' => $root->id, 'returnto' => 'url', 'returnurl' => $return->out(false)]);
    course_edit_form::mock_submit(['fullname' => 'TEST publication workflow', 'shortname' => 'TEST-PUBLICATION-' . uniqid(),
        'category' => $root->id, 'summary_editor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
        'saveanddisplay' => 1, 'format' => 'topics', 'visible' => 1]);
    $form = new course_edit_form(null, ['course' => new stdClass(), 'category' => $root,
        'editoroptions' => ['maxfiles' => -1, 'maxbytes' => $CFG->maxbytes, 'context' => $PAGE->context],
        'returnto' => 'url', 'returnurl' => $return]);
    $data = $form->get_data();
    publish_check($data && (int)$data->visible === 0, 'Nueva formación queda en borrador aunque se altere el POST para mostrarla');
    $data->summary = ''; $data->summaryformat = FORMAT_HTML;
    $course = create_course($data); $id = (int)$course->id;
    enrol_try_internal_enrol($id, $trainer->id, $CFG->creatornewroleid);
    $students = get_archetype_roles('student'); enrol_try_internal_enrol($id, $agent->id, reset($students)->id);
    $quality = $DB->get_record('user', ['username' => 'calidad.orange'], '*', MUST_EXIST);
    $teachers = get_archetype_roles('teacher'); enrol_try_internal_enrol($id, $quality->id, reset($teachers)->id);
    publish_check(!\local_orangeportal\content_access::author($id, $quality->id) &&
        !\local_orangeportal\content_access::course($id, $quality->id), 'Un perfil de consulta tampoco recibe acceso al borrador');
    publish_check(\local_orangeportal\content_access::recipient($id, $agent->id), 'El borrador permite seleccionar y matricular destinatarios antes de publicar');
    $PAGE->set_url('/local/orangeportal/training_setup.php', ['course' => $id]);
    publish_check(!publish_jobs($id) && !get_course($id)->visible && !publication::record($id)->firstpublished,
        'Guardar un borrador no encola avisos ni marca primera publicación');
    update_course((object)['id' => $id, 'fullname' => 'TEST publication draft edit']);
    create_module((object)['modulename' => 'page', 'course' => $id, 'section' => 0, 'visible' => 1,
        'name' => 'TEST draft resource', 'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
        'content' => '<p>TEST</p>', 'contentformat' => FORMAT_HTML, 'display' => 0, 'completion' => 0]);
    publish_check(!publish_jobs($id), 'Editar configuración y añadir recursos en borrador no genera avisos');
    \core\session\manager::set_user($agent);
    publish_check(!isset(orange_native_courses($root->id)[$id]), 'El alumno matriculado no ve el borrador en Formaciones');
    try { require_login($id, false, null, false, true); throw new RuntimeException('Draft access unexpectedly allowed'); }
    catch (require_login_exception $e) { publish_check(true, 'Acceso nativo del alumno al borrador rechazado'); }
    try { publication::set_visible($id, true, true, 0); throw new RuntimeException('Agent publication unexpectedly allowed'); }
    catch (moodle_exception $e) { publish_check(true, 'El agente no puede publicar'); }
    \core\session\manager::set_user($trainer);
    publish_check(publication::can_publish($id), 'Formador tiene permiso nativo para publicar su formación');
    publication::set_visible($id, true, true, publication::generation($id));
    $jobs = publish_jobs($id); $job = reset($jobs); $custom = json_decode($job->customdata);
    publish_check(count($jobs) === 1 && $custom->action === 'created' && $custom->generation === publication::generation($id),
        'Primera publicación encola un único aviso de nueva formación');
    $task = \core\task\manager::adhoc_task_from_record($job); $task->execute();
    $DB->delete_records('task_adhoc', ['id' => $job->id]);
    $stats = publish_stats();
    publish_check(count($stats['requests']) === 1 && str_starts_with($stats['requests'][0]['body']['titulo'], 'Nueva formación:'),
        'El servicio simulado recibe el aviso solo después de publicar');
    $oldcreated = clone get_course($id); $oldcreated->timecreated = time() - 30 * DAYSECS;
    publish_check(\local_orangeportal\personal_updates::new_course($oldcreated, [], time() - HOURSECS),
        'Indicador de novedades usa publicación, aunque el borrador se creara antes');
    \core\session\manager::set_user($agent);
    publish_check(isset(orange_native_courses($root->id)[$id]), 'La formación publicada es visible al alumno matriculado');
    \core\session\manager::set_user($trainer);
    update_course((object)['id' => $id, 'fullname' => 'TEST publication live edit', 'orangeaudiencepresent' => 1, 'orangepublished' => 1, 'orangenotifyteams' => 1]);
    $jobs = publish_jobs($id); $oldjob = reset($jobs); $oldtask = \core\task\manager::adhoc_task_from_record($oldjob);
    publish_check(count($jobs) === 1 && json_decode($oldjob->customdata)->action === 'updated', 'Editar una formación publicada con casilla Teams solicita el aviso de modificación');
    $oldgeneration = publication::generation($id);
    $resources = $DB->count_records('course_modules', ['course' => $id]);
    publication::set_visible($id, false, false, $oldgeneration);
    publish_check(!publish_jobs($id) && !$DB->get_field('course', 'visible', ['id' => $id]), 'Retirar oculta el curso y cancela avisos pendientes');
    $enrolled = is_enrolled(context_course::instance($id), $agent->id, '', true);
    publish_check($enrolled && $DB->count_records('course_modules', ['course' => $id]) === $resources, 'Retirar conserva matrículas y recursos');
    try { publication::set_visible($id, true, true, $oldgeneration); throw new RuntimeException('Stale publication unexpectedly accepted'); }
    catch (moodle_exception $e) { publish_check($e->errorcode === 'trainingpublicationchanged', 'Formulario antiguo no puede publicar accidentalmente'); }
    publication::set_visible($id, true, false, publication::generation($id));
    publish_check(!publish_jobs($id) && $DB->get_field('course', 'visible', ['id' => $id]), 'Publicar sin marcar Teams muestra el curso sin encolar avisos');
    $oldtask->execute();
    publish_check(count(publish_stats()['requests']) === 1, 'Un aviso anterior retirado no revive tras publicar sin Teams');
    update_course((object)['id' => $id, 'fullname' => 'TEST publication final live edit', 'orangeaudiencepresent' => 1, 'orangepublished' => 1, 'orangenotifyteams' => 1]);
    $jobs = publish_jobs($id); $job = reset($jobs);
    \core\task\manager::adhoc_task_from_record($job)->execute();
    $DB->delete_records('task_adhoc', ['id' => $job->id]);
    publish_check(count(publish_stats()['requests']) === 2, 'Las modificaciones posteriores requieren marcar Teams de nuevo');
} finally {
    \core\session\manager::set_user(get_admin());
    if ($id) { delete_course($id, false); }
    foreach ($status as $key => $value) {
        if ($value === false) { unset_config($key, 'local_orangeportal'); }
        else { set_config($key, $value, 'local_orangeportal'); }
    }
    cache::make('core', 'coursemodinfo')->purge(); course_modinfo::clear_instance_cache();
}
