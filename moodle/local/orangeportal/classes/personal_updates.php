<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** Personal updates reuse native access, membership dates and existing reading evidence. */
class personal_updates {
    public static function joined(): int {
        global $DB, $USER;
        return (int)$DB->get_field('cohort_members', 'timeadded', [
            'cohortid' => (int)get_config('local_orangeportal', 'audiencecohort'), 'userid' => $USER->id]);
    }

    public static function new_course(\stdClass $course, array $access, int $joined): bool {
        return $joined > 0 && $course->visible && $course->timecreated >= $joined && empty($access[$course->id]);
    }

    public static function course_access(): array {
        global $DB, $USER;
        return $DB->get_records_menu('user_lastaccess', ['userid' => $USER->id], '', 'courseid, timeaccess');
    }

    public static function new_document(\stdClass $doc, int $joined): bool {
        return $joined > 0 && $doc->published && $doc->added >= $joined && !$doc->readtime;
    }

    /** Exclude management previews, drafts and closed incidents from personal counters. */
    public static function recipient(\stdClass $notice): bool {
        global $USER;
        return $notice->published && empty($notice->deleted) && experience::member() &&
            service::assigned((int)$notice->id, (int)$USER->id) &&
            ($notice->kind !== 'incidents' || !notice_options::is_closed($notice->state));
    }

    public static function counts(): array {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/local/orangeportal/locallib.php');
        // Critical values are subsets of their section totals, not separate indicators.
        $counts = ['incidents' => 0, 'notifications' => 0, 'courses' => 0, 'library' => 0,
            'incidentscritical' => 0, 'notificationscritical' => 0];
        if (!experience::member()) { return $counts; }
        foreach (['incidents', 'notifications'] as $kind) {
            foreach (service::visible($kind) as $notice) {
                if (!self::recipient($notice)) { continue; }
                $r = service::get_receipt($notice, (int)$USER->id);
                if ($r && ($r->readtime || $r->confirmed)) { continue; }
                $counts[$kind]++;
                if ($notice->impact === 'critical') { $counts[$kind . 'critical']++; }
            }
        }
        $joined = self::joined();
        $access = self::course_access();
        foreach (\orange_native_courses((int)get_config('local_orangeportal', 'coursescategory')) as $course) {
            if (self::new_course($course, $access, $joined)) { $counts['courses']++; }
        }
        foreach (library_browser::documents((int)get_config('local_orangeportal', 'librarycategory')) as $doc) {
            if (self::new_document($doc, $joined)) { $counts['library']++; }
        }
        return $counts;
    }

    public static function navbar(): string {
        $paths = [
            'notifications' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Z"/><path d="M10 21h4"/>',
            'incidents' => '<path d="m12 3 10 18H2Z"/><path d="M12 9v5"/><circle cx="12" cy="17" r=".5"/>',
            'courses' => '<path d="M12 5v16M12 5C8 2 4 3 2 4v15c3-1 6-1 10 2 4-3 7-3 10-2V4c-2-1-6-2-10 1Z"/>',
            'library' => '<path d="M3 3h4v18H3ZM9 3h4v18H9ZM15 4l4-1 4 17-4 1Z"/>',
        ];
        $links = '';
        $counts = self::counts();
        foreach ($paths as $key => $path) {
            $total = $counts[$key];
            $critical = $counts[$key . 'critical'] ?? 0;
            $count = $critical ?: $total;
            $params = ['section' => $key, 'unread' => 1];
            if (in_array($key, ['incidents', 'notifications'])) { $params['personal'] = 1; }
            if ($key === 'incidents') {
                $params['incidentstate'] = 'openonly';
                if ($critical) { $params['priority'] = 'critical'; }
            } else if ($key === 'notifications' && $critical) {
                $params['impact'] = 'critical';
            }
            $label = get_string('updates_' . $key, 'local_orangeportal');
            $description = $label . ': ' . $total;
            if ($critical) {
                $description .= '. ' . get_string('updates_criticalsummary', 'local_orangeportal',
                    (object)['critical' => $critical, 'total' => $total]);
            }
            $icon = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
            $links .= \html_writer::link(\orange_link('index.php', $params), $icon .
                \html_writer::span((string)$count, 'orange-update-count', ['aria-hidden' => 'true']),
                ['class' => 'orange-update orange-update-' . $key . ($count ? ' has-updates' : '') . ($critical ? ' is-critical' : ''),
                    'aria-label' => $description, 'title' => $description, 'data-update' => $key, 'data-count' => $count,
                    'data-total' => $total, 'data-critical' => $critical]);
        }
        return \html_writer::tag('nav', $links, ['class' => 'orange-personal-updates',
            'aria-label' => get_string('personalupdates', 'local_orangeportal')]);
    }
}
