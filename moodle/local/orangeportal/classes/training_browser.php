<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** Native Moodle courses using the same category columns and layout as Library. */
class training_browser {
    private static function url(array $params = []): \moodle_url {
        if (optional_param('unread', 0, PARAM_BOOL)) { $params['unread'] = 1; }
        return \orange_link('index.php', ['section' => 'courses'] + $params);
    }
    public static function courses(int $rootid): array {
        global $CFG;
        require_once($CFG->dirroot . '/local/orangeportal/locallib.php');
        return \orange_native_courses($rootid);
    }
    public static function render(int $category, string $query, int $selectedcourse, string $order): string {
        $rootid = (int)get_config('local_orangeportal', 'coursescategory');
        $categories = library_browser::categories($rootid);
        if (!isset($categories[$category])) { $category = $rootid; }
        if (!in_array($order, ['name', 'newest'], true)) { $order = 'newest'; }
        $trail = library_browser::trail($category, $rootid, $categories);
        $selected = array_map(fn($c) => (int)$c->id, $trail);
        $children = [];
        foreach ($categories as $c) { $children[(int)$c->parent][] = $c; }
        $columns = library_browser::column(get_string('courses', 'local_orangeportal'), $children[$rootid] ?? [], $selected, $order, 'courses');
        foreach ($trail as $c) {
            if (!empty($children[$c->id])) { $columns .= library_browser::column($c->name, $children[$c->id], $selected, $order, 'courses'); }
        }
        $unread = optional_param('unread', 0, PARAM_BOOL);
        $hidden = \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'section', 'value' => 'courses']) .
            ($unread ? \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'unread', 'value' => 1]) : '');
        $search = $hidden . \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'order', 'value' => $order]) .
            \html_writer::tag('label', get_string('trainingsearch', 'local_orangeportal'), ['for' => 'orange-training-search']) .
            \html_writer::div(\html_writer::empty_tag('input', ['type' => 'search', 'name' => 'q', 'id' => 'orange-training-search',
                'value' => $query, 'class' => 'form-control', 'placeholder' => get_string('trainingsearchplaceholder', 'local_orangeportal')]) .
                \html_writer::tag('button', get_string('search'), ['type' => 'submit', 'class' => 'btn btn-primary']), 'orange-library-search-controls');
        if ($query !== '') { $search .= \html_writer::link(self::url(), get_string('libraryclearsearch', 'local_orangeportal'), ['class' => 'orange-library-clear']); }
        if ($unread) { $search .= \html_writer::link(\orange_link('index.php', ['section' => 'courses']), get_string('updates_showall', 'local_orangeportal'), ['class' => 'orange-library-clear']); }
        $html = \html_writer::tag('form', $search, ['method' => 'get', 'action' => self::url()->out(false), 'class' => 'orange-library-search']);
        $access = personal_updates::course_access();
        $joined = personal_updates::joined();
        $courses = array_filter(self::courses($rootid), function($course) use ($categories, $query, $category, $unread, $joined, $access) {
            if ($unread && !personal_updates::new_course($course, $access, $joined)) { return false; }
            if ($query !== '') {
                return \core_text::strpos(\core_text::strtolower($course->fullname . ' ' . strip_tags($course->summary)),
                    \core_text::strtolower(trim($query))) !== false;
            }
            return in_array($category, array_map('intval', explode('/', $categories[$course->category]->path ?? '')), true);
        });
        uasort($courses, fn($a, $b) => ($order === 'newest' ? ($b->timecreated <=> $a->timecreated ?: $b->id <=> $a->id) : 0) ?:
            (strnatcasecmp($a->fullname, $b->fullname) ?: $a->id <=> $b->id));
        $list = '';
        foreach ($courses as $course) {
            $viewed = !empty($access[$course->id]);
            $attrs = ['class' => 'orange-library-document orange-training-course ' . ($viewed ? 'is-read' : 'is-unread') .
                ((int)$course->id === $selectedcourse ? ' is-selected' : '')];
            if ((int)$course->id === $selectedcourse) { $attrs['aria-current'] = 'true'; }
            $list .= \html_writer::tag('li', \html_writer::link(self::url(['category' => $course->category, 'course' => $course->id, 'q' => $query, 'order' => $order]),
                \html_writer::span($viewed ? '✓' : '●', 'orange-library-read-icon', ['role' => 'img',
                    'aria-label' => get_string($viewed ? 'trainingviewed' : 'trainingunviewed', 'local_orangeportal'),
                    'title' => get_string($viewed ? 'trainingviewed' : 'trainingunviewed', 'local_orangeportal')]) .
                \html_writer::span(\html_writer::tag('strong', s($course->fullname), ['title' => $course->fullname]), 'orange-library-document-text'), $attrs));
        }
        $sort = $hidden . \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'category', 'value' => $category]) .
            \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'q', 'value' => $query]) .
            \html_writer::tag('label', get_string('trainingsort', 'local_orangeportal'), ['for' => 'orange-library-order', 'class' => 'sr-only']) .
            \html_writer::select(['newest' => get_string('librarysortnewest', 'local_orangeportal'), 'name' => get_string('librarysortname', 'local_orangeportal')],
                'order', $order, false, ['id' => 'orange-library-order', 'class' => 'custom-select custom-select-sm']);
        $heading = \html_writer::div(\html_writer::tag('h3', get_string('trainingcourses', 'local_orangeportal')) .
            \html_writer::tag('form', $sort, ['method' => 'get', 'action' => self::url()->out(false), 'class' => 'orange-library-sort']), 'orange-library-results-heading') .
            \html_writer::tag('p', get_string($query !== '' ? (count($courses) === 1 ? 'trainingglobalresultone' : 'trainingglobalresults') : (count($courses) === 1 ? 'trainingcourseone' : 'trainingcoursecount'), 'local_orangeportal', count($courses)), ['class' => 'orange-library-result-count']);
        $listing = $list ? \html_writer::tag('ul', $list, ['class' => 'orange-library-documents']) :
            \html_writer::tag('p', get_string('trainingnocourses', 'local_orangeportal'), ['class' => 'orange-library-empty']);
        $detail = '';
        if ($selectedcourse && isset($courses[$selectedcourse])) {
            $course = $courses[$selectedcourse];
            $context = \context_course::instance($course->id);
            $path = implode(' › ', array_map(fn($c) => $c->name, library_browser::trail((int)$course->category, $rootid, $categories)));
            $detail = \html_writer::tag('p', s($path), ['class' => 'orange-library-document-path']) .
                \html_writer::tag('h4', s($course->fullname)) .
                format_text($course->summary, $course->summaryformat, ['context' => $context]) .
                \html_writer::link(new \moodle_url('/course/view.php', ['id' => $course->id]), get_string('trainingopen', 'local_orangeportal'),
                    ['class' => 'btn btn-primary orange-training-open']);
            if (has_capability('moodle/course:manageactivities', $context)) {
                $detail .= \html_writer::div(\orange_post_button(new \moodle_url('/course/view.php', ['id' => $course->id]),
                    get_string('trainingedit', 'local_orangeportal'), ['edit' => 1]), 'orange-card-edit');
                if (has_capability('mod/quiz:addinstance', $context)) {
                    $detail .= \html_writer::link(new \moodle_url('/course/modedit.php', ['add' => 'quiz', 'course' => $course->id, 'section' => 0, 'return' => 0]),
                        get_string('trainingnewquiz', 'local_orangeportal'), ['class' => 'btn btn-outline-secondary orange-create-quiz']);
                }
            }
        } else { $detail = \html_writer::tag('p', get_string($selectedcourse ? 'trainingunavailable' : 'trainingselect', 'local_orangeportal')); }
        $panel = \html_writer::tag('section', $heading . $listing . \html_writer::div($detail, 'orange-library-detail'),
            ['class' => 'orange-library-column orange-library-results', 'aria-label' => get_string('trainingcourses', 'local_orangeportal')]);
        return $html . \html_writer::div($columns . $panel, 'orange-library-browser orange-training-browser', ['tabindex' => '0',
            'role' => 'region', 'aria-label' => get_string('trainingexplorer', 'local_orangeportal'),
            'data-focus' => $selectedcourse ? 'detail' : ($query !== '' || empty($children[$category]) ? 'documents' : 'branch')]);
    }
}
