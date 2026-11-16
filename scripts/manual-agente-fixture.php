<?php
/** Temporary local content for the agent manual's direct-opening screenshot. */
define('CLI_SCRIPT', true);
require '/var/www/html/config.php';
$CFG->debug = DEBUG_DEVELOPER;
$CFG->debugdisplay = 1;
set_exception_handler(function (Throwable $e) { fwrite(STDERR, (string)$e . PHP_EOL); exit(1); });
require_once $CFG->dirroot . '/local/orangeportal/locallib.php';
require_once $CFG->dirroot . '/course/lib.php';
require_once $CFG->dirroot . '/course/modlib.php';
use local_orangeportal\native_content;
use local_orangeportal\service;
if (!str_starts_with($CFG->wwwroot, 'http://localhost:')) {
    throw new coding_exception('Local demonstration only');
}
\core\session\manager::set_user(get_admin());
if (($argv[1] ?? '') === 'cleanup') {
    $id = (int)($argv[2] ?? 0);
    $notice = $DB->get_record('local_orange_notice', ['id' => $id], '*', MUST_EXIST);
    if ($notice->title !== 'Ejemplo · Instrucciones de atención durante una incidencia') {
        throw new coding_exception('Unexpected fixture');
    }
    if ($map = native_content::notice_mapping($id)) { delete_course($map->courseid, false); }
    foreach (['local_orange_revision', 'local_orange_audience', 'local_orange_receipt', 'local_orange_state_log'] as $table) {
        $DB->delete_records($table, ['noticeid' => $id]);
    }
    $DB->delete_records('local_orange_notice', ['id' => $id]);
    echo "Fixture removed\n";
    exit;
}
\core\session\manager::set_user($DB->get_record('user', ['username' => 'formador.orange'], '*', MUST_EXIST));
// Suppress outbound delivery only within this process; no persistent setting is changed.
$CFG->forced_plugin_settings['local_orangeportal']['teamsenabled'] = 0;
$agent = $DB->get_field('user', 'id', ['username' => 'agente.orange'], MUST_EXIST);
$transaction = $DB->start_delegated_transaction();
$id = service::save_notice((object)[
    'kind' => 'incidents', 'title' => 'Ejemplo · Instrucciones de atención durante una incidencia',
    'categoryid' => $DB->get_field('local_orange_category', 'id', ['kind' => 'incidents'], IGNORE_MULTIPLE),
    'content' => '<p>Consulta las instrucciones de atención asociadas a esta incidencia de ejemplo.</p>',
    'client' => 'Atención al cliente', 'product' => 'Plataforma de consultas',
    'state' => local_orangeportal\notice_options::default_code('states'),
    'impact' => local_orangeportal\notice_options::default_code('impacts'), 'published' => 1,
], ['user' => [(int)$agent]]);
try {
    $courseid = native_content::notice_container($id);
    $module = add_moduleinfo((object)[
        'module' => $DB->get_field('modules', 'id', ['name' => 'page']), 'modulename' => 'page',
        'section' => 0, 'visible' => 1, 'name' => 'Instrucciones de atención durante la incidencia',
        'intro' => '', 'introformat' => FORMAT_HTML,
        'content' => '<h3>Información para la atención al cliente</h3><p>Ejemplo de actividad asociada a una incidencia.</p>' .
            '<ol><li>Consulta el alcance de la incidencia y el servicio afectado.</li>' .
            '<li>Utiliza la información indicada por tu responsable para atender las consultas.</li>' .
            '<li>Vuelve a Incidencias para consultar las novedades.</li></ol>',
        'contentformat' => FORMAT_HTML, 'display' => 0, 'printintro' => 0, 'printlastmodified' => 0,
    ], get_course($courseid));
    $DB->set_field('local_orange_notice', 'openactivity', $module->coursemodule, ['id' => $id]);
    $transaction->allow_commit();
    echo json_encode(['noticeid' => $id, 'cmid' => (int)$module->coursemodule]);
} catch (Throwable $e) {
    // Preserve the identifier in the error so a failed fixture can be cleaned explicitly.
    throw new RuntimeException('Manual fixture notice #' . $id . ': ' . $e->getMessage(), 0, $e);
}
