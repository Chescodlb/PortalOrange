<?php
defined('MOODLE_INTERNAL') || die();
require($CFG->dirroot . '/theme/moove/config.php');
require_once(__DIR__ . '/lib.php');
$THEME->name = 'orange';
$THEME->parents = ['moove', 'boost'];
$THEME->scss = fn($theme) => theme_moove_get_main_scss_content($theme);
$THEME->extrascsscallback = 'theme_orange_extra_scss';
$THEME->prescsscallback = 'theme_orange_pre_scss';
$THEME->precompiledcsscallback = null;
