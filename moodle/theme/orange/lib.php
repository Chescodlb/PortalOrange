<?php
defined('MOODLE_INTERNAL') || die();
function theme_orange_pre_scss($theme): string {
    $theme->settings->brandcolor = $theme->settings->brandcolor ?? '#FF7900';
    $theme->settings->secondarymenucolor = $theme->settings->secondarymenucolor ?? '#000000';
    return theme_moove_get_pre_scss($theme);
}
function theme_orange_extra_scss($theme): string {
    return file_get_contents(__DIR__ . '/scss/brand.scss') . "\n" . ($theme->settings->scss ?? '');
}
function theme_orange_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel === CONTEXT_SYSTEM && $filearea === 'logo') {
        return theme_config::load('orange')->setting_file_serve($filearea, $args, $forcedownload, $options);
    }
    send_file_not_found();
}
