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
        $affectedservice = '';
        if (in_array($kind, ['incidents', 'notifications'], true)) {
            $record = $DB->get_record('local_orange_notice', ['id' => $id, 'kind' => $kind, 'published' => 1, 'deleted' => 0]);
            if (!$record || !$DB->record_exists('local_orange_audience', ['noticeid' => $id])) { return null; }
            $name = $record->title; $content = $record->content;
            $url = new \moodle_url('/local/orangeportal/view.php', ['id' => $id]);
            $affectedservice = self::text($record->client ?? '');
            $published = (int)$record->timecreated;
        } else if ($kind === 'library') {
            $cm = $DB->get_record('course_modules', ['id' => $id, 'visible' => 1, 'deletioninprogress' => 0]);
            if (!$cm || !self::visible_course((int)$cm->course, 'library') ||
                    !$DB->record_exists('course_sections', ['id' => $cm->section, 'visible' => 1])) { return null; }
            $module = $DB->get_field('modules', 'name', ['id' => $cm->module], MUST_EXIST);
            $record = $DB->get_record($module, ['id' => $cm->instance]);
            if (!$record) { return null; }
            $name = $record->name; $content = $record->intro ?? '';
            $published = (int)$cm->added;
            $url = new \moodle_url('/local/orangeportal/activity.php', ['id' => $id]);
        } else {
            $record = self::visible_course($id, 'courses');
            if (!$record) { return null; }
            $name = $record->fullname; $content = $record->summary;
            $published = training_publication::published_time($record);
            $url = new \moodle_url('/course/view.php', ['id' => $id]);
        }
        // Fixed language is intentional: the shared channel must not depend on the saving editor's language.
        $labels = ['incidents' => ['Nueva incidencia', 'Incidencia actualizada'],
            'notifications' => ['Nuevo comunicado', 'Comunicado actualizado'],
            'library' => ['Nuevo documento de Biblioteca', 'Documento de Biblioteca actualizado'],
            'courses' => ['Nueva formación', 'Formación actualizada']];
        return ['titulo' => $labels[$kind][$action === 'created' ? 0 : 1] . ': ' . self::text($name),
            'fechaPublicacion' => (new \DateTimeImmutable('@' . $published))
                ->setTimezone(\core_date::get_server_timezone_object())->format('Y-m-d\TH:i:s'),
            'servicioAfectado' => $affectedservice,
            'contenido' => self::text($content), 'url' => $url->out(false)];
    }

    /** Redact credentials from provider echoes and diagnostic text before writing task logs. */
    public static function log_text(string $text, string $endpoint): string {
        $parts = parse_url($endpoint);
        $destination = preg_replace('/[?#].*$/s', '', $endpoint);
        $text = str_replace([$endpoint, trim(json_encode($endpoint), '"'), rawurlencode($endpoint)], $destination . '?[oculto]', $text);
        parse_str($parts['query'] ?? '', $params);
        foreach ($params as $key => $value) {
            if (!preg_match('/sig|token|secret|password|authorization|^key$|^code$/i', $key)) { continue; }
            foreach ((array)$value as $secret) {
                if (!is_scalar($secret) || (string)$secret === '') { continue; }
                $secret = (string)$secret;
                $text = str_replace(array_unique([$secret, rawurlencode($secret), urlencode($secret),
                    trim(json_encode($secret), '"')]), '[oculto]', $text);
            }
        }
        $redact = function($value, $key = '') use (&$redact) {
            if (preg_match('/^(sig|signature|.*token|.*secret|password|authorization|api[-_]?key)$/i', (string)$key)) { return '[oculto]'; }
            if (is_array($value)) {
                foreach ($value as $k => $v) { $value[$k] = $redact($v, $k); }
                return $value;
            }
            if (is_string($value)) {
                $value = preg_replace('~([?&](?:amp;)?(?:sig|signature|token|access_token|secret|key|code)=)[^&\s"\x27<>\\\\]+~i', '$1[oculto]', $value);
                $value = preg_replace('/\bBearer\s+[^\s"\x27<>]+/i', 'Bearer [oculto]', $value);
            }
            return $value;
        };
        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $text = json_encode($redact($decoded), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        } else { $text = $redact($text); }
        $text = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', '', $text);
        return str_replace(["\r", "\n"], ['\\r', '\\n'], $text);
    }

    /** JSON POST through Moodle's proxy, certificate and URL security configuration. */
    public static function post(string $endpoint, array $payload, string $eventid = ''): void {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        if (!self::valid_endpoint($endpoint)) { throw new \RuntimeException('Teams: invalid HTTPS endpoint'); }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        mtrace('Teams: destino POST ' . self::log_text(preg_replace('/[?#].*$/s', '', $endpoint), $endpoint) . ' (parámetros de URL ocultos).');
        mtrace('Teams: JSON enviado: ' . self::log_text($json, $endpoint));
        if ($eventid !== '') { mtrace('Teams: identificador de envío: ' . $eventid); }
        $curl = new \curl();
        $curl->setHeader(['Content-Type: application/json; charset=utf-8', 'Accept: application/json']);
        if ($eventid !== '') { $curl->setHeader('X-Orange-Event-Id: ' . $eventid); }
        $start = microtime(true); $body = ''; $error = '';
        try {
            $body = $curl->post($endpoint, $json,
                ['CURLOPT_CONNECTTIMEOUT' => 5, 'CURLOPT_TIMEOUT' => 15, 'CURLOPT_FOLLOWLOCATION' => false,
                    'CURLOPT_SSL_VERIFYPEER' => true, 'CURLOPT_SSL_VERIFYHOST' => 2]);
            $info = $curl->get_info();
            $code = (int)($info['http_code'] ?? 0);
            $errno = (int)$curl->get_errno();
            if ($errno) { $error = (string)$curl->error; }
        } catch (\Throwable $e) {
            $code = 0; $errno = -1;
            $error = 'No se pudo completar la petición HTTPS.';
        }
        mtrace('Teams: respuesta HTTP ' . $code . ' (' . round((microtime(true) - $start) * 1000) . ' ms).');
        $response = self::log_text(is_string($body) ? $body : json_encode($body), $endpoint);
        $truncated = \core_text::strlen($response) > 4000;
        mtrace('Teams: cuerpo de respuesta: ' . ($response !== '' ? \core_text::substr($response, 0, 4000) : '(vacío)') .
            ($truncated ? ' [truncado a 4000 caracteres]' : ''));
        if ($errno) { mtrace('Teams: error de transporte ' . $errno . ': ' . \core_text::substr(self::log_text($error, $endpoint), 0, 1000)); }
        set_config('teamslastattempt', time(), 'local_orangeportal');
        set_config('teamslastresult', $errno ? 'transport' : 'HTTP ' . $code, 'local_orangeportal');
        if ($errno || $code < 200 || $code >= 300) {
            throw new \RuntimeException($errno ? 'Teams: transport error' : 'Teams: HTTP ' . $code);
        }
        set_config('teamslastsuccess', time(), 'local_orangeportal');
    }
}
