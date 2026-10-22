<?php
/** Explicit local demo arrangement. No automatic migration of client courses. */
define('CLI_SCRIPT', true);
require __DIR__ . '/../../../config.php';
require_once $CFG->dirroot . '/course/lib.php';
if (!preg_match('~^http://(localhost|127\\.0\\.0\\.1):~', $CFG->wwwroot)) { throw new coding_exception('Local demo only.'); }
\core\session\manager::set_user(get_admin());
$root = \local_orangeportal\native_categories::root('courses');
$branches = [];
foreach (['initial' => 'Formación inicial', 'continuous' => 'Formación continua'] as $key => $name) {
    $c = $DB->get_record('course_categories', ['parent' => $root->id, 'name' => $name]);
    if (!$c) {
        $c = \core_course_category::create(['name' => $name, 'parent' => $root->id, 'idnumber' => 'orange_training_' . $key]);
        echo "Creada categoría: $name\n";
    }
    $branches[$key] = (int)$c->id;
}
// Move only the known initial-training example, preserving its ID, enrolments and activities.
$course = $DB->get_record('course', ['shortname' => 'ORANGE-FORMACION-INICIAL', 'category' => $root->id]);
if ($course) {
    move_courses([$course->id], $branches['initial']);
    echo "Curso de ejemplo {$course->id} ubicado en Formación inicial.\n";
}
echo "Formación inicial y Formación continua disponibles. El resto del contenido se conserva.\n";
