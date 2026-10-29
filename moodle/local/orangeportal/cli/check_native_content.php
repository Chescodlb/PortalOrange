<?php
/** Local-only transactional tests: native resource containers and live access boundaries. */
define('CLI_SCRIPT', true);
require __DIR__ . '/../../../config.php';
require_once $CFG->dirroot . '/local/orangeportal/locallib.php';
require_once $CFG->dirroot . '/local/orangeportal/lib.php';
require_once $CFG->dirroot . '/course/lib.php';
require_once $CFG->dirroot . '/course/modlib.php';
require_once $CFG->dirroot . '/cohort/lib.php';
use local_orangeportal\native_content as content;
use local_orangeportal\service;
use local_orangeportal\library_browser;
if (!preg_match('~^http://(localhost|127\\.0\\.0\\.1):~', $CFG->wwwroot)) { throw new coding_exception('Local demo only.'); }
function native_check(bool $ok, string $label): void { if (!$ok) { throw new RuntimeException($label); } echo "PASS $label\n"; }
function native_denied(callable $fn, string $label): void {
    try { $fn(); } catch (moodle_exception $e) { native_check(true, $label); return; }
    throw new RuntimeException($label);
}
function native_page(int $courseid, string $name, string $module = 'page'): stdClass {
    global $DB;
    return add_moduleinfo((object)['module' => $DB->get_field('modules', 'id', ['name' => $module]),
        'modulename' => $module, 'section' => 0, 'visible' => 1, 'name' => $name,
        'intro' => '<p>Descripción conservada</p>', 'introformat' => FORMAT_HTML, 'content' => '<p>Contenido nativo</p>',
        'contentformat' => FORMAT_HTML, 'display' => 0, 'printintro' => 0, 'printlastmodified' => 0], get_course($courseid));
}
$trainer = $DB->get_record('user', ['username' => 'formador.orange'], '*', MUST_EXIST);
$agent = $DB->get_record('user', ['username' => 'agente.orange'], '*', MUST_EXIST);
$external = $DB->get_record('user', ['username' => 'usuario.externo'], '*', MUST_EXIST);
$coordinator = $DB->get_record('user', ['username' => 'coordinador.orange'], '*', MUST_EXIST);
\core\session\manager::set_user($trainer);
$PAGE->set_context(context_system::instance()); $PAGE->set_url('/local/orangeportal/index.php');
$before = ['course' => $DB->count_records('course'), 'modules' => $DB->count_records('course_modules'),
    'notices' => $DB->get_records('local_orange_notice'), 'containers' => $DB->count_records('local_orange_container')];
$tx = $DB->start_delegated_transaction();
try {
    $root = (int)get_config('local_orangeportal', 'librarycategory');
    $category = core_course_category::create((object)['name' => 'TEST categoría recursos', 'parent' => $root]);
    native_check(isset(content::library_options()[$category->id]), 'Formador puede añadir directamente en una categoría de Biblioteca');
    $courseid = content::library_container($category->id);
    native_check(content::library_container($category->id) === $courseid && get_course($courseid)->category == $category->id,
        'Un contenedor nativo por categoría, sin elegir colección');
    native_check(has_capability('moodle/course:manageactivities', context_course::instance($courseid)) &&
        !has_capability('moodle/site:config', context_system::instance()), 'Autor con edición local sin administración general');
    native_check(isset(content::modules(get_course($courseid))['quiz'], content::modules(get_course($courseid))['url'],
        content::modules(get_course($courseid))['page']), 'Todos los tipos nativos permitidos, incluidas actividades');
    $emptycat = core_course_category::create((object)['name' => 'TEST categoría vacía', 'parent' => $root]);
    $emptycourse = content::library_container((int)$emptycat->id);
    native_check(local_orangeportal\native_categories::category_delete_blocker('library', (int)$emptycat->id) === '',
        'Cancelar alta no bloquea eliminar una categoría sin documentos');
    native_check(content::empty_library_container($emptycourse), 'Contenedor vacío identificado sin borrar contenido');
    $page = native_page($courseid, 'TEST documento por categoría');
    $label = native_page($courseid, 'TEST contenido integrado', 'label');
    native_check(local_orangeportal\native_categories::category_delete_blocker('library', (int)$category->id) !== '', 'Categoría con actividades sigue protegida contra borrado');
    \core\session\manager::set_user($agent);
    content::guard($courseid);
    native_check(is_enrolled(context_course::instance($courseid), $agent->id, '', true), 'La audiencia Orange recibe acceso nativo a la categoría');
    $docs = library_browser::documents($root);
    native_check(isset($docs[$page->coursemodule], $docs[$label->coursemodule]) &&
        $docs[$page->coursemodule]->category == $category->id && str_contains($docs[$label->coursemodule]->url->out(false), '/activity.php'),
        'Listado directo incluye recursos y contenido sin view.php');
    native_denied(fn() => content::library_container($category->id), 'Agente no puede cargar documentos');
    \core\session\manager::set_user($external);
    native_denied(fn() => content::guard($courseid), 'No Orange no accede al nuevo contenedor');
    \core\session\manager::set_user($trainer);
    $noticecourses = [];
    foreach (['incidents', 'notifications'] as $kind) {
        $id = service::save_notice((object)['kind' => $kind, 'categoryid' => $DB->get_field('local_orange_category', 'id', ['kind' => $kind], IGNORE_MULTIPLE),
            'title' => 'TEST Recursos ' . $kind, 'content' => '', 'impact' => 'normal', 'state' => 'open', 'published' => 1], ['user' => [$agent->id]]);
        $nativeid = content::notice_container($id); $noticecourses[] = $nativeid;
        $resource = native_page($nativeid, 'TEST recurso privado ' . $kind);
        native_page($nativeid, 'TEST segundo recurso ' . $kind);
        native_check(count(content::activities($nativeid)) === 2, "$kind admite varios recursos sin descripción obligatoria");
        native_check(!isset(local_orangeportal\orange_scope::course_ids()[$nativeid]), "$kind no aparece como formación ni colección de Biblioteca");
        $notice = $DB->get_record('local_orange_notice', ['id' => $id]);
        native_check(content::opening_activity($notice) === null, "$kind existente abre la ficha por defecto");
        native_check(isset(content::opening_options($notice)[$resource->coursemodule]) &&
            !isset(content::opening_options($notice)[$page->coursemodule]), "$kind solo permite elegir sus propias actividades");
        $notice->openactivity = $page->coursemodule;
        native_denied(fn() => service::save_notice(clone $notice, ['user' => [$agent->id]]), "$kind rechaza un recurso de otra publicación o Biblioteca");
        $notice->openactivity = $resource->coursemodule;
        content::save_opening($id, (int)$resource->coursemodule, (int)$notice->revision, 0);
        native_check($DB->get_field('local_orange_notice', 'revision', ['id' => $id]) == $notice->revision,
            "$kind configurar apertura no cambia contenido ni reinicia lecturas");
        native_denied(fn() => content::save_opening($id, 0, (int)$notice->revision, 0),
            "$kind una pantalla antigua no sobrescribe una elección posterior");
        \core\session\manager::set_user($agent);
        $notice = $DB->get_record('local_orange_notice', ['id' => $id]);
        native_denied(fn() => content::save_opening($id, 0, (int)$notice->revision, (int)$notice->openactivity),
            "$kind agente no puede cambiar la apertura");
        native_check(content::opening_activity($notice)?->id == $resource->coursemodule &&
            !service::get_receipt($notice, $agent->id), "$kind resuelve la apertura directa sin completar ni leer durante la comprobación");
        $DB->set_field('course_modules', 'visible', 0, ['id' => $resource->coursemodule]);
        rebuild_course_cache($nativeid, true);
        native_check(content::opening_activity($notice) === null, "$kind recurso oculto vuelve a la ficha");
        $DB->set_field('course_modules', 'visible', 1, ['id' => $resource->coursemodule]);
        rebuild_course_cache($nativeid, true);
        $missing = clone $notice; $missing->openactivity = 2147483647;
        native_check(content::opening_activity($missing) === null, "$kind recurso eliminado vuelve a la ficha");
        content::enter_notice($nativeid); content::guard($nativeid);
        native_check(is_enrolled(context_course::instance($nativeid), $agent->id, '', true) &&
            !has_capability('moodle/course:manageactivities', context_course::instance($nativeid)), "$kind destinatario abre con rol Estudiante sin edición");
        service::receipt($notice, 'readtime');
        \core\session\manager::set_user($trainer);
        $event = \core\event\course_module_updated::create(['objectid' => $resource->coursemodule,
            'context' => context_module::instance($resource->coursemodule), 'other' => ['modulename' => 'page', 'instanceid' => $resource->instance, 'name' => 'TEST']]);
        content::module_changed($event);
        $updated = $DB->get_record('local_orange_notice', ['id' => $id]);
        native_check((int)$updated->revision === (int)$notice->revision + 1 && !service::get_receipt($updated, $agent->id), "$kind actualizar recurso reinicia lectura y conserva histórico");
        // Native download callbacks consult the live audience even if a previous enrolment remains.
        $DB->delete_records('local_orange_audience', ['noticeid' => $id]);
        \core\session\manager::set_user($agent);
        native_denied(fn() => local_orangeportal_after_require_login($nativeid, false, null, true, true), "$kind retirar destinatario bloquea URL nativa y pluginfile");
        native_denied(fn() => content::opening_activity($updated), "$kind apertura directa respeta destinatarios actuales");
        \core\session\manager::set_user($trainer);
        $DB->insert_record('local_orange_audience', (object)['noticeid' => $id, 'targettype' => 'user', 'targetid' => $agent->id]);
        $DB->set_field('local_orange_notice', 'published', 0, ['id' => $id]);
        \core\session\manager::set_user($agent);
        native_denied(fn() => content::guard($nativeid), "$kind borrador bloquea acceso nativo de agentes");
        \core\session\manager::set_user($trainer);
        $DB->set_field('local_orange_notice', 'published', 1, ['id' => $id]);
        if ($kind === 'incidents') {
            $notice = $DB->get_record('local_orange_notice', ['id' => $id]); service::change_state($notice, 'closed');
            \core\session\manager::set_user($agent);
            native_denied(fn() => content::guard($nativeid), 'Incidencia cerrada bloquea también sus actividades al agente');
        }
        \core\session\manager::set_user($coordinator);
        native_denied(fn() => content::notice_container($id), 'Coordinación no puede crear ni editar recursos');
        \core\session\manager::set_user($trainer);
    }
    $trainings = orange_native_courses((int)get_config('local_orangeportal', 'coursescategory'));
    $training = reset($trainings);
    $section = course_create_section($training);
    course_update_section($training, $section, (object)['name' => 'TEST colección formativa']);
    native_check(get_fast_modinfo($training)->get_section_info($section->section)->name === 'TEST colección formativa',
        'Colección formativa usa una sección nativa editable');
    native_check(has_capability('moodle/site:manageblocks', context_course::instance($training->id)), 'Formador dispone de edición nativa de bloques laterales');
    native_denied(fn() => content::training_course($courseid), 'Biblioteca no se confunde con colecciones de formación');
} finally {
    try { $tx->rollback(new RuntimeException('native-content-test-rollback')); }
    catch (RuntimeException $e) { if ($e->getMessage() !== 'native-content-test-rollback') { throw $e; } }
}
native_check($DB->count_records('course') === $before['course'] && $DB->count_records('course_modules') === $before['modules'] &&
    $DB->count_records('local_orange_container') === $before['containers'] && $DB->get_records('local_orange_notice') == $before['notices'],
    'Contenido manual y ejemplos existentes conservados; pruebas revertidas');
