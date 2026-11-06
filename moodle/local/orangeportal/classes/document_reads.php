<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** Personal document views, independent of academic completion settings. */
class document_reads {
    public static function viewed(\core\event\course_module_viewed $event): void {
        self::record((int)$event->userid, (int)$event->contextinstanceid, (int)$event->timecreated);
    }

    /** Also used by inline resources, which do not emit a native module-view event. */
    public static function record(int $userid, int $cmid, ?int $time = null): void {
        global $DB;
        if (!experience::member($userid)) { return; }
        $record = $DB->get_record_sql('SELECT cm.id, m.name, c.category
            FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module
            JOIN {course} c ON c.id = cm.course WHERE cm.id = ?', [$cmid]);
        if (!$record) { return; }
        $root = (int)get_config('local_orangeportal', 'librarycategory');
        $path = $DB->get_field('course_categories', 'path', ['id' => $record->category]);
        if (!$root || !in_array($root, array_map('intval', explode('/', $path ?: '')), true)) { return; }
        $lock = \core\lock\lock_config::get_lock_factory('local_orangeportal')->get_lock('docread_' . $userid . '_' . $cmid, 10);
        if (!$lock) { return; }
        try {
            if (!$DB->record_exists('local_orange_doc_read', ['userid' => $userid, 'cmid' => $cmid])) {
                $DB->insert_record('local_orange_doc_read', (object)['userid' => $userid, 'cmid' => $cmid,
                    'readtime' => $time ?? time()]);
            }
        } finally { $lock->release(); }
    }

    public static function deleted(\core\event\course_module_deleted $event): void {
        global $DB;
        $DB->delete_records('local_orange_doc_read', ['cmid' => $event->objectid]);
    }

    public static function times(int $userid): array {
        global $DB;
        // Use existing native view evidence where available, without enabling course completion.
        $times = $DB->get_records_menu('course_modules_viewed', ['userid' => $userid], '', 'coursemoduleid, timecreated');
        foreach ($DB->get_records('local_orange_doc_read', ['userid' => $userid]) as $record) {
            $native = (int)($times[$record->cmid] ?? 0);
            $times[$record->cmid] = $native > 0 && $record->readtime > 0 ? min($native, (int)$record->readtime) :
                ($record->readtime ?: $native);
        }
        return $times;
    }
}
