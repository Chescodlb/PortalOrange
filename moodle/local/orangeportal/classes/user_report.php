<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** Native training evidence, restricted to each viewer's authorised courses. */
class user_report {
    public static function require_access(int $userid = 0): void {
        global $DB;
        experience::require_access();
        require_capability('local/orangeportal:viewreports', \context_system::instance());
        if ($userid && (!experience::member($userid) || !$DB->record_exists('user', ['id' => $userid, 'deleted' => 0]))) {
            throw new \moodle_exception('outsideorange', 'local_orangeportal');
        }
    }

    public static function courses(): array {
        self::require_access();
        return array_filter(orange_native_courses((int)get_config('local_orangeportal', 'coursescategory')), static function($c) {
            $ctx = \context_course::instance($c->id);
            return has_capability('moodle/course:viewparticipants', $ctx) &&
                (has_capability('report/completion:view', $ctx) || has_capability('report/progress:view', $ctx));
        });
    }

    /** No grades or attempts are queried unless the viewer has the corresponding native permission. */
    public static function training(int $userid, bool $details = false): array {
        global $DB;
        self::require_access($userid);
        $courses = self::courses();
        $rows = []; $completed = 0; $started = 0;
        foreach ($courses as $course) {
            $ctx = \context_course::instance($course->id);
            if (!is_enrolled($ctx, $userid, '', true)) { continue; }
            $completion = $DB->get_record('course_completions', ['course' => $course->id, 'userid' => $userid]);
            $activity = $DB->record_exists_sql('SELECT 1 FROM {course_modules_completion} cc
                JOIN {course_modules} cm ON cm.id=cc.coursemoduleid WHERE cm.course=? AND cc.userid=? AND cc.completionstate>0',
                [$course->id, $userid]);
            $attempted = $DB->record_exists_sql('SELECT 1 FROM {quiz_attempts} qa JOIN {quiz} q ON q.id=qa.quiz
                WHERE q.course=? AND qa.userid=? AND qa.preview=0', [$course->id, $userid]);
            $done = !empty($completion->timecompleted);
            $begun = !empty($completion->timestarted) || $activity || $attempted;
            $status = $done ? 'reportfinished' : ($begun ? 'reportinprogress' : 'reportnotstarted');
            $completed += (int)$done;
            $started += (int)($begun && !$done);
            $row = (object)['course' => $course, 'status' => $status, 'completion' => $completion,
                'grade' => null, 'attempts' => [], 'surveys' => []];
            if ($details) {
                $cangrades = has_capability('moodle/grade:viewall', $ctx);
                if ($cangrades) {
                    $row->grade = $DB->get_record_sql("SELECT gg.id,gg.finalgrade,gi.grademax,gi.grademin,gi.gradepass
                        FROM {grade_grades} gg JOIN {grade_items} gi ON gi.id=gg.itemid
                        WHERE gi.courseid=? AND gi.itemtype='course' AND gg.userid=? AND gg.hidden=0 AND gi.hidden=0",
                        [$course->id, $userid]) ?: null;
                }
                foreach (get_fast_modinfo($course)->get_cms() as $cm) {
                    if (!$cm->uservisible) { continue; }
                    if ($cangrades && $cm->modname === 'quiz' && has_capability('mod/quiz:viewreports', $cm->context)) {
                        $row->attempts[$cm->id] = (object)['name' => $cm->name, 'cmid' => $cm->id,
                            'total' => $DB->count_records('quiz_attempts', ['quiz' => $cm->instance, 'userid' => $userid, 'preview' => 0]),
                            'rows' => $DB->get_records('quiz_attempts', ['quiz' => $cm->instance, 'userid' => $userid, 'preview' => 0],
                                'attempt DESC,id DESC', 'id,attempt,state,timestart,timefinish', 0, 25)];
                    }
                    if ($cm->modname === 'feedback' && has_capability('mod/feedback:viewreports', $cm->context)) {
                        $anonymous = (int)$DB->get_field('feedback', 'anonymous', ['id' => $cm->instance]) !== 2;
                        // Never infer an anonymous response's identity, even if a userid remains in legacy data.
                        $response = $anonymous ? false : $DB->get_record_sql('SELECT id,timemodified FROM {feedback_completed}
                            WHERE feedback=? AND userid=? AND anonymous_response=2 ORDER BY timemodified DESC',
                            [$cm->instance, $userid], IGNORE_MULTIPLE);
                        $row->surveys[] = (object)['name' => $cm->name, 'cmid' => $cm->id,
                            'anonymous' => $anonymous, 'response' => $response];
                    }
                }
            }
            $rows[$course->id] = $row;
        }
        return ['available' => (bool)$courses, 'total' => count($rows), 'completed' => $completed, 'started' => $started, 'rows' => $rows];
    }
}
