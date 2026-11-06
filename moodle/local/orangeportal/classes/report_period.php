<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** Exclusive upper bounds avoid missing the last day or depending on server timezone. */
class report_period {
    public static function date(string $name): int {
        $value = optional_param($name, '', PARAM_RAW_TRIMMED);
        if ($value === '') { return 0; }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, \core_date::get_user_timezone_object());
        if (!$date || $date->format('Y-m-d') !== $value) { throw new \invalid_parameter_exception(get_string('invalidnotificationdate', 'local_orangeportal')); }
        return ($name === 'report_to' ? $date->modify('+1 day') : $date)->getTimestamp();
    }
    public static function until(): int {
        return optional_param('period', 'month', PARAM_ALPHA) === 'custom' ? self::date('report_to') : 0;
    }
    public static function contains(int $date): bool {
        $from = reading_report::since(optional_param('period', 'month', PARAM_ALPHA));
        return (!$from || $date >= $from) && (!self::until() || $date < self::until());
    }
    public static function params(): array {
        return ['period' => optional_param('period', 'month', PARAM_ALPHA), 'report_cohort' => report_scope::selected(),
            'report_from' => optional_param('report_from', '', PARAM_RAW_TRIMMED),
            'report_to' => optional_param('report_to', '', PARAM_RAW_TRIMMED)];
    }
}
