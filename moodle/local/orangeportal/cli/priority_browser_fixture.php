<?php
/** Temporary fixtures for priority browser checks, limited to the local demo. */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');
use local_orangeportal\notice_options;
use local_orangeportal\service;
if (!str_starts_with($CFG->wwwroot, 'http://localhost:')) { throw new coding_exception('Local only'); }
\core\session\manager::set_user($DB->get_record('user', ['username' => 'admin.orange'], '*', MUST_EXIST));
$action = $argv[1] ?? '';
if ($action === 'create') {
    $token = bin2hex(random_bytes(6));
    $alpha = notice_options::save('impacts', '', 'TEST Alfa ' . $token, true, null, false);
    $agent = $DB->get_field('user', 'id', ['username' => 'agente.orange'], MUST_EXIST);
    $ids = [];
    foreach (['priority' => ['notifications', 'critical'], 'normal' => ['notifications', 'normal'],
            'alpha' => ['incidents', $alpha]] as $key => [$kind, $impact]) {
        $ids[$key] = service::save_notice((object)['kind' => $kind, 'title' => 'TEST priority ' . $token . ' ' . $key,
            'content' => '<p>Priority browser fixture</p>', 'impact' => $impact, 'state' => 'open', 'published' => 1,
            'categoryid' => $DB->get_field('local_orange_category', 'id', ['kind' => $kind], IGNORE_MULTIPLE)],
            ['user' => [$agent]]);
    }
    echo json_encode(['token' => $token, 'impact' => $alpha, 'notices' => $ids], JSON_THROW_ON_ERROR);
} else if (in_array($action, ['enable', 'disable'], true)) {
    $token = $argv[2] ?? '';
    if (!preg_match('/^[a-f0-9]{12}$/D', $token)) { throw new coding_exception('Invalid token'); }
    foreach (notice_options::records('impacts') as $code => $record) {
        if ($record['name'] === 'TEST Alfa ' . $token) {
            notice_options::save('impacts', $code, $record['name'], $record['active'], null, $action === 'enable');
        }
    }
} else if ($action === 'cleanup') {
    $token = $argv[2] ?? '';
    if (!preg_match('/^[a-f0-9]{12}$/D', $token)) { throw new coding_exception('Invalid token'); }
    foreach (['priority', 'normal', 'alpha'] as $key) {
        if ($notice = $DB->get_record('local_orange_notice', ['title' => 'TEST priority ' . $token . ' ' . $key])) {
            foreach (['local_orange_receipt', 'local_orange_audience', 'local_orange_revision', 'local_orange_state_log'] as $table) {
                $DB->delete_records($table, ['noticeid' => $notice->id]);
            }
            $DB->delete_records('local_orange_notice', ['id' => $notice->id]);
        }
    }
    foreach (notice_options::records('impacts') as $code => $record) {
        if ($record['name'] === 'TEST Alfa ' . $token) { notice_options::delete('impacts', $code); }
    }
    echo "Priority browser fixtures cleaned\n";
} else { throw new coding_exception('Invalid fixture action'); }
