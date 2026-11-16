<?php
/** Local-only regression for scoped critical reading lists; all fixture changes are rolled back. */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/orangeportal/locallib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
use local_orangeportal\orange_administration as admin;
use local_orangeportal\reading_report as readings;
use local_orangeportal\service;
if (!preg_match('~^http://(localhost|127\.0\.0\.1):~', $CFG->wwwroot)) { throw new coding_exception('Local only'); }
function critical_check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException($label); }
    echo "PASS $label\n";
}
$olduser = clone $USER; $oldget = $_GET; $oldpost = $_POST;
$tx = $DB->start_delegated_transaction();
try {
    $_GET = ['period' => 'all']; $_POST = [];
    $actor = $DB->get_record('user', ['username' => 'admin.orange'], '*', MUST_EXIST);
    \core\session\manager::set_user($actor);
    $createuser = static function(string $label): int {
        $token = bin2hex(random_bytes(7));
        return admin::create_user((object)['username' => 'criticaltest_' . $token, 'firstname' => $label,
            'lastname' => 'Critical test', 'email' => $token . '@localhost.invalid', 'password' => 'Testing!7aB' . $token]);
    };
    $inside = $createuser('Inside'); $outside = $createuser('Outside'); $coordinator = $createuser('Coordinator');
    $team = admin::save_cohort(0, 'TEST Critical team');
    $otherteam = admin::save_cohort(0, 'TEST Other critical team');
    cohort_add_member($team, $inside); cohort_add_member($team, $coordinator); cohort_add_member($otherteam, $outside);
    role_assign($DB->get_field('role', 'id', ['shortname' => 'orange_seguimiento'], MUST_EXIST),
        $coordinator, context_system::instance()->id);
    $make = static function(string $kind, string $impact = 'critical', string $state = 'open', int $published = 1,
            ?array $targets = null) use ($DB, $inside, $outside): int {
        return service::save_notice((object)['kind' => $kind, 'title' => 'TEST critical reading ' . bin2hex(random_bytes(5)),
            'content' => '<p>Critical reading fixture</p>', 'impact' => $impact, 'state' => $state, 'published' => $published,
            'categoryid' => $DB->get_field('local_orange_category', 'id', ['kind' => $kind], IGNORE_MULTIPLE)],
            ['user' => $targets ?? [$inside, $outside]]);
    };
    $critical = $make('notifications');
    $normal = $make('notifications', 'normal');
    $open = $make('incidents');
    $closed = $make('incidents', 'critical', 'closed');
    $draft = $make('notifications', 'critical', 'open', 0);
    $empty = $make('notifications');
    $DB->delete_records('local_orange_audience', ['noticeid' => $empty]);
    $read = $DB->insert_record('local_orange_receipt', (object)['noticeid' => $critical, 'userid' => $inside,
        'revision' => 1, 'received' => time(), 'readtime' => time(), 'confirmed' => 0]);
    $summary = readings::summary('notifications', 0, 25, $critical, 0, true)['rows'][$critical];
    critical_check($summary->recipients === 2 && $summary->readcount === 1 && $summary->unreadcount === 1,
        'Críticas pendientes conservan los totales completos de destinatarios, leídos y sin leer');
    $data = readings::data('notifications', $critical, 0, '', 0, 0, 0, true);
    critical_check($data['total'] === 1 && (int)reset($data['rows'])->userid === $outside,
        'El detalle de una crítica muestra solo sus destinatarios pendientes');
    critical_check(!readings::summary('notifications', 0, 25, $normal, 0, true)['total'] &&
        !readings::data('notifications', $normal, 0, '', 0, 0, 0, true)['total'], 'Normal queda fuera del filtro crítico');
    critical_check(!readings::summary('notifications', 0, 25, $draft, 0, true)['total'] &&
        !readings::summary('notifications', 0, 25, $empty, 0, true)['total'], 'Se excluyen borradores y publicaciones sin destinatarios');
    critical_check(readings::summary('incidents', 0, 25, $open, 0, true)['total'] === 1 &&
        !readings::summary('incidents', 0, 25, $closed, 0, true)['total'] &&
        !readings::data('incidents', $closed, 0, '', 0, 0, 0, true)['total'], 'Se incluyen incidencias abiertas y se excluyen cerradas');
    critical_check(readings::summary('incidents', 0, 25, $closed)['total'] === 1,
        'Todas las publicaciones conserva la consulta de incidencias cerradas');
    \core\session\manager::set_user($DB->get_record('user', ['id' => $coordinator], '*', MUST_EXIST));
    critical_check(!readings::summary('notifications', 0, 25, $critical, 0, true)['total'],
        'Pendientes de otro equipo no hacen aparecer una crítica en el equipo del coordinador');
    critical_check(!readings::data('notifications', $critical, 0, '', 0, 0, 0, true)['total'],
        'El detalle no expone al destinatario pendiente ajeno al colectivo');
    \core\session\manager::set_user($actor);
    $_GET['report_cohort'] = $team;
    critical_check(!readings::summary('notifications', 0, 25, $critical, 0, true)['total'],
        'El filtro de colectivo también restringe al administrador global de Orange');
    unset($_GET['report_cohort']);
    $DB->set_field('local_orange_receipt', 'readtime', 0, ['id' => $read]);
    $DB->set_field('local_orange_receipt', 'confirmed', time(), ['id' => $read]);
    critical_check(readings::data('notifications', $critical, $inside, '', 0, 0, 0, true)['total'] === 0,
        'La confirmación histórica cuenta como lectura y no aparece como pendiente');
    $DB->insert_record('local_orange_receipt', (object)['noticeid' => $critical, 'userid' => $outside,
        'revision' => 1, 'received' => time(), 'readtime' => time(), 'confirmed' => 0]);
    critical_check(!readings::summary('notifications', 0, 25, $critical, 0, true)['total'],
        'Una publicación desaparece de pendientes cuando todos la han leído');
    $notice = $DB->get_record('local_orange_notice', ['id' => $critical], '*', MUST_EXIST);
    $notice->title .= ' revised';
    service::save_notice($notice, ['user' => [$inside, $outside]]);
    critical_check(readings::data('notifications', $critical, 0, '', 0, 0, 0, true)['total'] === 2,
        'Una nueva versión solicita lectura y vuelve a aparecer como pendiente');
    $older = $make('notifications');
    $oldtime = time() - 60 * DAYSECS;
    $DB->set_field('local_orange_notice', 'timecreated', $oldtime, ['id' => $older]);
    $DB->set_field('local_orange_revision', 'timecreated', $oldtime, ['noticeid' => $older]);
    critical_check(readings::summary('notifications', 0, 25, $older, 0, true)['total'] === 1 &&
        !readings::summary('notifications', 0, 25, $older, time() - 30 * DAYSECS, true)['total'] &&
        !readings::data('notifications', $older, 0, '', 0, 0, time() - 30 * DAYSECS, true)['total'],
        'Todo el histórico incluye las antiguas; el periodo seleccionado se aplica a lista y detalle');
    $historic = readings::critical_pending();
    $_GET = ['period' => 'custom', 'report_from' => date('Y-m-d', time() - 70 * DAYSECS),
        'report_to' => date('Y-m-d', time() - 45 * DAYSECS)];
    critical_check(!readings::summary('notifications', 0, 25, $critical, 0, true)['total'] &&
        readings::summary('notifications', 0, 25, $older, 0, true)['total'] === 1,
        'El límite superior del periodo personalizado se respeta');
    critical_check(readings::critical_pending() == $historic, 'El histórico de Usuarios mantiene su alcance independiente del periodo');
    $_GET = ['period' => 'all'];
    $before = readings::summary('notifications', 0, 25, 0, 0, true)['total'];
    for ($i = 0; $i < 30; $i++) { $make('notifications', 'critical', 'open', 1, [$inside]); }
    $first = readings::summary('notifications', 0, 25, 0, 0, true);
    $second = readings::summary('notifications', 1, 25, 0, 0, true);
    critical_check($first['total'] === $before + 30 && count($first['rows']) === 25 &&
        !array_intersect(array_keys($first['rows']), array_keys($second['rows'])),
        'El conteo y la paginación se aplican después del filtro crítico');
    $export = readings::data('notifications', 0, 0, 'unread', 0, 0, 0, true);
    critical_check($export['total'] === count($export['rows']) && array_reduce($export['rows'],
        static fn($ok, $r) => $ok && $r->impact === 'critical' && !$r->readtime, true),
        'La consulta compartida del CSV exporta todas las páginas y solo críticas pendientes');
    \core\session\manager::set_user($DB->get_record('user', ['id' => $inside], '*', MUST_EXIST));
    try { readings::data('notifications', 0, 0, '', 0, 0, 0, true); throw new RuntimeException('Agent accessed reports'); }
    catch (required_capability_exception $e) { critical_check(true, 'El filtro no concede acceso de seguimiento a un agente'); }
} finally {
    $_GET = $oldget; $_POST = $oldpost;
    try { $tx->rollback(new RuntimeException('critical-reading-rollback')); }
    catch (RuntimeException $e) { if ($e->getMessage() !== 'critical-reading-rollback') { throw $e; } }
    \core\session\manager::set_user($olduser);
}
echo "Datos temporales restaurados\n";
