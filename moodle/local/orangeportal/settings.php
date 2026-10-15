<?php
defined('MOODLE_INTERNAL') || die();
$ADMIN->add('root', new admin_category('orangeportal', get_string('pluginname', 'local_orangeportal')));
$page = new admin_settingpage('local_orangeportal', get_string('configuration', 'local_orangeportal'));
$page->add(new admin_setting_configcheckbox('local_orangeportal/enabled', get_string('portalenabled', 'local_orangeportal'),
    get_string('portalenabled_desc', 'local_orangeportal'), 0));
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
