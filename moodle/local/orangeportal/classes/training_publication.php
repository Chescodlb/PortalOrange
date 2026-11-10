<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** Explicit publication workflow, backed by Moodle's native course visibility. */
class training_publication {
    private static array $changing = [];

    public static function edit_url(int $id): \moodle_url {
        global $CFG;
        require_once($CFG->dirroot . '/local/orangeportal/locallib.php');
        return new \moodle_url('/course/edit.php', ['id' => $id, 'returnto' => 'url', 'sesskey' => sesskey(),
            'returnurl' => \orange_link('index.php', ['section' => 'courses', 'course' => $id])->out(false)]);
    }
    public static function tools(int $id): string {
        global $CFG;
        require_once($CFG->dirroot . '/local/orangeportal/locallib.php');
        $html = \html_writer::link(\orange_link('collections.php', ['course' => $id]),
            get_string('trainingmanagecontent', 'local_orangeportal'), ['class' => 'btn btn-primary']);
        if (content_access::can_enrol($id)) {
            $html .= ' ' . \html_writer::link(\orange_link('training_participants.php', ['course' => $id]),
                get_string('participants'), ['class' => 'btn btn-primary']);
        }
        if (\core_course\management\helper::can_copy_course($id)) {
            $html .= ' ' . \html_writer::link(new \moodle_url('/backup/copy.php', ['id' => $id]),
                get_string('trackingcopyedition', 'local_orangeportal'), ['class' => 'btn btn-primary']);
        }
        return $html;
    }
    /** Called after audience validation; Moodle keeps native visibility as the canonical flag. */
    public static function submitted(\stdClass $data, bool $creating): void {
        global $DB, $CFG;
        if (empty($data->orangeaudiencepresent)) { return; }
        $id = (int)$data->id;
        $course = get_course($id);
        $context = $creating ? \context_coursecat::instance($course->category) : \context_course::instance($id);
        $visible = property_exists($data, 'orangepublished') ? !empty($data->orangepublished) : (bool)$course->visible;
        if ((bool)$course->visible !== $visible) { require_capability('moodle/course:visibility', $context); }
        $record = self::state($course);
        if (isset($data->orangegeneration) && (int)$data->orangegeneration !== (int)$record->generation) {
            throw new \moodle_exception('trainingpublicationchanged', 'local_orangeportal');
        }
        teams::choose('courses', $id, $visible && !empty($data->orangenotifyteams));
        if ($creating) {
            require_once($CFG->dirroot . '/course/lib.php');
            if ($visible) { course_change_visibility($id, true); }
            else { teams::consume('courses', $id); }
        } else {
            $data->visible = (int)$visible;
            $data->visibleold = (int)$visible;
        }
    }
    public static function record(int $id): ?\stdClass {
        global $DB;
        return $DB->get_record('local_orange_publication', ['courseid' => $id]) ?: null;
    }

    /** Existing visible courses remain published; merely opening management never sends an announcement. */
    public static function state(\stdClass $course): \stdClass {
        if ($record = self::record((int)$course->id)) { return $record; }
        $lock = \core\lock\lock_config::get_lock_factory('local_orangeportal')->get_lock('publish_course_' . $course->id, 10);
        if (!$lock) { throw new \moodle_exception('locktimeout'); }
        try { return self::ensure($course); } finally { $lock->release(); }
    }

    private static function ensure(\stdClass $course): \stdClass {
        global $DB;
        if ($record = self::record((int)$course->id)) { return $record; }
        $record = (object)['courseid' => $course->id, 'published' => (int)$course->visible,
            'firstpublished' => $course->visible ? (int)($course->timecreated ?: time()) : 0,
            'generation' => 0, 'notify' => 1];
        $record->id = $DB->insert_record('local_orange_publication', $record);
        return $record;
    }

    public static function generation(int $id): int {
        return (int)(self::record($id)->generation ?? 0);
    }

    public static function published_time(\stdClass $course): int {
        return (int)(self::record((int)$course->id)->firstpublished ?? $course->timecreated);
    }

    public static function can_publish(int $id): bool {
        $context = \context_course::instance($id);
        return experience::member() && content_access::section($id) === 'courses' &&
            has_capability('moodle/course:manageactivities', $context) &&
            has_capability('moodle/course:update', $context) && has_capability('moodle/course:visibility', $context);
    }

    public static function set_visible(int $id, bool $visible, bool $notify, int $generation): void {
        global $DB, $CFG;
        experience::require_access();
        if (!self::can_publish($id)) { throw new \moodle_exception('nativecontentdenied', 'local_orangeportal'); }
        require_once($CFG->dirroot . '/course/lib.php');
        $lock = \core\lock\lock_config::get_lock_factory('local_orangeportal')->get_lock('publish_course_' . $id, 10);
        if (!$lock) { throw new \moodle_exception('locktimeout'); }
        try {
            $course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
            $record = self::ensure($course);
            if ((int)$record->generation !== $generation) { throw new \moodle_exception('trainingpublicationchanged', 'local_orangeportal'); }
            if ((bool)$course->visible === $visible) { return; }
            $tx = $DB->start_delegated_transaction();
            $DB->set_field('local_orange_publication', 'notify', (int)$notify, ['id' => $record->id]);
            teams::choose('courses', $id, $notify && $visible);
            self::$changing[$id] = true;
            course_change_visibility($id, $visible);
            // Native events dispatch after the transaction commits, while this request still owns the lock.
            $tx->allow_commit();
        } finally { unset(self::$changing[$id]); $lock->release(); }
    }

    /** State is maintained even if Teams is disabled; the first publication is a new-content announcement. */
    public static function changed(\core\event\base $event): void {
        global $DB;
        $id = (int)$event->objectid;
        if (content_access::section($id) !== 'courses' || native_content::mapping($id)) { return; }
        $lock = null;
        if (empty(self::$changing[$id])) {
            $lock = \core\lock\lock_config::get_lock_factory('local_orangeportal')->get_lock('publish_course_' . $id, 10);
            if (!$lock) { throw new \moodle_exception('locktimeout'); }
        }
        try {
            $course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
            $record = self::ensure($course);
            $iscreated = $event instanceof \core\event\course_created;
            $tx = $DB->start_delegated_transaction();
            if (!$course->visible) {
                if ($record->published || array_key_exists('visible', $event->other['updatedfields'] ?? [])) { $record->generation++; }
                $record->published = 0; $record->notify = 1;
                $DB->update_record('local_orange_publication', $record);
                teams::consume('courses', $id);
                self::cancel_pending($id);
            } else {
                $transition = !$record->published;
                $first = !$record->firstpublished;
                if ($transition) { $record->generation++; }
                if ($first) { $record->firstpublished = time(); }
                $notify = !$transition || (bool)$record->notify;
                $record->published = 1; $record->notify = 1;
                $DB->update_record('local_orange_publication', $record);
                if (teams::consume('courses', $id) && $notify) { teams::queue('courses', $id, $iscreated || $first ? 'created' : 'updated'); }
            }
            $tx->allow_commit();
        } finally { if ($lock) { $lock->release(); } }
    }

    /** Remove only this formation's queued announcements. Running jobs are invalidated by generation. */
    private static function cancel_pending(int $id): void {
        global $DB;
        foreach ($DB->get_records('task_adhoc', ['classname' => '\\local_orangeportal\\task\\send_teams']) as $job) {
            $data = json_decode($job->customdata);
            if (($data->kind ?? '') === 'courses' && (int)($data->id ?? 0) === $id) {
                $DB->delete_records_select('task_adhoc', 'id=? AND timestarted IS NULL', [$job->id]);
            }
        }
    }

    public static function deleted(\core\event\course_deleted $event): void {
        global $DB;
        self::cancel_pending((int)$event->objectid);
        $DB->delete_records('local_orange_publication', ['courseid' => $event->objectid]);
    }
}
