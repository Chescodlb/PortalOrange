<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** One audience decision shared by navigation, presentation and portal endpoints. */
class experience {
    public static function member(?int $userid = null): bool {
        global $DB, $USER;
        $userid = $userid ?? (int)($USER->id ?? 0);
        $cohortid = (int)get_config('local_orangeportal', 'audiencecohort');
        return $userid > 1 && $cohortid > 0 && (bool)get_config('local_orangeportal', 'enabled') &&
            $DB->record_exists('cohort_members', ['cohortid' => $cohortid, 'userid' => $userid]);
    }

    public static function require_access(): void {
        if (!self::member()) {
            throw new \moodle_exception('portalaccessdenied', 'local_orangeportal');
        }
    }

}
