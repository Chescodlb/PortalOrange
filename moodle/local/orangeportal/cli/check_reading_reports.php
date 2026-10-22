<?php
/** Local-only report paging, assignment parity and automatic reading regression. */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/orangeportal/locallib.php');
use local_orangeportal\reading_report;
use local_orangeportal\service;
if (!preg_match('~^http://(localhost|127\.0\.0\.1):~', $CFG->wwwroot)) { throw new coding_exception('Local demo only.'); }
function reading_check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException($label); }
    echo "PASS $label\n";
}
$agent = $DB->get_record('user', ['username' => 'agente.orange'], '*', MUST_EXIST);
$trainer = $DB->get_record('user', ['username' => 'formador.orange'], '*', MUST_EXIST);
$tx = $DB->start_delegated_transaction();
try {
    \core\session\manager::set_user($trainer);
    $base = reading_report::data('notifications', 0, $agent->id)['total'];
    $category = $DB->get_field('local_orange_category', 'id', ['kind' => 'notifications'], IGNORE_MULTIPLE);
    $ids = [];
    for ($i = 0; $i < 30; $i++) {
        $ids[] = service::save_notice((object)['kind' => 'notifications', 'title' => 'TEST informe ' . $i,
            'content' => '<p>Fixture</p>', 'categoryid' => $category, 'published' => 1, 'impact' => 'normal', 'state' => 'open'],
            ['user' => [$agent->id]]);
    }
    $first = reading_report::data('notifications', 0, $agent->id, '', 0);
    $second = reading_report::data('notifications', 0, $agent->id, '', 1);
    reading_check($first['total'] === $base + 30 && count($first['rows']) === 25, 'Conteo completo y 25 filas en primera página');
    reading_check(!array_intersect(array_keys($first['rows']), array_keys($second['rows'])) && count($second['rows']) > 0,
        'Segunda página sin filas repetidas');
    $last = reading_report::data('notifications', 0, $agent->id, '', 9999);
    reading_check($last['page'] === (int)ceil($last['total'] / 25) - 1 && count($last['rows']) > 0, 'Página fuera de rango ajustada');
    $id = end($ids);
    $notice = $DB->get_record('local_orange_notice', ['id' => $id]);
    reading_check(reading_report::data('incidents', $id)['total'] === 0, 'Tipos separados incluso con ID de otro bloque');
    $DB->insert_record('local_orange_audience', (object)['noticeid' => $id, 'targettype' => 'cohort',
        'targetid' => (int)get_config('local_orangeportal', 'audiencecohort')]);
    $group = $DB->get_record_sql('SELECT g.* FROM {groups} g JOIN {groups_members} m ON m.groupid=g.id WHERE m.userid=?', [$agent->id], IGNORE_MULTIPLE);
    if ($group) { $DB->insert_record('local_orange_audience', (object)['noticeid' => $id, 'targettype' => 'group', 'targetid' => $group->id]); }
    $expected = [];
    foreach (\local_orangeportal\orange_scope::users() as $u) { if (service::assigned($id, $u->id)) { $expected[] = (int)$u->id; } }
    $all = reading_report::data('notifications', $id, 0, '', 0, 0);
    $actual = array_map(static fn($r) => (int)$r->userid, array_values($all['rows']));
    sort($expected); sort($actual);
    reading_check($expected === $actual && count($actual) === count(array_unique($actual)), 'Audiencia común sin duplicar usuario, cohorte o grupo');
    $overview = reading_report::summary('notifications', 0, 25, $id)['rows'][$id];
    reading_check($overview->recipients === count($actual) && $overview->readcount === 0 &&
        $overview->unreadcount === count($actual), 'Resumen por publicación cuenta destinatarios sin duplicarlos');
    $summarypage = reading_report::summary('notifications');
    reading_check(count($summarypage['rows']) === 25 &&
        !array_intersect(array_keys($summarypage['rows']), array_keys(reading_report::summary('notifications', 1)['rows'])),
        'Resumen paginado por publicaciones, no por usuarios');
    \core\session\manager::set_user($agent);
    service::receipt($notice, 'received');
    $receipt = service::receipt($notice, 'readtime');
    reading_check($receipt->readtime > 0 && !$receipt->confirmed, 'Apertura registra lectura sin confirmar');
    try {
        service::receipt($notice, 'confirmed');
        throw new RuntimeException('Confirmación manual aceptada');
    } catch (invalid_parameter_exception $expected) { reading_check(true, 'Confirmación manual rechazada en servidor'); }
    \core\session\manager::set_user($trainer);
    reading_check(reading_report::data('notifications', $id, $agent->id, 'read')['total'] === 1 &&
        reading_report::data('notifications', $id, $agent->id, 'unread')['total'] === 0, 'Filtros individuales y lectura coherentes');
    $overview = reading_report::summary('notifications', 0, 25, $id)['rows'][$id];
    reading_check($overview->readcount === 1 && $overview->unreadcount + $overview->readcount === $overview->recipients,
        'Lectura actualiza resumen y coincide con el detalle');
    $DB->delete_records('local_orange_audience', ['noticeid' => $ids[0]]);
    $empty = reading_report::summary('notifications', 0, 25, $ids[0])['rows'][$ids[0]];
    reading_check($empty->recipients === 0 && $empty->readcount === 0 && $empty->unreadcount === 0,
        'Publicación sin destinatarios actuales permanece en el resumen con ceros');
    $DB->set_field('local_orange_receipt', 'confirmed', time(), ['id' => $receipt->id]);
    $DB->set_field('local_orange_receipt', 'readtime', 0, ['id' => $receipt->id]);
    reading_check(reading_report::data('notifications', $id, $agent->id, 'read')['total'] === 1,
        'Evidencia histórica conservada sin solicitar confirmaciones nuevas');
    $export = reading_report::data('notifications', 0, $agent->id, '', 0, 0);
    reading_check(count($export['rows']) === $export['total'] && $export['total'] === $first['total'] - 1, 'Exportación usa mismos filtros y todas las páginas');
    \core\session\manager::set_user($agent);
    try { reading_report::data('notifications'); throw new RuntimeException('Informe accesible al agente'); }
    catch (required_capability_exception $expected) { reading_check(true, 'Agente sin acceso a informes'); }
} finally {
    try { $tx->rollback(new RuntimeException('reading-report-rollback')); }
    catch (RuntimeException $e) { if ($e->getMessage() !== 'reading-report-rollback') { throw $e; } }
}
echo "Datos temporales restaurados\n";
