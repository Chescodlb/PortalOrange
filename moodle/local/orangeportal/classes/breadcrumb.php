<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** Use the portal section as the native breadcrumb root without rebuilding activity nodes. */
class breadcrumb {
    public static function root(string $section): void {
        global $PAGE;
        $params = ['section' => $section];
        if (in_array($section, ['library', 'courses'], true)) {
            $params['category'] = native_categories::root($section)->id;
        } else {
            // Keep the root visible when Moove removes exact primary-menu duplicates.
            $params['category'] = 0;
        }
        $navigation = $PAGE->navigation;
        $navigation->initialise();
        $navigation->text = $section === 'administration' ? get_string('administration') :
            get_string($section, 'local_orangeportal');
        $navigation->shorttext = $navigation->text;
        $navigation->action = $section === 'administration' ?
            new \moodle_url('/local/orangeportal/reports.php', ['category' => 0]) :
            new \moodle_url('/local/orangeportal/index.php', $params);
    }

    public static function apply(): void {
        global $PAGE, $CFG, $DB;
        if (course_creation::section()) { return; } // The creation form already sets its breadcrumb.
        $categoryid = $PAGE->course->id != SITEID ? (int)$PAGE->course->category :
            ($PAGE->context->contextlevel === CONTEXT_COURSECAT ? (int)$PAGE->context->instanceid : 0);
        if ($categoryid) {
            $category = $DB->get_record('course_categories', ['id' => $categoryid]);
            $ancestors = $category ? array_map('intval', explode('/', $category->path)) : [];
            foreach (['library' => 'librarycategory', 'courses' => 'coursescategory'] as $section => $setting) {
                $rootid = (int)get_config('local_orangeportal', $setting);
                if ($rootid && in_array($rootid, $ancestors, true)) {
                    self::root($section);
                    return;
                }
            }
        }
        $path = $PAGE->url->get_path();
        $portalpath = parse_url($CFG->wwwroot . '/local/orangeportal/', PHP_URL_PATH);
        if (!str_starts_with($path, $portalpath)) { return; }
        $file = basename($path);
        $section = $PAGE->url->get_param('section') ?? $PAGE->url->get_param('kind');
        if ($file === 'view.php') {
            $section = $DB->get_field('local_orange_notice', 'kind', ['id' => (int)$PAGE->url->get_param('id')]);
        } else if ($file === 'add_document.php') {
            $section = 'library';
        } else if ($file === 'manage.php') {
            // admin_externalpage_setup uses a canonical URL without the current kind.
            $section = optional_param('kind', 'incidents', PARAM_ALPHA);
        } else if ($file === 'reports.php') {
            $section = 'administration';
        }
        if (!in_array($section, ['library', 'courses', 'incidents', 'notifications', 'administration'], true)) { return; }
        self::root($section);
        $PAGE->navbar->ignore_active();
        $PAGE->navbar->children = [];
        if ($file !== 'index.php') { $PAGE->navbar->add($PAGE->heading); }
    }
}
