<?php
/** Local regression checks for personal library reading and ordering; data is rolled back. */
define('CLI_SCRIPT', true);
require __DIR__ . '/../../../config.php';
require_once $CFG->dirroot . '/local/orangeportal/locallib.php';
use local_orangeportal\document_reads;
use local_orangeportal\library_browser;
if (!preg_match('~^http://(localhost|127\\.0\\.0\\.1):~', $CFG->wwwroot)) { throw new coding_exception('Local demo only.'); }
function reads_check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException($label); }
    echo "PASS $label\n";
}
$fixtures = [
    1 => (object)['id' => 1, 'name' => 'Z newest read', 'readtime' => 100, 'modified' => 300],
    2 => (object)['id' => 2, 'name' => 'A older unread', 'readtime' => 0, 'modified' => 100],
];
library_browser::sort_documents($fixtures, 'newest');
reads_check(array_keys($fixtures) === [1, 2], 'Recientes respeta la fecha, sin priorizar los no leídos');
library_browser::sort_documents($fixtures, 'name');
reads_check(array_keys($fixtures) === [2, 1], 'Orden alfabético independiente de la lectura');
$actor = $DB->get_record('user', ['username' => 'agente.orange'], '*', MUST_EXIST);
$other = $DB->get_record('user', ['username' => 'formador.orange'], '*', MUST_EXIST);
\core\session\manager::set_user($actor);
$rootid = (int)get_config('local_orangeportal', 'librarycategory');
$docs = array_filter(library_browser::documents($rootid), fn($d) => $d->module === 'page');
$doc = reset($docs);
if (!$doc) { throw new coding_exception('The demo requires a page in the library.'); }
$context = context_module::instance($doc->id);
\core\session\manager::set_user($other);
reads_check(isset(library_browser::documents($rootid)[$doc->id]), 'Ambos usuarios de prueba tienen acceso nativo al documento');
\core\session\manager::set_user($actor);
$tx = $DB->start_delegated_transaction();
try {
    foreach ([$actor, $other] as $user) {
        $DB->delete_records('local_orange_doc_read', ['userid' => $user->id, 'cmid' => $doc->id]);
        $DB->delete_records('course_modules_viewed', ['userid' => $user->id, 'coursemoduleid' => $doc->id]);
    }
    reads_check(!library_browser::documents($rootid)[$doc->id]->readtime, 'Documento inicialmente sin leer');
    library_browser::render($doc->category, '', $doc->id);
    reads_check(!$DB->record_exists('local_orange_doc_read', ['userid' => $actor->id, 'cmid' => $doc->id]), 'La ficha no registra lectura');
    $event = \mod_page\event\course_module_viewed::create(['context' => $context, 'objectid' => $doc->instance]);
    document_reads::viewed($event);
    reads_check(library_browser::documents($rootid)[$doc->id]->readtime > 0, 'La apertura nativa registra lectura sin requerir finalización');
    document_reads::viewed($event);
    reads_check($DB->count_records('local_orange_doc_read', ['userid' => $actor->id, 'cmid' => $doc->id]) === 1, 'Reabrir no duplica el registro');
    \core\session\manager::set_user($other);
    reads_check(!library_browser::documents($rootid)[$doc->id]->readtime, 'La lectura no afecta a otro usuario');
    $DB->insert_record('course_modules_viewed', (object)['userid' => $other->id, 'coursemoduleid' => $doc->id, 'timecreated' => time()]);
    reads_check(library_browser::documents($rootid)[$doc->id]->readtime > 0, 'Se aprovechan las vistas nativas ya existentes');
    $contexts = \local_orangeportal\privacy\provider::get_contexts_for_userid($actor->id)->get_contextids();
    reads_check(in_array(context_system::instance()->id, $contexts), 'Lecturas incluidas en el descubrimiento de privacidad');
    $approved = new \core_privacy\local\request\approved_contextlist($actor, 'local_orangeportal', [context_system::instance()->id]);
    \local_orangeportal\privacy\provider::delete_data_for_user($approved);
    reads_check(!$DB->record_exists('local_orange_doc_read', ['userid' => $actor->id]), 'La eliminación de datos personales borra sus lecturas');
    \core\session\manager::set_user(get_admin());
    $before = $DB->count_records('local_orange_doc_read', ['userid' => $USER->id]);
    document_reads::viewed(\mod_page\event\course_module_viewed::create(['context' => $context, 'objectid' => $doc->instance]));
    reads_check($DB->count_records('local_orange_doc_read', ['userid' => $USER->id]) === $before, 'Usuario fuera de Orange sin registro de vistas del portal');
    $DB->insert_record('local_orange_doc_read', (object)['userid' => $actor->id, 'cmid' => $doc->id, 'readtime' => time()]);
    document_reads::deleted(\core\event\course_module_deleted::create(['context' => $context, 'objectid' => $doc->id,
        'other' => ['modulename' => 'page', 'instanceid' => $doc->instance]]));
    reads_check(!$DB->record_exists('local_orange_doc_read', ['cmid' => $doc->id]), 'Eliminar el recurso limpia las marcas de lectura');
} finally {
    try { $tx->rollback(new RuntimeException('reads-test-rollback')); }
    catch (RuntimeException $e) { if ($e->getMessage() !== 'reads-test-rollback') { throw $e; } }
}
echo "Datos temporales revertidos\n";
