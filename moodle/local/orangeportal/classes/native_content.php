<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** Native modules behind category documents, notice resources and training collections. */
class native_content {
    /** Navigation preferences do not change publication content, state or reading revision. */
    public static function save_opening(int $id, int $activity, int $revision, int $previous): void {
        global $DB;
        experience::require_access();
        require_capability('local/orangeportal:manage', \context_system::instance());
        $lock = \core\lock\lock_config::get_lock_factory('local_orangeportal')->get_lock('notice_' . $id, 10);
        if (!$lock) { throw new \moodle_exception('locktimeout'); }
        try {
            $notice = $DB->get_record('local_orange_notice', ['id' => $id], '*', MUST_EXIST);
            service::require_view($notice);
            if ((int)$notice->revision !== $revision || (int)$notice->openactivity !== $previous) {
                throw new \invalid_parameter_exception(get_string('noticerevisionchanged', 'local_orangeportal'));
            }
            if (!array_key_exists($activity, self::opening_options($notice))) {
                throw new \invalid_parameter_exception(get_string('noticeopeninginvalid', 'local_orangeportal'));
            }
            $DB->set_field('local_orange_notice', 'openactivity', $activity, ['id' => $id]);
        } finally { $lock->release(); }
    }
    /** Only native modules belonging to this publication may be configured as its entry point. */
    public static function opening_options(?\stdClass $notice): array {
        global $DB;
        $options = [0 => get_string('noticeopenrecord', 'local_orangeportal')];
        if (!$notice || !($map = self::notice_mapping((int)$notice->id))) { return $options; }
        foreach ($DB->get_records('course_modules', ['course' => $map->courseid, 'deletioninprogress' => 0], 'id') as $record) {
            $cm = get_fast_modinfo($map->courseid)->get_cm($record->id);
            $options[$cm->id] = $cm->name . ' (' . get_string('modulename', $cm->modname) . ')';
        }
        return $options;
    }
    /** Resolve against this reader's native visibility, availability and module capabilities. */
    public static function opening_activity(\stdClass $notice): ?\cm_info {
        service::require_view($notice);
        if (empty($notice->openactivity) || !($map = self::notice_mapping((int)$notice->id))) { return null; }
        self::enter_notice((int)$map->courseid);
        return self::activities((int)$map->courseid)[$notice->openactivity] ?? null;
    }
    public static function install_roles(): void {
        global $DB;
        $id = $DB->get_field('role', 'id', ['shortname' => 'orange_autorrecursos']);
        if (!$id) {
            $id = create_role('Autor de recursos Orange', 'orange_autorrecursos',
                'Edición de actividades en un contenedor Orange. No concede administración del campus.');
            set_role_contextlevels($id, [CONTEXT_COURSE]);
        }
        foreach (get_default_capabilities('editingteacher') as $cap => $permission) {
            $info = get_capability_info($cap);
            if ($permission === CAP_ALLOW && $info && $info->contextlevel >= CONTEXT_COURSE &&
                    !str_starts_with($cap, 'enrol/') && !str_starts_with($cap, 'moodle/role:')) {
                assign_capability($cap, CAP_ALLOW, $id, \context_system::instance()->id);
            }
        }
        assign_capability('moodle/course:view', CAP_ALLOW, $id, \context_system::instance()->id);
    }
    public static function mapping(int $courseid): ?\stdClass {
        global $DB;
        return $DB->get_record('local_orange_container', ['courseid' => $courseid]) ?: null;
    }
    public static function library_options(): array {
        $root = native_categories::root('library');
        $options = [];
        $categories = library_browser::categories((int)$root->id);
        foreach ($categories as $c) {
            if (has_capability('moodle/course:create', \context_coursecat::instance($c->id))) {
                $trail = library_browser::trail((int)$c->id, (int)$root->id, $categories);
                $options[$c->id] = implode(' › ', array_map(fn($x) => $x->name, $trail)) ?: $c->name;
            }
        }
        return $options;
    }
    public static function can_author(\stdClass $map): bool {
        if (!experience::member()) { return false; }
        if ($map->kind === 'notice') {
            global $DB;
            $notice = $DB->get_record('local_orange_notice', ['id' => $map->noticeid]);
            return service::manager() && $notice && service::can_view($notice);
        }
        global $DB;
        $cat = $DB->get_record('course_categories', ['id' => $map->categoryid]);
        $root = (int)get_config('local_orangeportal', 'librarycategory');
        return $cat && in_array($root, array_map('intval', explode('/', $cat->path)), true) &&
            has_capability('moodle/course:create', \context_coursecat::instance($cat->id));
    }
    public static function author(int $courseid): void {
        global $DB, $USER;
        $map = self::mapping($courseid);
        if (!$map || !self::can_author($map)) { throw new \moodle_exception('nativecontentdenied', 'local_orangeportal'); }
        $role = $DB->get_field('role', 'id', ['shortname' => 'orange_autorrecursos'], MUST_EXIST);
        role_assign($role, $USER->id, \context_course::instance($courseid)->id, 'local_orangeportal', (int)$map->id);
    }
    /** Explicit creation only; existing user collections and modules are never moved or replaced. */
    public static function library_container(int $categoryid): int {
        experience::require_access();
        if (!isset(self::library_options()[$categoryid])) { throw new \moodle_exception('nativecontentdenied', 'local_orangeportal'); }
        return self::ensure('library', $categoryid, 0);
    }
    public static function notice_container(int $noticeid): int {
        global $DB;
        experience::require_access();
        require_capability('local/orangeportal:manage', \context_system::instance());
        $notice = $DB->get_record('local_orange_notice', ['id' => $noticeid], '*', MUST_EXIST);
        service::require_view($notice);
        return self::ensure('notice', 0, $noticeid);
    }
    private static function ensure(string $kind, int $categoryid, int $noticeid): int {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $lock = \core\lock\lock_config::get_lock_factory('local_orangeportal')->get_lock("container_{$kind}_{$categoryid}_{$noticeid}", 10);
        if (!$lock) { throw new \moodle_exception('locktimeout'); }
        try {
            $params = ['kind' => $kind, 'categoryid' => $categoryid, 'noticeid' => $noticeid];
            if ($map = $DB->get_record('local_orange_container', $params)) {
                $DB->get_record('course', ['id' => $map->courseid], '*', MUST_EXIST);
                self::author((int)$map->courseid);
                return (int)$map->courseid;
            }
            $tx = $DB->start_delegated_transaction();
            if ($kind === 'notice') {
                $category = self::resources_category();
                $name = $DB->get_field('local_orange_notice', 'title', ['id' => $noticeid], MUST_EXIST);
            } else {
                $category = $categoryid;
                $name = $DB->get_field('course_categories', 'name', ['id' => $categoryid], MUST_EXIST);
            }
            $course = create_course((object)['category' => $category, 'fullname' => $name,
                'shortname' => 'ORANGE-RES-' . $kind . '-' . ($noticeid ?: $categoryid) . '-' . bin2hex(random_bytes(4)),
                'format' => 'topics', 'numsections' => 0, 'visible' => 1, 'startdate' => 0, 'enddate' => 0,
                'newsitems' => 0, 'showgrades' => 0, 'enablecompletion' => 0]);
            $map = (object)($params + ['courseid' => $course->id]);
            $map->id = $DB->insert_record('local_orange_container', $map);
            // Disable default guest/self enrolment; access is granted through the portal/native cohort sync.
            foreach (enrol_get_instances($course->id, false) as $e) {
                if ($plugin = enrol_get_plugin($e->enrol)) { $plugin->delete_instance($e); }
            }
            if ($kind === 'library') {
                if (!enrol_is_enabled('cohort')) { throw new \moodle_exception('nativecohortrequired', 'local_orangeportal'); }
                $base = (int)get_config('local_orangeportal', 'audiencecohort');
                $DB->get_record('cohort', ['id' => $base], '*', MUST_EXIST);
                $roles = get_archetype_roles('student');
                if (!$roles) { throw new \moodle_exception('csvnostudentrole', 'local_orangeportal'); }
                enrol_get_plugin('cohort')->add_instance($course, ['customint1' => $base,
                    'roleid' => reset($roles)->id, 'status' => ENROL_INSTANCE_ENABLED]);
                require_once($CFG->dirroot . '/enrol/cohort/locallib.php');
                enrol_cohort_sync(new \null_progress_trace(), $course->id);
            }
            self::author((int)$course->id);
            $tx->allow_commit();
            return (int)$course->id;
        } finally { $lock->release(); }
    }
    private static function resources_category(): int {
        global $DB;
        $lock = \core\lock\lock_config::get_lock_factory('local_orangeportal')->get_lock('resources_category', 10);
        if (!$lock) { throw new \moodle_exception('locktimeout'); }
        try {
            $id = (int)$DB->get_field('config_plugins', 'value', ['plugin' => 'local_orangeportal', 'name' => 'resourcescategory']);
            if (!$id || !$DB->record_exists('course_categories', ['id' => $id])) {
                $id = (int)\core_course_category::create((object)['name' => get_string('nativeinternalcategory', 'local_orangeportal'),
                    'parent' => 0, 'visible' => 0])->id;
                set_config('resourcescategory', $id, 'local_orangeportal');
            }
            return $id;
        } finally { $lock->release(); }
    }
    public static function notice_mapping(int $noticeid): ?\stdClass {
        global $DB;
        return $DB->get_record('local_orange_container', ['kind' => 'notice', 'noticeid' => $noticeid]) ?: null;
    }
    /** Open a publication activity through a checked, CSRF-protected portal POST. */
    public static function enter_notice(int $courseid): void {
        global $DB, $USER;
        $map = self::mapping($courseid);
        if (!$map || $map->kind !== 'notice') { throw new \moodle_exception('nativecontentdenied', 'local_orangeportal'); }
        $notice = $DB->get_record('local_orange_notice', ['id' => $map->noticeid], '*', MUST_EXIST);
        service::require_view($notice);
        if (service::manager()) { self::author($courseid); return; }
        if (!enrol_is_enabled('manual')) { throw new \moodle_exception('nativemanualrequired', 'local_orangeportal'); }
        $plugin = enrol_get_plugin('manual');
        $instances = array_filter(enrol_get_instances($courseid, true), fn($e) => $e->enrol === 'manual');
        $instance = reset($instances);
        if (!$instance) {
            $lock = \core\lock\lock_config::get_lock_factory('local_orangeportal')->get_lock('notice_enrol_' . $courseid, 10);
            if (!$lock) { throw new \moodle_exception('locktimeout'); }
            try {
                $instances = array_filter(enrol_get_instances($courseid, true), fn($e) => $e->enrol === 'manual');
                $instance = reset($instances);
                if (!$instance) { $instance = $DB->get_record('enrol', ['id' => $plugin->add_instance(get_course($courseid))], '*', MUST_EXIST); }
            } finally { $lock->release(); }
        }
        $roles = get_archetype_roles('student');
        if (!$roles) { throw new \moodle_exception('csvnostudentrole', 'local_orangeportal'); }
        $plugin->enrol_user($instance, $USER->id, reset($roles)->id, 0, 0, ENROL_USER_ACTIVE);
    }
    /** Native require_login callback also runs on module pluginfile downloads and external APIs. */
    public static function guard(int $courseid): void {
        global $DB, $USER;
        $map = self::mapping($courseid);
        if (!$map) { return; }
        if (is_siteadmin()) { return; }
        experience::require_access();
        if ($map->kind === 'notice') {
            service::require_view($DB->get_record('local_orange_notice', ['id' => $map->noticeid], '*', MUST_EXIST));
        } else {
            $root = native_categories::root('library');
            $category = $DB->get_record('course_categories', ['id' => get_course($courseid)->category], '*', MUST_EXIST);
            if ($category->id != $map->categoryid || !in_array((int)$root->id, array_map('intval', explode('/', $category->path)), true)) {
                throw new \moodle_exception('nativecontentdenied', 'local_orangeportal');
            }
        }
        // A removed management role must not leave a permanent editing grant on containers.
        if (!self::can_author($map)) {
            $role = $DB->get_field('role', 'id', ['shortname' => 'orange_autorrecursos']);
            if ($role) { role_unassign($role, $USER->id, \context_course::instance($courseid)->id, 'local_orangeportal', $map->id); }
        }
    }
    /** Use Moodle's actual chooser template and AMD controller, including native permissions and favourites. */
    public static function chooser(int $courseid, int $sectionnum = 0, bool $autostart = false): string {
        global $PAGE;
        require_capability('moodle/course:manageactivities', \context_course::instance($courseid));
        $section = get_fast_modinfo($courseid)->get_section_info($sectionnum, MUST_EXIST);
        $renderer = $PAGE->get_renderer('core', 'course');
        $renderer->course_activitychooser($courseid);
        $button = $renderer->render(new \core_course\output\activitychooserbutton($section));
        if ($autostart) {
            $config = json_encode(['tabmode' => get_config('core', 'activitychoosertabmode')], JSON_THROW_ON_ERROR);
            $PAGE->requires->js_init_code("require(['core_course/activitychooser'], function(Chooser) { " .
                "Chooser.init(" . $courseid . ", " . $config . "); " .
                "document.querySelector('[data-native-autostart] button.section-modchooser-link')?.click(); });", true);
        }
        return \html_writer::div($button, 'orange-native-chooser', $autostart ? ['data-native-autostart' => '1'] : []);
    }

    public static function modules(\stdClass $course): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $context = \context_course::instance($course->id);
        require_capability('moodle/course:manageactivities', $context);
        $options = [];
        foreach ($DB->get_records('modules', ['visible' => 1], 'name') as $module) {
            if (has_capability('mod/' . $module->name . ':addinstance', $context) && course_allowed_module($course, $module->name)) {
                $options[$module->name] = get_string('modulename', $module->name);
            }
        }
        return $options;
    }
    /** Module objects are filtered by native availability, group restrictions and visibility. */
    public static function activities(int $courseid, ?int $sectionnum = null, bool $preview = false): array {
        $cms = [];
        foreach (get_fast_modinfo($courseid)->get_cms() as $cm) {
            if (($sectionnum !== null && $cm->sectionnum !== $sectionnum) || !$cm->uservisible ||
                    !$cm->is_visible_on_course_page()) { continue; }
            if (!$preview) {
                $cap = 'mod/' . $cm->modname . ($cm->modname === 'book' ? ':read' : ':view');
                if (get_capability_info($cap) && !has_capability($cap, \context_module::instance($cm->id))) { continue; }
            }
            $cms[$cm->id] = $cm;
        }
        return $cms;
    }
    public static function list_html(int $courseid, ?int $section = null, bool $noticepreview = false, string $buttonclass = 'btn btn-outline-secondary btn-sm'): string {
        $items = '';
        $manager = has_capability('moodle/course:manageactivities', \context_course::instance($courseid));
        foreach (self::activities($courseid, $section, $noticepreview) as $cm) {
            $url = new \moodle_url('/local/orangeportal/activity.php', ['id' => $cm->id]);
            $map = self::mapping($courseid);
            $open = $map && $map->kind === 'notice' ?
                \orange_post_button($url, $cm->name, ['open' => 1]) : \html_writer::link($url, s($cm->name));
            $type = \html_writer::span(s(get_string('modulename', $cm->modname)), 'orange-native-module-type');
            $edit = $manager ? \html_writer::link(new \moodle_url('/course/modedit.php', ['update' => $cm->id, 'return' => 0]),
                get_string('edit'), ['class' => $buttonclass]) : '';
            if ($manager) { $edit .= ' ' . \html_writer::link(new \moodle_url('/course/mod.php', ['delete' => $cm->id, 'sesskey' => sesskey()]), get_string('delete'), ['class' => $buttonclass]); }
            $items .= \html_writer::tag('li', $open . $type . $edit, ['class' => 'orange-native-activity']);
        }
        return $items ? \html_writer::tag('ul', $items, ['class' => 'orange-native-activities']) :
            \html_writer::tag('p', get_string('nativenoactivities', 'local_orangeportal'));
    }
    public static function notice_html(\stdClass $notice): string {
        global $DB;
        service::require_view($notice);
        $map = self::notice_mapping((int)$notice->id);
        $tools = service::manager() ? \html_writer::link(\orange_link('resources.php', ['notice' => $notice->id]),
            get_string('nativemanageresources', 'local_orangeportal'), ['class' => 'btn btn-outline-secondary']) : '';
        // Preparing this reader's native access grants no more than require_view already allows.
        // The guard rechecks the live audience on every native request, including downloads.
        $list = '';
        if ($map) {
            self::enter_notice((int)$map->courseid);
            $list = self::list_html((int)$map->courseid);
        }
        return \html_writer::tag('section', \html_writer::tag('h3', get_string('nativeactivities', 'local_orangeportal')) .
            $tools . ($list ?: \html_writer::tag('p', get_string('nativenoactivities', 'local_orangeportal'))),
            ['class' => 'orange-notice-resources']);
    }
    public static function module_changed(\core\event\base $event): void {
        global $DB;
        $map = self::mapping((int)$event->courseid);
        if (!$map || $map->kind !== 'notice') { return; }
        $lock = \core\lock\lock_config::get_lock_factory('local_orangeportal')->get_lock('notice_' . $map->noticeid, 10);
        if (!$lock) { throw new \moodle_exception('locktimeout'); }
        try {
            $notice = $DB->get_record('local_orange_notice', ['id' => $map->noticeid]);
            if (!$notice || !empty($notice->deleted)) { return; }
            $notice->revision++; $notice->timemodified = time();
            $tx = $DB->start_delegated_transaction();
            $DB->update_record('local_orange_notice', $notice);
            $DB->insert_record('local_orange_revision', (object)['noticeid' => $notice->id, 'revision' => $notice->revision,
                'title' => $notice->title, 'content' => $notice->content, 'authorid' => $event->userid, 'timecreated' => time()]);
            $tx->allow_commit();
        } finally { $lock->release(); }
    }
    public static function module_viewed(\core\event\course_module_viewed $event): void {
        global $DB;
        $map = self::mapping((int)$event->courseid);
        if ($map && $map->kind === 'notice') {
            $notice = $DB->get_record('local_orange_notice', ['id' => $map->noticeid], '*', MUST_EXIST);
            if (service::can_view($notice)) { service::receipt($notice, 'readtime'); }
        }
    }
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;
        $DB->delete_records('local_orange_container', ['courseid' => $event->objectid]);
    }
    /** Empty technical courses do not make an otherwise empty Library category undeletable. */
    public static function empty_library_container(int $courseid): bool {
        global $DB;
        $map = self::mapping($courseid);
        if (!$map || $map->kind !== 'library' || $DB->record_exists('course_modules', ['course' => $courseid])) { return false; }
        $course = get_course($courseid);
        if (trim((string)$course->summary) !== '') { return false; }
        foreach ($DB->get_records('course_sections', ['course' => $courseid]) as $section) {
            if (trim((string)$section->summary) !== '') { return false; }
        }
        // Unknown/custom blocks may contain authored data and require manual review.
        $standard = ['navigation', 'settings', 'admin', 'activity_modules', 'search_forums',
            'news_items', 'recent_activity', 'calendar_upcoming', 'calendar_month', 'course_list'];
        foreach ($DB->get_records('block_instances', ['parentcontextid' => \context_course::instance($courseid)->id]) as $block) {
            if (!in_array($block->blockname, $standard, true)) { return false; }
        }
        return true;
    }

    /** Training collections are native course sections; there are no duplicate section tables. */
    public static function training_course(int $id): \stdClass {
        experience::require_access();
        $courses = \orange_native_courses((int)get_config('local_orangeportal', 'coursescategory'));
        if (!isset($courses[$id])) { throw new \moodle_exception('nativecontentdenied', 'local_orangeportal'); }
        return $courses[$id];
    }
}
