<?php
/** Backwards-compatible link: editing goes straight to the native form. */
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_login();
\local_orangeportal\experience::require_access();
$id = required_param('course', PARAM_INT);
$course = \local_orangeportal\native_content::training_course($id);
require_login($course);
require_capability('moodle/course:manageactivities', context_course::instance($id));
require_capability('moodle/course:update', context_course::instance($id));
redirect(\local_orangeportal\training_publication::edit_url($id));
