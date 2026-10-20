<?php
defined('MOODLE_INTERNAL') || die();
$settings = new admin_settingpage('themesettingorange', get_string('configtitle', 'theme_orange'));
if ($ADMIN->fulltree) {
    $logo = new admin_setting_configstoredfile('theme_orange/logo', get_string('logo', 'admin'),
        get_string('logodesc', 'theme_orange'), 'logo', 0, ['accepted_types' => ['web_image'], 'maxfiles' => 1]);
    $logo->set_updatedcallback('theme_reset_all_caches');
    $settings->add($logo);
    $scss = new admin_setting_scsscode('theme_orange/scss', get_string('extrascss', 'theme_orange'),
        get_string('extrascss_desc', 'theme_orange'), '', PARAM_RAW);
    $scss->set_updatedcallback('theme_reset_all_caches');
    $settings->add($scss);
}
