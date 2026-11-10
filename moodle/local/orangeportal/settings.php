<?php
defined('MOODLE_INTERNAL') || die();
$ADMIN->add('root', new admin_category('orangeportal', get_string('pluginname', 'local_orangeportal')));
$page = new admin_settingpage('local_orangeportal', get_string('configuration', 'local_orangeportal'));
$page->add(new admin_setting_configcheckbox('local_orangeportal/enabled', get_string('portalenabled', 'local_orangeportal'),
    get_string('portalenabled_desc', 'local_orangeportal'), 0));
$page->add(new admin_setting_configselect('local_orangeportal/audiencecohort',
    get_string('audiencecohort', 'local_orangeportal'), get_string('audiencecohort_desc', 'local_orangeportal'), 0,
    [0 => get_string('none')] + $DB->get_records_menu('cohort', ['contextid' => context_system::instance()->id], 'name', 'id, name')));
$page->add(new \local_orangeportal\admin_setting_optional_multiselect('local_orangeportal/managedcohorts',
    get_string('orangemanagedcohorts', 'local_orangeportal'), get_string('orangemanagedcohorts_desc', 'local_orangeportal'), [],
    $DB->get_records_menu('cohort', ['component' => ''], 'name', 'id,name')));
$page->add(new admin_setting_configselect('local_orangeportal/landingsection',
    get_string('landingsection', 'local_orangeportal'), get_string('landingsection_desc', 'local_orangeportal'), 'library',
    array_combine(['library', 'courses', 'incidents', 'notifications'], array_map(
        fn($key) => get_string($key, 'local_orangeportal'), ['library', 'courses', 'incidents', 'notifications']))));
$page->add(new admin_setting_configtext('local_orangeportal/librarycategory', get_string('libraryroot', 'local_orangeportal'),
    get_string('libraryroot_desc', 'local_orangeportal'), 0, PARAM_INT));
$page->add(new admin_setting_configtext('local_orangeportal/coursescategory', get_string('coursesroot', 'local_orangeportal'),
    get_string('coursesroot_desc', 'local_orangeportal'), 0, PARAM_INT));
$page->add(new admin_setting_configcheckbox('local_orangeportal/automaticalerts', get_string('automaticalerts', 'local_orangeportal'),
    get_string('automaticalerts_desc', 'local_orangeportal'), 1));
$ADMIN->add('orangeportal', $page);
$teams = new admin_settingpage('local_orangeportal_teams', get_string('teamstitle', 'local_orangeportal'));
$teams->add(new admin_setting_heading('local_orangeportal/teamsintro', '', get_string('teamsintro', 'local_orangeportal')));
$teams->add(new admin_setting_configcheckbox('local_orangeportal/teamsenabled', get_string('teamsenabled', 'local_orangeportal'),
    get_string('teamsenabled_desc', 'local_orangeportal'), 0));
$teams->add(new \local_orangeportal\admin_setting_teams_url('local_orangeportal/teamsurl', get_string('teamsurl', 'local_orangeportal'),
    get_string('teamsurl_desc', 'local_orangeportal'), ''));
foreach (\local_orangeportal\teams::KINDS as $kind) {
    $teams->add(new admin_setting_configcheckbox('local_orangeportal/teams_' . $kind,
        get_string($kind, 'local_orangeportal'), get_string('teamstype_desc', 'local_orangeportal'), 1));
}
foreach (['created', 'updated'] as $action) {
    $teams->add(new admin_setting_configcheckbox('local_orangeportal/teams_' . $action,
        get_string('teams_' . $action, 'local_orangeportal'), '', 1));
}
if ($ADMIN->fulltree) {
    $attempt = (int)get_config('local_orangeportal', 'teamslastattempt');
    $success = (int)get_config('local_orangeportal', 'teamslastsuccess');
    $status = get_string('teamsnever', 'local_orangeportal');
    if ($attempt) {
        $status = get_string('teamslastattempt', 'local_orangeportal') . ': ' . userdate($attempt) . ' — ' .
            s(get_config('local_orangeportal', 'teamslastresult'));
    }
    if ($success) { $status .= '<br>' . get_string('teamslastsuccess', 'local_orangeportal') . ': ' . userdate($success); }
    $status .= '<br>' . html_writer::link(new moodle_url('/admin/tool/task/adhoctasks.php', ['classname' => '\\local_orangeportal\\task\\send_teams']),
        get_string('teamsqueue', 'local_orangeportal')) . ' · ' . html_writer::link(new moodle_url('/admin/tasklogs.php'),
        get_string('teamstasklogs', 'local_orangeportal'));
    $teams->add(new admin_setting_heading('local_orangeportal/teamsstatus', get_string('teamsstatus', 'local_orangeportal'), $status));
}
$ADMIN->add('orangeportal', $teams);
$ADMIN->add('orangeportal', new admin_externalpage('orangeportalmanage', get_string('publishmanage', 'local_orangeportal'),
    new moodle_url('/local/orangeportal/manage.php'), 'local/orangeportal:manage'));
$ADMIN->add('orangeportal', new admin_externalpage('orangeportalcategories', get_string('noticecategories', 'local_orangeportal'),
    new moodle_url('/local/orangeportal/categories.php'), 'local/orangeportal:manage'));
$ADMIN->add('orangeportal', new admin_externalpage('orangeportalreports', get_string('reportstitle', 'local_orangeportal'),
    new moodle_url('/local/orangeportal/reports.php'), 'local/orangeportal:viewreports'));
