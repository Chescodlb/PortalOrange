<?php
defined('MOODLE_INTERNAL') || die();
$ADMIN->add('root', new admin_category('orangeportal', get_string('pluginname', 'local_orangeportal')));
$page = new admin_settingpage('local_orangeportal', get_string('configuration', 'local_orangeportal'));
$page->add(new admin_setting_configcheckbox('local_orangeportal/enabled', get_string('portalenabled', 'local_orangeportal'),
    get_string('portalenabled_desc', 'local_orangeportal'), 0));
$page->add(new admin_setting_configselect('local_orangeportal/audiencecohort',
    get_string('audiencecohort', 'local_orangeportal'), get_string('audiencecohort_desc', 'local_orangeportal'), 0,
    [0 => get_string('none')] + $DB->get_records_menu('cohort', ['contextid' => context_system::instance()->id], 'name', 'id, name')));
$page->add(new admin_setting_configmultiselect('local_orangeportal/managedcohorts',
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
$ADMIN->add('orangeportal', new admin_externalpage('orangeportalmanage', get_string('publishmanage', 'local_orangeportal'),
    new moodle_url('/local/orangeportal/manage.php'), 'local/orangeportal:manage'));
$ADMIN->add('orangeportal', new admin_externalpage('orangeportalcategories', get_string('noticecategories', 'local_orangeportal'),
    new moodle_url('/local/orangeportal/categories.php'), 'local/orangeportal:manage'));
$ADMIN->add('orangeportal', new admin_externalpage('orangeportalreports', get_string('reportstitle', 'local_orangeportal'),
    new moodle_url('/local/orangeportal/reports.php'), 'local/orangeportal:viewreports'));
