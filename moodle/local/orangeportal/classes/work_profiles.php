<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** Fixed work profiles delegated to Orange administrators, using native role assignments. */
class work_profiles {
    // Separate these assignments from catalogue, content and enrolment observers.
    private const ITEMID = 20261111;
    private const PREFERENCE = 'local_orangeportal_workprofile';

    public static function options(): array {
        $options = [];
        foreach (['agent', 'trainer', 'readonly', 'coordinator', 'quality'] as $key) {
            $options[$key] = get_string('workprofile_' . $key, 'local_orangeportal');
        }
        return $options;
    }

    private static function definitions(): array {
        return [
            'orange_publicador' => ['Formador Orange', [CONTEXT_SYSTEM], [
                'local/orangeportal:manage', 'local/orangeportal:changestate', 'local/orangeportal:manageoptions',
                'local/orangeportal:viewreports', 'local/orangeportal:viewallincidents']],
            'orange_seguimiento' => ['Coordinación Orange', [CONTEXT_SYSTEM], [
                'local/orangeportal:viewreports', 'local/orangeportal:viewallincidents']],
            'orange_calidad' => ['Calidad Orange', [CONTEXT_SYSTEM], [
                'local/orangeportal:viewreports', 'local/orangeportal:viewallincidents']],
            'orange_consulta_formativa' => ['Consulta formativa Orange', [CONTEXT_COURSECAT, CONTEXT_COURSE], [
                'moodle/course:view', 'moodle/course:viewparticipants', 'moodle/site:accessallgroups',
                'moodle/grade:viewall', 'gradereport/grader:view', 'gradereport/user:view', 'mod/quiz:view',
                'mod/quiz:viewreports', 'mod/feedback:view', 'mod/feedback:viewreports',
                'report/completion:view', 'report/progress:view']],
            'orange_gestorcategorias' => ['Gestión de categorías Orange', [CONTEXT_COURSECAT], ['moodle/category:manage']],
            'orange_creadorcontenidos' => ['Creación de contenidos Orange', [CONTEXT_COURSECAT], ['moodle/course:create']],
        ];
    }

    /** Installation creates missing definitions without overwriting existing campus customisations. */
    public static function install(): void {
        global $DB;
        foreach (self::definitions() as $short => [$name, $levels, $caps]) {
            if ($DB->record_exists('role', ['shortname' => $short])) { continue; }
            $id = create_role($name, $short, 'Perfil de trabajo Orange. Sin administración general del campus.');
            set_role_contextlevels($id, $levels);
            foreach ($caps as $cap) { assign_capability($cap, CAP_ALLOW, $id, \context_system::instance()->id); }
        }
    }

    public static function courses(): array {
        global $DB;
        $ids = orange_scope::course_ids();
        return $ids ? $DB->get_records_list('course', 'id', array_values($ids), 'fullname', 'id, fullname, shortname') : [];
    }

    public static function categories(): array {
        global $DB;
        $categories = [];
        foreach (['librarycategory', 'coursescategory'] as $setting) {
            $id = (int)get_config('local_orangeportal', $setting);
            if ($id && $category = $DB->get_record('course_categories', ['id' => $id])) {
                $categories[$id] = $category->name;
            }
        }
        return $categories;
    }

    public static function state(int $userid): \stdClass {
        global $DB;
        $profile = get_user_preferences(self::PREFERENCE, 'keep', $userid);
        if (!isset(self::options()[$profile])) { $profile = 'keep'; }
        $courses = [];
        $categories = [];
        foreach ($DB->get_records('role_assignments', ['userid' => $userid,
                'component' => 'local_orangeportal', 'itemid' => self::ITEMID]) as $ra) {
            $context = \context::instance_by_id($ra->contextid);
            if ($context->contextlevel === CONTEXT_COURSE) { $courses[] = (int)$context->instanceid; }
            if ($context->contextlevel === CONTEXT_COURSECAT) { $categories[] = (int)$context->instanceid; }
        }
        return (object)['workprofile' => $profile, 'profilecourses' => array_values(array_intersect($courses,
            array_keys(self::courses()))), 'profilecategories' => array_values(array_unique(array_intersect($categories,
            array_keys(self::categories()))))];
    }

    /** Only remove assignments created by this flow; never revoke other campus roles. */
    public static function clear(int $userid): void {
        global $DB;
        role_unassign_all(['userid' => $userid, 'component' => 'local_orangeportal', 'itemid' => self::ITEMID]);
        // Entering native Orange resources may have generated dependent author assignments.
        // Remove those as well, so changing a trainer to consultation really removes writing.
        $author = $DB->get_field('role', 'id', ['shortname' => 'orange_autorrecursos']);
        if ($author) {
            foreach ($DB->get_records('role_assignments', ['userid' => $userid, 'roleid' => $author,
                    'component' => 'local_orangeportal']) as $ra) {
                $map = $DB->get_record('local_orange_container', ['id' => $ra->itemid]);
                if ($map && $ra->contextid == \context_course::instance($map->courseid)->id) {
                    role_unassign($author, $userid, $ra->contextid, 'local_orangeportal', $ra->itemid);
                }
            }
        }
        unset_user_preference(self::PREFERENCE, $userid);
    }

    /** Validate custom role definitions before a delegated administrator can assign them. */
    private static function role(string $short): int {
        global $DB;
        if ($short === 'orange_autorrecursos') {
            $caps = ['moodle/course:view'];
            foreach (get_default_capabilities('editingteacher') as $cap => $permission) {
                $info = get_capability_info($cap);
                if ($permission === CAP_ALLOW && $info && $info->contextlevel >= CONTEXT_COURSE &&
                        !str_starts_with($cap, 'enrol/') && !str_starts_with($cap, 'moodle/role:')) { $caps[] = $cap; }
            }
        } else {
            $caps = self::definitions()[$short][2];
        }
        $id = $DB->get_field('role', 'id', ['shortname' => $short]);
        if (!$id) { throw new \moodle_exception('workprofilerolemissing', 'local_orangeportal', '', $short); }
        foreach ($DB->get_records('role_capabilities', ['roleid' => $id]) as $permission) {
            if ($permission->permission == CAP_ALLOW && !in_array($permission->capability, $caps, true)) {
                throw new \moodle_exception('workprofilecustomrole', 'local_orangeportal', '', $short);
            }
        }
        foreach (array_unique($caps) as $cap) {
            if (!$DB->record_exists('role_capabilities', ['roleid' => $id, 'contextid' => \context_system::instance()->id,
                    'capability' => $cap, 'permission' => CAP_ALLOW])) {
                throw new \moodle_exception('workprofilecustomrole', 'local_orangeportal', '', $short);
            }
        }
        return (int)$id;
    }

    public static function save(int $userid, string $profile, array $courseids, array $categoryids = []): void {
        global $DB;
        orange_administration::require_access();
        orange_scope::user($userid); // Also protects site and Orange administrators, including the actor.
        if ($profile === 'keep') { return; }
        if (!isset(self::options()[$profile])) { throw new \moodle_exception('workprofileinvalid', 'local_orangeportal'); }
        $courses = self::courses();
        $courseids = array_unique(array_map('intval', $courseids));
        foreach ($courseids as $id) {
            if (!isset($courses[$id])) { throw new \moodle_exception('outsideorange', 'local_orangeportal'); }
        }
        $categoryids = array_unique(array_map('intval', $categoryids));
        foreach ($categoryids as $id) {
            if (!isset(self::categories()[$id])) { throw new \moodle_exception('outsideorange', 'local_orangeportal'); }
        }
        if ($profile !== 'trainer' && $categoryids) { throw new \moodle_exception('workprofileinvalid', 'local_orangeportal'); }
        $formative = in_array($profile, ['trainer', 'readonly', 'coordinator'], true);
        if (!$formative && $courseids) { throw new \moodle_exception('workprofileinvalid', 'local_orangeportal'); }
        if (in_array($profile, ['readonly', 'coordinator'], true) && !$courseids) {
            throw new \moodle_exception('workprofileneedcourses', 'local_orangeportal');
        }
        $systemrole = match ($profile) {
            'trainer' => 'orange_publicador',
            'readonly', 'coordinator' => 'orange_seguimiento',
            'quality' => 'orange_calidad',
            default => null,
        };
        // Validate all role definitions before changing any assignment.
        $globalid = $systemrole ? self::role($systemrole) : 0;
        $courseidrole = $formative ? self::role($profile === 'trainer' ? 'orange_autorrecursos' : 'orange_consulta_formativa') : 0;
        $categoryroles = $profile === 'trainer' ? [self::role('orange_gestorcategorias'), self::role('orange_creadorcontenidos')] : [];
        $tx = $DB->start_delegated_transaction();
        try {
            self::clear($userid);
            if ($globalid) { role_assign($globalid, $userid, \context_system::instance()->id, 'local_orangeportal', self::ITEMID); }
            foreach ($courseids as $courseid) {
                role_assign($courseidrole, $userid, \context_course::instance($courseid)->id, 'local_orangeportal', self::ITEMID);
            }
            foreach ($categoryids as $category) {
                foreach ($categoryroles as $roleid) {
                    role_assign($roleid, $userid, \context_coursecat::instance($category)->id, 'local_orangeportal', self::ITEMID);
                }
            }
            if ($profile !== 'trainer') { self::require_readonly($userid, $profile, $courseids); }
            set_user_preference(self::PREFERENCE, $profile, $userid);
            $tx->allow_commit();
        } catch (\Throwable $e) {
            $tx->rollback($e);
        }
    }

    /** A consultation label must never conceal inherited/manual writing or export permissions. */
    private static function require_readonly(int $userid, string $profile, array $courseids): void {
        global $DB, $CFG;
        $forbidden = ['local/orangeportal:manage', 'local/orangeportal:manageoptions', 'local/orangeportal:changestate',
            'local/orangeportal:administer', 'local/orangeportal:exportreports', 'local/orangeportal:viewallreports',
            'local/orangeportal:enrolusers', 'moodle/course:manageactivities', 'moodle/course:update',
            'moodle/course:create', 'moodle/category:manage', 'moodle/grade:edit', 'moodle/grade:manage',
            'moodle/grade:override', 'moodle/grade:export', 'mod/quiz:grade', 'mod/quiz:regrade',
            'mod/feedback:edititems', 'enrol/manual:enrol', 'moodle/role:assign', 'moodle/role:override'];
        if (in_array($profile, ['agent', 'quality'], true)) { $forbidden[] = 'moodle/grade:viewall'; }
        if ($profile === 'agent') {
            $forbidden[] = 'local/orangeportal:viewreports';
            $forbidden[] = 'local/orangeportal:viewallincidents';
        }
        foreach ($DB->get_fieldset_select('capabilities', 'name', 'name LIKE ?', ['gradeexport/%']) as $cap) {
            $forbidden[] = $cap;
        }
        // Catch custom writing/grading roles too, while keeping ordinary participant actions
        // (submitting work, posting as oneself, updating one's profile) available.
        $personal = [];
        foreach (['student', 'user'] as $archetype) {
            foreach (get_default_capabilities($archetype) as $cap => $permission) {
                if ($permission === CAP_ALLOW) { $personal[$cap] = true; }
            }
        }
        $roles = $DB->get_fieldset_select('role_assignments', 'roleid', 'userid = ?', [$userid]);
        $roles[] = $CFG->defaultuserroleid;
        [$rolesql, $params] = $DB->get_in_or_equal(array_unique($roles));
        $writecaps = $DB->get_fieldset_sql("SELECT DISTINCT rc.capability FROM {role_capabilities} rc
            JOIN {capabilities} c ON c.name = rc.capability
            WHERE rc.roleid $rolesql AND rc.permission = 1 AND c.captype = 'write'", $params);
        foreach ($writecaps as $cap) { if (!isset($personal[$cap])) { $forbidden[] = $cap; } }
        $forbidden = array_unique($forbidden);
        $contexts = [\context_system::instance()];
        foreach (['librarycategory', 'coursescategory'] as $setting) {
            $root = (int)get_config('local_orangeportal', $setting);
            if (!$root) { continue; }
            $context = \context_coursecat::instance($root);
            $contexts[$context->id] = $context;
            foreach ($DB->get_records_select('context', 'path LIKE ?', [$context->path . '/%']) as $child) {
                $contexts[$child->id] = \context::instance_by_id($child->id);
            }
        }
        foreach ($contexts as $context) {
            $caps = $forbidden;
            $course = $context->get_course_context(false);
            if (!$course || !in_array((int)$course->instanceid, $courseids, true)) { $caps[] = 'moodle/grade:viewall'; }
            foreach ($caps as $cap) {
                if (get_capability_info($cap) && has_capability($cap, $context, $userid)) {
                    throw new \moodle_exception('workprofileconflict', 'local_orangeportal', '', $cap);
                }
            }
        }
    }
}
