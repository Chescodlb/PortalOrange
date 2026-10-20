<?php
/** Local library regression checks; temporary activities, groups and role data are rolled back. */
define('CLI_SCRIPT', true);
require __DIR__ . '/../../../config.php';
require_once $CFG->dirroot . '/local/orangeportal/locallib.php';
require_once $CFG->dirroot . '/course/lib.php';
require_once $CFG->libdir . '/grouplib.php';
require_once $CFG->dirroot . '/group/lib.php';
use local_orangeportal\library_browser as browser;
if (!preg_match('~^http://(localhost|127\\.0\\.0\\.1):~', $CFG->wwwroot)) {
    throw new coding_exception('Local demo only.');
}
function library_check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException($label); }
    echo "PASS $label\n";
}
$agent = $DB->get_record('user', ['username' => 'agente.orange'], '*', MUST_EXIST);
\core\session\manager::set_user($agent);
$rootid = (int)get_config('local_orangeportal', 'librarycategory');
$categories = browser::categories($rootid);
$documents = browser::documents($rootid);
library_check((bool)$documents, 'El agente ve recursos nativos de sus colecciones');
$document = reset($documents);
$centrex = $DB->get_record('course_categories', ['parent' => $rootid, 'name' => 'CENTREX'], '*', MUST_EXIST);
library_check(!str_contains(browser::render($centrex->id, '', $document->id), 'orange-library-open'), 'Rama vacía sin documento ajeno ni botón de apertura');
library_check(str_contains(browser::render($centrex->id, $document->name, $document->id), 'orange-library-open'), 'La búsqueda es global aunque se indique otra rama');
library_check(str_contains(browser::render($document->category, '', $document->id), '/mod/'), 'Abrir documento usa el recurso nativo');
library_check(!str_contains(browser::render($rootid, '', 2147483647), 'orange-library-open'), 'ID desconocido sin acceso ni ficha');
library_check(!str_contains(browser::render($rootid, '<script>alert(1)</script>', 0), '<script>'), 'Búsqueda escapada');
library_check(count(browser::trail($document->category, $rootid, $categories)) >= 2, 'Ruta dinámica de varios niveles');
$tx = $DB->start_delegated_transaction();
try {
    \core\session\manager::set_user(get_admin());
    $groupid = groups_create_group((object)['courseid' => $document->courseid, 'name' => 'Temporary library restriction group']);
    $created = [];
    foreach (['visible', 'hidden', 'restricted'] as $kind) {
        $data = (object)['modulename' => 'page', 'course' => $document->courseid, 'section' => 0,
            'visible' => $kind === 'hidden' ? 0 : 1, 'name' => 'LIBTEST-' . $kind,
            'introeditor' => ['text' => '<p>SEARCH-DESCRIPTION-' . $kind . '</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
            'content' => '<p>Example</p>', 'contentformat' => FORMAT_HTML, 'display' => 0,
            'displayoptions' => serialize([]), 'completion' => 0];
        if ($kind === 'restricted') {
            $data->availability = json_encode(['op' => '&', 'c' => [['type' => 'group', 'id' => (int)$groupid]], 'showc' => [false]]);
        }
        $created[$kind] = create_module($data)->coursemodule;
    }
    rebuild_course_cache($document->courseid, true);
    \core\session\manager::set_user($agent);
    $docs = browser::documents($rootid);
    library_check(isset($docs[$created['visible']]), 'El recurso visible aparece en Documentos');
    library_check(!isset($docs[$created['hidden']]), 'El recurso oculto no aparece');
    library_check(!isset($docs[$created['restricted']]), 'La restricción nativa por grupo oculta el recurso');
    library_check(str_contains(browser::render($rootid, 'SEARCH-DESCRIPTION-visible', 0), 'LIBTEST-visible'), 'Buscar por descripción encuentra el recurso permitido');
    library_check(!str_contains(browser::render($rootid, 'SEARCH-DESCRIPTION-hidden', 0), 'LIBTEST-hidden'), 'La búsqueda no filtra nombres de recursos ocultos');
    library_check(!str_contains(browser::render($rootid, 'LIBTEST-restricted', $created['restricted']), 'orange-library-open'), 'URL de selección manipulada no permite abrir un recurso restringido');
    $roleid = create_role('Temporary library view denial', 'temporary_library_view_denial', '', '');
    role_assign($roleid, $agent->id, context_system::instance()->id);
    assign_capability('mod/page:view', CAP_PROHIBIT, $roleid, context_module::instance($created['visible'])->id);
    reload_all_capabilities();
    library_check(!isset(browser::documents($rootid)[$created['visible']]), 'Se respeta también la capacidad específica de vista del módulo');
} finally {
    try { $tx->rollback(new RuntimeException('library-test-rollback')); }
    catch (RuntimeException $e) { if ($e->getMessage() !== 'library-test-rollback') { throw $e; } }
    // Rolled-back revision numbers can collide with cached temporary module lists.
    \cache::make('core', 'coursemodinfo')->purge();
    \course_modinfo::clear_instance_cache($document->courseid);
    rebuild_course_cache($document->courseid, true);
}
echo "Datos temporales revertidos\n";
