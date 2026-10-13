<?php
defined('MOODLE_INTERNAL') || die();
$callbacks = [
    ['hook' => \core\hook\navigation\primary_extend::class,
        'callback' => [\local_orangeportal\hooks::class, 'navigation']],
    ['hook' => \core_user\hook\extend_default_homepage::class,
        'callback' => [\local_orangeportal\hooks::class, 'homepage']],
    ['hook' => \core\hook\output\before_http_headers::class,
        'callback' => [\local_orangeportal\hooks::class, 'headers']],
];
