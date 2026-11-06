<?php
/** Local browser fixtures for counters changing while another page stays open. */
define('CLI_SCRIPT', true);
require __DIR__ . '/../../../config.php';
require_once $CFG->dirroot . '/local/orangeportal/locallib.php';
require_once $CFG->dirroot . '/user/lib.php';
require_once $CFG->dirroot . '/cohort/lib.php';
use local_orangeportal\service;
if (!preg_match('~^http://(localhost|127\\.0\\.0\\.1):~', $CFG->wwwroot)) { throw new coding_exception('Local demo only.'); }
$action = $argv[1] ?? '';
$token = $argv[2] ?? '';
if (!preg_match('/^[a-f0-9]{12}$/D', $token)) { throw new coding_exception('Fixture token required'); }
$username = 'testlive_' . $token;
$trainer = $DB->get_record('user', ['username' => 'formador.orange'], '*', MUST_EXIST);
\core\session\manager::set_user($trainer);
if ($action === 'create') {
    if ($DB->record_exists('user', ['username' => $username])) { throw new coding_exception('Fixture already exists'); }
    $template = $DB->get_record('user', ['username' => 'agente.orange'], '*', MUST_EXIST);
    $userid = user_create_user((object)['username' => $username, 'auth' => 'manual', 'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id, 'password' => $template->password, 'firstname' => 'TESTLIVE',
        'lastname' => $token, 'email' => $username . '@example.invalid', 'theme' => 'orange', 'lang' => 'es'], false);
    cohort_add_member((int)get_config('local_orangeportal', 'audiencecohort'), $userid);
    $ids = [];
    foreach (['normal' => ['incidents', 'normal', 0, $userid],
            'critical' => ['incidents', 'critical', 0, $userid],
            'communication' => ['notifications', 'critical', 0, $userid],
            'draft' => ['incidents', 'normal', 0, $userid],
            'unrelated' => ['incidents', 'normal', 1, $trainer->id]] as $key => $data) {
        [$kind, $impact, $published, $target] = $data;
        $ids[$key] = service::save_notice((object)['kind' => $kind,
            'categoryid' => $DB->get_field('local_orange_category', 'id', ['kind' => $kind], IGNORE_MULTIPLE),
            'title' => 'TESTLIVE ' . $token . ' ' . $key, 'content' => '<p>Temporary live update example</p>',
            'impact' => $impact, 'state' => 'open', 'published' => $published], ['user' => [$target]]);
    }
    echo json_encode(['token' => $token, 'username' => $username, 'notices' => $ids], JSON_THROW_ON_ERROR);
} else if ($action === 'publish') {
    $key = $argv[3] ?? '';
    if (!in_array($key, ['normal', 'critical', 'communication'], true)) { throw new coding_exception('Invalid fixture publication'); }
    $notice = $DB->get_record('local_orange_notice', ['title' => 'TESTLIVE ' . $token . ' ' . $key], '*', MUST_EXIST);
    $notice->published = 1;
    service::save_notice($notice, ['user' => [$DB->get_field('user', 'id', ['username' => $username], MUST_EXIST)]]);
    echo "Published temporary $key\n";
} else if ($action === 'cleanup') {
    foreach ($DB->get_records_select('local_orange_notice', 'title LIKE ?', ['TESTLIVE ' . $token . ' %']) as $notice) {
        foreach (['local_orange_receipt', 'local_orange_revision', 'local_orange_audience', 'local_orange_state_log'] as $table) {
            $DB->delete_records($table, ['noticeid' => $notice->id]);
        }
        $DB->delete_records('local_orange_notice', ['id' => $notice->id]);
    }
    if ($user = $DB->get_record('user', ['username' => $username, 'deleted' => 0])) { delete_user($user); }
    echo "Live update fixtures removed\n";
} else { throw new coding_exception('Unknown action'); }
