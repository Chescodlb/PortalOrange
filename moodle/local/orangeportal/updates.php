<?php
/** Authenticated personal counters only; polling never records delivery or reading. */
define('AJAX_SCRIPT', true);
// Background polling must not keep an otherwise inactive Moodle session alive.
define('NO_SESSION_UPDATE', true);
require_once(__DIR__ . '/../../config.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('Pragma: no-cache');
if (!isloggedin() || isguestuser()) {
    http_response_code(401);
    echo json_encode(['error' => 'loginrequired']);
    exit;
}
require_login(null, false, null, false, true);
if (!\local_orangeportal\experience::member() || !confirm_sesskey()) {
    http_response_code(403);
    echo json_encode(['error' => 'accessdenied']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'postrequired']);
    exit;
}
// Avoid holding the session lock while counting native courses and resources.
\core\session\manager::write_close();
echo json_encode(['indicators' => \local_orangeportal\personal_updates::indicators()], JSON_THROW_ON_ERROR);
