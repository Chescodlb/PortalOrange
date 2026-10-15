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
    foreach (['library', 'courses'] as $kind) {
        $root = native_categories::root($kind);
        category_denied(fn() => native_categories::delete_category($kind, (int)$root->id), 'No elimina raíz de ' . $kind);
        category_denied(fn() => native_categories::delete_category($kind,
            $created[$kind === 'library' ? 'courses' : 'library']), 'No elimina categorías de otra sección nativa');
        $parent = native_categories::save($kind, 0, 'Prueba de eliminación nativa', 0);
        $child = native_categories::save($kind, 0, 'Hija de eliminación nativa', $parent);
        category_denied(fn() => native_categories::delete_category($kind, $parent), 'No elimina categoría nativa con subcategorías');
        $course = clone $DB->get_record('course', ['id' => SITEID], '*', MUST_EXIST);
        unset($course->id);
        $course->category = $child;
        $course->shortname = 'orange-delete-test-' . $kind;
        $course->visible = 0;
        $courseid = $DB->insert_record('course', $course);
        category_denied(fn() => native_categories::delete_category($kind, $child), 'No elimina categoría con curso o colección ocultos');
        category_check($DB->record_exists('course', ['id' => $courseid]), 'Conserva contenido bloqueado');
        $DB->delete_records('course', ['id' => $courseid]);
        $contextid = context_coursecat::instance($child)->id;
        native_categories::delete_category($kind, $child);
        category_check(!$DB->record_exists('course_categories', ['id' => $child]) &&
            !$DB->record_exists('context', ['id' => $contextid]), 'Eliminación nativa retira categoría y contexto');
        native_categories::delete_category($kind, $parent);
        category_check(!$DB->record_exists('course_categories', ['id' => $parent]), 'Elimina padre nativo vacío');
    }
    foreach (['incidents', 'notifications'] as $kind) {
        $parent = $DB->insert_record('local_orange_category', (object)[
            'kind' => $kind, 'name' => 'Prueba eliminar', 'parentid' => 0]);
        $child = $DB->insert_record('local_orange_category', (object)[
            'kind' => $kind, 'name' => 'Subcategoría temporal', 'parentid' => $parent]);
        category_denied(fn() => \local_orangeportal\service::delete_category($kind, $parent),
            'No elimina categoría con subcategorías: ' . $kind);
        $notices = $DB->get_records('local_orange_notice', ['kind' => $kind], 'id', '*', 0, 1);
        $notice = reset($notices);
        unset($notice->id);
        $notice->categoryid = $child;
        $notice->published = 0;
        $noticeid = $DB->insert_record('local_orange_notice', $notice);
        category_denied(fn() => \local_orangeportal\service::delete_category($kind, $child),
            'No elimina categoría con borradores: ' . $kind);
        $DB->set_field('local_orange_notice', 'published', 1, ['id' => $noticeid]);
        category_denied(fn() => \local_orangeportal\service::delete_category($kind, $child),
            'No elimina categoría con publicaciones: ' . $kind);
        category_check($DB->record_exists('local_orange_category', ['id' => $child]) &&
            $DB->record_exists('local_orange_notice', ['id' => $noticeid]), 'Bloqueo conserva categoría y aviso');
        category_denied(fn() => \local_orangeportal\service::delete_category(
            $kind === 'incidents' ? 'notifications' : 'incidents', $child), 'No elimina categorías de otra sección');
        $DB->delete_records('local_orange_notice', ['id' => $noticeid]);
        \local_orangeportal\service::delete_category($kind, $child);
        category_check(!$DB->record_exists('local_orange_category', ['id' => $child]), 'Elimina subcategoría vacía');
        \local_orangeportal\service::delete_category($kind, $parent);
        category_check(!$DB->record_exists('local_orange_category', ['id' => $parent]), 'Elimina categoría vacía');
    }
    category_denied(fn() => \local_orangeportal\service::delete_category('library', $created['library']),
        'Eliminación personalizada no acepta categorías nativas');
    $protected = $DB->insert_record('local_orange_category', (object)[
        'kind' => 'notifications', 'name' => 'Prueba de permisos', 'parentid' => 0]);
    $agent = $DB->get_record('user', ['username' => 'agente.orange'], '*', MUST_EXIST);
    \core\session\manager::set_user($agent);
    category_denied(fn() => native_categories::save('library', 0, 'No autorizado', 0), 'Agente no crea categorías');
    category_denied(fn() => native_categories::save('courses', $created['courses'], 'No autorizado', 0), 'Agente no edita categorías');
    category_denied(fn() => \local_orangeportal\service::delete_category('notifications', $protected),
        'Agente no puede eliminar categorías vacías');
    foreach (['library', 'courses'] as $kind) {
        category_denied(fn() => native_categories::delete_category($kind, $created[$kind]), 'Agente no elimina categorías nativas');
    }
    echo 'Comprobaciones: ' . $checks . '. Se revierten todas las categorías de prueba.' . PHP_EOL;
    $transaction->rollback(new Exception('orange_category_test_rollback'));
} catch (Exception $e) {
    if ($e->getMessage() !== 'orange_category_test_rollback') {
        if (!$transaction->is_disposed()) { $transaction->rollback($e); }
        throw $e;
    }
}
