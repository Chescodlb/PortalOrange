<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** Outbound content announcements. Never sends during the content-saving request. */
class teams {
    const KINDS = ['incidents', 'notifications', 'library', 'courses'];

    private static array $choices = [];

    /** Explicit, request-local consent for exactly one native save event. */
    public static function choose(string $kind, int $id, bool $notify): void {
        self::$choices[$kind . ':' . $id] = $notify;
    }
    public static function consume(string $kind, int $id): bool {
        $key = $kind . ':' . $id;
        $notify = self::$choices[$key] ?? false;
        unset(self::$choices[$key]);
        return $notify;
    }
    public static function form(\MoodleQuickForm $form, string $kind, string $published): void {
        $form->addElement('advcheckbox', 'orangenotifyteams', get_string('teamsnotice', 'local_orangeportal'),
            get_string('teamsonsave', 'local_orangeportal'));
        $form->setDefault('orangenotifyteams', 0);
        $form->setType('orangenotifyteams', PARAM_BOOL);
        $form->disabledIf('orangenotifyteams', $published, 'notchecked');
        if (!self::enabled($kind, 'created') && !self::enabled($kind, 'updated')) {
            $form->setConstant('orangenotifyteams', 0);
            $form->hardFreeze('orangenotifyteams');
            $form->addElement('static', 'orangeteamsdisabled', '', get_string('teamsnotconfigured', 'local_orangeportal'));
        }
        $form->addElement('static', 'orangeteamschoicehint', '', get_string('teamsonsavehint', 'local_orangeportal'));
    }
    public static function valid_endpoint(string $url): bool {
        $parts = parse_url($url);
        return filter_var($url, FILTER_VALIDATE_URL) && $parts && ($parts['scheme'] ?? '') === 'https' &&
            !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['fragment']);
    }

    public static function enabled(string $kind, string $action): bool {
        return in_array($kind, self::KINDS, true) && in_array($action, ['created', 'updated'], true) &&
            get_config('local_orangeportal', 'enabled') && get_config('local_orangeportal', 'teamsenabled') &&
            get_config('local_orangeportal', 'teams_' . $kind) && get_config('local_orangeportal', 'teams_' . $action) &&
            self::valid_endpoint((string)get_config('local_orangeportal', 'teamsurl'));
    }

    /** Enqueue only IDs, never a credential, content snapshot, author or recipient list. */
    public static function queue(string $kind, int $id, string $action): void {
        if (!self::enabled($kind, $action) || !($payload = self::payload($kind, $id, $action))) { return; }
        $task = new \local_orangeportal\task\send_teams();
        $task->set_component('local_orangeportal');
        $data = (object)['kind' => $kind, 'id' => $id, 'action' => $action,
            'endpoint' => hash('sha256', get_config('local_orangeportal', 'teamsurl')),
            'contenthash' => hash('sha256', json_encode($payload))];
        if ($kind === 'courses') { $data->generation = training_publication::generation($id); }
        $task->set_custom_data($data);
        $task->set_next_run_time(time() + 60);
        $task->set_attempts_available(5);
        // Identical pending announcements are merged; different edits can queue during a running delivery.
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /** Capture native resources and real Orange courses, excluding technical containers. */
    public static function changed(\core\event\base $event): void {
        global $DB;
        if (!get_config('local_orangeportal', 'enabled') || !get_config('local_orangeportal', 'teamsenabled')) { return; }
        $action = str_ends_with($event->eventname, '_created') ? 'created' : 'updated';
        if (str_contains($event->eventname, 'course_module_')) {
            $cm = $DB->get_record('course_modules', ['id' => $event->objectid, 'visible' => 1, 'deletioninprogress' => 0]);
            if (!$cm || !$DB->record_exists('course_sections', ['id' => $cm->section, 'visible' => 1])) { return; }
            $map = native_content::mapping((int)$event->courseid);
            if ($map && $map->kind === 'notice') {
                $kind = $DB->get_field('local_orange_notice', 'kind', ['id' => $map->noticeid]);
                if ($kind && self::consume($kind, (int)$map->noticeid)) { self::queue($kind, (int)$map->noticeid, 'updated'); }
            } else {
                if (self::consume('library', (int)$event->objectid)) { self::queue('library', (int)$event->objectid, $action); }
            }
        } else {
            if (self::consume('courses', (int)$event->objectid)) { self::queue('courses', (int)$event->objectid, $action); }
        }
    }

    /** Global visibility, independent of the current editor's ability to see hidden items. */
    private static function visible_course(int $id, string $kind): ?\stdClass {
        global $DB;
        $course = $DB->get_record('course', ['id' => $id, 'visible' => 1]);
        $root = (int)get_config('local_orangeportal', $kind === 'library' ? 'librarycategory' : 'coursescategory');
        if (!$course || !$root) { return null; }
        $category = $DB->get_record('course_categories', ['id' => $course->category]);
        $path = $category ? array_filter(array_map('intval', explode('/', $category->path))) : [];
        if (!in_array($root, $path, true)) { return null; }
        foreach ($DB->get_records_list('course_categories', 'id', $path) as $ancestor) {
            if (!$ancestor->visible) { return null; }
        }
        if ($kind === 'courses' && native_content::mapping($id)) { return null; }
        return $course;
    }

    private static function text(string $html): string {
        return trim(\core_text::substr(html_to_text($html, 0, false), 0, 2000));
    }

    /** Resolve current content again at dispatch: withdrawn/deleted/hidden items are skipped. */
    public static function payload(string $kind, int $id, string $action): ?array {
        global $DB;
        if (!in_array($kind, self::KINDS, true) || !in_array($action, ['created', 'updated'], true)) { return null; }
        $extra = '';
        if (in_array($kind, ['incidents', 'notifications'], true)) {
            $record = $DB->get_record('local_orange_notice', ['id' => $id, 'kind' => $kind, 'published' => 1, 'deleted' => 0]);
            if (!$record || !$DB->record_exists('local_orange_audience', ['noticeid' => $id])) { return null; }
            $name = $record->title; $content = $record->content;
            $url = new \moodle_url('/local/orangeportal/view.php', ['id' => $id]);
            $extra = 'Impacto: ' . (service::impacts()[$record->impact] ?? $record->impact);
            if ($kind === 'incidents') {
                if ($record->client !== '') { $extra .= "\nCliente afectado: " . self::text($record->client); }
                if ($record->product !== '') { $extra .= "\nProducto: " . self::text($record->product); }
            }
        } else if ($kind === 'library') {
            $cm = $DB->get_record('course_modules', ['id' => $id, 'visible' => 1, 'deletioninprogress' => 0]);
            if (!$cm || !self::visible_course((int)$cm->course, 'library') ||
                    !$DB->record_exists('course_sections', ['id' => $cm->section, 'visible' => 1])) { return null; }
            $module = $DB->get_field('modules', 'name', ['id' => $cm->module], MUST_EXIST);
            $record = $DB->get_record($module, ['id' => $cm->instance]);
            if (!$record) { return null; }
            $name = $record->name; $content = $record->intro ?? '';
            $url = new \moodle_url('/local/orangeportal/activity.php', ['id' => $id]);
        } else {
            $record = self::visible_course($id, 'courses');
            if (!$record) { return null; }
            $name = $record->fullname; $content = $record->summary;
            $url = new \moodle_url('/course/view.php', ['id' => $id]);
        }
        // Fixed language is intentional: the shared channel must not depend on the saving editor's language.
        $labels = ['incidents' => ['Nueva incidencia', 'Incidencia actualizada'],
            'notifications' => ['Nuevo comunicado', 'Comunicado actualizado'],
            'library' => ['Nuevo documento de Biblioteca', 'Documento de Biblioteca actualizado'],
            'courses' => ['Nueva formación', 'Formación actualizada']];
        return ['titulo' => $labels[$kind][$action === 'created' ? 0 : 1] . ': ' . self::text($name),
            'contenido' => trim($extra . "\n\n" . self::text($content)), 'url' => $url->out(false)];
    }

    /** JSON POST through Moodle's proxy, certificate and URL security configuration. */
    public static function post(string $endpoint, array $payload, string $eventid = ''): void {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        if (!self::valid_endpoint($endpoint)) { throw new \RuntimeException('Teams: invalid HTTPS endpoint'); }
        $curl = new \curl();
        $curl->setHeader(['Content-Type: application/json; charset=utf-8', 'Accept: application/json']);
        if ($eventid !== '') { $curl->setHeader('X-Orange-Event-Id: ' . $eventid); }
        try {
            $curl->post($endpoint, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                ['CURLOPT_CONNECTTIMEOUT' => 5, 'CURLOPT_TIMEOUT' => 15, 'CURLOPT_FOLLOWLOCATION' => false,
                    'CURLOPT_SSL_VERIFYPEER' => true, 'CURLOPT_SSL_VERIFYHOST' => 2]);
            $info = $curl->get_info();
            $code = (int)($info['http_code'] ?? 0);
            $errno = (int)$curl->get_errno();
        } catch (\Throwable $e) {
            // Provider responses and exception strings can contain the signed URL. Never log them.
            $code = 0; $errno = -1;
        }
        set_config('teamslastattempt', time(), 'local_orangeportal');
        set_config('teamslastresult', $errno ? 'transport' : 'HTTP ' . $code, 'local_orangeportal');
        if ($errno || $code < 200 || $code >= 300) {
            throw new \RuntimeException($errno ? 'Teams: transport error' : 'Teams: HTTP ' . $code);
        }
        set_config('teamslastsuccess', time(), 'local_orangeportal');
    }
}
