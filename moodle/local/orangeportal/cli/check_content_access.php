<?php
/** Local-only end-to-end access regression; temporary users, courses and collectives are deleted. */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/orangeportal/locallib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
use local_orangeportal\content_access as access;
if (!str_starts_with($CFG->wwwroot, 'http://localhost:')) {
    throw new coding_exception('Local only');
}
// Do not change the saved Teams configuration or queue real messages from test fixtures.
$CFG->forced_plugin_settings['local_orangeportal']['teamsenabled'] = 0;
function content_check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException($label); } echo "PASS $label\n";
}
function content_denied(callable $action, string $label): void {
    try { $action(); } catch (moodle_exception $e) { content_check(true, $label); return; }
    throw new RuntimeException($label);
}
$base = (int)get_config('local_orangeportal', 'audiencecohort');
$users = []; $courses = []; $cohorts = []; $noticeid = 0;
\core\session\manager::set_user(get_admin());
try {
    foreach (['a', 'b', 'external'] as $key) {
        $user = (object)['auth' => 'manual', 'confirmed' => 1, 'mnethostid' => $CFG->mnet_localhost_id,
            'username' => 'test.orange.access.' . $key . '.' . uniqid(), 'firstname' => 'TEST access', 'lastname' => $key,
            'email' => uniqid() . '@example.invalid', 'password' => hash_internal_user_password(random_string(24))];
        $user->id = user_create_user($user, false, false); $users[$key] = $user;
        if ($key !== 'external') { cohort_add_member($base, $user->id); }
    }
    foreach (['a', 'b', 'external'] as $key) {
        $cohorts[$key] = cohort_add_cohort((object)['name' => 'TEST collective ' . $key,
            'contextid' => context_system::instance()->id, 'component' => 'local_orangeportal', 'description' => '', 'descriptionformat' => FORMAT_HTML]);
        cohort_add_member($cohorts[$key], $users[$key]->id);
    }
    $students = get_archetype_roles('student'); $student = reset($students);
    $trainer = $DB->get_record('user', ['username' => 'formador.orange'], '*', MUST_EXIST);
    $trainingcontext = context_coursecat::instance((int)get_config('local_orangeportal', 'coursescategory'));
    $librarycontext = context_coursecat::instance((int)get_config('local_orangeportal', 'librarycategory'));
    content_check(has_capability('local/orangeportal:enrolusers', $trainingcontext, $trainer->id) &&
        !has_capability('local/orangeportal:enrolusers', context_system::instance(), $trainer->id) &&
        !has_capability('local/orangeportal:enrolusers', $librarycontext, $trainer->id),
        'Formador puede matricular solo en Formaciones Orange, sin permiso global ni en Biblioteca');
    content_check(!has_capability('local/orangeportal:enrolusers', $trainingcontext, $users['a']->id),
        'Agente no recibe permiso de matrícula');
    foreach (['library', 'courses'] as $kind) {
        $root = (int)get_config('local_orangeportal', $kind === 'library' ? 'librarycategory' : 'coursescategory');
        $context = context_coursecat::instance($root);
        content_check(has_capability('moodle/category:viewcourselist', $context, $users['a']->id), "$kind catálogo visible para Orange");
        content_check(!has_capability('moodle/category:viewcourselist', $context, $users['external']->id), "$kind catálogo excluye externos");
        $course = create_course((object)['fullname' => 'TEST access ' . $kind, 'shortname' => 'TEST-ACCESS-' . uniqid(),
            'category' => $root, 'visible' => 1]);
        $courses[$kind] = (int)$course->id;
        $plugin = enrol_get_plugin('manual');
        $instances = array_filter(enrol_get_instances($course->id, true), fn($e) => $e->enrol === 'manual');
        $instance = reset($instances);
        $enrolid = $instance ? $instance->id : $plugin->add_instance($course);
        $instance = $DB->get_record('enrol', ['id' => $enrolid]);
        foreach ($users as $user) { $plugin->enrol_user($instance, $user->id, $student->id); }
        content_check(access::course($course->id, $users['a']->id) && access::course($course->id, $users['b']->id), "$kind sin selección permite a Orange");
        content_check(!access::course($course->id, $users['external']->id), "$kind externo matriculado sigue sin acceso");
        \core\session\manager::set_user($users['external']);
        content_denied(fn() => access::guard($course->id), "$kind enlace directo denegado al externo");
        \core\session\manager::set_user(get_admin());
    }
    $doc = create_module((object)['modulename' => 'page', 'course' => $courses['library'], 'section' => 0, 'visible' => 1,
        'name' => 'TEST access document', 'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
        'content' => '<p>TEST access</p>', 'contentformat' => FORMAT_HTML, 'display' => 0, 'completion' => 0]);
    $cmid = (int)$doc->coursemodule;
    access::save('module', $cmid, [$cohorts['a']]);
    content_check(access::module($cmid, $users['a']->id) && !access::module($cmid, $users['b']->id), 'Documento limitado al colectivo elegido');
    $raw = $DB->get_field('course_modules', 'availability', ['id' => $cmid]);
    new \core_availability\tree(json_decode($raw));
    content_check((bool)get_fast_modinfo($courses['library'], $users['a']->id)->get_cm($cmid)->uservisible &&
        !get_fast_modinfo($courses['library'], $users['b']->id)->get_cm($cmid)->uservisible,
        'Restricción nativa oculta el documento a otro colectivo');
    \core\session\manager::set_user($users['b']);
    $cm = get_fast_modinfo($courses['library'])->get_cm($cmid);
    content_denied(fn() => require_login($courses['library'], false, $cm), 'Apertura nativa rechazada fuera del colectivo');
    \core\session\manager::set_user(get_admin());
    access::save('module', $cmid, [$cohorts['a'], $cohorts['b']]);
    content_check(access::module($cmid, $users['a']->id) && access::module($cmid, $users['b']->id), 'Varios colectivos se combinan con OR');
    $tree = json_decode($DB->get_field('course_modules', 'availability', ['id' => $cmid]), true);
    $tree['c'][0]['c'] = array_reverse($tree['c'][0]['c']);
    unset($tree['c'][0]['show']);
    $DB->set_field('course_modules', 'availability', json_encode($tree), ['id' => $cmid]);
    access::save('module', $cmid, [$cohorts['b']]);
    content_check(get_fast_modinfo($courses['library'], $users['b']->id)->get_cm($cmid)->uservisible &&
        !get_fast_modinfo($courses['library'], $users['a']->id)->get_cm($cmid)->uservisible,
        'Cambiar colectivos tras serialización nativa no duplica ni acumula restricciones antiguas');
    access::save('module', $cmid, [$cohorts['a'], $cohorts['b']]);
    cohort_remove_member($cohorts['a'], $users['a']->id);
    content_check(!access::module($cmid, $users['a']->id) &&
        !get_fast_modinfo($courses['library'], $users['a']->id)->get_cm($cmid)->uservisible, 'Baja del colectivo revoca acceso nativo y del portal');
    cohort_add_member($cohorts['a'], $users['a']->id);
    content_check(access::module($cmid, $users['a']->id), 'Alta en colectivo restaura acceso sin reeditar documento');
    $date = ['type' => 'date', 'd' => '>=', 't' => time() + DAYSECS];
    $tree = json_decode($DB->get_field('course_modules', 'availability', ['id' => $cmid]), true);
    $tree['c'][] = $date; $tree['showc'][] = false;
    $DB->set_field('course_modules', 'availability', json_encode($tree), ['id' => $cmid]);
    access::save('module', $cmid, []);
    $tree = json_decode($DB->get_field('course_modules', 'availability', ['id' => $cmid]), true);
    content_check(in_array($date, $tree['c']) && count($tree['c']) === 1, 'Quitar colectivos conserva restricciones nativas adicionales');
    // Individual and cross-course group targets combine with cohorts, never with outsiders.
    $source = groups_create_group((object)['courseid' => $courses['courses'], 'name' => 'TEST source recipients',
        'description' => '', 'descriptionformat' => FORMAT_HTML]);
    groups_add_member($source, $users['b']->id);
    course_modinfo::clear_instance_cache($courses['library']);
    // Remove the extra date restriction before testing the audience itself.
    $DB->set_field('course_modules', 'availability', null, ['id' => $cmid]);
    access::save('module', $cmid, [], [$users['a']->id]);
    content_check(access::module($cmid, $users['a']->id) && !access::module($cmid, $users['b']->id) &&
        get_fast_modinfo($courses['library'], $users['a']->id)->get_cm($cmid)->uservisible &&
        !get_fast_modinfo($courses['library'], $users['b']->id)->get_cm($cmid)->uservisible,
        'Usuario individual limita documento tanto en portal como en disponibilidad nativa');
    access::save('module', $cmid, [], [], [$source]);
    content_check(!access::module($cmid, $users['a']->id) && access::module($cmid, $users['b']->id) &&
        get_fast_modinfo($courses['library'], $users['b']->id)->get_cm($cmid)->uservisible,
        'Grupo de otro curso Orange permite consultar el documento a sus miembros');
    groups_remove_member($source, $users['b']->id);
    course_modinfo::clear_instance_cache($courses['library']);
    content_check(!access::module($cmid, $users['b']->id) &&
        !get_fast_modinfo($courses['library'], $users['b']->id)->get_cm($cmid)->uservisible,
        'Salir del grupo revoca acceso nativo y del portal');
    groups_add_member($source, $users['b']->id);
    course_modinfo::clear_instance_cache($courses['library']);
    content_check(get_fast_modinfo($courses['library'], $users['b']->id)->get_cm($cmid)->uservisible,
        'Entrar de nuevo en el grupo restaura acceso nativo sin reeditar');
    access::save('module', $cmid, [$cohorts['a']], [], [$source]);
    content_check(access::module($cmid, $users['a']->id) && access::module($cmid, $users['b']->id),
        'Colectivos y grupos se combinan con OR');
    access::save('module', $cmid, [], [$users['a']->id], [$source]);
    content_check(access::module($cmid, $users['a']->id) && access::module($cmid, $users['b']->id) &&
        !access::module($cmid, $users['external']->id), 'Usuarios y grupos se combinan sin permitir externos');
    access::save('course', $courses['courses'], [], [$users['a']->id]);
    content_check(access::course($courses['courses'], $users['a']->id) && !access::course($courses['courses'], $users['b']->id),
        'Formación permite elegir usuarios individuales sin alterar matrículas');
    access::save('course', $courses['courses'], [], [], [$source]);
    content_check(!access::recipient($courses['courses'], $users['a']->id) && access::recipient($courses['courses'], $users['b']->id),
        'Matrícula ofrece solo los destinatarios del grupo elegido');
    content_denied(fn() => access::save('course', $courses['courses'], [], [$users['external']->id]), 'Usuario ajeno a Orange rechazado');
    content_check(isset(access::validation(['orangeusers' => [$users['external']->id]])['orangeusers']), 'Formulario rechaza usuario externo manipulado');
    groups_delete_group($source);
    course_modinfo::clear_instance_cache($courses['library']);
    content_check(!access::module($cmid, $users['b']->id) &&
        !get_fast_modinfo($courses['library'], $users['b']->id)->get_cm($cmid)->uservisible,
        'Borrar grupo destinatario no amplía el acceso a todo Orange');
    access::save('course', $courses['courses'], [$cohorts['a']]);
    content_check(access::course($courses['courses'], $users['a']->id) && !access::course($courses['courses'], $users['b']->id), 'Formación limitada a colectivo sin cambiar matrículas');
    content_check(is_enrolled(context_course::instance($courses['courses']), $users['b']->id), 'Cambiar visibilidad conserva matrícula e histórico');
    content_denied(fn() => access::save('course', $courses['courses'], [$cohorts['external']]), 'Colectivo ajeno a Orange rechazado');
    \core\session\manager::set_user($users['a']);
    content_denied(fn() => access::save('module', $cmid, []), 'Agente no puede cambiar destinatarios');
    \core\session\manager::set_user(get_admin());
    access::save('course', $courses['courses'], []);
    content_check(access::course($courses['courses'], $users['b']->id), 'Quitar selección de formación vuelve a Orange');
    foreach (enrol_get_instances($courses['courses'], false) as $instance) {
        content_check(!in_array($instance->enrol, ['guest', 'self'], true), 'Nueva formación no activa invitado ni automatrícula');
    }
    $catid = $DB->get_field('local_orange_category', 'id', ['kind' => 'notifications'], IGNORE_MULTIPLE);
    $noticeid = \local_orangeportal\service::save_notice((object)['kind' => 'notifications', 'title' => 'TEST default audience',
        'categoryid' => $catid, 'content' => '', 'published' => 1,
        'state' => \local_orangeportal\notice_options::default_code('states'), 'impact' => \local_orangeportal\notice_options::default_code('impacts')], []);
    content_check($DB->record_exists('local_orange_audience', ['noticeid' => $noticeid, 'targettype' => 'cohort', 'targetid' => $base]),
        'Publicación sin destinatarios se dirige solo a la cohorte Orange');
    access::save('course', $courses['courses'], [], [$users['a']->id]);
    $privacy = \local_orangeportal\privacy\provider::get_contexts_for_userid($users['a']->id);
    content_check(in_array(context_system::instance()->id, $privacy->get_contextids()), 'Privacidad incluye destinatarios individuales en su ámbito');
    \local_orangeportal\privacy\provider::delete_data_for_user(new \core_privacy\local\request\approved_contextlist(
        $users['a'], 'local_orangeportal', [context_system::instance()->id]));
    course_modinfo::clear_instance_cache($courses['library']);
    content_check(!in_array($users['a']->id, access::selected('course', $courses['courses'], 'users'), true) &&
        !access::course($courses['courses'], $users['a']->id) && !access::course($courses['courses'], $users['b']->id),
        'Borrado de privacidad elimina el destinatario individual sin ampliar acceso a todo Orange');
    cohort_remove_member($base, $users['a']->id);
    content_check(!access::course($courses['courses'], $users['a']->id) &&
        !has_capability('moodle/category:viewcourselist', context_coursecat::instance(get_course($courses['courses'])->category), $users['a']->id),
        'Retirar pertenencia a Orange revoca contenido y catálogo');
} catch (Throwable $e) {
    echo get_class($e) . ": " . $e->getMessage() . "\n" . ($e->debuginfo ?? "") . "\n" . $e->getTraceAsString() . "\n";
    throw $e;
} finally {
    \core\session\manager::set_user(get_admin());
    foreach ($courses as $id) { delete_course($id, false); }
    foreach ($cohorts as $id) { if ($cohort = $DB->get_record('cohort', ['id' => $id])) { cohort_delete_cohort($cohort); } }
    foreach ($users as $user) { delete_user($user); }
    if ($noticeid) {
        foreach (['local_orange_revision', 'local_orange_audience', 'local_orange_receipt'] as $table) { $DB->delete_records($table, ['noticeid' => $noticeid]); }
        $DB->delete_records('local_orange_notice', ['id' => $noticeid]);
    }
    cache::make('core', 'coursemodinfo')->purge(); course_modinfo::clear_instance_cache();
}
