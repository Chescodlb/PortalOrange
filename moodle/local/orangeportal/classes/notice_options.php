<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** Portal catalogues stored using Moodle's plugin configuration API. */
class notice_options {
    private static function check_type(string $type): void {
        if (!in_array($type, ['states', 'impacts'])) { throw new \invalid_parameter_exception('Invalid catalogue'); }
    }
    public static function records(string $type): array {
        self::check_type($type);
        $stored = get_config('local_orangeportal', 'notice_' . $type);
        if ($stored !== false) { return json_decode($stored, true, 512, JSON_THROW_ON_ERROR); }
        $defaults = $type === 'states' ? ['open' => 'Abierta', 'progress' => 'En seguimiento', 'closed' => 'Cerrada'] :
            ['normal' => 'Normal', 'high' => 'Alto', 'critical' => 'Crítico'];
        return array_map(fn($name) => ['name' => $name, 'active' => true], $defaults);
    }
    public static function choices(string $type, bool $activeonly = false, ?string $current = null): array {
        $out = [];
        foreach (self::records($type) as $code => $record) {
            if (!$activeonly || $record['active'] || $code === $current) {
                $out[$code] = $record['name'] . ($activeonly && !$record['active'] ?
                    ' (' . get_string('optioninactive', 'local_orangeportal') . ')' : '');
            }
        }
        return $out;
    }
    public static function default_code(string $type): string {
        return array_key_first(self::choices($type, true));
    }
    public static function with_lock(callable $action) {
        $lock = \core\lock\lock_config::get_lock_factory('local_orangeportal')->get_lock('notice_options', 10);
        if (!$lock) { throw new \moodle_exception('locktimeout'); }
        try {
            // Refresh settings read before waiting for another writer's lock.
            \cache::make('core', 'config')->delete('local_orangeportal');
            return $action();
        } finally { $lock->release(); }
    }
    public static function used(string $type, string $code): bool {
        global $DB;
        self::check_type($type);
        $field = $type === 'states' ? 'state' : 'impact';
        return $DB->record_exists('local_orange_notice', [$field => $code]) ||
            ($type === 'states' && $DB->record_exists_select('local_orange_state_log',
                'oldstate = ? OR newstate = ?', [$code, $code]));
    }
    public static function save(string $type, string $code, string $name, bool $active): string {
        require_capability('local/orangeportal:manageoptions', \context_system::instance());
        return self::with_lock(function() use ($type, $code, $name, $active) {
            $records = self::records($type);
            if ($code !== '' && !isset($records[$code])) { throw new \invalid_parameter_exception('Unknown option'); }
            $name = trim(clean_param($name, PARAM_TEXT));
            if ($name === '' || \core_text::strlen($name) > 100) {
                throw new \moodle_exception('invalidoptionname', 'local_orangeportal');
            }
            foreach ($records as $key => $record) {
                if ($key !== $code && \core_text::strtolower($record['name']) === \core_text::strtolower($name)) {
                    throw new \moodle_exception('duplicateoptionname', 'local_orangeportal');
                }
            }
            // Alphabetic immutable identifiers fit existing fields and PARAM_ALPHA inputs.
            if ($code === '') { $code = 'custom' . strtr(bin2hex(random_bytes(6)), '0123456789', 'ghijklmnop'); }
            $records[$code] = ['name' => $name, 'active' => $active];
            self::store($type, $records);
            return $code;
        });
    }
    public static function delete(string $type, string $code): void {
        require_capability('local/orangeportal:manageoptions', \context_system::instance());
        self::with_lock(function() use ($type, $code) {
            $records = self::records($type);
            if (!isset($records[$code])) { throw new \invalid_parameter_exception('Unknown option'); }
            if (self::used($type, $code)) { throw new \moodle_exception('optioninuse', 'local_orangeportal'); }
            unset($records[$code]);
            self::store($type, $records);
        });
    }
    private static function store(string $type, array $records): void {
        if (!array_filter($records, fn($r) => $r['active'])) {
            throw new \moodle_exception('lastactiveoption', 'local_orangeportal');
        }
        set_config('notice_' . $type, json_encode($records, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'local_orangeportal');
    }
    public static function validate_selection(string $type, string $code, ?string $current = null): void {
        if (!array_key_exists($code, self::choices($type, true, $current))) {
            throw new \moodle_exception('inactiveoptionselection', 'local_orangeportal');
        }
    }
}
