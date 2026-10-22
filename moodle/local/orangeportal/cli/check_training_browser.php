<?php
/** Local regression checks; temporary visibility changes are rolled back. */
define('CLI_SCRIPT', true);
require __DIR__ . '/../../../config.php';
require_once $CFG->dirroot . '/local/orangeportal/locallib.php';
require_once $CFG->dirroot . '/course/lib.php';
use local_orangeportal\training_browser;
if (!preg_match('~^http://(localhost|127\\.0\\.0\\.1):~', $CFG->wwwroot)) { throw new coding_exception('Local demo only.'); }
function training_check(bool $ok, string $label): void { if (!$ok) { throw new RuntimeException($label); } echo "PASS $label\n"; }
$root = (int)get_config('local_orangeportal', 'coursescategory');
$initial = $DB->get_record('course_categories', ['parent' => $root, 'name' => 'Formación inicial'], '*', MUST_EXIST);
$continuous = $DB->get_record('course_categories', ['parent' => $root, 'name' => 'Formación continua'], '*', MUST_EXIST);
$course = $DB->get_record('course', ['shortname' => 'ORANGE-FORMACION-INICIAL'], '*', MUST_EXIST);
$agent = $DB->get_record('user', ['username' => 'agente.orange'], '*', MUST_EXIST);
$trainer = $DB->get_record('user', ['username' => 'formador.orange'], '*', MUST_EXIST);
\core\session\manager::set_user($agent);
training_check(isset(training_browser::courses($root)[$course->id]), 'El agente conserva acceso al curso movido a Formación inicial');
$html = training_browser::render($root, '', 0, 'newest');
training_check(str_contains($html, 'Formación inicial') && str_contains($html, 'Formación continua') && !str_contains($html, 'class="btn btn-primary orange-training-open"'),
    'Dos ramas disponibles sin selección ni apertura ficticia al entrar');
training_check(str_contains(training_browser::render($initial->id, '', $course->id, 'newest'), 'orange-training-open'), 'La rama inicial permite seleccionar y abrir su curso');
training_check(!str_contains(training_browser::render($continuous->id, '', $course->id, 'newest'), 'orange-training-open'), 'No abre un curso de otra rama por ID manipulado');
training_check(str_contains(training_browser::render($continuous->id, $course->fullname, $course->id, 'newest'), 'orange-training-open'), 'La búsqueda por título abarca todas las formaciones');
training_check(str_contains(training_browser::render($root, strip_tags($course->summary), $course->id, 'name'), 'orange-training-open'), 'La búsqueda encuentra el resumen nativo');
training_check(!str_contains(training_browser::render($root, '<script>alert(1)</script>', 0, 'newest'), '<script>'), 'La búsqueda escapa HTML');
training_check(!str_contains(training_browser::render($root, '', 2147483647, 'newest'), 'orange-training-open'), 'ID desconocido sin acceso ni ficha');
$library = orange_native_courses((int)get_config('local_orangeportal', 'librarycategory'));
$documentcourse = reset($library);
training_check($documentcourse && !str_contains(training_browser::render($root, '', $documentcourse->id, 'newest'), 'orange-training-open'), 'No mezcla colecciones documentales en Formaciones');
$tx = $DB->start_delegated_transaction();
try {
    \core\session\manager::set_user(get_admin());
    update_course((object)['id' => $course->id, 'visible' => 0]);
    \core\session\manager::set_user($agent);
    training_check(!isset(training_browser::courses($root)[$course->id]), 'Un curso oculto no aparece para el agente');
    \core\session\manager::set_user($trainer);
    training_check(isset(training_browser::courses($root)[$course->id]), 'Formador conserva gestión de cursos ocultos según permisos nativos');
    $html = training_browser::render($initial->id, '', $course->id, 'newest');
    training_check(str_contains($html, 'orange-create-quiz') && str_contains($html, 'name="edit"'), 'Formador conserva edición y creación de evaluación');
} finally {
    try { $tx->rollback(new RuntimeException('training-test-rollback')); }
    catch (RuntimeException $e) { if ($e->getMessage() !== 'training-test-rollback') { throw $e; } }
    \cache::make('core', 'coursemodinfo')->purge();
    \course_modinfo::clear_instance_cache($course->id);
    rebuild_course_cache($course->id, true);
}
\core\session\manager::set_user($DB->get_record('user', ['username' => 'usuario.externo'], '*', MUST_EXIST));
training_check(!isset(training_browser::courses($root)[$course->id]), 'Usuario sin matrícula no obtiene el curso por el nuevo explorador');
echo "Datos temporales restaurados\n";
