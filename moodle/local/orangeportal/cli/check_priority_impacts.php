<?php
/** Local-only regression: configurable incident alerts and independent announcement priority. */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once(__DIR__ . '/../locallib.php');
use local_orangeportal\notice_options as options;
use local_orangeportal\service;
use local_orangeportal\reading_report;
use local_orangeportal\personal_updates;
if (!str_starts_with($CFG->wwwroot, 'http://localhost:')) { cli_error('Local only'); }
function priority_check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException($label); }
    echo "PASS $label\n";
}
$original = get_config('local_orangeportal', 'notice_impacts');
$olduser = clone $USER;
$oldget = $_GET;
$failure = null;
$tx = $DB->start_delegated_transaction();
try {
    $_GET = ['period' => 'all'];
    $admin = $DB->get_record('user', ['username' => 'admin.orange'], '*', MUST_EXIST);
    $agent = $DB->get_record('user', ['username' => 'agente.orange'], '*', MUST_EXIST);
    \core\session\manager::set_user($admin);
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url('/local/orangeportal/index.php');
    $alpha = options::save('impacts', '', 'Alfa ' . bin2hex(random_bytes(4)), true, null, true);
    $beta = options::save('impacts', '', 'Beta ' . bin2hex(random_bytes(4)), true, null, false);
    $make = static function(string $kind, string $impact) use ($DB, $agent): stdClass {
        $id = service::save_notice((object)['kind' => $kind, 'title' => 'TEST priorities ' . bin2hex(random_bytes(5)),
            'content' => '<p>Priority regression</p>', 'impact' => $impact, 'state' => 'open', 'published' => 1,
            'categoryid' => $DB->get_field('local_orange_category', 'id', ['kind' => $kind], IGNORE_MULTIPLE)],
            ['user' => [$agent->id]]);
        return $DB->get_record('local_orange_notice', ['id' => $id]);
    };
    $incident = $make('incidents', $alpha);
    $ordinary = $make('incidents', $beta);
    $priority = $make('notifications', 'critical');
    $normal = $make('notifications', 'normal');
    priority_check(service::priority($incident) && !service::priority($ordinary), 'Alfa activa alerta por clasificación; Beta no');
    priority_check(reading_report::summary('incidents', 0, 25, $incident->id, 0, true)['total'] === 1 &&
        !reading_report::summary('incidents', 0, 25, $ordinary->id, 0, true)['total'], 'Seguimiento y detalle reconocen impactos críticos personalizados');
    priority_check(reading_report::data('incidents', $incident->id, 0, '', 0, 0, 0, true)['total'] === 1,
        'La consulta compartida con CSV incluye Alfa');
    \core\session\manager::set_user($agent);
    service::receipt($incident, 'received');
    $before = personal_updates::counts();
    $snapshot = $DB->get_record('local_orange_notice', ['id' => $incident->id]);
    $receipts = $DB->get_records('local_orange_receipt', ['noticeid' => $incident->id]);
    $tasks = $DB->count_records('task_adhoc');
    \core\session\manager::set_user($admin);
    options::save('impacts', $alpha, 'Alfa renombrada', false, null, true);
    priority_check(options::is_critical($alpha), 'Renombrar y desactivar conserva la clasificación de publicaciones existentes');
    options::save('impacts', $alpha, 'Alfa renombrada', false, null, false);
    priority_check(!reading_report::data('incidents', $incident->id, 0, '', 0, 0, 0, true)['total'],
        'Quitar alerta retira Alfa de las críticas de Seguimiento y CSV');
    \core\session\manager::set_user($agent);
    $after = personal_updates::counts();
    priority_check($after['incidentscritical'] === $before['incidentscritical'] - 1 && $after['incidents'] === $before['incidents'],
        'Desactivar alerta cambia el contador rojo y conserva el total pendiente');
    priority_check($snapshot == $DB->get_record('local_orange_notice', ['id' => $incident->id]) &&
        $receipts == $DB->get_records('local_orange_receipt', ['noticeid' => $incident->id]) &&
        $tasks === $DB->count_records('task_adhoc'), 'Cambiar clasificación conserva publicación, revisiones, lecturas y cola de envíos');
    \core\session\manager::set_user($admin);
    foreach (options::records('impacts') as $code => $record) {
        options::save('impacts', $code, $record['name'], $record['active'], null, false);
    }
    priority_check(!options::critical_codes() && !reading_report::summary('incidents', 0, 25, $incident->id, 0, true)['total'],
        'Sin impactos críticos no hay alertas de incidencias ni SQL inválido');
    priority_check(service::priority($priority) && !service::priority($normal) &&
        reading_report::summary('notifications', 0, 25, $priority->id, 0, true)['total'] === 1,
        'Prioridad de Comunicados es independiente del catálogo de Incidencias');
    priority_check(service::impact_label($normal, true) === '' && service::impact_label($priority) === get_string('noticepriority', 'local_orangeportal'),
        'Tabla muestra Prioritario y deja vacío el valor normal');
    \core\session\manager::set_user($agent);
    $indicators = array_column(personal_updates::indicators(), null, 'key');
    priority_check(!$indicators['incidents']['critical'] && $indicators['notifications']['critical'] > 0 &&
        str_contains($indicators['notifications']['description'], get_string('noticepriority', 'local_orangeportal')),
        'Iconos distinguen alertas de incidencias y comunicados prioritarios');
    \core\session\manager::set_user($admin);
    options::save('impacts', $beta, 'Beta', true, null, true);
    options::save('impacts', $alpha, 'Alfa', false, null, true);
    priority_check(count(options::critical_codes()) === 2, 'Se permiten varios impactos con alerta crítica');
    try { $make('notifications', $beta); throw new RuntimeException('Accepted invalid priority'); }
    catch (moodle_exception $e) { priority_check($e->errorcode === 'inactiveoptionselection', 'Comunicados rechaza impactos de Incidencias'); }
    // Free the legacy incident code inside this rollback transaction; announcements must not block its deletion.
    $DB->set_field('local_orange_notice', 'impact', $beta, ['kind' => 'incidents', 'impact' => 'critical']);
    options::delete('impacts', 'critical');
    priority_check(service::priority($priority) && reading_report::data('notifications', $priority->id, 0, '', 0, 0, 0, true)['total'] === 1,
        'Borrar el impacto Crítico de Incidencias no desactiva la prioridad de Comunicados');
    $keeppriority = $make('notifications', 'critical');
    $DB->set_field('local_orange_notice', 'impact', $beta, ['id' => $normal->id]);
    $DB->set_field('local_orange_notice', 'impact', 'high', ['id' => $priority->id]);
    $DB->insert_record('local_orange_receipt', (object)['noticeid' => $normal->id, 'userid' => $agent->id,
        'revision' => $normal->revision, 'received' => time(), 'readtime' => time(), 'confirmed' => 0]);
    $beforemigration = $DB->get_record('local_orange_notice', ['id' => $normal->id]);
    $beforemigration->impact = 'normal';
    $readings = $DB->get_records('local_orange_receipt', ['noticeid' => $normal->id]);
    // Run only the new migration, rolled back with the fixtures below.
    options::migrate_priorities();
    priority_check($beforemigration == $DB->get_record('local_orange_notice', ['id' => $normal->id]) &&
        $DB->get_field('local_orange_notice', 'impact', ['id' => $priority->id]) === 'normal' &&
        $DB->get_field('local_orange_notice', 'impact', ['id' => $keeppriority->id]) === 'critical' &&
        $readings == $DB->get_records('local_orange_receipt', ['noticeid' => $normal->id]),
        'Migración conserva Crítico como prioritario y convierte el resto a Normal sin tocar lecturas o fechas');
    options::migrate_priorities();
    priority_check($beforemigration == $DB->get_record('local_orange_notice', ['id' => $normal->id]) &&
        options::is_critical($alpha) && options::is_critical($beta), 'Repetir migración conserva datos y clasificación configurada');
} catch (Throwable $e) { $failure = $e; }
try { $tx->rollback(new RuntimeException('priority_rollback')); } catch (Throwable $e) {
    if ($e->getMessage() !== 'priority_rollback') { $failure = $failure ?? $e; }
}
if ($original === false) { unset_config('notice_impacts', 'local_orangeportal'); }
else { set_config('notice_impacts', $original, 'local_orangeportal'); }
\cache_helper::purge_by_definition('core', 'config');
\core\session\manager::set_user($olduser);
$_GET = $oldget;
if ($failure) { cli_error($failure->getMessage()); }
echo "Priority fixtures rolled back\n";
