<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();
class hooks {
    public static function library_navigation(\core\hook\navigation\secondary_extend $hook): void {
        global $PAGE, $DB;
        if (!get_config('local_orangeportal', 'enabled') || !isloggedin() || isguestuser() ||
                $PAGE->course->id == SITEID || $PAGE->context->contextlevel != CONTEXT_COURSE) { return; }
        $rootid = (int)get_config('local_orangeportal', 'librarycategory');
        $category = $DB->get_record('course_categories', ['id' => $PAGE->course->category]);
        if (!$rootid || !$category || !in_array($rootid, array_map('intval', explode('/', $category->path)))) { return; }
        $labels = ['coursehome' => 'Documentos', 'editsettings' => 'Configuración', 'participants' => 'Accesos'];
        $nav = $hook->get_secondaryview();
        foreach (iterator_to_array($nav->children) as $node) {
            if (!isset($labels[$node->key])) { $node->remove(); continue; }
            $node->text = $labels[$node->key];
            $node->title = $labels[$node->key];
        }
    }
    public static function menus(\core\hook\output\before_footer_html_generation $hook): void {
        global $PAGE, $CFG;
        if (!isloggedin() || isguestuser() || !get_config('local_orangeportal', 'enabled')) { return; }
        require_once($CFG->dirroot . '/local/orangeportal/locallib.php');
        foreach (['library' => 'Biblioteca', 'courses' => 'Cursos', 'incidents' => 'Incidencias',
                'notifications' => 'Notificaciones'] as $section => $label) {
            $label = get_string($section, 'local_orangeportal');
            if (in_array($section, ['library', 'courses'])) {
                $rootid = (int)get_config('local_orangeportal', $section == 'library' ? 'librarycategory' : 'coursescategory');
                $tree = orange_library_tree($rootid, 0, orange_native_courses($rootid), $section);
            } else {
                $tree = orange_notice_tree($section, service::visible($section), 0);
            }
            $content = \html_writer::tag('h2', s($label), ['class' => 'orange-menu-title']) .
                \html_writer::link(orange_link('index.php', ['section' => $section]), 'Ver todo', ['class' => 'orange-tree-all']) .
                ($tree ?: \html_writer::tag('p', 'No hay categorías disponibles.', ['class' => 'orange-menu-empty']));
            $hook->add_html(\html_writer::tag('nav', $content, ['id' => 'orange-menu-' . $section,
                'class' => 'orange-nav-panel', 'aria-label' => 'Categorías de ' . $label, 'hidden' => 'hidden']));
        }
        $PAGE->requires->js('/local/orangeportal/navigation.js');
        $PAGE->requires->js_init_call('OrangePortalNavigation.init');
    }
    public static function navigation(\core\hook\navigation\primary_extend $hook): void {
        global $PAGE, $DB;
        if (!isloggedin() || isguestuser() || !get_config('local_orangeportal', 'enabled')) {
            return;
        }
        $nav = $hook->get_primaryview();
        $existing = iterator_to_array($nav->children);
        foreach ($existing as $node) {
            $node->remove();
        }
        foreach (['library' => 'Biblioteca', 'courses' => 'Cursos', 'incidents' => 'Incidencias',
                'notifications' => 'Notificaciones'] as $key => $label) {
            $label = get_string($key, 'local_orangeportal');
            $nav->add($label, new \moodle_url('/local/orangeportal/index.php', ['section' => $key]),
                \navigation_node::TYPE_CUSTOM, null, 'orange_' . $key);
        }
        if ($PAGE->course->id != SITEID) {
            $category = $DB->get_record('course_categories', ['id' => $PAGE->course->category]);
            $rootid = (int)get_config('local_orangeportal', 'librarycategory');
            $key = $category && in_array($rootid, array_map('intval', explode('/', $category->path))) ? 'library' : 'courses';
            $PAGE->set_primary_active_tab('orange_' . $key);
        }
        if (is_siteadmin() || has_capability('local/orangeportal:manage', \context_system::instance()) ||
                has_capability('local/orangeportal:viewreports', \context_system::instance())) {
            // Delegated roles have portal capabilities, not site configuration access.
            $adminpath = has_capability('moodle/site:config', \context_system::instance()) ? '/admin/search.php' :
                (has_capability('local/orangeportal:manage', \context_system::instance()) ?
                    '/local/orangeportal/manage.php' : '/local/orangeportal/reports.php');
            $nav->add(get_string('administration'), new \moodle_url($adminpath),
                \navigation_node::TYPE_SITE_ADMIN, null, 'siteadminnode');
        }
    }
    public static function homepage(\core_user\hook\extend_default_homepage $hook): void {
        foreach (['library' => 'Biblioteca Orange', 'courses' => 'Cursos Orange',
                'incidents' => 'Incidencias Orange', 'notifications' => 'Notificaciones Orange'] as $key => $title) {
            $hook->add_option(new \core\url('/local/orangeportal/index.php', ['section' => $key]), $title);
        }
    }
    public static function headers(\core\hook\output\before_http_headers $hook): void {
        global $PAGE, $CFG;
        if (!get_config('local_orangeportal', 'enabled') || !isloggedin() || isguestuser() ||
                defined('CLI_SCRIPT') && CLI_SCRIPT) {
            return;
        }
        $PAGE->requires->css('/local/orangeportal/styles.css');
        course_creation::presentation();
        breadcrumb::apply();
        // Error pages may default PAGE->url to the site root; use the actual request.
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (in_array($path, ['/', '/index.php', '/my/index.php', '/my/'])) {
            $target = strpos((string)$CFG->defaulthomepage, '/local/orangeportal/index.php') === 0 ?
                new \moodle_url($CFG->defaulthomepage) : new \moodle_url('/local/orangeportal/index.php', ['section' => 'library']);
            redirect($target);
        }
    }
}
