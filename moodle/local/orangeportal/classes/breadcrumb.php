<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** Use the portal section as the native breadcrumb root without rebuilding activity nodes. */
class breadcrumb {
    /** Personal Moodle pages belong to the user account, not the portal landing section. */
    private static function personal(): bool {
        global $PAGE, $CFG, $USER;
        if ($PAGE->course->id != SITEID || $PAGE->context->contextlevel === CONTEXT_COURSECAT) { return false; }
        $basepath = rtrim(parse_url($CFG->wwwroot, PHP_URL_PATH) ?? '', '/');
        $path = substr($PAGE->url->get_path(), strlen($basepath));
        $labels = [
            '/user/preferences.php' => get_string('preferences'),
            '/grade/report/overview/index.php' => get_string('grades'),
            '/calendar/view.php' => get_string('calendar', 'calendar'),
            '/user/files.php' => get_string('privatefiles'),
        ];
        if (!isset($labels[$path]) && $path !== '/user/profile.php') { return false; }
        $userid = $PAGE->context->contextlevel === CONTEXT_USER ? (int)$PAGE->context->instanceid : (int)$USER->id;
        $navigation = $PAGE->navigation;
        $navigation->initialise();
        $user = $userid === (int)$USER->id ? $USER : \core_user::get_user($userid, '*', MUST_EXIST);
        $navigation->text = $userid === (int)$USER->id ? get_string('myaccount', 'local_orangeportal') : fullname($user);
        $navigation->shorttext = $navigation->text;
        $navigation->action = new \moodle_url('/user/profile.php', ['id' => $userid]);
        // Keep calendar month/day nodes, while replacing the native Home/settings ancestry.
        $calendartrail = $path === '/calendar/view.php' ? $PAGE->navbar->children : [];
        $PAGE->navbar->ignore_active();
        $PAGE->navbar->children = [];
        if ($path === '/user/profile.php') { return true; }
        $PAGE->navbar->add($labels[$path], $calendartrail ? new \moodle_url('/calendar/view.php') : null);
        foreach ($calendartrail as $node) {
            if ($node->text !== $labels[$path]) { $PAGE->navbar->add($node->text, $node->action); }
        }
        return true;
    }
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
        if (self::personal()) { return; }
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
        } else if (in_array($file, ['reports.php', 'options.php'])) {
            $section = 'administration';
        }
        if (!in_array($section, ['library', 'courses', 'incidents', 'notifications', 'administration'], true)) { return; }
        self::root($section);
        $PAGE->navbar->ignore_active();
        $PAGE->navbar->children = [];
        if ($file !== 'index.php') { $PAGE->navbar->add($PAGE->heading); }
    }
}
