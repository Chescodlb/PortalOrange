<?php
/** Local-only library tracking regression, with temporary data rolled back. */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/orangeportal/locallib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/group/lib.php');
use local_orangeportal\library_report as report;
use local_orangeportal\document_reads;
if (!preg_match('~^http://(localhost|127\.0\.0\.1):~', $CFG->wwwroot)) { throw new coding_exception('Local only'); }
function library_report_check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException($label); }
    echo "PASS $label\n";
}
$agent = $DB->get_record('user', ['username' => 'agente.orange'], '*', MUST_EXIST);
$admin = $DB->get_record('user', ['username' => 'admin.orange'], '*', MUST_EXIST);
$coordinator = $DB->get_record('user', ['username' => 'coordinador.orange'], '*', MUST_EXIST);
$quality = $DB->get_record('user', ['username' => 'calidad.orange'], '*', MUST_EXIST);
\core\session\manager::set_user($agent);
$documents = \local_orangeportal\library_browser::documents((int)get_config('local_orangeportal', 'librarycategory'));
$base = reset($documents);
if (!$base) { throw new coding_exception('Library demo required'); }
$tx = $DB->start_delegated_transaction();
try {
    $_GET['period'] = 'all';
    \core\session\manager::set_user($admin);
    $cohort = cohort_add_cohort((object)['name' => 'TEST Library team', 'contextid' => context_system::instance()->id,
        'component' => 'local_orangeportal', 'description' => '', 'descriptionformat' => FORMAT_HTML]);
    foreach ([$agent, $coordinator, $quality] as $u) { cohort_add_member($cohort, $u->id); }
    $foreigncohort = cohort_add_cohort((object)['name' => 'TEST Foreign library team', 'contextid' => context_system::instance()->id,
        'component' => 'local_orangeportal', 'description' => '', 'descriptionformat' => FORMAT_HTML]);
    $group = (int)groups_create_group((object)['courseid' => $base->courseid, 'name' => 'TEST library restricted']);
    $ids = [];
    foreach (['visible', 'hidden', 'restricted', 'old', 'inline'] as $key) {
        $data = (object)['modulename' => 'page', 'course' => $base->courseid, 'section' => 0, 'visible' => $key !== 'hidden',
            'name' => 'TEST Library report ' . $key, 'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            'content' => '<p>TEST</p>', 'contentformat' => FORMAT_HTML, 'display' => 0, 'completion' => 0];
        if ($key === 'restricted') { $data->availability = json_encode(['op' => '&', 'c' => [['type' => 'group', 'id' => $group]], 'showc' => [false]]); }
        if ($key === 'inline') { $data->modulename = 'label'; $data->introeditor['text'] = '<p>TEST inline library</p>'; }
        $ids[$key] = create_module($data)->coursemodule;
    }
    $DB->set_field('course_modules', 'added', time() - 60 * DAYSECS, ['id' => $ids['old']]);
    rebuild_course_cache($base->courseid, true);
    $all = report::documents('TEST Library report');
    library_report_check(isset($all[$ids['visible']]) && !isset($all[$ids['hidden']]), 'Solo recursos publicados; los ocultos no cuentan como pendientes');
    library_report_check(isset($all[$ids['visible']]->users[$agent->id]), 'Denominador incluye al agente con acceso nativo');
    library_report_check(!isset($all[$ids['restricted']]->users[$agent->id]), 'Restricciones nativas de grupo excluyen pendientes inaccesibles');
    $first = time() - 120;
    document_reads::record($agent->id, $ids['visible'], $first);
    document_reads::record($agent->id, $ids['visible'], $first + 60);
    $doc = report::document($ids['visible']);
    library_report_check($doc->users[$agent->id]->readtime === $first, 'Reabrir conserva la primera fecha registrada');
    library_report_check($DB->count_records('local_orange_doc_read', ['userid' => $agent->id, 'cmid' => $ids['visible']]) === 1,
        'Lecturas repetidas no duplican usuarios');
    document_reads::record($agent->id, $ids['inline'], $first);
    library_report_check(report::document($ids['inline'])->users[$agent->id]->readtime === $first,
        'Área de texto y medios registra apertura sin finalización');
    $DB->insert_record('course_modules_viewed', (object)['userid' => $agent->id, 'coursemoduleid' => $ids['visible'], 'timecreated' => $first - 20]);
    library_report_check(report::document($ids['visible'])->users[$agent->id]->readtime === $first - 20,
        'Entre evidencias nativas y del portal se usa la primera fecha disponible');
    library_report_check(count(report::readers($doc, 'read', fullname($agent))) === 1 && !report::readers($doc, 'unread', fullname($agent)),
        'Filtros leído/sin leer y búsqueda coinciden con el detalle');
    library_report_check($doc->readcount + count(report::readers($doc, 'unread', '')) === $doc->recipients,
        'Leídos más pendientes coincide con usuarios accesibles');
    $DB->insert_record('course_modules_viewed', (object)['userid' => $agent->id, 'coursemoduleid' => $ids['old'], 'timecreated' => $first]);
    library_report_check(report::document($ids['old'])->users[$agent->id]->readtime === $first, 'Se recupera evidencia nativa anterior');
    $_GET['period'] = 'week';
    library_report_check(!isset(report::documents()[$ids['old']]) && isset(report::documents()[$ids['visible']]),
        'Periodo filtra fecha de alta del documento, no fecha de apertura');
    $_GET['period'] = 'all';
    \core\session\manager::set_user($coordinator); $_GET['report_cohort'] = $cohort;
    $doc = report::document($ids['visible']);
    library_report_check(!array_diff(array_keys($doc->users), [$agent->id, $coordinator->id, $quality->id]),
        'Detalle limitado al colectivo seleccionado');
    $_GET['report_cohort'] = $foreigncohort;
    try { report::document($ids['visible']); throw new RuntimeException('Foreign cohort accepted'); }
    catch (moodle_exception $e) { library_report_check($e->errorcode === 'outsideorange', 'URL o CSV de colectivo ajeno rechazados'); }
    unset($_GET['report_cohort']);
    \core\session\manager::set_user($agent);
    try { report::documents(); throw new RuntimeException('Agent report accepted'); }
    catch (required_capability_exception $e) { library_report_check(true, 'Agente no obtiene seguimiento ajeno'); }
    \core\session\manager::set_user($quality);
    library_report_check(isset(report::document($ids['visible'])->users[$agent->id]), 'Calidad consulta lecturas sin necesitar permisos de notas');
    \core\session\manager::set_user($admin);
    try { report::document(2147483647); throw new RuntimeException('Invalid resource accepted'); }
    catch (moodle_exception $e) { library_report_check($e->errorcode === 'outsideorange', 'ID ajeno o inexistente no revela datos'); }
} finally {
    try { $tx->rollback(new RuntimeException('library-report-rollback')); }
    catch (RuntimeException $e) { if ($e->getMessage() !== 'library-report-rollback') { throw $e; } }
    rebuild_course_cache($base->courseid, true);
    // Rollback can restore a cacherev equal to a revision used by temporary modules.
    cache::make('core', 'coursemodinfo')->purge();
    course_modinfo::clear_instance_cache();
}
echo "Datos temporales restaurados\n";
