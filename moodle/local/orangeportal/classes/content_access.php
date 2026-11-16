<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** Project boundary and optional collective selection, without granting training enrolments. */
class content_access {
    public static function section(int $courseid): ?string {
        global $DB;
        $category = $DB->get_record_sql('SELECT cat.* FROM {course_categories} cat JOIN {course} c ON c.category=cat.id WHERE c.id=?', [$courseid]);
        if (!$category) { return null; }
        $path = array_map('intval', explode('/', $category->path));
        foreach (['library', 'courses'] as $kind) {
            $root = (int)get_config('local_orangeportal', $kind === 'library' ? 'librarycategory' : 'coursescategory');
            if ($root && in_array($root, $path, true)) { return $kind; }
        }
        return null;
    }
    public static function policy(string $kind, int $id): ?\stdClass {
        global $DB;
        if ((int)get_config('local_orangeportal', 'version') < 2026111000) { return null; }
        return $DB->get_record('local_orange_access', ['kind' => $kind, 'itemid' => $id]) ?: null;
    }
    public static function selected(string $kind, int $id, string $type = 'cohorts'): array {
        $policy = self::policy($kind, $id);
        return $policy ? json_decode($policy->{['users' => 'userids', 'groups' => 'groupids'][$type] ?? $type} ?? '[]', true, 512, JSON_THROW_ON_ERROR) : [];
    }
    public static function validate(array $ids, string $type = 'cohorts'): array {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $options = self::choices($type);
        foreach ($ids as $id) { if (!isset($options[$id])) { throw new \moodle_exception('outsideorange', 'local_orangeportal'); } }
        sort($ids);
        return $ids;
    }
    public static function choices(string $type): array {
        switch ($type) {
            case 'users':
                $options = [];
                foreach (orange_scope::users() as $user) {
                    if (!$user->suspended && !isguestuser($user)) { $options[$user->id] = fullname($user) . ' (' . $user->username . ')'; }
                }
                return $options;
            case 'groups':
                return array_map(fn($g) => $g->coursename . ' / ' . $g->name, orange_scope::groups());
            case 'cohorts': return array_map(fn($c) => $c->name, orange_scope::cohorts());
            default: throw new \coding_exception('Invalid recipient type');
        }
    }
    public static function options(): array { return self::choices('cohorts'); }
    public static function validation(array $data): array {
        $errors = [];
        foreach (['users', 'cohorts', 'groups'] as $type) {
            try { self::validate((array)($data['orange' . $type] ?? []), $type); }
            catch (\moodle_exception $e) { $errors['orange' . $type] = get_string('outsideorange', 'local_orangeportal'); }
        }
        return $errors;
    }
    public static function field(\MoodleQuickForm $form, array $selected = [], array $users = [], array $groups = []): void {
        if ($form->elementExists('orangecohorts')) { return; }
        $form->addElement('hidden', 'orangeaudiencepresent', 1);
        $form->setType('orangeaudiencepresent', PARAM_BOOL);
        $form->addElement('header', 'orangeaudiencehdr', get_string('recipients', 'local_orangeportal'));
        $form->setExpanded('orangeaudiencehdr', true);
        foreach (['users' => $users, 'cohorts' => $selected, 'groups' => $groups] as $type => $ids) {
            $options = self::choices($type);
            foreach ($ids as $id) {
                if (!isset($options[$id])) { $options[$id] = get_string('contentrecipientunavailable', 'local_orangeportal', $id); }
            }
            $form->addElement('autocomplete', 'orange' . $type, get_string('contentrecipients' . $type, 'local_orangeportal'),
                $options, ['multiple' => true]);
            $form->setType('orange' . $type, PARAM_INT);
            $form->setDefault('orange' . $type, $ids);
        }
        $form->addElement('static', 'orangeaudiencehint', '', get_string('contentaudiencehint', 'local_orangeportal'));
    }
    /** Union of recipient types; a nonempty selection never falls back to all Orange. */
    private static function matches(string $kind, int $id, int $userid): bool {
        global $DB;
        $users = self::selected($kind, $id, 'users');
        $cohorts = self::selected($kind, $id);
        $groups = self::selected($kind, $id, 'groups');
        if (!$users && !$cohorts && !$groups) { return true; }
        if (in_array($userid, $users, true)) { return true; }
        foreach ($cohorts as $cohort) {
            if ($DB->record_exists('cohort_members', ['cohortid' => $cohort, 'userid' => $userid])) { return true; }
        }
        foreach ($groups as $group) {
            if (self::target_member('group', $group, $userid)) { return true; }
        }
        return false;
    }
    public static function author(int $courseid, ?int $userid = null): bool {
        return has_capability('moodle/course:manageactivities', \context_course::instance($courseid), $userid);
    }
    /** Audience eligibility is separate from publication: staff can enrol recipients while preparing a draft. */
    public static function recipient(int $courseid, int $userid): bool {
        return self::section($courseid) === 'courses' && experience::member($userid) &&
            self::matches('course', $courseid, $userid);
    }
    public static function course(int $courseid, ?int $userid = null): bool {
        global $USER, $DB;
        $userid ??= (int)$USER->id;
        $section = self::section($courseid);
        if (!$section && !native_content::mapping($courseid)) { return true; }
        if (is_siteadmin($userid)) { return true; }
        if (!experience::member($userid)) { return false; }
        $author = self::author($courseid, $userid);
        if ($section === 'courses' && !$author && !$DB->get_field('course', 'visible', ['id' => $courseid])) { return false; }
        return $author || self::matches('course', $courseid, $userid);
    }
    public static function module(int $cmid, ?int $userid = null): bool {
        global $DB, $USER;
        $userid ??= (int)$USER->id;
        $cm = $DB->get_record('course_modules', ['id' => $cmid]);
        if (!$cm || !self::course((int)$cm->course, $userid)) { return false; }
        if (is_siteadmin($userid) || self::author((int)$cm->course, $userid)) { return true; }
        return self::matches('module', $cmid, $userid);
    }
    public static function guard(int $courseid, int $cmid = 0): void {
        if (!self::course($courseid) || ($cmid && !self::module($cmid))) {
            throw new \moodle_exception('nativecontentdenied', 'local_orangeportal');
        }
    }
    /** Owned native groups make the collective condition work in Moodle's own lists and APIs too. */
    private static function group(int $courseid, int $cohortid): int {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $key = 'orange-audience-' . $cohortid;
        $group = $DB->get_record('groups', ['courseid' => $courseid, 'idnumber' => $key]);
        if (!$group) {
            $id = groups_create_group((object)['courseid' => $courseid, 'idnumber' => $key,
                'name' => get_string('contentnativegroup', 'local_orangeportal', $DB->get_field('cohort', 'name', ['id' => $cohortid])),
                'description' => '', 'descriptionformat' => FORMAT_HTML, 'visibility' => GROUPS_VISIBILITY_MEMBERS]);
            $group = $DB->get_record('groups', ['id' => $id], '*', MUST_EXIST);
        }
        foreach ($DB->get_records('cohort_members', ['cohortid' => $cohortid]) as $member) {
            if (experience::member((int)$member->userid)) { groups_add_member($group, $member->userid, 'local_orangeportal', $cohortid); }
        }
        return (int)$group->id;
    }
    private static function target_member(string $type, int $target, int $userid): bool {
        global $DB;
        if (!experience::member($userid)) { return false; }
        if ($type === 'user') { return $target === $userid; }
        $courseid = (int)$DB->get_field('groups', 'courseid', ['id' => $target]);
        return isset(orange_scope::course_ids()[$courseid]) &&
            $DB->record_exists('groups_members', ['groupid' => $target, 'userid' => $userid]);
    }
    private static function target_group(int $courseid, string $type, int $target): int {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $key = 'orange-audience-' . $type . '-' . $target;
        $group = $DB->get_record('groups', ['courseid' => $courseid, 'idnumber' => $key]);
        if (!$group) {
            $id = groups_create_group((object)['courseid' => $courseid, 'idnumber' => $key,
                'name' => 'Orange · ' . $type . ' ' . $target, 'description' => '',
                'descriptionformat' => FORMAT_HTML, 'visibility' => GROUPS_VISIBILITY_MEMBERS]);
            $group = $DB->get_record('groups', ['id' => $id], '*', MUST_EXIST);
        }
        foreach (orange_scope::users() as $user) { self::sync_target($group, $type, $target, (int)$user->id); }
        return (int)$group->id;
    }
    private static function sync_target(\stdClass $group, string $type, int $target, int $userid): void {
        if (self::target_member($type, $target, $userid)) { groups_add_member($group, $userid, 'local_orangeportal', $target); }
        else { groups_remove_member($group, $userid); }
    }
    /** Ignore mirror events to prevent loops; source memberships remain authoritative. */
    public static function group_changed(\core\event\base $event): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $snapshot = $event->get_record_snapshot('groups', $event->objectid);
        if (str_starts_with($snapshot->idnumber ?? '', 'orange-audience-')) { return; }
        $userid = (int)$event->relateduserid;
        foreach ($DB->get_records('groups', ['idnumber' => 'orange-audience-group-' . $event->objectid]) as $group) {
            if ($userid) { self::sync_target($group, 'group', (int)$event->objectid, $userid); }
            else {
                foreach ($DB->get_records('groups_members', ['groupid' => $group->id]) as $member) {
                    groups_remove_member($group, $member->userid);
                }
            }
        }
    }
    private static function strip(?array $tree, ?array $rule): ?array {
        if (!$tree || !$rule) { return $tree; }
        $check = $tree; $expected = $rule;
        // Native availability JS drops root-only display flags from nested trees on normal edits.
        unset($check['show'], $check['showc'], $expected['show'], $expected['showc']);
        if (($check['op'] ?? '') === '|' && ($expected['op'] ?? '') === '|') {
            usort($check['c'], fn($a, $b) => json_encode($a) <=> json_encode($b));
            usort($expected['c'], fn($a, $b) => json_encode($a) <=> json_encode($b));
        }
        if ($check == $expected) { return null; }
        if (!isset($tree['c'])) { return $tree; }
        $children = []; $show = [];
        foreach ($tree['c'] as $i => $child) {
            $new = self::strip($child, $rule);
            if ($new !== null) { $children[] = $new; $show[] = $tree['showc'][$i] ?? true; }
        }
        if (!$children) { return null; }
        $tree['c'] = $children;
        if (isset($tree['showc'])) { $tree['showc'] = $show; }
        return $tree;
    }
    public static function save(string $kind, int $id, array $ids, array $users = [], array $groups = []): void {
        self::write($kind, $id, $ids, false, $users, $groups);
    }
    private static function write(string $kind, int $id, array $ids, bool $creating, array $users = [], array $groups = []): void {
        global $DB, $CFG;
        if (!in_array($kind, ['course', 'module'], true)) { throw new \coding_exception('Invalid audience type'); }
        $courseid = $kind === 'course' ? $id : (int)$DB->get_field('course_modules', 'course', ['id' => $id], MUST_EXIST);
        if (!self::section($courseid)) { throw new \moodle_exception('outsideorange', 'local_orangeportal'); }
        if ($creating && $kind === 'course') {
            // Moodle assigns the creator's editing role only after create_course() returns.
            require_capability('moodle/course:create', \context_coursecat::instance(get_course($courseid)->category));
        } else { require_capability('moodle/course:manageactivities', \context_course::instance($courseid)); }
        $ids = self::validate($ids);
        $users = self::validate($users, 'users');
        $groups = self::validate($groups, 'groups');
        $restricted = $ids || $users || $groups;
        $lock = \core\lock\lock_config::get_lock_factory('local_orangeportal')->get_lock('access_' . $courseid, 10);
        if (!$lock) { throw new \moodle_exception('locktimeout'); }
        try {
            $tx = $DB->start_delegated_transaction();
            $old = self::policy($kind, $id);
            $rule = null;
            if ($kind === 'module') {
                if ($restricted && empty($CFG->enableavailability)) { throw new \moodle_exception('contentavailabilityrequired', 'local_orangeportal'); }
                $cm = $DB->get_record('course_modules', ['id' => $id], '*', MUST_EXIST);
                $tree = self::strip($cm->availability ? json_decode($cm->availability, true) : null,
                    $old && $old->rule ? json_decode($old->rule, true) : null);
                if ($restricted) {
                    $rule = ['op' => '|', 'c' => array_map(fn($cohort) => ['type' => 'group', 'id' => self::group($courseid, $cohort)], $ids)];
                    foreach (['user' => $users, 'group' => $groups] as $type => $targets) {
                        foreach ($targets as $target) {
                            $rule['c'][] = ['type' => 'group', 'id' => self::target_group($courseid, $type, $target)];
                        }
                    }
                    if ($tree && $tree['op'] === '&') {
                        $tree['c'][] = $rule; $tree['showc'][] = false;
                    } else {
                        $tree = $tree ? ['op' => '&', 'c' => [$tree, $rule], 'showc' => [$tree['show'] ?? true, false]] :
                            ['op' => '&', 'c' => [$rule], 'showc' => [false]];
                    }
                }
                $DB->set_field('course_modules', 'availability', $tree ? json_encode($tree) : null, ['id' => $id]);
            }
            $record = (object)['kind' => $kind, 'itemid' => $id, 'cohorts' => json_encode($ids), 'userids' => json_encode($users), 'groupids' => json_encode($groups), 'rule' => $rule ? json_encode($rule) : ''];
            if ($old) { $record->id = $old->id; $DB->update_record('local_orange_access', $record); }
            else { $DB->insert_record('local_orange_access', $record); }
            $tx->allow_commit();
            if ($kind === 'module') { rebuild_course_cache($courseid, true); }
        } catch (\Throwable $e) {
            if (isset($tx)) { $tx->rollback($e); }
            throw $e;
        } finally { $lock->release(); }
    }
    /** Cohort membership is the authority; the native group is maintained for availability only. */
    public static function membership(\core\event\base $event): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $userid = (int)$event->relateduserid;
        self::sync_catalogue_user($userid);
        self::sync_enrol_user($userid);
        foreach ($DB->get_records_select('groups', 'idnumber LIKE ?', ['orange-audience-%']) as $group) {
            if (preg_match('/^orange-audience-(user|group)-(\d+)$/', $group->idnumber, $target)) {
                self::sync_target($group, $target[1], (int)$target[2], $userid);
                continue;
            }
            $cohortid = (int)substr($group->idnumber, strlen('orange-audience-'));
            $allowed = experience::member($userid) && $DB->record_exists('cohort_members', ['cohortid' => $cohortid, 'userid' => $userid]);
            if ($allowed) { groups_add_member($group, $userid, 'local_orangeportal', $cohortid); }
            else { groups_remove_member($group, $userid); }
        }
    }
    public static function cohort_deleted(\core\event\cohort_deleted $event): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        foreach ($DB->get_records('groups', ['idnumber' => 'orange-audience-' . $event->objectid]) as $group) {
            foreach ($DB->get_records('groups_members', ['groupid' => $group->id]) as $member) {
                groups_remove_member($group, $member->userid);
            }
        }
        if ((int)$event->objectid === (int)get_config('local_orangeportal', 'audiencecohort')) { self::catalogue(); }
    }
    /** Native category permissions also hide Orange catalogues from ordinary campus accounts. */
    public static function catalogue(): void {
        global $DB, $CFG;
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'orange_catalogo']);
        if (!$roleid) {
            $roleid = create_role(get_string('contentcataloguerole', 'local_orangeportal'), 'orange_catalogo',
                get_string('contentcataloguerole_desc', 'local_orangeportal'));
            set_role_contextlevels($roleid, [CONTEXT_COURSECAT]);
        }
        assign_capability('moodle/category:viewcourselist', CAP_ALLOW, $roleid, \context_system::instance()->id);
        $saved = json_decode(get_config('local_orangeportal', 'catalogueoverrides') ?: '{}', true);
        $active = [];
        foreach (['librarycategory', 'coursescategory'] as $setting) {
            $root = (int)get_config('local_orangeportal', $setting);
            if (!$root || !$DB->record_exists('course_categories', ['id' => $root])) { continue; }
            $context = \context_coursecat::instance($root);
            foreach (array_unique([(int)$CFG->defaultuserroleid, (int)$CFG->guestroleid]) as $defaultrole) {
                if (!$defaultrole) { continue; }
                $existing = $DB->get_field('role_capabilities', 'permission', ['roleid' => $defaultrole,
                    'contextid' => $context->id, 'capability' => 'moodle/category:viewcourselist']);
                if ((int)$existing !== CAP_PROHIBIT) {
                    $key = $context->id . ':' . $defaultrole;
                    $active[$key] = true;
                    $saved[$key] ??= ['contextid' => $context->id, 'roleid' => $defaultrole, 'previous' => (int)$existing];
                    assign_capability('moodle/category:viewcourselist', CAP_PREVENT, $defaultrole, $context->id);
                }
            }
        }
        foreach ($saved as $key => $override) {
            if (isset($active[$key])) { continue; }
            $current = $DB->get_field('role_capabilities', 'permission', ['contextid' => $override['contextid'],
                'roleid' => $override['roleid'], 'capability' => 'moodle/category:viewcourselist']);
            if ((int)$current === CAP_PREVENT) {
                if ($override['previous']) {
                    assign_capability('moodle/category:viewcourselist', $override['previous'], $override['roleid'], $override['contextid']);
                } else { unassign_capability('moodle/category:viewcourselist', $override['roleid'], $override['contextid']); }
            }
            unset($saved[$key]);
        }
        set_config('catalogueoverrides', json_encode($saved), 'local_orangeportal');
        foreach (orange_scope::users() as $user) { self::sync_catalogue_user((int)$user->id); self::sync_enrol_user((int)$user->id); }
        foreach ($DB->get_records('role_assignments', ['roleid' => $roleid, 'component' => 'local_orangeportal']) as $assignment) {
            if (!experience::member((int)$assignment->userid)) { self::sync_catalogue_user((int)$assignment->userid); }
        }
        $enrolrole = $DB->get_field('role', 'id', ['shortname' => 'orange_matriculador']);
        if ($enrolrole) {
            foreach ($DB->get_records('role_assignments', ['roleid' => $enrolrole, 'component' => 'local_orangeportal']) as $assignment) {
                self::sync_enrol_user((int)$assignment->userid);
            }
        }
    }
    private static function sync_catalogue_user(int $userid): void {
        global $DB;
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'orange_catalogo']);
        if (!$roleid || !$userid) { return; }
        $contexts = [];
        if (experience::member($userid)) {
            foreach (['librarycategory', 'coursescategory'] as $setting) {
                $root = (int)get_config('local_orangeportal', $setting);
                if ($root && $DB->record_exists('course_categories', ['id' => $root])) {
                    $context = \context_coursecat::instance($root);
                    $contexts[$context->id] = $root;
                    role_assign($roleid, $userid, $context->id, 'local_orangeportal', $root);
                }
            }
        }
        foreach ($DB->get_records('role_assignments', ['roleid' => $roleid, 'userid' => $userid, 'component' => 'local_orangeportal']) as $assignment) {
            if (!isset($contexts[$assignment->contextid])) {
                role_unassign($roleid, $userid, $assignment->contextid, 'local_orangeportal', $assignment->itemid);
            }
        }
    }
    /** No default Moodle archetype receives enrol permission; the assignment is Orange-category-only. */
    public static function install_enrol_role(): void {
        global $DB;
        $id = $DB->get_field('role', 'id', ['shortname' => 'orange_matriculador']);
        if (!$id) {
            $id = create_role(get_string('contentenrolrole', 'local_orangeportal'), 'orange_matriculador',
                get_string('contentenrolrole_desc', 'local_orangeportal'));
            set_role_contextlevels($id, [CONTEXT_COURSECAT]);
        }
        assign_capability('local/orangeportal:enrolusers', CAP_ALLOW, $id, \context_system::instance()->id);
        foreach (orange_scope::users() as $user) { self::sync_enrol_user((int)$user->id); }
    }
    public static function role_changed(\core\event\base $event): void {
        global $DB;
        if ($event->objectid == $DB->get_field('role', 'id', ['shortname' => 'orange_matriculador'])) { return; }
        self::sync_enrol_user((int)$event->relateduserid);
    }
    public static function can_enrol(int $courseid): bool {
        global $USER;
        if (!experience::member() || self::section($courseid) !== 'courses') { return false; }
        // Also revoke stale automatic assignments after a role's capabilities were edited in place.
        self::sync_enrol_user((int)$USER->id);
        return has_capability('local/orangeportal:enrolusers', \context_course::instance($courseid));
    }
    private static function sync_enrol_user(int $userid): void {
        global $DB;
        $role = $DB->get_field('role', 'id', ['shortname' => 'orange_matriculador']);
        if (!$role || !$userid) { return; }
        $root = (int)get_config('local_orangeportal', 'coursescategory');
        $context = $root && $DB->record_exists('course_categories', ['id' => $root]) ? \context_coursecat::instance($root) : null;
        $eligible = $context && experience::member($userid) &&
            (has_capability('local/orangeportal:manage', \context_system::instance(), $userid) ||
                has_capability('local/orangeportal:administer', \context_system::instance(), $userid)) &&
            has_capability('moodle/course:create', $context, $userid);
        foreach ($DB->get_records('role_assignments', ['roleid' => $role, 'userid' => $userid, 'component' => 'local_orangeportal']) as $assignment) {
            if (!$eligible || $assignment->contextid != $context->id) {
                role_unassign($role, $userid, $assignment->contextid, 'local_orangeportal', $assignment->itemid);
            }
        }
        if ($eligible) { role_assign($role, $userid, $context->id, 'local_orangeportal', $root); }
    }
    public static function deleted(\core\event\base $event): void {
        global $DB;
        $kind = $event instanceof \core\event\course_deleted ? 'course' : 'module';
        $DB->delete_records('local_orange_access', ['kind' => $kind, 'itemid' => $event->objectid]);
    }
    /** New training courses never enable guest or self-enrolment by default. */
    public static function created(\core_course\hook\after_form_submission $hook): void {
        global $SESSION;
        $data = $hook->get_data();
        if (self::section((int)$data->id) !== 'courses') { return; }
        if ($hook->isnewcourse) {
            foreach (enrol_get_instances($data->id, false) as $instance) {
                if (in_array($instance->enrol, ['guest', 'self'], true)) { enrol_get_plugin($instance->enrol)->delete_instance($instance); }
            }
        }
        if (!$hook->isnewcourse) { training_publication::submitted($data, false); }
        if (!empty($data->orangeaudiencepresent)) { self::write('course', (int)$data->id, (array)($data->orangecohorts ?? []), $hook->isnewcourse,
            (array)($data->orangeusers ?? []), (array)($data->orangegroups ?? [])); }
        if ($hook->isnewcourse) { training_publication::submitted($data, true); }
        if ($hook->isnewcourse && course_creation::section() === 'courses') {
            $SESSION->orangecreatedcourses[$data->id] = time();
            $data->saveanddisplay = 1;
        }
    }
}
