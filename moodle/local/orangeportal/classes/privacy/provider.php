<?php
namespace local_orangeportal\privacy;
defined('MOODLE_INTERNAL') || die();
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
/** Moodle export and erasure for recipient and audit records. */
class provider implements \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {
    public static function get_metadata(collection $collection): collection {
        foreach (['local_orange_doc_read' => ['userid', 'cmid', 'readtime'],
                'local_orange_notice' => ['authorid', 'title', 'content', 'timecreated', 'client', 'product', 'closedby', 'timeclosed', 'deleted', 'deletedby'],
                'local_orange_revision' => ['authorid', 'title', 'content', 'timecreated'],
                'local_orange_audience' => ['targetid', 'targettype', 'noticeid'],
                'local_orange_receipt' => ['userid', 'noticeid', 'revision', 'received', 'readtime', 'confirmed'],
                'local_orange_state_log' => ['userid', 'noticeid', 'oldstate', 'newstate', 'timecreated']] as $table => $fields) {
            $mapping = [];
            foreach ($fields as $field) {
                $mapping[$field] = 'privacy:metadata:' . (in_array($field, ['userid', 'authorid', 'targetid', 'closedby', 'deletedby']) ? 'userid' :
                    (in_array($field, ['received', 'readtime', 'confirmed', 'timecreated', 'timeclosed', 'deleted']) ? 'time' :
                    (in_array($field, ['oldstate', 'newstate']) ? 'state' : 'content')));
            }
            $collection->add_database_table($table, $mapping, 'privacy:metadata:records');
        }
        return $collection;
    }
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $list = new contextlist();
        foreach (['local_orange_doc_read' => 'userid', 'local_orange_receipt' => 'userid', 'local_orange_state_log' => 'userid',
                'local_orange_notice' => 'authorid', 'local_orange_revision' => 'authorid'] as $table => $field) {
            if ($DB->record_exists($table, [$field => $userid])) { $list->add_system_context(); return $list; }
        }
        if ($DB->record_exists('local_orange_audience', ['targettype' => 'user', 'targetid' => $userid])) {
            $list->add_system_context();
        }
        foreach (['closedby', 'deletedby'] as $field) {
            if ($DB->record_exists('local_orange_notice', [$field => $userid])) { $list->add_system_context(); }
        }
        return $list;
    }
    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel != CONTEXT_SYSTEM) { return; }
        foreach (['local_orange_doc_read' => 'userid', 'local_orange_receipt' => 'userid', 'local_orange_state_log' => 'userid',
                'local_orange_notice' => 'authorid', 'local_orange_revision' => 'authorid'] as $table => $field) {
            $userlist->add_from_sql($field, "SELECT $field FROM {{$table}}");
        }
        $userlist->add_from_sql('targetid', "SELECT targetid FROM {local_orange_audience} WHERE targettype = 'user'");
        foreach (['closedby', 'deletedby'] as $field) {
            $userlist->add_from_sql($field, "SELECT $field FROM {local_orange_notice} WHERE $field > 0");
        }
    }
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $context = \context_system::instance();
        if (!in_array($context->id, $contextlist->get_contextids())) { return; }
        $userid = $contextlist->get_user()->id;
        $data = new \stdClass();
        foreach (['local_orange_doc_read' => 'userid', 'local_orange_receipt' => 'userid', 'local_orange_state_log' => 'userid',
                'local_orange_notice' => 'authorid', 'local_orange_revision' => 'authorid'] as $table => $field) {
            $data->$table = array_values($DB->get_records($table, [$field => $userid]));
        }
        $data->individualassignments = array_values($DB->get_records('local_orange_audience', ['targettype' => 'user', 'targetid' => $userid]));
        $data->incidentactions = array_values($DB->get_records_select('local_orange_notice',
            'closedby = ? OR deletedby = ?', [$userid, $userid]));
        writer::with_context($context)->export_data(['Portal Orange'], $data);
    }
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel != CONTEXT_SYSTEM) { return; }
        foreach (['local_orange_doc_read', 'local_orange_receipt', 'local_orange_state_log', 'local_orange_audience', 'local_orange_revision', 'local_orange_notice'] as $table) {
            $DB->delete_records($table);
        }
    }
    private static function erase(int $userid): void {
        global $DB;
        $DB->delete_records('local_orange_doc_read', ['userid' => $userid]);
        $DB->delete_records('local_orange_receipt', ['userid' => $userid]);
        $DB->delete_records('local_orange_audience', ['targettype' => 'user', 'targetid' => $userid]);
        $DB->delete_records('local_orange_state_log', ['userid' => $userid]);
        foreach (['closedby', 'deletedby'] as $field) {
            $DB->set_field('local_orange_notice', $field, 0, [$field => $userid]);
        }
        // Authored free text is erased as well, not merely its author identifier.
        foreach (['local_orange_notice', 'local_orange_revision'] as $table) {
            foreach ($DB->get_records($table, ['authorid' => $userid]) as $record) {
                $record->authorid = 0; $record->title = 'Contenido eliminado'; $record->content = '';
                if ($table === 'local_orange_notice') { $record->published = 0; }
                $DB->update_record($table, $record);
            }
        }
    }
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        if (in_array(\context_system::instance()->id, $contextlist->get_contextids())) {
            self::erase((int)$contextlist->get_user()->id);
        }
    }
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_context()->contextlevel == CONTEXT_SYSTEM) {
            foreach ($userlist->get_userids() as $userid) { self::erase((int)$userid); }
        }
    }
}
