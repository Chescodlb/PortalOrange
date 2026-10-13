<?php
/** Verify the real native form and its section validation without creating courses. */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/edit_form.php');
use local_orangeportal\course_creation;
use local_orangeportal\native_categories;
if (!str_starts_with($CFG->wwwroot, 'http://localhost:')) { cli_error('Prueba limitada a local.'); }
$checks = 0;
function creation_check(bool $condition, string $label): void {
    global $checks;
    if (!$condition) { throw new RuntimeException($label); }
    $checks++; echo 'PASS ' . $label . PHP_EOL;
}
foreach (['formador.orange', 'admin'] as $username) {
    $user = $username === 'admin' ? get_admin() : $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
    \core\session\manager::set_user($user);
    foreach (['courses', 'library'] as $section) {
        $root = native_categories::root($section);
        $other = native_categories::root($section === 'courses' ? 'library' : 'courses');
        $returnurl = new moodle_url('/local/orangeportal/index.php', ['section' => $section]);
        $PAGE->set_context(context_coursecat::instance($root->id));
        $PAGE->set_url('/course/edit.php', ['category' => $root->id, 'returnto' => 'url',
            'returnurl' => $returnurl->out(false)]);
        $form = new course_edit_form(null, ['course' => new stdClass(), 'category' => $root,
            'editoroptions' => ['maxfiles' => -1, 'maxbytes' => $CFG->maxbytes, 'context' => $PAGE->context],
            'returnto' => 'url', 'returnurl' => $returnurl]);
        $html = $form->render();
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($dom);
        $ids = [];
        foreach ($xpath->query('//select[@name="category"]/option') as $option) {
            $ids[] = (int)$option->getAttribute('value');
        }
        creation_check(in_array((int)$root->id, $ids), "$username / $section: selector incluye la raíz correcta");
        creation_check(!in_array((int)$other->id, $ids) && $ids &&
            !array_diff($ids, array_keys(course_creation::options($section))),
            "$username / $section: formulario nativo excluye la otra sección");
        $valid = new \core_course\hook\after_form_validation($form, ['category' => $root->id]);
        course_creation::validation($valid);
        creation_check(!$valid->get_errors(), "$username / $section: acepta categoría de destino");
        $invalid = new \core_course\hook\after_form_validation($form, ['category' => $other->id]);
        course_creation::validation($invalid);
        creation_check(isset($invalid->get_errors()['category']), "$username / $section: rechaza destino cruzado");
        $PAGE->set_url('/course/edit.php', ['category' => $root->id]);
        creation_check(course_creation::section() === null, "$username / $section: administración general sin filtro");
        $PAGE->set_url('/course/edit.php', ['id' => 999, 'returnto' => 'url', 'returnurl' => $returnurl->out(false)]);
        creation_check(course_creation::section() === null, "$username / $section: edición existente sin filtro");
    }
}
\core\session\manager::set_user($DB->get_record('user', ['username' => 'agente.orange'], '*', MUST_EXIST));
creation_check(!course_creation::options('courses') && !course_creation::options('library'),
    'Agente sin permisos no obtiene destinos de creación');
$tx = $DB->start_delegated_transaction();
try {
    \core\session\manager::set_user($DB->get_record('user', ['username' => 'formador.orange'], '*', MUST_EXIST));
    $root = native_categories::root('library');
    $returnurl = new moodle_url('/local/orangeportal/index.php', ['section' => 'library']);
    $PAGE->set_context(context_coursecat::instance($root->id));
    $PAGE->set_url('/course/edit.php', ['category' => $root->id, 'returnto' => 'url', 'returnurl' => $returnurl->out(false)]);
    course_edit_form::mock_submit(['fullname' => 'Colección de prueba temporal', 'shortname' => 'orange-test-' . uniqid(),
        'category' => $root->id, 'summary_editor' => ['text' => 'Descripción de prueba', 'format' => FORMAT_HTML, 'itemid' => 0],
        'saveanddisplay' => 1, 'format' => 'weeks', 'showgrades' => 1, 'enablecompletion' => 1,
        'startdate' => time(), 'enddate' => time() + DAYSECS]);
    $form = new course_edit_form(null, ['course' => new stdClass(), 'category' => $root,
        'editoroptions' => ['maxfiles' => -1, 'maxbytes' => $CFG->maxbytes, 'context' => $PAGE->context],
        'returnto' => 'url', 'returnurl' => $returnurl]);
    $data = $form->get_data();
    creation_check($data !== null, 'Formulario documental valida una publicación real');
    creation_check($data->format === 'topics' && !$data->showgrades && !$data->enablecompletion &&
        !$data->startdate && !$data->enddate && (int)$data->numsections === 1,
        'Valores documentales prevalecen sobre campos POST alterados');
    $data->summary = $data->summary_editor['text'];
    $data->summaryformat = $data->summary_editor['format'];
    $created = create_course($data);
    creation_check((int)$created->category === (int)$root->id && $created->format === 'topics' &&
        !$created->showgrades && !$created->enablecompletion && !$created->enddate,
        'Moodle guarda una colección con configuración documental');
    creation_check($DB->count_records('course_sections', ['course' => $created->id, 'section' => 1]) === 1,
        'La colección nativa contiene una sección para documentos');
    echo "Comprobaciones: $checks. Se revierte la colección temporal." . PHP_EOL;
    $tx->rollback(new RuntimeException('orange_collection_test_rollback'));
} catch (Throwable $e) {
    if ($e->getMessage() !== 'orange_collection_test_rollback') {
        if (!$tx->is_disposed()) { $tx->rollback($e); }
        throw $e;
    }
}
