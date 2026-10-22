<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** Delegated administration without Moodle's global account or site configuration capabilities. */
class orange_administration {
    public static function require_access(): void {
        experience::require_access();
        require_capability('local/orangeportal:administer', \context_system::instance());
    }
    public static function install_roles(): void {
        global $DB;
        $definitions = [
            'orange_administrador' => ['Administrador de Orange', [CONTEXT_SYSTEM]],
            'orange_admin_area' => ['Administración de contenidos Orange', [CONTEXT_COURSECAT]],
            'orange_cuenta' => ['Cuenta exclusiva Orange (sin permisos)', [CONTEXT_USER]],
        ];
        foreach ($definitions as $short => [$name, $levels]) {
            if (!$DB->record_exists('role', ['shortname' => $short])) {
                $id = create_role($name, $short, 'Ámbito Orange. Sin administración general de Moodle.');
                set_role_contextlevels($id, $levels);
            }
        }
        $admin = $DB->get_field('role', 'id', ['shortname' => 'orange_administrador'], MUST_EXIST);
        foreach (['administer', 'exportreports', 'manage', 'viewreports', 'viewallincidents', 'changestate', 'manageoptions'] as $cap) {
            assign_capability('local/orangeportal:' . $cap, CAP_ALLOW, $admin, \context_system::instance()->id);
        }
        // Native content capabilities are assigned only at the two Orange category roots.
        $area = $DB->get_field('role', 'id', ['shortname' => 'orange_admin_area'], MUST_EXIST);
        foreach (get_default_capabilities('editingteacher') as $cap => $permission) {
            $info = get_capability_info($cap);
            if ($permission === CAP_ALLOW && $info && $info->contextlevel >= CONTEXT_COURSECAT &&
                    !str_starts_with($cap, 'enrol/') && !str_starts_with($cap, 'moodle/role:')) {
                assign_capability($cap, CAP_ALLOW, $area, \context_system::instance()->id);
            }
        }
        foreach (['moodle/course:create', 'moodle/category:manage', 'moodle/course:view', 'moodle/course:viewhiddencourses',
                'moodle/category:viewhiddencategories', 'moodle/grade:export', 'gradeexport/txt:view', 'gradeexport/xls:view'] as $cap) {
            assign_capability($cap, CAP_ALLOW, $area, \context_system::instance()->id);
        }
    }
    public static function sync_areas(int $userid): void {
        global $DB;
        $system = $DB->get_field('role', 'id', ['shortname' => 'orange_administrador']);
        $area = $DB->get_field('role', 'id', ['shortname' => 'orange_admin_area']);
        if (!$system || !$area) { return; }
        $active = experience::member($userid) && $DB->record_exists('role_assignments',
            ['roleid' => $system, 'userid' => $userid, 'contextid' => \context_system::instance()->id]);
        role_unassign_all(['roleid' => $area, 'userid' => $userid, 'component' => 'local_orangeportal']);
        if (!$active) { return; }
        foreach (['librarycategory', 'coursescategory'] as $setting) {
            $id = (int)get_config('local_orangeportal', $setting);
            if ($id && $DB->record_exists('course_categories', ['id' => $id])) {
                role_assign($area, $userid, \context_coursecat::instance($id)->id, 'local_orangeportal');
            }
        }
    }
    public static function role_changed(\core\event\base $event): void {
        global $DB;
        $role = $DB->get_field('role', 'id', ['shortname' => 'orange_administrador']);
        if ($event->objectid == $role && $event->contextlevel === CONTEXT_SYSTEM) {
            self::sync_areas((int)$event->relateduserid);
        }
    }
    public static function membership_changed(\core\event\base $event): void {
        if ((int)$event->objectid === (int)get_config('local_orangeportal', 'audiencecohort')) {
            self::sync_areas((int)$event->relateduserid);
        }
    }
    /** Existing shared accounts may have memberships managed, but never global credentials edited. */
    public static function owned_account(int $userid): bool {
        global $DB;
        $role = $DB->get_field('role', 'id', ['shortname' => 'orange_cuenta']);
        if (!$role) { return false; }
        return $DB->record_exists('role_assignments', ['roleid' => $role, 'userid' => $userid,
            'contextid' => \context_user::instance($userid)->id, 'component' => 'local_orangeportal']);
    }
    public static function exclusive_account(int $userid): bool {
        global $DB;
        if (!self::owned_account($userid)) { return false; }
        $allowed = array_keys(orange_scope::cohorts());
        $allowed[] = (int)get_config('local_orangeportal', 'audiencecohort');
        foreach ($DB->get_records('cohort_members', ['userid' => $userid]) as $m) {
            if (!in_array((int)$m->cohortid, $allowed, true)) { return false; }
        }
        $roots = [];
        foreach (['librarycategory', 'coursescategory'] as $setting) {
            $id = (int)get_config('local_orangeportal', $setting);
            if ($id) { $roots[] = \context_coursecat::instance($id)->id; }
        }
        $owncontext = \context_user::instance($userid)->id;
        $portalroles = $DB->get_records_list('role', 'shortname', ['orange_publicador', 'orange_seguimiento']);
        foreach ($DB->get_records('role_assignments', ['userid' => $userid]) as $ra) {
            if ($ra->contextid == $owncontext) { continue; }
            $context = \context::instance_by_id($ra->contextid);
            if ($context->contextlevel === CONTEXT_SYSTEM && isset($portalroles[$ra->roleid])) { continue; }
            if (!array_intersect($roots, array_map('intval', explode('/', $context->path)))) { return false; }
        }
        $courses = orange_scope::course_ids();
        foreach ($DB->get_records_sql('SELECT e.courseid FROM {enrol} e JOIN {user_enrolments} ue ON ue.enrolid=e.id
                WHERE ue.userid=?', [$userid]) as $e) {
            if (!isset($courses[$e->courseid])) { return false; }
        }
        return true;
    }
    public static function create_user(\stdClass $data): int {
        global $CFG, $DB;
        self::require_access();
        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->dirroot . '/cohort/lib.php');
        $error = '';
        if (!check_password_policy($data->password ?? '', $error)) { throw new \moodle_exception('invalidpassword', 'local_orangeportal', '', $error); }
        $username = clean_param(trim($data->username), PARAM_USERNAME);
        if ($username === '' || $username !== trim($data->username) ||
                $DB->record_exists('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id]) ||
                !validate_email($data->email) || $DB->record_exists('user', ['email' => $data->email, 'deleted' => 0]) ||
                trim($data->firstname) === '' || trim($data->lastname) === '') {
            throw new \moodle_exception('orangeinvalidaccount', 'local_orangeportal');
        }
        $tx = $DB->start_delegated_transaction();
        $u = (object)['username' => $username, 'password' => $data->password, 'auth' => 'manual',
            'firstname' => clean_param($data->firstname, PARAM_NOTAGS), 'lastname' => clean_param($data->lastname, PARAM_NOTAGS),
            'email' => clean_param($data->email, PARAM_EMAIL), 'confirmed' => 1, 'mnethostid' => $CFG->mnet_localhost_id];
        $id = user_create_user($u);
        set_user_preference('auth_forcepasswordchange', 1, $id);
        $marker = $DB->get_field('role', 'id', ['shortname' => 'orange_cuenta'], MUST_EXIST);
        role_assign($marker, $id, \context_user::instance($id)->id, 'local_orangeportal');
        cohort_add_member((int)get_config('local_orangeportal', 'audiencecohort'), $id);
        $tx->allow_commit();
        return $id;
    }
    public static function update_user(int $id, \stdClass $data): void {
        global $DB, $CFG;
        self::require_access();
        orange_scope::user($id);
        if (!self::exclusive_account($id)) { throw new \moodle_exception('orangesharedaccount', 'local_orangeportal'); }
        if (!validate_email($data->email) || trim($data->firstname) === '' || trim($data->lastname) === '' ||
                $DB->record_exists_select('user', 'email = ? AND id <> ? AND deleted = 0', [$data->email, $id])) {
            throw new \moodle_exception('orangeinvalidaccount', 'local_orangeportal');
        }
        // The portal never changes usernames, authentication, passwords, suspension or global roles.
        require_once($CFG->dirroot . '/user/lib.php');
        user_update_user((object)['id' => $id, 'firstname' => clean_param($data->firstname, PARAM_NOTAGS),
            'lastname' => clean_param($data->lastname, PARAM_NOTAGS), 'email' => clean_param($data->email, PARAM_EMAIL)], false);
    }
    public static function set_cohorts(int $userid, array $cohortids): void {
        global $CFG;
        self::require_access();
        orange_scope::user($userid);
        $cohorts = orange_scope::cohorts();
        foreach ($cohortids as $id) { if (!isset($cohorts[$id])) { throw new \moodle_exception('outsideorange', 'local_orangeportal'); } }
        require_once($CFG->dirroot . '/cohort/lib.php');
        $tx = $GLOBALS['DB']->start_delegated_transaction();
        foreach ($cohorts as $c) {
            if (in_array($c->id, $cohortids)) { cohort_add_member($c->id, $userid); }
            else { cohort_remove_member($c->id, $userid); }
        }
        $tx->allow_commit();
    }
    public static function remove_access(int $userid): void {
        global $CFG;
        self::require_access();
        orange_scope::user($userid);
        require_once($CFG->dirroot . '/cohort/lib.php');
        foreach (orange_scope::cohorts() as $c) { cohort_remove_member($c->id, $userid); }
        cohort_remove_member((int)get_config('local_orangeportal', 'audiencecohort'), $userid);
    }
    public static function save_cohort(int $id, string $name): int {
        global $DB, $CFG;
        self::require_access();
        require_once($CFG->dirroot . '/cohort/lib.php');
        $name = trim(clean_param($name, PARAM_NOTAGS));
        if ($name === '' || \core_text::strlen($name) > 254) { throw new \moodle_exception('orangeinvalidcohort', 'local_orangeportal'); }
        if ($id && !isset(orange_scope::cohorts()[$id])) { throw new \moodle_exception('outsideorange', 'local_orangeportal'); }
        if ($id) { $cohort = orange_scope::cohorts()[$id]; $cohort->name = $name; cohort_update_cohort($cohort); return $id; }
        return cohort_add_cohort((object)['name' => $name, 'contextid' => \context_system::instance()->id,
            'idnumber' => 'orange_' . bin2hex(random_bytes(12)), 'component' => 'local_orangeportal']);
    }
    public static function delete_cohort(int $id): void {
        global $DB, $CFG;
        self::require_access();
        if (!isset(orange_scope::cohorts()[$id])) { throw new \moodle_exception('outsideorange', 'local_orangeportal'); }
        if ($DB->record_exists('cohort_members', ['cohortid' => $id]) ||
                $DB->record_exists('enrol', ['enrol' => 'cohort', 'customint1' => $id]) ||
                $DB->record_exists('local_orange_audience', ['targettype' => 'cohort', 'targetid' => $id])) {
            throw new \moodle_exception('orangecohortinuse', 'local_orangeportal');
        }
        require_once($CFG->dirroot . '/cohort/lib.php');
        cohort_delete_cohort(orange_scope::cohorts()[$id]);
    }
    public static function enrol_cohort(int $cohortid, int $courseid): void {
        global $DB, $CFG;
        self::require_access();
        if (!isset(orange_scope::cohorts()[$cohortid]) || !isset(orange_scope::course_ids()[$courseid])) {
            throw new \moodle_exception('outsideorange', 'local_orangeportal');
        }
        if (!enrol_is_enabled('cohort')) { throw new \moodle_exception('enrolcohortdisabled', 'enrol_cohort'); }
        if (!$DB->record_exists('enrol', ['enrol' => 'cohort', 'customint1' => $cohortid, 'courseid' => $courseid])) {
            $student = get_archetype_roles('student');
            enrol_get_plugin('cohort')->add_instance(get_course($courseid), ['customint1' => $cohortid,
                'roleid' => reset($student)->id, 'status' => ENROL_INSTANCE_ENABLED]);
        }
        require_once($CFG->dirroot . '/enrol/cohort/locallib.php');
        enrol_cohort_sync(new \null_progress_trace(), $courseid);
    }
}
