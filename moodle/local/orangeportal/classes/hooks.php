<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();
class hooks {
    public static function navigation(\core\hook\navigation\primary_extend $hook): void {
        if (!isloggedin() || isguestuser() || !get_config('local_orangeportal', 'enabled')) {
            return;
        }
        $nav = $hook->get_primaryview();
        foreach ($nav->children as $node) {
            $node->remove();
        }
        foreach (['library' => 'Biblioteca', 'courses' => 'Cursos', 'incidents' => 'Incidencias',
                'notifications' => 'Notificaciones'] as $key => $label) {
            $nav->add($label, new \moodle_url('/local/orangeportal/index.php', ['section' => $key]),
                \navigation_node::TYPE_CUSTOM, null, 'orange_' . $key);
        }
        if (is_siteadmin() || has_capability('local/orangeportal:manage', \context_system::instance()) ||
                has_capability('local/orangeportal:viewreports', \context_system::instance())) {
            $nav->add('Administración', new \moodle_url('/admin/search.php'),
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
        $path = $PAGE->url->get_path();
        if (in_array($path, ['/', '/index.php', '/my/index.php', '/my/'])) {
            redirect(new \moodle_url($CFG->defaulthomepage));
        }
    }
}
