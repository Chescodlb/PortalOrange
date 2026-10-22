<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** Explicit project boundaries; names and username prefixes never grant access. */
class orange_scope {
    public static function users(): array {
        global $DB;
        return $DB->get_records_sql('SELECT u.* FROM {user} u JOIN {cohort_members} m ON m.userid = u.id
            WHERE m.cohortid = ? AND u.deleted = 0 AND u.id > 1 ORDER BY u.lastname, u.firstname, u.id',
            [(int)get_config('local_orangeportal', 'audiencecohort')]);
    }
    public static function user(int $id): \stdClass {
        global $DB;
        if (!experience::member($id) || is_siteadmin($id)) { throw new \moodle_exception('outsideorange', 'local_orangeportal'); }
        $u = $DB->get_record('user', ['id' => $id, 'deleted' => 0], '*', MUST_EXIST);
        if (has_capability('local/orangeportal:administer', \context_system::instance(), $id)) {
            throw new \moodle_exception('orangeprotecteduser', 'local_orangeportal');
        }
        return $u;
    }
    public static function course_ids(): array {
        global $DB;
        $ids = [];
        foreach (['librarycategory', 'coursescategory'] as $key) {
            $root = $DB->get_record('course_categories', ['id' => (int)get_config('local_orangeportal', $key)]);
            if (!$root) { continue; }
            $records = $DB->get_records_sql('SELECT c.id FROM {course} c JOIN {course_categories} cat ON cat.id=c.category
                WHERE cat.id = ? OR cat.path LIKE ?', [$root->id, $root->path . '/%']);
            foreach ($records as $r) { $ids[$r->id] = (int)$r->id; }
        }
        return $ids;
    }
    /** Cohorts are native Moodle objects explicitly managed by this plugin. */
    public static function cohorts(): array {
        global $DB;
        $candidates = $DB->get_records('cohort', ['component' => 'local_orangeportal'], 'name');
        $extra = array_filter(array_map('intval', explode(',', get_config('local_orangeportal', 'managedcohorts') ?: '')));
        if ($extra) {
            foreach ($DB->get_records_list('cohort', 'id', $extra, 'name') as $c) {
                if ($c->component === '') { $candidates[$c->id] = $c; }
            }
        }
        $base = (int)get_config('local_orangeportal', 'audiencecohort');
        $courses = self::course_ids();
        foreach ($candidates as $id => $c) {
            if ($id === $base || $DB->record_exists_sql('SELECT 1 FROM {cohort_members} m WHERE m.cohortid=? AND NOT EXISTS
                    (SELECT 1 FROM {cohort_members} a WHERE a.cohortid=? AND a.userid=m.userid)', [$id, $base])) {
                unset($candidates[$id]); continue;
            }
            foreach ($DB->get_records('enrol', ['enrol' => 'cohort', 'customint1' => $id]) as $enrol) {
                if (!isset($courses[$enrol->courseid])) { unset($candidates[$id]); break; }
            }
        }
        uasort($candidates, fn($a, $b) => strnatcasecmp($a->name, $b->name));
        return $candidates;
    }
    public static function groups(): array {
        global $DB;
        $ids = self::course_ids();
        if (!$ids) { return []; }
        [$sql, $params] = $DB->get_in_or_equal(array_values($ids));
        return $DB->get_records_sql("SELECT g.*, c.fullname AS coursename FROM {groups} g JOIN {course} c ON c.id=g.courseid
            WHERE g.courseid $sql ORDER BY c.fullname, g.name", $params);
    }
    public static function target(string $type, int $id): bool {
        if ($type === 'user') { return isset(self::users()[$id]); }
        if ($type === 'cohort') {
            return $id === (int)get_config('local_orangeportal', 'audiencecohort') || isset(self::cohorts()[$id]);
        }
        return $type === 'group' && isset(self::groups()[$id]);
    }
}
