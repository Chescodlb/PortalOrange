<?php
/** Local-only regression using real native events and the local Postman HTTPS fixture. */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/orangeportal/locallib.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/course/lib.php');
use local_orangeportal\teams;
use local_orangeportal\service;
use local_orangeportal\notice_options;
if (!preg_match('~^http://(localhost|127\.0\.0\.1):~', $CFG->wwwroot)) { throw new coding_exception('Local only'); }
$endpoint = str_replace('--endpoint=', '', $argv[1] ?? '');
if ($endpoint !== 'https://host.docker.internal:4017/teams/invoke') { throw new coding_exception('Local simulation only'); }
// Process-local test settings; never persist a relaxation of the site's HTTP security policy.
$CFG->curlsecurityallowedport = "443\n4017";
$CFG->curlsecurityblockedhosts = '';
function teams_check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException($label); }
    echo "PASS $label\n";
}
function teams_fixture(string $path): array {
    $curl = new curl();
    $reply = $curl->get('https://host.docker.internal:4017/teams/' . $path, [],
        ['CURLOPT_SSL_VERIFYPEER' => true, 'CURLOPT_SSL_VERIFYHOST' => 2]);
    if (json_decode($reply, true) === null) {
        throw new RuntimeException('Local fixture response: ' . substr($reply, 0, 300) . ' errno=' . $curl->get_errno());
    }
    return json_decode($reply, true, 512, JSON_THROW_ON_ERROR);
}
function teams_jobs(): array {
    global $DB;
    return $DB->get_records('task_adhoc', ['classname' => '\\local_orangeportal\\task\\send_teams'], 'id');
}
function teams_notice(int $id): stdClass {
    global $DB;
    return $DB->get_record('local_orange_notice', ['id' => $id], '*', MUST_EXIST);
}
$settings = ['enabled', 'teamsenabled', 'teamsurl', 'teams_created', 'teams_updated', 'teamslastattempt', 'teamslastresult', 'teamslastsuccess'];
foreach (teams::KINDS as $kind) { $settings[] = 'teams_' . $kind; }
$before = [];
foreach ($settings as $key) { $before[$key] = get_config('local_orangeportal', $key); }
$oldjobs = array_keys(teams_jobs()); $courses = []; $notices = []; $category = null;
$original = $USER;
try {
    \core\session\manager::set_user(get_admin());
    teams_check(has_capability('moodle/site:config', context_system::instance()), 'Configuración reservada al administrador general');
    $setting = new \local_orangeportal\admin_setting_teams_url('local_orangeportal/teamsurl', 'Teams', '', '');
    teams_check($setting->validate('') === true && $setting->validate($endpoint) === true &&
        $setting->validate('http://insecure.invalid') !== true && $setting->validate('https://user:password@example.invalid') !== true,
        'URL opcional; solo HTTPS sin credenciales de usuario');
    foreach (['enabled', 'teams_created', 'teams_updated', 'teams_incidents', 'teams_notifications', 'teams_library', 'teams_courses'] as $key) {
        set_config($key, 1, 'local_orangeportal');
    }
    set_config('teamsurl', $endpoint, 'local_orangeportal');
    set_config('teamsenabled', 0, 'local_orangeportal');
    teams::queue('courses', 1, 'created');
    teams_check(array_keys(teams_jobs()) === $oldjobs, 'Integración desactivada no encola');
    set_config('teamsenabled', 1, 'local_orangeportal');
    $agent = $DB->get_record('user', ['username' => 'agente.orange'], '*', MUST_EXIST);
    $targets = ['user' => [(int)$agent->id]];
    $orangeadmin = $DB->get_record('user', ['username' => 'admin.orange'], '*', MUST_EXIST);
    teams_check(!has_capability('moodle/site:config', context_system::instance(), $orangeadmin->id),
        'Administrador Orange sin configuración general no puede cambiar el servicio');
    \core\session\manager::set_user($orangeadmin);
    foreach (['incidents', 'notifications'] as $kind) {
        $catid = $DB->get_field('local_orange_category', 'id', ['kind' => $kind], IGNORE_MULTIPLE);
        $data = (object)['kind' => $kind, 'categoryid' => $catid, 'title' => 'TEST Teams ' . $kind,
            'content' => '<p>Descripción de prueba</p>', 'published' => 0, 'state' => notice_options::default_code('states'),
            'impact' => notice_options::default_code('impacts'), 'client' => '', 'product' => ''];
        $id = service::save_notice($data, []); $notices[] = $id;
        teams_check(!teams::payload($kind, $id, 'created'), "$kind borrador excluido");
        $data = teams_notice($id); $data->published = 1; $data->orangenotifyteams = 1;
        service::save_notice($data, $targets);
        $jobs = teams_jobs(); $job = end($jobs); $custom = json_decode($job->customdata);
        teams_check($custom->kind === $kind && $custom->action === 'created' && $custom->id === $id,
            "$kind primera publicación encola alta");
        $count = count($jobs);
        service::save_notice(teams_notice($id), $targets);
        teams_check(count(teams_jobs()) === $count, "$kind guardar sin cambios no avisa");
        $data = teams_notice($id); $data->orangenotifyteams = 1; $data->title .= ' modificado'; service::save_notice($data, $targets);
        teams_check(count(teams_jobs()) === $count + 1, "$kind modificar texto encola actualización");
        if ($kind === 'incidents') {
            $count = count(teams_jobs());
            $states = array_keys(service::states(true));
            $state = array_values(array_diff($states, [teams_notice($id)->state]))[0];
            service::change_state(teams_notice($id), $state);
            $data = teams_notice($id); $data->state = notice_options::default_code('states');
            service::save_notice($data, $targets);
            teams_check(count(teams_jobs()) === $count, 'Cambios de estado desde ficha o edición no generan avisos');
        }
    }
    teams_check(count(teams_fixture('__stats')['requests']) === 0, 'Guardar publicaciones no ejecuta HTTP');
    \core\session\manager::set_user(get_admin());
    foreach (['library', 'courses', 'outside'] as $kind) {
        if ($kind === 'outside') {
            $category = core_course_category::create(['name' => 'TEST Teams outside Orange', 'parent' => 0]);
            $catid = $category->id;
        } else { $catid = (int)get_config('local_orangeportal', $kind === 'library' ? 'librarycategory' : 'coursescategory'); }
        $count = count(teams_jobs());
        $course = create_course((object)['category' => $catid, 'fullname' => 'TEST Teams ' . $kind,
            'shortname' => 'TEAMS-' . $kind . '-' . uniqid(), 'summary' => '<p>Resumen</p>', 'visible' => $kind === 'courses' ? 0 : 1,
            'orangeaudiencepresent' => (int)($kind === 'courses'), 'orangepublished' => 1, 'orangenotifyteams' => 1]);
        $courses[] = (int)$course->id;
        teams_check(count(teams_jobs()) === $count + ($kind === 'courses' ? 1 : 0), "$kind evento nativo de curso respeta ámbito");
        $data = (object)['modulename' => 'page', 'course' => $course->id, 'section' => 0, 'visible' => 1,
            'orangeaudiencepresent' => (int)($kind === 'library'), 'orangepublished' => 1, 'orangenotifyteams' => 1,
            'name' => 'TEST Teams document', 'introeditor' => ['text' => '<p>Intro</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
            'content' => '<p>Contenido</p>', 'contentformat' => FORMAT_HTML, 'display' => 0, 'completion' => 0];
        $count = count(teams_jobs()); $module = create_module($data);
        teams_check(count(teams_jobs()) === $count + ($kind === 'library' ? 1 : 0), "$kind evento nativo de recurso respeta ámbito");
        if ($kind === 'library') {
            $docid = (int)$module->coursemodule;
            $count = count(teams_jobs());
            $module->name .= ' actualizado';
            $module->instance = $DB->get_field('course_modules', 'instance', ['id' => $docid], MUST_EXIST);
            $module->page = ['text' => '<p>Contenido actualizado</p>', 'format' => FORMAT_HTML, 'itemid' => 0];
            $module->introeditor = ['text' => '<p>Intro actualizada</p>', 'format' => FORMAT_HTML, 'itemid' => 0];
            $module->revision = 1;
            $module->printintro = 0; $module->printlastmodified = 1;
            update_module($module);
            teams_check(count(teams_jobs()) === $count + 1, 'Editar documento nativo encola actualización');
            $DB->set_field('course_modules', 'visible', 0, ['id' => $docid]);
            teams_check(teams::payload('library', $docid, 'updated') === null, 'Documento oculto no se envía');
            $DB->set_field('course_modules', 'visible', 1, ['id' => $docid]);
        }
        if ($kind === 'courses') {
            $course->orangeaudiencepresent = 1; $course->orangepublished = 1; $course->orangenotifyteams = 1;
            $course->fullname .= ' actualizado'; update_course($course);
            teams_check(str_contains(teams::payload('courses', $course->id, 'updated')['titulo'], 'actualizado'),
                'La formación usa el contenido actual');
            $DB->set_field('course', 'visible', 0, ['id' => $course->id]);
            teams_check(teams::payload('courses', $course->id, 'updated') === null, 'Curso oculto no se envía');
            $DB->set_field('course', 'visible', 1, ['id' => $course->id]);
        }
    }
    $payload = teams::payload('incidents', $notices[0], 'created');
    teams_check(array_keys($payload) === ['titulo', 'contenido', 'url'] && !str_contains($payload['contenido'], '<p>') &&
        str_contains($payload['titulo'], 'Nueva incidencia:'), 'Contrato exacto y contenido sin HTML');
    // A notice resource is an update of its publication, never an extra Library/training announcement.
    $DB->insert_record('local_orange_container', (object)['kind' => 'notice', 'categoryid' => 0,
        'noticeid' => $notices[1], 'courseid' => $courses[0]]);
    foreach (teams_jobs() as $job) {
        $custom = json_decode($job->customdata);
        if ($custom->kind === 'notifications' && $custom->id === $notices[1] && $custom->action === 'updated') {
            $DB->delete_records('task_adhoc', ['id' => $job->id]);
        }
    }
    $count = count(teams_jobs());
    $data->course = $courses[0]; $data->visible = 1; $data->name = 'TEST Teams notice resource';
    $data->introeditor = ['text' => '<p>Intro</p>', 'format' => FORMAT_HTML, 'itemid' => 0];
    create_module($data);
    $jobs = teams_jobs();
    teams_check(count($jobs) === $count, 'Añadir recurso de comunicado no avisa sin consentimiento desde su publicación');
    foreach (teams_jobs() as $job) {
        if (in_array($job->id, $oldjobs)) { continue; }
        $task = \core\task\manager::adhoc_task_from_record($job);
        teams_check(!str_contains($job->customdata, $endpoint) && !str_contains($job->customdata, 'Descripción'),
            'Cola sin URL secreta ni texto publicado');
        $task->execute();
        $DB->delete_records('task_adhoc', ['id' => $job->id]);
    }
    $state = teams_fixture('__stats');
    teams_check(count($state['requests']) >= 4 && strlen($state['requests'][0]['eventid']) === 64 &&
        str_starts_with($state['requests'][0]['type'], 'application/json'), 'Entrega HTTPS real a simulación con JSON e ID estable');
    foreach ([400, 429, 500, 302] as $code) {
        teams_fixture('__control?code=' . $code);
        try { teams::post($endpoint, $payload); throw new coding_exception('Expected HTTP failure'); }
        catch (RuntimeException $e) {
            teams_check($e->getMessage() === 'Teams: HTTP ' . $code && !str_contains($e->getMessage(), $endpoint),
                "HTTP $code falla sin filtrar URL ni seguir redirecciones");
        }
    }
    teams_fixture('__control?code=202');
    teams::queue('incidents', $notices[0], 'updated'); $jobs = teams_jobs(); $job = end($jobs);
    $task = \core\task\manager::adhoc_task_from_record($job);
    teams_check($task->get_attempts_available() === 5 && $task->retry_until_success(), 'Moodle conserva cola y hasta cinco intentos');
    $count = count(teams_fixture('__stats')['requests']);
    $DB->set_field('local_orange_notice', 'published', 0, ['id' => $notices[0]]); $task->execute();
    teams_check(count(teams_fixture('__stats')['requests']) === $count, 'Retirada antes del envío descarta aviso');
    $DB->set_field('local_orange_notice', 'published', 1, ['id' => $notices[0]]);
    set_config('teamsurl', $endpoint . '?changed=1', 'local_orangeportal'); $task->execute();
    teams_check(count(teams_fixture('__stats')['requests']) === $count, 'Cambio de destino no reenvía avisos antiguos');
    set_config('teamsurl', $endpoint, 'local_orangeportal');
    set_config('teamsenabled', 0, 'local_orangeportal'); $task->execute();
    teams_check(count(teams_fixture('__stats')['requests']) === $count, 'Desactivar bloquea también tareas ya encoladas');
    set_config('teamsenabled', 1, 'local_orangeportal');
    teams_fixture('__control?code=500');
    $task = \core\task\manager::get_adhoc_task($job->id);
    \core\task\manager::adhoc_task_starting($task);
    try { $task->execute(); throw new coding_exception('Expected provider error'); }
    catch (RuntimeException $e) { \core\task\manager::adhoc_task_failed($task); }
    $failed = $DB->get_record('task_adhoc', ['id' => $job->id], '*', MUST_EXIST);
    teams_check((int)$failed->attemptsavailable === 4 && (int)$failed->faildelay === 60 && (int)$failed->nextruntime >= time() + 59,
        'Fallo de servicio conserva tarea y programa reintento real de Moodle');
    teams_fixture('__control?code=202');
    $task = \core\task\manager::get_adhoc_task($job->id);
    \core\task\manager::adhoc_task_starting($task); $task->execute();
    \core\task\manager::adhoc_task_complete($task);
    teams_check(!$DB->record_exists('task_adhoc', ['id' => $job->id]), 'Reintento aceptado elimina tarea de la cola');
} catch (\dml_exception $e) {
    throw new RuntimeException('Local fixture database failure: ' . $e->debuginfo);
} finally {
    \core\session\manager::set_user(get_admin());
    set_config('teamsenabled', 0, 'local_orangeportal');
    foreach ($courses as $courseid) { delete_course($courseid, false); }
    if ($category) { $category->delete_full(false); }
    foreach ($notices as $id) {
        foreach (['local_orange_revision', 'local_orange_audience', 'local_orange_receipt', 'local_orange_state_log'] as $table) {
            $DB->delete_records($table, ['noticeid' => $id]);
        }
        $DB->delete_records('local_orange_notice', ['id' => $id]);
    }
    foreach (teams_jobs() as $job) { if (!in_array($job->id, $oldjobs)) { $DB->delete_records('task_adhoc', ['id' => $job->id]); } }
    foreach ($before as $key => $value) {
        if ($value === false) { unset_config($key, 'local_orangeportal'); }
        else { set_config($key, $value, 'local_orangeportal'); }
    }
    \core\session\manager::set_user($original);
    cache::make('core', 'coursemodinfo')->purge(); course_modinfo::clear_instance_cache();
}
