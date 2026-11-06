<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** Current document access is the denominator; opening evidence is never inferred from enrolment. */
class library_report {
    public static function documents(string $search = '', int $onlyid = 0): array {
        global $DB;
        user_report::require_access();
        $root = (int)get_config('local_orangeportal', 'librarycategory');
        if (!$root) { return []; }
        $documents = [];
        foreach (\orange_native_courses($root) as $course) {
            if (!$course->visible) { continue; }
            $info = get_fast_modinfo($course);
            foreach ($info->get_cms() as $cm) {
                if ($onlyid && $cm->id != $onlyid) { continue; }
                if (!$cm->uservisible || !$cm->visible || !$cm->is_visible_on_course_page() ||
                        !$info->get_section_info($cm->sectionnum)->visible || !report_period::contains((int)$cm->added)) { continue; }
                $cap = 'mod/' . $cm->modname . ($cm->modname === 'book' ? ':read' : ':view');
                if (get_capability_info($cap) && !has_capability($cap, $cm->context)) { continue; }
                if ($search !== '' && \core_text::strpos(\core_text::strtolower($cm->name . ' ' . $course->fullname),
                        \core_text::strtolower($search)) === false) { continue; }
                $documents[$cm->id] = (object)['id' => $cm->id, 'name' => $cm->name, 'course' => $course,
                    'category' => (int)$course->category, 'added' => (int)$cm->added, 'recipients' => 0, 'readcount' => 0, 'users' => []];
            }
        }
        if (!$documents) { return []; }
        $categories = $DB->get_records('course_categories', [], '', 'id,path,visible');
        foreach (report_scope::users() as $user) {
            $times = document_reads::times((int)$user->id);
            $access = []; $infos = [];
            foreach ($documents as $doc) {
                $course = $doc->course;
                if (!array_key_exists($course->id, $access)) {
                    $context = \context_course::instance($course->id);
                    $access[$course->id] = is_enrolled($context, $user->id, '', true) || has_capability('moodle/course:view', $context, $user->id);
                    foreach (explode('/', $categories[$course->category]->path) as $id) {
                        if ($id && !$categories[$id]->visible && !has_capability('moodle/category:viewhiddencategories',
                                \context_coursecat::instance($id), $user->id)) { $access[$course->id] = false; }
                    }
                }
                if (!$access[$course->id]) { continue; }
                $infos[$course->id] ??= get_fast_modinfo($course, $user->id);
                $cm = $infos[$course->id]->get_cm($doc->id);
                $cap = 'mod/' . $cm->modname . ($cm->modname === 'book' ? ':read' : ':view');
                if (!$cm->uservisible || !$cm->is_visible_on_course_page() ||
                        (get_capability_info($cap) && !has_capability($cap, $cm->context, $user->id))) { continue; }
                $doc->recipients++;
                $readtime = (int)($times[$doc->id] ?? 0);
                $doc->readcount += (int)($readtime > 0);
                $doc->users[$user->id] = (object)['user' => $user, 'readtime' => $readtime];
            }
        }
        if (!report_scope::global_access() || report_scope::selected()) {
            $documents = array_filter($documents, fn($d) => $d->recipients > 0);
        }
        uasort($documents, fn($a, $b) => $b->added <=> $a->added ?: $b->id <=> $a->id);
        return $documents;
    }
    public static function document(int $id): \stdClass {
        $documents = self::documents('', $id);
        if (!isset($documents[$id])) { throw new \moodle_exception('outsideorange', 'local_orangeportal'); }
        return $documents[$id];
    }
    public static function readers(\stdClass $document, string $reading, string $search): array {
        if (!in_array($reading, ['', 'read', 'unread'], true)) { throw new \invalid_parameter_exception('Invalid reading filter'); }
        return array_filter($document->users, static function($row) use ($reading, $search) {
            return ($reading === '' || ($reading === 'read') === ($row->readtime > 0)) &&
                ($search === '' || \core_text::strpos(\core_text::strtolower(fullname($row->user) . ' ' . $row->user->idnumber),
                    \core_text::strtolower($search)) !== false);
        });
    }
}
