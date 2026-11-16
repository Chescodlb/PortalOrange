<?php
/** Local-only first-install simulation: every database/configuration change is rolled back. */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
use local_orangeportal\deployment_config as config;
if (!str_starts_with($CFG->wwwroot, 'http://localhost:')) { throw new coding_exception('Local only'); }
function catalog_check(bool $ok, string $label): void { if (!$ok) { throw new RuntimeException($label); } echo "PASS $label\n"; }
$oldcfg = clone $CFG; $olduser = clone $USER; $file = tempnam('/tmp', 'orange-catalog-fixture-');
$CFG->forced_plugin_settings['local_orangeportal']['teamsenabled'] = 0;
$prefix = 'test_initial_' . bin2hex(random_bytes(4));
$catalog = ['format' => 1, 'roots' => [
    'librarycategory' => ['sourceid' => 1, 'categories' => [
        ['sourceid' => 1, 'parent' => 0, 'name' => 'Library', 'visible' => 1],
        ['sourceid' => 2, 'parent' => 1, 'name' => 'RPV', 'visible' => 1, 'idnumber' => $prefix . '_rpv']]],
    'coursescategory' => ['sourceid' => 3, 'categories' => [
        ['sourceid' => 3, 'parent' => 0, 'name' => 'Training', 'visible' => 1],
        ['sourceid' => 4, 'parent' => 3, 'name' => 'Initial', 'visible' => 1, 'idnumber' => $prefix . '_initial'],
        ['sourceid' => 5, 'parent' => 3, 'name' => 'Continuous', 'visible' => 1, 'idnumber' => $prefix . '_continuous']]]],
    'noticecategories' => [['id' => 1, 'parentid' => 0, 'kind' => 'notifications', 'name' => 'TEST notice category']],
    'catalogs' => ['states' => ['open' => ['name' => 'Open', 'active' => true], 'closed' => ['name' => 'Closed', 'active' => true]],
        'impacts' => ['normal' => ['name' => 'Normal', 'active' => true], 'critical' => ['name' => 'Critical', 'active' => true]]]];
file_put_contents($file, json_encode($catalog));
$tx = $DB->start_delegated_transaction();
try {
    // Model an empty Orange installation within this single rollback-only local transaction.
    // Other requests retain their committed data; nothing is deleted after this test finishes.
    $DB->delete_records('local_orange_notice'); $DB->delete_records('local_orange_category');
    foreach (['notice_states', 'notice_impacts', 'deploymentcataloghash'] as $key) { unset_config($key, 'local_orangeportal'); }
    foreach (['orange_pre_library', 'orange_pre_training'] as $idnumber) {
        $DB->set_field('course_categories', 'idnumber', $prefix . '_' . $idnumber, ['idnumber' => $idnumber]);
    }
    $data = ['format' => 1, 'site_url' => $CFG->wwwroot, 'allow_scope_change' => true,
        'portal' => ['enabled' => true,
            'library' => ['idnumber' => 'orange_pre_library', 'name' => 'TEST initial library', 'create' => true],
            'training' => ['idnumber' => 'orange_pre_training', 'name' => 'TEST initial training', 'create' => true],
            'audience' => ['idnumber' => $prefix . '_audience', 'name' => 'TEST initial audience', 'create' => true]],
        'catalog_file' => $file];
    $count = $DB->count_records('course_categories');
    $plan = config::plan($data, '/tmp');
    catalog_check($DB->count_records('course_categories') === $count && !$DB->record_exists('local_orange_category', []),
        'Valida el catálogo antes de crear las raíces, sin escribir');
    $ids = config::apply($data, '/tmp');
    catalog_check($DB->count_records('course_categories') === $count + 5 &&
        $DB->count_records('course_categories', ['parent' => $ids['training']]) === 2,
        'Carga raíces y árbol inicial en una operación sin IDs manuales');
    catalog_check($DB->count_records('local_orange_category') === 1 &&
        get_config('local_orangeportal', 'notice_states') && get_config('local_orangeportal', 'notice_impacts'),
        'Carga categorías de publicaciones, estados e impactos');
    $again = config::apply($data, '/tmp');
    catalog_check($again === $ids && $DB->count_records('course_categories') === $count + 5 &&
        $DB->count_records('local_orange_category') === 1, 'Repetir el catálogo aplicado no duplica registros');
    file_put_contents($file, json_encode($catalog) . "\n");
    try { config::plan($data, '/tmp'); throw new RuntimeException('Unexpected changed catalogue'); }
    catch (coding_exception $e) { catalog_check(true, 'Rechaza usar otro catálogo como sincronización automática'); }
} finally {
    try { $tx->rollback(new RuntimeException('catalog-deployment-test-rollback')); }
    catch (RuntimeException $e) { if ($e->getMessage() !== 'catalog-deployment-test-rollback') { throw $e; } }
    foreach (get_object_vars($CFG) as $key => $value) { if (!property_exists($oldcfg, $key)) { unset($CFG->$key); } }
    foreach (get_object_vars($oldcfg) as $key => $value) { $CFG->$key = $value; }
    \core\session\manager::set_user($olduser); context_helper::reset_caches();
    cache_helper::purge_by_definition('core', 'config'); cache_helper::purge_by_event('changesincoursecat');
    unlink($file);
}
echo "Catálogo, datos y configuración originales restaurados.\n";
