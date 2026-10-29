<?php
/** Local native browser fixtures. No changes to existing resources; explicit cleanup supported. */
define('CLI_SCRIPT', true);
require __DIR__ . '/../../../config.php';
require_once $CFG->dirroot . '/local/orangeportal/locallib.php';
require_once $CFG->dirroot . '/course/lib.php';
require_once $CFG->dirroot . '/course/modlib.php';
use local_orangeportal\native_content as content;
use local_orangeportal\service;
if (!preg_match('~^http://(localhost|127\\.0\\.0\\.1):~', $CFG->wwwroot)) { throw new coding_exception('Local demo only.'); }
$action = $argv[1] ?? '';
\core\session\manager::set_user($DB->get_record('user', ['username' => 'formador.orange'], '*', MUST_EXIST));
if ($action === 'create') {
    $token = bin2hex(random_bytes(6));
    $category = core_course_category::create((object)['name' => 'TESTWEB Recursos ' . $token,
        'parent' => (int)get_config('local_orangeportal', 'librarycategory')]);
    $library = content::library_container((int)$category->id);
    $agent = $DB->get_field('user', 'id', ['username' => 'agente.orange']);
    $notice = service::save_notice((object)['kind' => 'notifications',
        'categoryid' => $DB->get_field('local_orange_category', 'id', ['kind' => 'notifications'], IGNORE_MULTIPLE),
        'title' => 'TESTWEB Recursos ' . $token, 'content' => '', 'impact' => 'normal', 'state' => 'open', 'published' => 1],
        ['user' => [$agent]]);
    $courseid = content::notice_container($notice);
    $modules = [];
    foreach ([$library, $courseid] as $target) {
        $module = add_moduleinfo((object)['module' => $DB->get_field('modules', 'id', ['name' => 'page']),
            'modulename' => 'page', 'section' => 0, 'visible' => 1, 'name' => 'TESTWEB Página ' . $token,
            'intro' => '<p>Ejemplo temporal de navegador</p>', 'introformat' => FORMAT_HTML,
            'content' => '<p>ORANGE-NATIVE-' . $token . '</p>', 'contentformat' => FORMAT_HTML,
            'display' => 0, 'printintro' => 0, 'printlastmodified' => 0], get_course($target));
        $modules[$target] = $module->coursemodule;
    }
    $context = context_module::instance($modules[$courseid]);
    $label = add_moduleinfo((object)['module' => $DB->get_field('modules', 'id', ['name' => 'label']),
        'modulename' => 'label', 'section' => 0, 'visible' => 1, 'name' => 'TESTWEB Área ' . $token,
        'intro' => '<p>ORANGE-INLINE-' . $token . '</p>', 'introformat' => FORMAT_HTML], get_course($courseid));
    get_file_storage()->create_file_from_string((object)['contextid' => $context->id, 'component' => 'mod_page',
        'filearea' => 'content', 'itemid' => 0, 'filepath' => '/', 'filename' => 'prueba.txt'], 'ORANGE-FILE-' . $token);
    $training = create_course((object)['category' => (int)get_config('local_orangeportal', 'coursescategory'),
        'fullname' => 'TESTWEB Formación ' . $token, 'shortname' => 'TESTWEB-' . $token, 'format' => 'topics', 'numsections' => 1]);
    $roles = get_archetype_roles('editingteacher');
    $manual = enrol_get_plugin('manual');
    $instances = array_filter(enrol_get_instances($training->id, true), fn($e) => $e->enrol === 'manual');
    $instance = reset($instances);
    if (!$instance) { $instance = $DB->get_record('enrol', ['id' => $manual->add_instance($training)]); }
    $manual->enrol_user($instance, $USER->id, reset($roles)->id);
    echo json_encode(['token' => $token, 'category' => (int)$category->id, 'library' => $library, 'notice' => $notice,
        'course' => $courseid, 'training' => (int)$training->id, 'modules' => $modules, 'label' => (int)$label->coursemodule,
        'file' => moodle_url::make_pluginfile_url($context->id, 'mod_page', 'content', 0, '/', 'prueba.txt')->out(false)], JSON_THROW_ON_ERROR);
} else {
    $token = $argv[2] ?? '';
    if (!preg_match('/^[a-f0-9]{12}$/D', $token)) { throw new coding_exception('Fixture token required'); }
    $notices = $DB->get_records('local_orange_notice', ['title' => 'TESTWEB Recursos ' . $token]);
    foreach ($notices as $notice) {
        if ($action === 'withdraw') { $DB->set_field('local_orange_notice', 'published', 0, ['id' => $notice->id]); continue; }
        if ($action !== 'cleanup') { throw new coding_exception('Unknown action'); }
        if ($map = content::notice_mapping((int)$notice->id)) { delete_course($map->courseid, false); }
        foreach (['local_orange_audience', 'local_orange_receipt', 'local_orange_revision', 'local_orange_state_log'] as $table) {
            $DB->delete_records($table, ['noticeid' => $notice->id]);
        }
        $DB->delete_records('local_orange_notice', ['id' => $notice->id]);
    }
    if ($action === 'cleanup') {
        foreach ($DB->get_records('course_categories', ['name' => 'TESTWEB Recursos ' . $token]) as $cat) {
            foreach ($DB->get_records('course', ['category' => $cat->id]) as $course) {
                foreach ($DB->get_records('course_modules', ['course' => $course->id]) as $cm) { course_delete_module($cm->id); }
            }
            \local_orangeportal\native_categories::delete_category('library', (int)$cat->id);
        }
        foreach ($DB->get_records('course', ['shortname' => 'TESTWEB-' . $token]) as $course) { delete_course($course, false); }
    }
    echo "Fixture $action completed\n";
}
