<?php
defined('MOODLE_INTERNAL') || die();
$callbacks = [
    ['hook' => \core_course\hook\after_form_definition_after_data::class,
        'callback' => [\local_orangeportal\course_creation::class, 'after_data']],
    ['hook' => \core_course\hook\after_form_definition::class,
        'callback' => [\local_orangeportal\course_creation::class, 'definition']],
    ['hook' => \core_course\hook\after_form_validation::class,
        'callback' => [\local_orangeportal\course_creation::class, 'validation']],
    ['hook' => \core\hook\navigation\secondary_extend::class,
        'callback' => [\local_orangeportal\hooks::class, 'library_navigation']],
    ['hook' => \core\hook\navigation\primary_extend::class,
        'callback' => [\local_orangeportal\hooks::class, 'navigation']],
    ['hook' => \core\hook\output\before_http_headers::class,
        'callback' => [\local_orangeportal\hooks::class, 'headers']],
];
