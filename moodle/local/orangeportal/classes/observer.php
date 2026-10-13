<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();
/** Private portal alerts; no Teams integration or external messaging. */
class observer {
    public static function course_completed(\core\event\course_completed $event): void {
        self::alert((int)$event->relateduserid, (int)$event->courseid, 'Curso completado',
            'Has completado el curso. Puedes consultar tu seguimiento y tus resultados dentro del curso.');
    }
    public static function user_graded(\core\event\user_graded $event): void {
        global $DB;
        $grade = $event->get_grade();
        $item = $grade->grade_item;
        // Never disclose hidden grades, automatic totals, or a grade that is still unavailable.
        if ($grade->is_hidden() || $item->is_hidden() || $item->itemtype !== 'mod' || $grade->finalgrade === null) { return; }
        self::alert((int)$grade->userid, (int)$item->courseid, 'Nueva calificación disponible',
            'Hay una calificación visible en tu curso. Consulta el informe de calificaciones para verla.');
    }
    private static function alert(int $userid, int $courseid, string $title, string $text): void {
        global $DB;
        if (!$userid || !get_config('local_orangeportal', 'automaticalerts') ||
                !get_config('local_orangeportal', 'version')) { return; }
        $course = $DB->get_record('course', ['id' => $courseid]);
        $rootid = (int)get_config('local_orangeportal', 'coursescategory');
        $cat = $course ? $DB->get_record('course_categories', ['id' => $course->category]) : null;
        if (!$cat || !in_array($rootid, array_map('intval', explode('/', $cat->path)))) { return; }
        $category = $DB->get_record('local_orange_category', ['kind' => 'notifications', 'name' => 'Registro', 'parentid' => 0]);
        if (!$category) {
            $category = (object)['kind' => 'notifications', 'name' => 'Registro', 'parentid' => 0];
            $category->id = $DB->insert_record('local_orange_category', $category);
        }
        $url = new \moodle_url('/course/view.php', ['id' => $courseid]);
        $title = \core_text::substr($title . ': ' . $course->fullname, 0, 255);
        $content = \html_writer::tag('p', s($text)) . \html_writer::link($url, 'Abrir ' . s($course->fullname));
        $tx = $DB->start_delegated_transaction();
        $id = $DB->insert_record('local_orange_notice', (object)['kind' => 'notifications', 'categoryid' => $category->id,
            'title' => $title, 'content' => $content, 'impact' => 'normal', 'state' => 'open',
            'published' => 1, 'revision' => 1, 'authorid' => 0, 'timecreated' => time(), 'timemodified' => time()]);
        $DB->insert_record('local_orange_audience', (object)['noticeid' => $id, 'targettype' => 'user', 'targetid' => $userid]);
        $DB->insert_record('local_orange_revision', (object)['noticeid' => $id, 'revision' => 1,
            'title' => $title, 'content' => $content, 'authorid' => 0, 'timecreated' => time()]);
        $tx->allow_commit();
    }
}
