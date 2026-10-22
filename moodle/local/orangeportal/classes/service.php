<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();
/** Shared access control for views, counters and mutations. */
class service {
    public static function manager(): bool {
        return has_capability('local/orangeportal:manage', \context_system::instance());
    }
    public static function assigned(int $noticeid, int $userid): bool {
        global $DB;
        return $DB->record_exists_sql("SELECT 1 FROM {local_orange_audience} a
            WHERE a.noticeid = :notice AND (
              (a.targettype = 'user' AND a.targetid = :u1) OR
              (a.targettype = 'cohort' AND EXISTS (SELECT 1 FROM {cohort_members} m
                  WHERE m.cohortid = a.targetid AND m.userid = :u2)) OR
              (a.targettype = 'group' AND EXISTS (SELECT 1 FROM {groups_members} g
                  JOIN {groups} gr ON gr.id = g.groupid
                  JOIN {enrol} e ON e.courseid = gr.courseid
                  JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = g.userid
                  WHERE g.groupid = a.targetid AND g.userid = :u3 AND ue.status = 0 AND e.status = 0
                    AND (ue.timestart = 0 OR ue.timestart <= :t1)
                    AND (ue.timeend = 0 OR ue.timeend > :t2))))",
            ['notice' => $noticeid, 'u1' => $userid, 'u2' => $userid, 'u3' => $userid,
                't1' => time(), 't2' => time()]);
    }
    public static function can_view(\stdClass $notice, ?int $userid = null, bool $allowmanager = true): bool {
        global $USER;
        $userid = $userid ?? (int)$USER->id;
        if (!experience::member($userid)) { return false; }
        if (!empty($notice->deleted)) { return false; }
        if ($allowmanager && $userid == $USER->id && (self::manager() ||
                ($notice->kind === 'incidents' && incident_workflow::all()))) {
            return true;
        }
        if ($notice->kind === 'incidents' && notice_options::is_closed($notice->state) && !incident_workflow::all($userid)) {
            return false;
        }
        return $notice->published && self::assigned((int)$notice->id, $userid);
    }
    public static function visible(string $kind): array {
        global $DB;
        return array_filter($DB->get_records('local_orange_notice', ['kind' => $kind], 'timecreated DESC, id DESC'),
            fn($n) => self::can_view($n));
    }
    public static function require_view(\stdClass $notice): void {
        if (!self::can_view($notice)) {
            throw new \required_capability_exception(\context_system::instance(), 'local/orangeportal:manage',
                'nopermissions', '');
        }
    }
    public static function receipt(\stdClass $notice, string $stage): ?\stdClass {
        global $DB, $USER;
        self::require_view($notice);
        if ($notice->kind === 'incidents' && $stage === 'confirmed') {
            throw new \moodle_exception('incidentnoconfirmation', 'local_orangeportal');
        }
        if (!in_array($stage, ['received', 'readtime', 'confirmed'])) {
            throw new \coding_exception('Invalid receipt stage');
        }
        // Managers can record their own view, but delivery and confirmation require assignment.
        if ($stage !== 'readtime' && !self::can_view($notice, (int)$USER->id, false)) {
            return null;
        }
        $factory = \core\lock\lock_config::get_lock_factory('local_orangeportal');
        $lock = $factory->get_lock('notice_' . $notice->id, 10);
        if (!$lock) {
            throw new \moodle_exception('locktimeout');
        }
        try {
            $current = $DB->get_record('local_orange_notice', ['id' => $notice->id], '*', MUST_EXIST);
            self::require_view($current);
            if ($current->revision != $notice->revision) {
                throw new \invalid_parameter_exception('El contenido ha cambiado. Vuelve a abrir el aviso antes de confirmar.');
            }
            $isrecipient = self::can_view($current, (int)$USER->id, false);
            if ($stage !== 'readtime' && !$isrecipient) {
                return null;
            }
            $r = self::get_receipt($notice, (int)$USER->id);
            if (!$r) {
                $r = (object)['noticeid' => $notice->id, 'userid' => $USER->id, 'revision' => $notice->revision,
                    'received' => $isrecipient ? time() : 0, 'readtime' => 0, 'confirmed' => 0];
                $r->id = $DB->insert_record('local_orange_receipt', $r);
            }
            $changed = false;
            if ($isrecipient && !$r->received) {
                $r->received = time();
                $changed = true;
            }
            if (!$r->$stage) {
                $r->$stage = time();
                $changed = true;
                if ($stage == 'confirmed' && !$r->readtime) {
                    $r->readtime = time();
                }
            }
            if ($changed) {
                $DB->update_record('local_orange_receipt', $r);
            }
            return $r;
        } finally {
            $lock->release();
        }
    }
    public static function get_receipt(\stdClass $notice, int $userid): ?\stdClass {
        global $DB;
        return $DB->get_record('local_orange_receipt', ['noticeid' => $notice->id, 'userid' => $userid,
            'revision' => $notice->revision]) ?: null;
    }
    public static function change_state(\stdClass $notice, string $state): void {
        notice_options::with_lock(fn() => self::change_state_serialized($notice, $state));
    }
    private static function change_state_serialized(\stdClass $notice, string $state): void {
        global $DB, $USER;
        self::require_view($notice);
        require_capability('local/orangeportal:changestate', \context_system::instance());
        if ($notice->kind !== 'incidents') {
            throw new \invalid_parameter_exception('Invalid incident state');
        }
        $factory = \core\lock\lock_config::get_lock_factory('local_orangeportal');
        $lock = $factory->get_lock('notice_' . $notice->id, 10);
        if (!$lock) { throw new \moodle_exception('locktimeout'); }
        try {
            $current = $DB->get_record('local_orange_notice', ['id' => $notice->id], '*', MUST_EXIST);
            self::require_view($current);
            notice_options::validate_selection('states', $state, $current->state);
            $transaction = $DB->start_delegated_transaction();
            if ($current->state != $state) {
                $closure = (object)['kind' => 'incidents', 'state' => $state];
                incident_workflow::change_metadata($closure, $current);
                $DB->insert_record('local_orange_state_log', (object)['noticeid' => $notice->id,
                    'userid' => $USER->id, 'oldstate' => $current->state, 'newstate' => $state,
                    'timecreated' => time()]);
                $current->state = $state;
                $current->timemodified = time();
                $current->timeclosed = $closure->timeclosed;
                $current->closedby = $closure->closedby;
                $DB->update_record('local_orange_notice', $current);
            }
            $transaction->allow_commit();
        } finally { $lock->release(); }
    }
    public static function states(bool $activeonly = false, ?string $current = null): array {
        return notice_options::choices('states', $activeonly, $current);
    }
    public static function impacts(bool $activeonly = false, ?string $current = null): array {
        return notice_options::choices('impacts', $activeonly, $current);
    }
    public static function category_descendants(int $id, array $categories): array {
        $ids = [$id];
        foreach ($categories as $c) {
            if ((int)$c->parentid === $id) {
                $ids = array_merge($ids, self::category_descendants((int)$c->id, $categories));
            }
        }
        return $ids;
    }
    public static function validate_category(string $kind, int $categoryid): void {
        global $DB;
        if (!$DB->record_exists('local_orange_category', ['id' => $categoryid, 'kind' => $kind])) {
            throw new \invalid_parameter_exception('Category does not belong to this section');
        }
    }
    /** Empty categories only: never delete publications or subcategories implicitly. */
    public static function category_delete_blocker(string $kind, int $categoryid): string {
        global $DB;
        require_capability('local/orangeportal:manage', \context_system::instance());
        if (!in_array($kind, ['incidents', 'notifications'])) {
            throw new \invalid_parameter_exception('Invalid section');
        }
        self::validate_category($kind, $categoryid);
        if ($DB->record_exists('local_orange_category', ['parentid' => $categoryid])) {
            return 'categoryhassubcategories';
        }
        if ($DB->record_exists('local_orange_notice', ['categoryid' => $categoryid])) {
            return 'categoryhaspublications';
        }
        return '';
    }
    public static function delete_category(string $kind, int $categoryid): void {
        global $DB;
        $blocker = self::category_delete_blocker($kind, $categoryid);
        if ($blocker) { throw new \moodle_exception($blocker, 'local_orangeportal'); }
        $DB->delete_records('local_orange_category', ['id' => $categoryid, 'kind' => $kind]);
    }
    public static function save_notice(\stdClass $data, array $targets): int {
        return notice_options::with_lock(fn() => self::save_notice_serialized($data, $targets));
    }
    private static function save_notice_serialized(\stdClass $data, array $targets): int {
        $factory = \core\lock\lock_config::get_lock_factory('local_orangeportal');
        $lock = $factory->get_lock('notice_' . (int)($data->id ?? 0), 10);
        if (!$lock) { throw new \moodle_exception('locktimeout'); }
        try { return self::save_notice_locked($data, $targets); } finally { $lock->release(); }
    }
    private static function save_notice_locked(\stdClass $data, array $targets): int {
        global $DB, $USER;
        require_capability('local/orangeportal:manage', \context_system::instance());
        if (!in_array($data->kind, ['incidents', 'notifications'])) {
            throw new \invalid_parameter_exception('Invalid section');
        }
        self::validate_category($data->kind, (int)$data->categoryid);
        foreach ($targets as $type => $ids) {
            $table = ['user' => 'user', 'cohort' => 'cohort', 'group' => 'groups'][$type] ?? null;
            if (!$table) { throw new \invalid_parameter_exception('Invalid audience'); }
            foreach ($ids as $id) {
                if (!orange_scope::target($type, (int)$id)) { throw new \moodle_exception('outsideorange', 'local_orangeportal'); }
                $record = $DB->get_record($table, ['id' => $id], '*', MUST_EXIST);
                if ($type == 'user' && ($record->deleted || $record->suspended || isguestuser($record))) {
                    throw new \invalid_parameter_exception('Inactive recipient');
                }
            }
        }
        if ($data->published && !array_filter($targets)) {
            throw new \invalid_parameter_exception('Choose at least one recipient');
        }
        $existing = !empty($data->id) ? $DB->get_record('local_orange_notice',
            ['id' => $data->id, 'kind' => $data->kind], '*', MUST_EXIST) : null;
        if ($existing && !empty($existing->deleted)) { throw new \invalid_parameter_exception('Deleted publication'); }
        incident_workflow::change_metadata($data, $existing);
        notice_options::validate_selection('states', $data->state, $existing->state ?? null);
        notice_options::validate_selection('impacts', $data->impact, $existing->impact ?? null);
        $tx = $DB->start_delegated_transaction();
        $data->timemodified = time();
        if (empty($data->id)) {
            $data->revision = 1;
            $data->authorid = $USER->id;
            $data->timecreated = time();
            $data->id = $DB->insert_record('local_orange_notice', $data);
        } else {
            $old = $DB->get_record('local_orange_notice', ['id' => $data->id, 'kind' => $data->kind], '*', MUST_EXIST);
            if (isset($data->revision) && $data->revision != $old->revision) {
                throw new \invalid_parameter_exception('Otra persona ha editado el aviso. Recarga la publicación.');
            }
            $data->revision = $old->revision;
            if ($old->state !== $data->state) {
                $DB->insert_record('local_orange_state_log', (object)['noticeid' => $data->id, 'userid' => $USER->id,
                    'oldstate' => $old->state, 'newstate' => $data->state, 'timecreated' => time()]);
            }
            // Text or impact changes require a fresh reading of the current version.
            if ($old->title !== $data->title || $old->content !== $data->content || $old->impact !== $data->impact ||
                    ($data->kind === 'incidents' && ($old->client !== $data->client || $old->product !== $data->product))) {
                $data->revision++;
            }
            $DB->update_record('local_orange_notice', $data);
            $DB->delete_records('local_orange_audience', ['noticeid' => $data->id]);
        }
        if (!$DB->record_exists('local_orange_revision', ['noticeid' => $data->id, 'revision' => $data->revision])) {
            $DB->insert_record('local_orange_revision', (object)['noticeid' => $data->id, 'revision' => $data->revision,
                'title' => $data->title, 'content' => $data->content, 'authorid' => $USER->id, 'timecreated' => time()]);
        }
        foreach ($targets as $type => $ids) {
            foreach (array_unique($ids) as $id) {
                $DB->insert_record('local_orange_audience', (object)['noticeid' => $data->id,
                    'targettype' => $type, 'targetid' => $id]);
            }
        }
        $tx->allow_commit();
        return (int)$data->id;
    }
}
