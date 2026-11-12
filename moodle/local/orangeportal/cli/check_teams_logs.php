<?php
/** Local HTTPS simulation: diagnostic detail must not expose the endpoint credential. */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/filelib.php');
use local_orangeportal\teams;
if (!str_starts_with($CFG->wwwroot, 'http://localhost:') || ($argv[1] ?? '') !== '--endpoint=https://host.docker.internal:4017/teams/invoke') {
    throw new coding_exception('Local simulation only');
}
$base = 'https://host.docker.internal:4017/teams';
// Fake credentials exist only in this test; no real URL/configuration is read or changed.
$secret = 'FAKE/LOG+SIGNATURE=123';
$endpoint = $base . '/invoke?api-version=1&sig=' . rawurlencode($secret);
$CFG->curlsecurityallowedport = "443\n4017"; $CFG->curlsecurityblockedhosts = '';
$captured = '';
function orange_capture_delivery_log($line, $eol): void { global $captured; $captured .= $line . $eol; }
function logs_check(bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); } echo 'PASS ' . $message . "\n";
}
function logs_control(int $code, string $body): void {
    global $base;
    $curl = new curl(); $curl->get($base . '/__control?code=' . $code . '&body=' . rawurlencode($body));
}
$previous = [];
foreach (['teamslastattempt', 'teamslastsuccess', 'teamslastresult'] as $key) { $previous[$key] = get_config('local_orangeportal', $key); }
$wrapper = $CFG->mtrace_wrapper ?? null;
try {
    $CFG->mtrace_wrapper = 'orange_capture_delivery_log';
    $payload = ['titulo' => 'Comunicado de prueba de logs', 'fechaPublicacion' => '2026-11-11T11:30:00', 'servicioAfectado' => 'Servicio de Atención al Cliente', 'contenido' => 'Detalle de prueba', 'url' => 'http://localhost:8080/local/orangeportal/view.php?id=123'];
    logs_control(202, '');
    teams::post($endpoint, $payload, 'test-local-correlation');
    logs_check(str_contains($captured, 'destino POST ' . $base . '/invoke') &&
        str_contains($captured, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 'Destino y JSON enviados aparecen en el registro');
    logs_check(str_contains($captured, 'respuesta HTTP 202') && str_contains($captured, '(vacío)') &&
        str_contains($captured, 'test-local-correlation'), 'HTTP, respuesta vacía e identificador aparecen explícitamente');
    logs_check(!str_contains($captured, $secret) && !str_contains($captured, rawurlencode($secret)), 'Firma secreta excluida del registro del destino');
    $captured = '';
    $response = json_encode(['error' => ['code' => 'InvalidAuthenticationToken', 'message' => 'Request rejected at ' . $endpoint],
        'access_token' => 'ANOTHER-FAKE-ACCESS-TOKEN', 'signature' => $secret]);
    logs_control(403, $response);
    try { teams::post($endpoint, $payload); throw new RuntimeException('Expected HTTP error'); }
    catch (RuntimeException $e) { logs_check($e->getMessage() === 'Teams: HTTP 403', 'El fallo conserva el código HTTP para los reintentos de Moodle'); }
    logs_check(str_contains($captured, 'respuesta HTTP 403') && str_contains($captured, 'InvalidAuthenticationToken') &&
        str_contains($captured, 'Request rejected'), 'El cuerpo de error y su código de negocio se conservan');
    logs_check(!str_contains($captured, $secret) && !str_contains($captured, rawurlencode($secret)) &&
        !str_contains($captured, 'ANOTHER-FAKE-ACCESS-TOKEN'), 'Firmas y tokens reflejados por el proveedor quedan ocultos');
    $captured = '';
    logs_control(202, str_repeat('X', 5000)); teams::post($endpoint, $payload);
    logs_check(str_contains($captured, '[truncado a 4000 caracteres]') && !str_contains($captured, str_repeat('X', 4001)), 'Las respuestas extensas se truncan de forma explícita');
    $text = teams::log_text('token at https://example.invalid/invoke?sig=OTHER-FAKE-TOKEN&x=1 Bearer BEARER-FAKE-TOKEN', $endpoint);
    logs_check(!str_contains($text, 'OTHER-FAKE-TOKEN') && !str_contains($text, 'BEARER-FAKE-TOKEN'), 'También se ocultan firmas y bearer tokens en texto no JSON');
    // Observe an actual task being skipped, without resending an existing real publication.
    $captured = '';
    $CFG->forced_plugin_settings['local_orangeportal']['teamsenabled'] = 0;
    $task = new \local_orangeportal\task\send_teams();
    $task->set_custom_data((object)['kind' => 'notifications', 'id' => 0, 'action' => 'updated']);
    $task->execute();
    logs_check(str_contains($captured, 'omitido; integración'), 'Una tarea omitida registra el motivo en lugar de terminar en silencio');
} finally {
    if ($wrapper === null) { unset($CFG->mtrace_wrapper); } else { $CFG->mtrace_wrapper = $wrapper; }
    logs_control(202, '{}');
    foreach ($previous as $key => $value) {
        if ($value === false) { unset_config($key, 'local_orangeportal'); } else { set_config($key, $value, 'local_orangeportal'); }
    }
}
