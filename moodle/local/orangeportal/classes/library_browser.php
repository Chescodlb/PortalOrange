<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** Column navigation over native categories and permission-filtered Moodle resources. */
class library_browser {
    private const MODULES = ['resource', 'page', 'book', 'folder', 'url'];

    public static function categories(int $rootid): array {
        global $DB;
        $root = $DB->get_record('course_categories', ['id' => $rootid], '*', MUST_EXIST);
        return $DB->get_records_sql('SELECT * FROM {course_categories} WHERE id = ? OR path LIKE ? ORDER BY sortorder, id',
            [$rootid, $root->path . '/%']);
    }

    /** No inaccessible resource names or descriptions are returned to the browser. */
    public static function documents(int $rootid): array {
        global $DB, $USER;
        $documents = [];
        $readtimes = document_reads::times((int)$USER->id);
        $instances = [];
        foreach (\orange_native_courses($rootid) as $course) {
            foreach (get_fast_modinfo($course)->get_cms() as $cm) {
                if (!in_array($cm->modname, self::MODULES, true) || !$cm->uservisible ||
                        !$cm->is_visible_on_course_page()) { continue; }
                $viewcap = 'mod/' . $cm->modname . ($cm->modname === 'book' ? ':read' : ':view');
                if (!has_capability($viewcap, \context_module::instance($cm->id))) { continue; }
                $documents[$cm->id] = (object)['id' => (int)$cm->id, 'name' => $cm->name,
                    'published' => (bool)($course->visible && $cm->visible && get_fast_modinfo($course)->get_section_info($cm->sectionnum)->visible),
                    'added' => 0, 'category' => (int)$course->category, 'collection' => $course->fullname,
                    'courseid' => (int)$course->id, 'module' => $cm->modname, 'instance' => (int)$cm->instance,
                    'description' => '', 'modified' => 0, 'readtime' => (int)($readtimes[$cm->id] ?? 0), 'url' => new \moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id])];
                $instances[$cm->modname][$cm->instance] = $cm->instance;
            }
        }
        $added = $documents ? $DB->get_records_list('course_modules', 'id', array_keys($documents), '', 'id, added') : [];
        $intros = [];
        foreach ($instances as $module => $ids) {
            $intros[$module] = $DB->get_records_list($module, 'id', array_values($ids), '', 'id, intro, timemodified');
        }
        foreach ($documents as $doc) {
            $doc->added = (int)($added[$doc->id]->added ?? 0);
            $intro = $intros[$doc->module][$doc->instance]->intro ?? '';
            $doc->modified = (int)($intros[$doc->module][$doc->instance]->timemodified ?? 0);
            $doc->description = trim(html_entity_decode(strip_tags($intro), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        uasort($documents, fn($a, $b) => strnatcasecmp($a->name, $b->name) ?: $a->id <=> $b->id);
        return $documents;
    }

    /** Stable ordering reused by the browser and regression checks. */
    public static function sort_documents(array &$documents, string $order): void {
        uasort($documents, function($a, $b) use ($order) {
            $difference = $order === 'newest' ? ($b->modified <=> $a->modified ?: $b->id <=> $a->id) : 0;
            return $difference ?: strnatcasecmp($a->name, $b->name) ?: $a->id <=> $b->id;
        });
    }

    /** Ordered ancestors, excluding the library container, safe against invalid/cyclic paths. */
    public static function trail(int $category, int $rootid, array $categories): array {
        $trail = [];
        $visited = [];
        while ($category && $category !== $rootid && isset($categories[$category]) && !isset($visited[$category])) {
            $visited[$category] = true;
            array_unshift($trail, $categories[$category]);
            $category = (int)$categories[$category]->parent;
        }
        return $category === $rootid ? $trail : [];
    }

    private static function url(array $params): \moodle_url {
        if (optional_param('unread', 0, PARAM_BOOL)) { $params['unread'] = 1; }
        return \orange_link('index.php', ['section' => 'library'] + $params);
    }

    private static function column(string $name, array $children, array $selected, string $order): string {
        $items = '';
        foreach ($children as $child) {
            $active = in_array((int)$child->id, $selected, true);
            $attrs = ['class' => 'orange-library-category' . ($active ? ' is-selected' : '')];
            if ($active) { $attrs['aria-current'] = 'step'; }
            $items .= \html_writer::tag('li', \html_writer::link(self::url(['category' => $child->id, 'order' => $order]),
                \html_writer::span(s($child->name)) . \html_writer::span('›', '', ['aria-hidden' => 'true']), $attrs));
        }
        return \html_writer::tag('section', \html_writer::tag('h3', s($name)) .
            \html_writer::tag('ul', $items, ['class' => 'orange-library-branches']),
            ['class' => 'orange-library-column', 'aria-label' => $name]);
    }

    public static function render(int $category, string $query, int $selecteddocument, string $order = ''): string {
        $rootid = (int)get_config('local_orangeportal', 'librarycategory');
        $categories = self::categories($rootid);
        if (!isset($categories[$category])) { $category = $rootid; }
        if (!in_array($order, ['name', 'newest'], true)) {
            $order = 'newest';
        }
        $trail = self::trail($category, $rootid, $categories);
        $selected = array_map(fn($cat) => (int)$cat->id, $trail);
        $children = [];
        foreach ($categories as $cat) { $children[(int)$cat->parent][] = $cat; }
        $columns = self::column(get_string('library', 'local_orangeportal'), $children[$rootid] ?? [], $selected, $order);
        foreach ($trail as $cat) {
            if (!empty($children[$cat->id])) {
                $columns .= self::column($cat->name, $children[$cat->id], $selected, $order);
            }
        }

        $search = \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'section', 'value' => 'library']) .
            \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'order', 'value' => $order]) .
            \html_writer::tag('label', get_string('librarysearch', 'local_orangeportal'), ['for' => 'orange-library-search']) .
            \html_writer::div(\html_writer::empty_tag('input', ['type' => 'search', 'name' => 'q', 'value' => $query,
                'id' => 'orange-library-search', 'class' => 'form-control',
                'placeholder' => get_string('librarysearchplaceholder', 'local_orangeportal')]) .
                \html_writer::tag('button', get_string('search'), ['type' => 'submit', 'class' => 'btn btn-primary']),
                'orange-library-search-controls');
        if ($query !== '') {
            $search .= \html_writer::link(self::url([]), get_string('libraryclearsearch', 'local_orangeportal'),
                ['class' => 'orange-library-clear']);
        }
        if (optional_param('unread', 0, PARAM_BOOL)) {
            $search .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'unread', 'value' => 1]);
            $search .= \html_writer::link(\orange_link('index.php', ['section' => 'library']), get_string('updates_showall', 'local_orangeportal'));
        }
        $html = \html_writer::tag('form', $search, ['method' => 'get', 'action' => self::url([])->out(false),
            'class' => 'orange-library-search']);

        $unread = optional_param('unread', 0, PARAM_BOOL);
        $joined = personal_updates::joined();
        $documents = array_filter(self::documents($rootid), function($doc) use ($query, $category, $categories, $unread, $joined) {
            if ($unread && !personal_updates::new_document($doc, $joined)) { return false; }
            if ($query !== '') {
                return \core_text::strpos(\core_text::strtolower($doc->name . ' ' . $doc->description),
                    \core_text::strtolower(trim($query))) !== false;
            }
            $path = $categories[$doc->category]->path ?? '';
            return in_array($category, array_map('intval', explode('/', $path)), true);
        });
        self::sort_documents($documents, $order);
        $list = '';
        foreach ($documents as $doc) {
            $attrs = ['class' => 'orange-library-document' . ($doc->readtime ? ' is-read' : ' is-unread') . ($doc->id === $selecteddocument ? ' is-selected' : '')];
            if ($doc->id === $selecteddocument) { $attrs['aria-current'] = 'true'; }
            $path = implode(' › ', array_map(fn($cat) => $cat->name, self::trail($doc->category, $rootid, $categories)));
            $list .= \html_writer::tag('li', \html_writer::link(self::url(['category' => $doc->category,
                'document' => $doc->id, 'q' => $query, 'order' => $order]),
                \html_writer::span($doc->readtime ? '✓' : '●', 'orange-library-read-icon', ['role' => 'img',
                    'aria-label' => get_string($doc->readtime ? 'libraryread' : 'libraryunread', 'local_orangeportal'),
                    'title' => get_string($doc->readtime ? 'libraryread' : 'libraryunread', 'local_orangeportal')]) .
                \html_writer::span(\html_writer::tag('strong', s($doc->name), ['title' => $doc->name]) .
                    \html_writer::span(s($doc->description ?: get_string('librarynodescription', 'local_orangeportal')),
                        'orange-library-document-description', ['title' => $doc->description]) .
                    \html_writer::span(s($path), 'orange-library-document-path', ['title' => $path]),
                    'orange-library-document-text'), $attrs));
        }
        $resulttitle = \html_writer::tag('h3', get_string('librarydocuments', 'local_orangeportal'));
        $resultcount = \html_writer::tag('p', get_string($query !== '' ? 'libraryglobalresults' : 'librarydocumentcount',
            'local_orangeportal', count($documents)), ['class' => 'orange-library-result-count']);
        $sortform = (optional_param('unread', 0, PARAM_BOOL) ? \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'unread', 'value' => 1]) : '') . \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'section', 'value' => 'library']) .
            \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'category', 'value' => $category]) .
            \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'q', 'value' => $query]) .
            \html_writer::tag('label', get_string('librarysort', 'local_orangeportal'),
                ['for' => 'orange-library-order', 'class' => 'sr-only']) .
            \html_writer::select(['newest' => get_string('librarysortnewest', 'local_orangeportal'),
                'name' => get_string('librarysortname', 'local_orangeportal')], 'order', $order, false,
                ['id' => 'orange-library-order', 'class' => 'custom-select custom-select-sm']);
        $resultheading = \html_writer::div($resulttitle . \html_writer::tag('form', $sortform, ['method' => 'get',
            'action' => self::url([])->out(false), 'class' => 'orange-library-sort']),
            'orange-library-results-heading') . $resultcount;
        $listing = $list ? \html_writer::tag('ul', $list, ['class' => 'orange-library-documents']) :
            \html_writer::tag('p', get_string($query !== '' ? 'librarynomatches' : 'librarynodocuments', 'local_orangeportal'),
                ['class' => 'orange-library-empty']);
        $details = '';
        if ($selecteddocument && isset($documents[$selecteddocument])) {
            $doc = $documents[$selecteddocument];
            $path = implode(' › ', array_map(fn($cat) => $cat->name, self::trail($doc->category, $rootid, $categories)));
            $details = \html_writer::tag('p', s($path), ['class' => 'orange-library-document-path']) .
                \html_writer::tag('h4', s($doc->name)) .
                \html_writer::tag('p', s($doc->description ?: get_string('librarynodescription', 'local_orangeportal'))) .
                \html_writer::span(s(get_string('modulename', $doc->module)), 'orange-library-resource-type') .
                \html_writer::link($doc->url, get_string('libraryopendocument', 'local_orangeportal'),
                    ['class' => 'btn btn-primary orange-library-open']);
            if (has_capability('moodle/course:manageactivities', \context_course::instance($doc->courseid))) {
                $details .= \html_writer::link(new \moodle_url('/course/view.php', ['id' => $doc->courseid]),
                    get_string('librarymanagecollection', 'local_orangeportal'),
                    ['class' => 'orange-library-manage']);
            }
        } else {
            $details = \html_writer::tag('p', get_string($selecteddocument ? 'librarydocumentunavailable' : 'libraryselectdocument',
                'local_orangeportal'));
        }
        $panel = \html_writer::tag('section', $resultheading . $listing .
            \html_writer::div($details, 'orange-library-detail', ['id' => 'orange-library-detail']),
            ['class' => 'orange-library-column orange-library-results',
                'aria-label' => get_string('librarydocuments', 'local_orangeportal')]);
        $html .= \html_writer::tag('div', $columns . $panel, ['class' => 'orange-library-browser', 'tabindex' => '0',
            'data-focus' => $selecteddocument ? 'detail' : ($query !== '' || empty($children[$category]) ? 'documents' : 'branch'),
            'role' => 'region', 'aria-label' => get_string('libraryexplorer', 'local_orangeportal')]);
        return $html;
    }
}
