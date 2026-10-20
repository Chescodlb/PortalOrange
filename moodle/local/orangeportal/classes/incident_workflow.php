<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** Incident-specific authorization and closure metadata shared by all entry points. */
class incident_workflow {
    public static function all(?int $userid = null): bool {
        return has_capability('local/orangeportal:viewallincidents', \context_system::instance(), $userid);
    }
    public static function change_metadata(\stdClass $data, ?\stdClass $old): void {
        global $USER;
        if ($data->kind !== 'incidents') { return; }
        if (!$old || $old->state !== $data->state) {
            require_capability('local/orangeportal:changestate', \context_system::instance());
        }
        foreach (['client', 'product'] as $field) {
            $data->$field = trim(clean_param($data->$field ?? $old->$field ?? '', PARAM_TEXT));
            if (\core_text::strlen($data->$field) > 255) { throw new \invalid_parameter_exception('Incident field too long'); }
        }
        $closed = notice_options::is_closed($data->state);
        $wasclosed = $old && notice_options::is_closed($old->state);
        $data->timeclosed = $closed ? ($wasclosed ? $old->timeclosed : time()) : 0;
        $data->closedby = $closed ? ($wasclosed ? $old->closedby : (int)$USER->id) : 0;
    }
    public static function delete(int $id): void {
        global $DB, $USER;
        experience::require_access();
        require_capability('local/orangeportal:manage', \context_system::instance());
        require_capability('local/orangeportal:changestate', \context_system::instance());
        $lock = \core\lock\lock_config::get_lock_factory('local_orangeportal')->get_lock('notice_' . $id, 10);
        if (!$lock) { throw new \moodle_exception('locktimeout'); }
        try {
            $n = $DB->get_record('local_orange_notice', ['id' => $id, 'kind' => 'incidents'], '*', MUST_EXIST);
            if (!$n->deleted) {
                $DB->update_record('local_orange_notice', (object)['id' => $id, 'deleted' => time(),
                    'deletedby' => $USER->id, 'published' => 0]);
            }
        } finally { $lock->release(); }
    }
}
