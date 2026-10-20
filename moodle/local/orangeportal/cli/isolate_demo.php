<?php
// Local-only migration of our former all-Orange demo. Never run against the existing client site.
define('CLI_SCRIPT', true);
require __DIR__ . '/../../../config.php';
require_once $CFG->dirroot . '/cohort/lib.php';
require_once $CFG->dirroot . '/user/lib.php';
require_once $CFG->libdir . '/adminlib.php';
if (!preg_match('~^http://(localhost|127\\.0\\.0\\.1):~', $CFG->wwwroot)) {
    throw new coding_exception('This command is for the local demo only.');
}
\core\session\manager::set_user(get_admin());
$password = getenv('ORANGE_DEMO_PASSWORD');
if (!$password) { throw new coding_exception('ORANGE_DEMO_PASSWORD is required.'); }
$backupdir = $CFG->dirroot . '/../backups';
if (!is_dir($backupdir) && !mkdir($backupdir, 0770, true)) {
    throw new coding_exception('Cannot create local backup directory.');
}
$backupfile = $backupdir . '/before-isolation-' . date('Ymd-His') . '.json';
$sitekeys = ['theme','enabledashboard','defaulthomepage','custommenuitems','allowcohortthemes'];
$backup = ['moove' => get_config('theme_moove'), 'orange' => get_config('theme_orange'),
    'portal' => get_config('local_orangeportal'), 'sitecourse' => ['fullname' => $SITE->fullname, 'shortname' => $SITE->shortname], 'site' => []];
foreach ($sitekeys as $key) { $backup['site'][$key] = get_config('core', $key); }
if (file_put_contents($backupfile, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
    throw new coding_exception('Cannot save the settings backup.');
}
chmod($backupfile, 0600);
$cohort = $DB->get_record('cohort', ['idnumber' => 'orange_portal_users']);
if (!$cohort) {
    $cohort = (object)['contextid' => context_system::instance()->id, 'name' => 'Usuarios Orange',
        'idnumber' => 'orange_portal_users', 'visible' => 1];
    $cohort->id = cohort_add_cohort($cohort);
}
foreach (['agente.orange', 'agente.otro', 'editor.orange', 'coordinador.orange', 'formador.orange'] as $username) {
    $id = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
    cohort_add_member($cohort->id, $id);
}
// Only remove the Orange overrides introduced by our former local bootstrap.
foreach (['scss','brandcolor','secondarymenucolor','fontsite','logo'] as $key) { unset_config($key, 'theme_moove'); }
set_config('brandcolor', '#FF7900', 'theme_orange');
set_config('secondarymenucolor', '#000000', 'theme_orange');
set_config('fontsite', 'Moodle', 'theme_orange');
// Copy the uploaded logo into the Orange file area, preserving Moodle's native upload management.
$fs = get_file_storage();
$context = context_system::instance();
$oldlogo = $fs->get_file($context->id, 'theme_moove', 'logo', 0, '/', 'orange-logo.png');
if ($oldlogo && !$fs->get_file($context->id, 'theme_orange', 'logo', 0, '/', 'orange-logo.png')) {
    $fs->create_file_from_storedfile(['contextid' => $context->id, 'component' => 'theme_orange',
        'filearea' => 'logo', 'itemid' => 0, 'filepath' => '/', 'filename' => 'orange-logo.png'], $oldlogo);
    set_config('logo', '/orange-logo.png', 'theme_orange');
}
set_config('allowcohortthemes', 1);
$cohort->theme = 'orange';
cohort_update_cohort($cohort);
set_config('audiencecohort', $cohort->id, 'local_orangeportal');
set_config('enabled', 1, 'local_orangeportal');
set_config('theme', 'moove');
set_config('enabledashboard', 1);
set_config('defaulthomepage', HOMEPAGE_MY);
admin_write_settings(['s__fullname' => 'Campus de formación · Demo', 's__shortname' => 'Campus Demo']);
if (!$DB->record_exists('user', ['username' => 'usuario.externo'])) {
    user_create_user((object)['username' => 'usuario.externo', 'password' => $password, 'auth' => 'manual',
        'firstname' => 'Usuario', 'lastname' => 'Externo', 'email' => 'usuario.externo@example.invalid',
        'confirmed' => 1, 'mnethostid' => $CFG->mnet_localhost_id, 'lang' => 'es']);
}
theme_reset_all_caches();
echo "Local isolation configured. Orange cohort: {$cohort->id}. External test user: usuario.externo.\n";
echo "Settings backup: " . basename($backupfile) . "\n";
