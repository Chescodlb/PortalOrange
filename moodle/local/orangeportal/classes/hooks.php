<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();
class hooks {
    public static function library_navigation(\core\hook\navigation\secondary_extend $hook): void {
        global $PAGE, $DB;
        if (!experience::member() || !isloggedin() || isguestuser() ||
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
    public static function navigation(\core\hook\navigation\primary_extend $hook): void {
        global $PAGE, $DB;
        if (!isloggedin() || isguestuser() || !experience::member()) {
            return;
        }
        $nav = $hook->get_primaryview();
        $existing = iterator_to_array($nav->children);
        foreach ($existing as $node) {
            $node->remove();
        }
        foreach (['library', 'notifications', 'incidents', 'courses'] as $key) {
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
        if (is_siteadmin() || has_capability('local/orangeportal:administer', \context_system::instance()) || has_capability('local/orangeportal:manage', \context_system::instance()) ||
                has_capability('local/orangeportal:viewreports', \context_system::instance()) ||
                has_capability('local/orangeportal:manageoptions', \context_system::instance())) {
            // Delegated roles have portal capabilities, not site configuration access.
            $adminpath = has_capability('moodle/site:config', \context_system::instance()) ? '/admin/search.php' :
                (has_capability('local/orangeportal:manage', \context_system::instance()) ?
                    '/local/orangeportal/manage.php' : (has_capability('local/orangeportal:viewreports', \context_system::instance()) ?
                        '/local/orangeportal/reports.php' : '/local/orangeportal/options.php'));
            if (!is_siteadmin() && has_capability('local/orangeportal:administer', \context_system::instance())) { $adminpath = '/local/orangeportal/users.php'; }
            $nav->add(get_string('administration'), new \moodle_url($adminpath),
                \navigation_node::TYPE_SITE_ADMIN, null, 'siteadminnode');
        }
    }
    public static function headers(\core\hook\output\before_http_headers $hook): void {
        global $PAGE, $CFG;
        if (!experience::member() || !isloggedin() || isguestuser() ||
                defined('CLI_SCRIPT') && CLI_SCRIPT) {
            return;
        }
        $PAGE->requires->css(new \moodle_url('/local/orangeportal/styles.css', ['v' => filemtime(__DIR__ . '/../styles.css')]));
        // Consumer menus omit optional tools; combined staff roles retain their menu.
        $staffcaps = ['administer', 'manage', 'viewreports', 'manageoptions', 'viewallincidents', 'exportreports'];
        $staff = is_siteadmin();
        foreach ($staffcaps as $cap) {
            $staff = $staff || has_capability('local/orangeportal:' . $cap, \context_system::instance());
        }
        if (!$staff) { $PAGE->add_body_class('orange-agent-menu'); }
        course_creation::presentation();
        breadcrumb::apply();
        // Error pages may default PAGE->url to the site root; use the actual request.
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (in_array($path, ['/', '/index.php', '/my/index.php', '/my/'])) {
            $section = get_config('local_orangeportal', 'landingsection') ?: 'library';
            if (!in_array($section, ['library', 'courses', 'incidents', 'notifications'], true)) { $section = 'library'; }
            $target = new \moodle_url('/local/orangeportal/index.php', ['section' => $section]);
            redirect($target);
        }
    }
}
