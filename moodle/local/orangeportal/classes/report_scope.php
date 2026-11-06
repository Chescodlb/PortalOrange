<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** The same recipient boundary applies to lists, aggregates, individual URLs and exports. */
class report_scope {
    public static function guard_native_reports(): void {
        global $CFG;
        if ((defined('CLI_SCRIPT') && CLI_SCRIPT) || !experience::member() || self::global_access() ||
                !has_capability('local/orangeportal:viewreports', \context_system::instance())) { return; }
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $root = rtrim(parse_url($CFG->wwwroot, PHP_URL_PATH) ?: '', '/');
        $path = substr($path ?? '', strlen($root));
        global $USER;
        if (in_array($path, ['/grade/report/user/index.php', '/grade/report/overview/index.php'], true)) {
            $context = \context_course::instance(optional_param('id', SITEID, PARAM_INT), IGNORE_MISSING);
            // Staff grade reports expose selectors for every learner, even when viewing oneself.
            if ($context && !has_capability('moodle/grade:viewall', $context) &&
                    optional_param('userid', (int)$USER->id, PARAM_INT) === (int)$USER->id) {
                if (!optional_param('userid', 0, PARAM_INT)) {
                    redirect(new \moodle_url($path, ['id' => $context->instanceid, 'userid' => $USER->id]));
                }
                return;
            }
        }
        if (preg_match('~^/(grade/(report|export)/|report/|user/index\.php|mod/quiz/report\.php|mod/feedback/(analysis|show_entries|export))~', $path)) {
            throw new \moodle_exception('trackingscopednative', 'local_orangeportal',
                new \moodle_url('/local/orangeportal/reports.php'));
        }
    }
    public static function global_access(): bool {
        return is_siteadmin() || has_capability('local/orangeportal:viewallreports', \context_system::instance());
    }
    public static function cohorts(): array {
        global $DB, $USER;
        $cohorts = orange_scope::cohorts();
        if (self::global_access()) { return $cohorts; }
        return array_filter($cohorts, fn($c) => $DB->record_exists('cohort_members', ['cohortid' => $c->id, 'userid' => $USER->id]));
    }
    public static function selected(): int {
        $id = optional_param('report_cohort', optional_param('user_cohort', 0, PARAM_INT), PARAM_INT);
        if ($id && !isset(self::cohorts()[$id])) { throw new \moodle_exception('outsideorange', 'local_orangeportal'); }
        return $id;
    }
    public static function user_sql(string $column, string $prefix = 'reportscope'): array {
        global $DB;
        if (!preg_match('/^[a-z]+\.[a-z]+$/D', $column) || !preg_match('/^[a-z]+$/D', $prefix)) { throw new \coding_exception('Invalid scope column'); }
        $selected = self::selected();
        if (!$selected && self::global_access()) { return ['1=1', []]; }
        $ids = $selected ? [$selected] : array_keys(self::cohorts());
        if (!$ids) { return ['1=0', []]; }
        [$sql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, $prefix);
        return ["EXISTS (SELECT 1 FROM {cohort_members} rs WHERE rs.userid=$column AND rs.cohortid $sql)", $params];
    }
    public static function users(): array {
        global $DB;
        [$sql, $params] = self::user_sql('u.id');
        $params['rootaudience'] = (int)get_config('local_orangeportal', 'audiencecohort');
        return $DB->get_records_sql("SELECT u.* FROM {user} u JOIN {cohort_members} a ON a.userid=u.id
            WHERE a.cohortid=:rootaudience AND u.deleted=0 AND u.id>1 AND $sql ORDER BY u.lastname,u.firstname,u.id", $params);
    }
    public static function require_user(int $id): void {
        if (!isset(self::users()[$id])) { throw new \moodle_exception('outsideorange', 'local_orangeportal'); }
    }
    public static function require_notice(int $id): void {
        if (self::global_access() && !self::selected()) { return; }
        foreach (self::users() as $u) { if (service::assigned($id, (int)$u->id)) { return; } }
        throw new \moodle_exception('outsideorange', 'local_orangeportal');
    }
    public static function user_cohorts(int $userid): string {
        global $DB;
        $names = [];
        foreach (self::cohorts() as $c) {
            if ($DB->record_exists('cohort_members', ['cohortid' => $c->id, 'userid' => $userid])) { $names[] = $c->name; }
        }
        return implode(' · ', $names);
    }
}
