<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** New-agent CSV import using native accounts/cohorts and short, session-bound batches. */
class agent_import {
    public const MAX_ROWS = 1000;
    public const MAX_BYTES = 2097152;
    public const BATCH_SIZE = 20;
    public const LIFETIME = 1800;

    public static function parse(string $content, string $delimiter = 'semicolon'): array {
        global $CFG;
        orange_administration::require_access();
        require_once($CFG->libdir . '/csvlib.class.php');
        if (strlen($content) > self::MAX_BYTES || !mb_check_encoding($content, 'UTF-8') ||
                !in_array($delimiter, ['comma', 'semicolon'], true)) {
            throw new \moodle_exception('csvinvalidfile', 'local_orangeportal');
        }
        $reader = new \csv_import_reader(\csv_import_reader::get_new_iid('orange_agents'), 'orange_agents');
        try {
            if ($reader->load_csv_content($content, 'UTF-8', $delimiter) === false) {
                throw new \moodle_exception('csvparseerror', 'local_orangeportal', '', strip_tags($reader->get_error()));
            }
            $header = array_map(static fn($v) => trim((string)$v), $reader->get_columns());
            $required = ['username', 'firstname', 'lastname', 'email', 'password'];
            $allowed = array_merge($required, ['idnumber', 'cohorts', 'courses']);
            if (count($header) !== count(array_unique($header)) || array_diff($required, $header) || array_diff($header, $allowed)) {
                throw new \moodle_exception('csvinvalidheaders', 'local_orangeportal');
            }
            $reader->init(); $rows = [];
            while (($values = $reader->next()) !== false) {
                if (count($rows) >= self::MAX_ROWS) { throw new \moodle_exception('csvtoomany', 'local_orangeportal', '', self::MAX_ROWS); }
                $rows[] = array_combine($header, $values);
            }
            if (!$rows) { throw new \moodle_exception('csvempty', 'local_orangeportal'); }
            return self::validate($rows);
        } finally {
            $reader->close(); $reader->cleanup();
        }
    }

    /** Revalidate before every write: account collisions, policy and project boundaries can change. */
    public static function validate(array $rows, int $offset = 0): array {
        global $DB, $CFG;
        orange_administration::require_access();
        $cohorts = orange_scope::cohorts();
        $studentrole = null;
        $courses = self::available_courses();
        $courselookup = [];
        foreach ($courses as $c) { $courselookup[$c->shortname] = $c; }
        $lookup = [];
        foreach ($cohorts as $c) {
            foreach (array_unique(array_filter([$c->name, $c->idnumber], static fn($s) => $s !== '')) as $value) {
                $lookup[$value][(int)$c->id] = (int)$c->id;
            }
        }
        $seenusers = []; $seenemails = []; $results = []; $errors = 0;
        foreach ($rows as $i => $input) {
            $error = [];
            $row = (object)['line' => $offset + $i + 2, 'cohortids' => [], 'cohortnames' => [], 'courseids' => [], 'coursenames' => [], 'courseinstances' => []];
            foreach (['username','firstname','lastname','email','idnumber','cohorts','courses'] as $key) {
                $row->$key = trim((string)($input[$key] ?? ''));
            }
            $row->password = (string)($input['password'] ?? '');
            if (!$row->username || strlen($row->username) > 100 || clean_param($row->username, PARAM_USERNAME) !== $row->username ||
                    \core_text::strtolower($row->username) !== $row->username) {
                $error[] = get_string('csvbadusername', 'local_orangeportal');
            }
            if (isset($seenusers[$row->username]) || $DB->record_exists('user', ['username' => $row->username, 'mnethostid' => $CFG->mnet_localhost_id])) {
                $error[] = get_string('csvusernameunavailable', 'local_orangeportal');
            }
            $seenusers[$row->username] = true;
            foreach (['firstname', 'lastname'] as $key) {
                if ($row->$key === '' || \core_text::strlen($row->$key) > 100 || clean_param($row->$key, PARAM_NOTAGS) !== $row->$key) {
                    $error[] = get_string('csvbadname', 'local_orangeportal'); break;
                }
            }
            $emailkey = \core_text::strtolower($row->email);
            if (!validate_email($row->email) || strlen($row->email) > 100 || isset($seenemails[$emailkey]) ||
                    $DB->record_exists('user', ['email' => $row->email, 'deleted' => 0])) {
                $error[] = get_string('csvbademail', 'local_orangeportal');
            }
            $seenemails[$emailkey] = true;
            $passworderror = '';
            if ($row->password === '' || !check_password_policy($row->password, $passworderror)) {
                // Policy text can contain HTML and is unnecessary in a bulk preview. Never echo a supplied password.
                $error[] = get_string('csvbadpassword', 'local_orangeportal');
            }
            if (\core_text::strlen($row->idnumber) > 255 || clean_param($row->idnumber, PARAM_NOTAGS) !== $row->idnumber) {
                $error[] = get_string('csvbadidnumber', 'local_orangeportal');
            }
            foreach (array_unique(array_filter(array_map('trim', explode('|', $row->cohorts)), static fn($s) => $s !== '')) as $value) {
                if (count($lookup[$value] ?? []) !== 1) {
                    $error[] = get_string('csvunknowncohort', 'local_orangeportal', $value); continue;
                }
                $id = reset($lookup[$value]);
                $row->cohortids[$id] = $id; $row->cohortnames[$id] = $cohorts[$id]->name;
            }
            foreach (array_unique(array_filter(array_map('trim', explode('|', $row->courses)), static fn($v) => $v !== '')) as $shortname) {
                if (!isset($courselookup[$shortname])) {
                    $error[] = get_string('csvunknowncourse', 'local_orangeportal', $shortname); continue;
                }
                $course = $courselookup[$shortname];
                $row->courseids[] = (int)$course->id;
                $row->coursenames[] = $course->fullname . ' (' . $course->shortname . ')';
                $row->courseinstances[$course->id] = (int)$course->importenrolid;
            }
            sort($row->courseids);
            if ($row->courseids) { $studentrole ??= self::student_role(); }
            $row->cohortids = array_values($row->cohortids); sort($row->cohortids);
            $row->cohortnames = array_values($row->cohortnames);
            $row->errors = array_values(array_unique($error)); $errors += (int)(bool)$error;
            $results[] = $row;
        }
        self::check_enrolments(array_unique(array_merge([(int)get_config('local_orangeportal', 'audiencecohort')],
            ...array_map(static fn($r) => $r->cohortids, $results))));
        return ['rows' => $results, 'errors' => $errors, 'total' => count($results)];
    }

    /** Only Orange training courses with native manual enrolment enabled are CSV targets. */
    public static function available_courses(): array {
        global $DB;
        orange_administration::require_access();
        if (!enrol_is_enabled('manual')) { return []; }
        $plugin = enrol_get_plugin('manual');
        if (!$plugin) { return []; }
        $libraryroot = $DB->get_record('course_categories', ['id' => (int)get_config('local_orangeportal', 'librarycategory')]);
        $courses = \orange_native_courses((int)get_config('local_orangeportal', 'coursescategory'));
        foreach ($courses as $id => $course) {
            // Never expose library collections as training targets, even with overlapping root configuration.
            $category = $DB->get_record('course_categories', ['id' => $course->category], '*', MUST_EXIST);
            if ($libraryroot && ($category->id == $libraryroot->id || str_starts_with($category->path, $libraryroot->path . '/'))) {
                unset($courses[$id]); continue;
            }
            foreach (enrol_get_instances($id, true) as $instance) {
                if ($instance->enrol === 'manual' && $plugin->allow_enrol($instance)) {
                    $course->importenrolid = (int)$instance->id; break;
                }
            }
            if (empty($course->importenrolid)) { unset($courses[$id]); }
        }
        return $courses;
    }

    /** Always override the manual instance default role; CSV cannot request teaching roles. */
    private static function student_role(): int {
        $roles = get_archetype_roles('student');
        foreach ($roles as $role) { if ($role->shortname === 'student') { return (int)$role->id; } }
        if (!$roles) { throw new \moodle_exception('csvnostudentrole', 'local_orangeportal'); }
        return (int)reset($roles)->id;
    }

    /** Cohort membership must not indirectly enrol these new agents as staff or outside Orange. */
    private static function check_enrolments(array $ids): void {
        global $DB;
        $courses = orange_scope::course_ids();
        foreach ($ids as $id) {
            foreach ($DB->get_records('enrol', ['enrol' => 'cohort', 'customint1' => $id]) as $e) {
                $role = $DB->get_record('role', ['id' => $e->roleid]);
                if (!isset($courses[$e->courseid]) || !$role || $role->archetype !== 'student') {
                    throw new \moodle_exception('csvunsafeenrolment', 'local_orangeportal');
                }
            }
        }
    }

    public static function stage(array $report): void {
        global $SESSION, $USER;
        orange_administration::require_access();
        if ($report['errors'] || !$report['total'] || $report['total'] > self::MAX_ROWS) {
            throw new \moodle_exception('csvfixerrors', 'local_orangeportal');
        }
        $SESSION->orangeagentimport = [
            'actor' => (int)$USER->id, 'token' => bin2hex(random_bytes(24)), 'expires' => time() + self::LIFETIME,
            'cursor' => 0, 'total' => $report['total'], 'started' => false,
            'payload' => \core\encryption::encrypt(json_encode($report['rows'], JSON_THROW_ON_ERROR)),
        ];
    }

    public static function pending(): ?array {
        global $SESSION, $USER;
        orange_administration::require_access();
        $state = $SESSION->orangeagentimport ?? null;
        if (!$state || $state['actor'] !== (int)$USER->id || $state['expires'] < time()) {
            unset($SESSION->orangeagentimport); return null;
        }
        return $state;
    }

    public static function preview_rows(array $state): array {
        return empty($state['payload']) ? [] : array_slice(json_decode(\core\encryption::decrypt($state['payload']), false, 512, JSON_THROW_ON_ERROR), 0, 25);
    }

    private static function ticket(string $token): array {
        $state = self::pending();
        if (!$state || empty($state['token']) || !hash_equals($state['token'], $token)) {
            throw new \moodle_exception('csvexpired', 'local_orangeportal');
        }
        return $state;
    }

    public static function cancel(string $token): int {
        global $SESSION;
        $state = self::ticket($token); unset($SESSION->orangeagentimport);
        return $state['cursor'];
    }

    public static function process(string $token): array {
        global $DB, $SESSION;
        $state = self::ticket($token);
        $rows = json_decode(\core\encryption::decrypt($state['payload']), true, 512, JSON_THROW_ON_ERROR);
        $remaining = array_slice($rows, $state['cursor']);
        $check = self::validate($state['started'] ? array_slice($remaining, 0, self::BATCH_SIZE) : $remaining, $state['cursor']);
        foreach ($check['rows'] as $i => $row) {
            if ($row->cohortids !== $remaining[$i]['cohortids']) { $row->errors[] = get_string('csvcohortchanged', 'local_orangeportal'); }
            if ($row->courseids !== ($remaining[$i]['courseids'] ?? [])) { $row->errors[] = get_string('csvcoursechanged', 'local_orangeportal'); }
            if ($row->errors) {
                throw new \moodle_exception('csvrowerror', 'local_orangeportal', '', (object)[
                    'line' => $row->line, 'error' => implode(' · ', $row->errors)]);
            }
        }
        $studentrole = null;
        $tx = $DB->start_delegated_transaction();
        try {
            foreach (array_slice($check['rows'], 0, self::BATCH_SIZE) as $row) {
                $id = orange_administration::create_user($row);
                orange_administration::set_cohorts($id, $row->cohortids);
                foreach ($row->courseinstances as $courseid => $instanceid) {
                    $instance = $DB->get_record('enrol', ['id' => $instanceid, 'courseid' => $courseid,
                        'enrol' => 'manual', 'status' => ENROL_INSTANCE_ENABLED], '*', MUST_EXIST);
                    $start = time();
                    $end = empty($instance->enrolperiod) ? 0 : $start + (int)$instance->enrolperiod;
                    enrol_get_plugin('manual')->enrol_user($instance, $id, $studentrole ??= self::student_role(), $start, $end, ENROL_USER_ACTIVE);
                }
            }
            $tx->allow_commit();
        } catch (\Throwable $e) { $tx->rollback($e); }
        $state['cursor'] += min(count($remaining), self::BATCH_SIZE);
        $state['started'] = true; $state['expires'] = time() + self::LIFETIME;
        $state['token'] = $state['cursor'] < $state['total'] ? bin2hex(random_bytes(24)) : '';
        if ($state['cursor'] === $state['total']) { unset($state['payload']); }
        $SESSION->orangeagentimport = $state;
        return ['created' => $state['cursor'], 'total' => $state['total'], 'token' => $state['token'],
            'done' => $state['cursor'] === $state['total']];
    }
}
