<?php
/** Regression: submit the Moodle form and save notices within a rolled-back transaction. */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');
use local_orangeportal\form\notice;
use local_orangeportal\service;
if (!str_starts_with($CFG->wwwroot, 'http://localhost:')) { cli_error('Prueba limitada a local.'); }
$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/orangeportal/manage.php');
$admin = get_admin();
$editor = $DB->get_record('user', ['username' => 'editor.orange'], '*', MUST_EXIST);
$agent = $DB->get_record('user', ['username' => 'agente.orange'], '*', MUST_EXIST);
$cohort = $DB->get_record('cohort', ['idnumber' => 'ORANGE-EQUIPO-DEMO'], '*', MUST_EXIST);
$tx = $DB->start_delegated_transaction();
$checks = 0;
function form_check(bool $condition, string $label): void {
    global $checks;
    if (!$condition) { throw new RuntimeException($label); }
    $checks++; echo 'PASS ' . $label . PHP_EOL;
}
function submit_notice_form(string $kind, array $values): ?stdClass {
    notice::mock_submit($values);
    $form = new notice(orange_link('manage.php', ['kind' => $kind]), ['kind' => $kind]);
    return $form->get_data();
}
function save_notice_form(stdClass $data): int {
    return service::save_notice((object)['id' => $data->id, 'revision' => $data->revision, 'kind' => $data->kind,
        'categoryid' => $data->categoryid, 'title' => $data->title,
        'content' => format_text($data->body['text'], $data->body['format'], ['context' => context_system::instance(), 'filter' => false]),
        'state' => $data->state, 'impact' => $data->impact, 'published' => (int)$data->published],
        ['user' => $data->users ?? [], 'cohort' => $data->cohorts ?? [], 'group' => $data->groups ?? []]);
}
try {
    \core\session\manager::set_user($editor);
    foreach (['notifications', 'incidents'] as $kind) {
        $categories = $DB->get_records('local_orange_category', ['kind' => $kind], 'id', '*', 0, 1);
        $category = reset($categories);
        $values = ['id' => 0, 'revision' => 1, 'kind' => $kind, 'title' => 'Prueba de formulario · ' . $kind,
            'categoryid' => $category->id, 'body' => ['text' => '<p>Contenido inicial</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
            'users' => [$agent->id], 'published' => 1, 'submitbutton' => 'Guardar publicación'];
        // Frozen notification fields are deliberately omitted, as in the browser POST.
        if ($kind === 'incidents') { $values += ['state' => 'progress', 'impact' => 'high']; }
        $data = submit_notice_form($kind, $values);
        form_check($data !== null, 'Formulario valida: ' . $kind);
        form_check($data->state === ($kind === 'notifications' ? 'open' : 'progress') &&
            $data->impact === ($kind === 'notifications' ? 'normal' : 'high'), 'Estado e impacto válidos: ' . $kind);
        $id = save_notice_form($data);
        $saved = $DB->get_record('local_orange_notice', ['id' => $id], '*', MUST_EXIST);
        form_check($saved->kind === $kind && $saved->published && service::can_view($saved, (int)$agent->id, false),
            'Publicación guardada y asignada: ' . $kind);
        $values['id'] = $id;
        $values['body']['text'] = '<p>Contenido actualizado</p>';
        save_notice_form(submit_notice_form($kind, $values));
        form_check((int)$DB->get_field('local_orange_notice', 'revision', ['id' => $id]) === 2,
            'Edición conserva versionado: ' . $kind);
        $values['id'] = 0; $values['published'] = 0; $values['users'] = []; $values['cohorts'] = [$cohort->id];
        $draftid = save_notice_form(submit_notice_form($kind, $values));
        form_check((int)$DB->get_field('local_orange_notice', 'published', ['id' => $draftid]) === 0,
            'Borrador guardado con cohorte: ' . $kind);
    }
    foreach (['' => 'formulario antiguo', 'closed' => 'valor alterado'] as $postedstate => $label) {
        foreach ($DB->get_records('local_orange_category', ['kind' => 'notifications'], 'id') as $category) {
            $values = ['id' => 0, 'revision' => 1, 'kind' => 'notifications', 'title' => 'Pruebas nueva Notificación',
                'categoryid' => $category->id, 'body' => ['text' => '<p>Prueba del título indicado</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
                'users' => [$agent->id], 'published' => 1, 'state' => $postedstate, 'impact' => $postedstate === '' ? '' : 'critical'];
            $data = submit_notice_form('notifications', $values);
            form_check($data && $data->state === 'open' && $data->impact === 'normal',
                'Campos constantes prevalecen: ' . $label . ' / ' . $category->name);
            $id = save_notice_form($data);
            form_check($DB->get_field('local_orange_notice', 'title', ['id' => $id]) === 'Pruebas nueva Notificación',
                'Título con tilde guardado: ' . $label . ' / ' . $category->name);
        }
    }
    $values['kind'] = 'notifications';
    $values['categoryid'] = $DB->get_field('local_orange_category', 'id', ['kind' => 'notifications', 'name' => 'Comunicado']);
    $values['published'] = 1; $values['users'] = []; $values['cohorts'] = [];
    form_check(submit_notice_form('notifications', $values) === null, 'Falta de destinatarios se muestra como validación del formulario');
    echo 'Comprobaciones: ' . $checks . '. Se revierten todas las publicaciones de prueba.' . PHP_EOL;
    $tx->rollback(new RuntimeException('orange_form_test_rollback'));
} catch (Throwable $e) {
    if ($e->getMessage() !== 'orange_form_test_rollback') {
        try { $tx->rollback($e); } catch (Throwable $rolled) { fwrite(STDERR, $rolled->getMessage() . PHP_EOL); exit(1); }
    }
} finally {
    $_POST = []; $_FILES = [];
    \core\session\manager::set_user($admin);
}
