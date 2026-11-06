<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** Native courses represent editions; metadata uses Moodle custom fields, not copied grade data. */
class training_report {
    public static function fields(): array {
        return ['code', 'edition', 'type', 'modality', 'minutes', 'actualminutes', 'trainer', 'trainer2',
            'centre', 'territory', 'portfolio', 'objective', 'source', 'subject', 'kpi'];
    }
    public static function install(): void {
        global $DB;
        $handler = \core_course\customfield\course_handler::create();
        $categoryid = (int)get_config('local_orangeportal', 'editionfieldcategory');
        if (!$categoryid || !$DB->record_exists('customfield_category', ['id' => $categoryid])) {
            $categoryid = $handler->create_category('Orange');
            set_config('editionfieldcategory', $categoryid, 'local_orangeportal');
        }
        foreach (self::fields() as $key) {
            if ($DB->record_exists('customfield_field', ['categoryid' => $categoryid, 'shortname' => 'orange_' . $key])) { continue; }
            $field = \core_customfield\field_controller::create(0, (object)['type' => 'text', 'categoryid' => $categoryid,
                'shortname' => 'orange_' . $key, 'name' => get_string('edition_' . $key, 'local_orangeportal'),
                'configdata' => json_encode(['required' => 0, 'locked' => 0, 'visibility' => 1, 'maxlength' => 255])]);
            $handler->save_field_configuration($field, new \stdClass());
        }
    }
    /** Do not introduce Orange form fields into other projects or documentary containers. */
    public static function course_form(\core_course\hook\after_form_definition_after_data $hook): void {
        global $DB;
        $id = optional_param('id', 0, PARAM_INT);
        $categoryid = $id ? (int)$DB->get_field('course', 'category', ['id' => $id]) : optional_param('category', 0, PARAM_INT);
        $root = (int)get_config('local_orangeportal', 'coursescategory');
        $path = $DB->get_field('course_categories', 'path', ['id' => $categoryid]);
        if (experience::member() && $root && in_array($root, array_map('intval', explode('/', $path ?: '')), true)) {
            foreach (self::fields() as $key) {
                $name = 'customfield_orange_' . $key;
                if ($hook->mform->elementExists($name)) { $hook->mform->getElement($name)->setLabel(get_string('edition_' . $key, 'local_orangeportal')); }
            }
            return;
        }
        foreach (self::fields() as $key) {
            $name = 'customfield_orange_' . $key;
            if ($hook->mform->elementExists($name)) { $hook->mform->removeElement($name); }
        }
        $header = 'category_' . (int)get_config('local_orangeportal', 'editionfieldcategory');
        if ($hook->mform->elementExists($header)) { $hook->mform->removeElement($header); }
    }
    public static function metadata(int $courseid): array {
        $data = [];
        foreach (\core_course\customfield\course_handler::create()->get_instance_data($courseid, true) as $field) {
            $name = $field->get_field()->get('shortname');
            if (str_starts_with($name, 'orange_')) { $data[substr($name, 7)] = (string)$field->get_value(); }
        }
        return $data;
    }
    public static function courses(): array {
        return array_filter(user_report::courses(), fn($c) => report_period::contains((int)$c->startdate));
    }
    public static function matches(\stdClass $course, string $search, string $modality): bool {
        $metadata = self::metadata((int)$course->id);
        return ($modality === '' || ($metadata['modality'] ?? '') === $modality) &&
            ($search === '' || \core_text::strpos(\core_text::strtolower($course->fullname . ' ' . implode(' ', $metadata)),
                \core_text::strtolower($search)) !== false);
    }
    public static function course(int $id): \stdClass {
        $courses = user_report::courses();
        if (!isset($courses[$id])) { throw new \moodle_exception('outsideorange', 'local_orangeportal'); }
        $users = self::participants($courses[$id]);
        if (!$users && !report_scope::global_access() && !service::manager()) { throw new \moodle_exception('outsideorange', 'local_orangeportal'); }
        return $courses[$id];
    }
    public static function participants(\stdClass $course): array {
        $users = report_scope::users();
        $enrolled = get_enrolled_users(\context_course::instance($course->id), 'moodle/course:isincompletionreports', 0, 'u.*', null, 0, 0, true);
        return array_intersect_key($users, $enrolled);
    }
    public static function can_manage(\stdClass $course): bool {
        return service::manager() && has_capability('moodle/course:manageactivities', \context_course::instance($course->id));
    }
    public static function save_attendance(int $courseid, int $userid, string $status, ?int $minutes): void {
        global $DB, $USER;
        $course = self::course($courseid);
        if (!self::can_manage($course)) { throw new \required_capability_exception(\context_course::instance($courseid), 'moodle/course:manageactivities', 'nopermissions', ''); }
        if (!isset(self::participants($course)[$userid]) || !in_array($status, ['unknown', 'present', 'absent', 'partial'], true) ||
                ($minutes !== null && ($minutes < 0 || $minutes > 1000000))) { throw new \invalid_parameter_exception('Invalid attendance'); }
        $row = $DB->get_record('local_orange_attendance', ['courseid' => $courseid, 'userid' => $userid]);
        $data = (object)['courseid' => $courseid, 'userid' => $userid, 'status' => $status,
            'minutes' => in_array($status, ['unknown', 'absent']) ? null : $minutes, 'authorid' => $USER->id, 'timemodified' => time()];
        // Preserve the explicit, current organisational labels at the time of recording.
        $data->collectives = report_scope::user_cohorts($userid);
        if ($row) { $data->id = $row->id; $DB->update_record('local_orange_attendance', $data); }
        else { $DB->insert_record('local_orange_attendance', $data); }
    }
    public static function evaluations(\stdClass $course): array {
        global $DB;
        $allowed = self::participants($course);
        if (!has_capability('moodle/grade:viewall', \context_course::instance($course->id))) { return []; }
        $results = [];
        foreach (get_fast_modinfo($course)->get_cms() as $cm) {
            if ($cm->modname !== 'quiz' || !$cm->uservisible || !has_capability('mod/quiz:viewreports', $cm->context)) { continue; }
            $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
            $attempts = []; $questions = []; $gradeitem = $DB->get_record('grade_items', ['itemmodule' => 'quiz', 'iteminstance' => $quiz->id, 'itemnumber' => 0]);
            $grades = $DB->get_records('quiz_grades', ['quiz' => $quiz->id], '', 'userid,grade');
            foreach ($DB->get_records('quiz_attempts', ['quiz' => $quiz->id, 'preview' => 0], 'userid,attempt') as $a) {
                if (!isset($allowed[$a->userid]) || !report_period::contains((int)$a->timestart)) { continue; }
                $a->user = $allowed[$a->userid];
                $a->grade = $a->state === 'finished' && $a->sumgrades !== null && $quiz->sumgrades > 0 ?
                    (float)$a->sumgrades / $quiz->sumgrades * $quiz->grade : null;
                $attempts[] = $a;
                if ($a->state !== 'finished') { continue; }
                foreach ($DB->get_records_sql('SELECT qa.*,q.name AS questionname FROM {question_attempts} qa
                        JOIN {question} q ON q.id=qa.questionid WHERE qa.questionusageid=? ORDER BY qa.slot', [$a->uniqueid]) as $qa) {
                    $step = $DB->get_records_select('question_attempt_steps', 'questionattemptid=? AND fraction IS NOT NULL',
                        [$qa->id], 'sequencenumber DESC', '*', 0, 1);
                    $step = reset($step);
                    if (!$step || $qa->maxmark <= 0) { continue; }
                    $questions[$qa->questionid]['name'] = $qa->questionname;
                    $questions[$qa->questionid]['sum'] = ($questions[$qa->questionid]['sum'] ?? 0) + (float)$step->fraction * 100;
                    $questions[$qa->questionid]['n'] = ($questions[$qa->questionid]['n'] ?? 0) + 1;
                }
            }
            $finals = array_filter($grades, fn($g) => isset($allowed[$g->userid]));
            $results[] = ['name' => $cm->name, 'cmid' => $cm->id, 'max' => (float)$quiz->grade,
                'gradepass' => (float)($gradeitem->gradepass ?? 0), 'grades' => $finals,
                'attempts' => $attempts, 'questions' => $questions];
        }
        return $results;
    }
    /** Anonymous response identity is never used to infer cohort or individual participation. */
    public static function satisfaction(\stdClass $course): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/feedback/lib.php');
        $allowed = self::participants($course);
        $allstudents = get_enrolled_users(\context_course::instance($course->id), 'moodle/course:isincompletionreports', 0, 'u.id', null, 0, 0, true);
        $wholeedition = !array_diff_key($allstudents, $allowed);
        $reports = [];
        foreach (get_fast_modinfo($course)->get_cms() as $cm) {
            if ($cm->modname !== 'feedback' || !$cm->uservisible || !has_capability('mod/feedback:viewreports', $cm->context)) { continue; }
            $feedback = $DB->get_record('feedback', ['id' => $cm->instance], '*', MUST_EXIST);
            $anonymous = (int)$feedback->anonymous !== 2;
            $report = ['name' => $cm->name, 'anonymous' => $anonymous, 'restricted' => $anonymous && !$wholeedition,
                'responses' => 0, 'questions' => [], 'comments' => []];
            if ($report['restricted']) { $reports[] = $report; continue; }
            $responses = $DB->get_records('feedback_completed', ['feedback' => $feedback->id]);
            $responses = array_filter($responses, fn($r) => report_period::contains((int)$r->timemodified) &&
                ($anonymous || ((int)$r->anonymous_response === 2 && isset($allowed[$r->userid]))));
            $report['responses'] = count($responses);
            $values = [];
            if ($responses) {
                foreach ($DB->get_records_list('feedback_value', 'completed', array_keys($responses)) as $value) {
                    $values[$value->item][$value->completed] = $value->value;
                }
            }
            foreach ($DB->get_records('feedback_item', ['feedback' => $feedback->id], 'position') as $item) {
                $scores = []; $distribution = [];
                foreach ($responses as $r) {
                    $value = $values[$item->id][$r->id] ?? '';
                    if (trim($value) === '') { continue; }
                    $score = null;
                    if ($item->typ === 'numeric' && is_numeric($value)) { $score = (float)$value; }
                    else if ($item->typ === 'multichoicerated') {
                        $plugin = feedback_get_item_class('multichoicerated');
                        $info = $plugin->get_info($item);
                        $lines = explode(FEEDBACK_MULTICHOICERATED_LINE_SEP, $info->presentation);
                        $index = (int)$value - 1;
                        $weight = isset($lines[$index]) ? explode(FEEDBACK_MULTICHOICERATED_VALUE_SEP, $lines[$index])[0] : null;
                        if (is_numeric($weight)) { $score = (float)$weight; }
                    } else if (in_array($item->typ, ['textfield', 'textarea'], true)) {
                        $report['comments'][] = ['question' => $item->name, 'text' => $value];
                    }
                    if ($score !== null) { $scores[] = $score; $key = (string)$score; $distribution[$key] = ($distribution[$key] ?? 0) + 1; }
                }
                if ($scores) { $report['questions'][] = ['name' => $item->name, 'n' => count($scores),
                    'mean' => array_sum($scores) / count($scores), 'distribution' => $distribution]; }
            }
            $reports[] = $report;
        }
        return $reports;
    }
}
