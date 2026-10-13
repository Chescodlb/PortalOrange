<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
use local_orangeportal\native_categories;
if (!str_starts_with($CFG->wwwroot, 'http://localhost:')) { cli_error('Prueba limitada a local.'); }
$transaction = $DB->start_delegated_transaction();
$checks = 0;
function category_check(bool $condition, string $label): void {
    global $checks;
    if (!$condition) { throw new Exception($label); }
    $checks++; echo 'PASS ' . $label . PHP_EOL;
}
function category_denied(callable $action, string $label): void {
    try { $action(); } catch (moodle_exception $e) { category_check(true, $label); return; }
    category_check(false, $label);
}
try {
    $editor = $DB->get_record('user', ['username' => 'editor.orange'], '*', MUST_EXIST);
    \core\session\manager::set_user($editor);
    $created = [];
    foreach (['library', 'courses'] as $kind) {
        $root = native_categories::root($kind);
        $id = native_categories::save($kind, 0, 'Prueba de categoría · ' . $kind, 0);
        category_check((int)$DB->get_field('course_categories', 'parent', ['id' => $id]) === (int)$root->id,
            'Categoría nativa bajo raíz de ' . $kind);
        $child = native_categories::save($kind, 0, 'Subcategoría de prueba', $id);
        category_check((int)$DB->get_field('course_categories', 'parent', ['id' => $child]) === $id,
            'Subcategoría nativa bajo categoría elegida');
        category_check(!isset(native_categories::options($kind, $id)[$child]), 'Selector excluye descendientes al editar');
        category_denied(fn() => native_categories::save($kind, $id, 'Ciclo', $child), 'No permite ciclos');
        native_categories::save($kind, $child, 'Subcategoría renombrada', 0);
        category_check($DB->get_field('course_categories', 'name', ['id' => $child]) === 'Subcategoría renombrada' &&
            (int)$DB->get_field('course_categories', 'parent', ['id' => $child]) === (int)$root->id, 'Renombrar y mover usa Moodle');
        category_denied(fn() => native_categories::save($kind, (int)$root->id, 'Cambiar raíz', 0), 'No permite editar la raíz desde el formulario');
        $created[$kind] = $id;
    }
    category_denied(fn() => native_categories::save('library', 0, 'Cruzar raíz', $created['courses']), 'No crea Biblioteca dentro de Cursos');
    category_denied(fn() => native_categories::save('library', $created['courses'], 'Editar otra sección', 0), 'No edita categorías de otra sección');
    $agent = $DB->get_record('user', ['username' => 'agente.orange'], '*', MUST_EXIST);
    \core\session\manager::set_user($agent);
    category_denied(fn() => native_categories::save('library', 0, 'No autorizado', 0), 'Agente no crea categorías');
    category_denied(fn() => native_categories::save('courses', $created['courses'], 'No autorizado', 0), 'Agente no edita categorías');
    echo 'Comprobaciones: ' . $checks . '. Se revierten todas las categorías de prueba.' . PHP_EOL;
    $transaction->rollback(new Exception('orange_category_test_rollback'));
} catch (Exception $e) {
    if ($e->getMessage() !== 'orange_category_test_rollback') {
        if (!$transaction->is_disposed()) { $transaction->rollback($e); }
        throw $e;
    }
}
