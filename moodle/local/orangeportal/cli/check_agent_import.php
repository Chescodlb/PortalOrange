<?php
/** Local-only reversible tests for the delegated agent CSV importer. */
define('CLI_SCRIPT', true);
require __DIR__ . '/../../../config.php';
require_once $CFG->dirroot . '/local/orangeportal/locallib.php';
require_once $CFG->dirroot . '/cohort/lib.php';
require_once $CFG->dirroot . '/course/lib.php';
use local_orangeportal\agent_import as importer;
use local_orangeportal\orange_administration as admin;
if (!preg_match('~^http://(localhost|127\\.0\\.0\\.1):~', $CFG->wwwroot)) { throw new coding_exception('Local demo only.'); }
function csv_check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException($label); } echo "PASS $label\n";
}
function csv_denied(callable $fn, string $label): void {
    try { $fn(); } catch (moodle_exception $e) { csv_check(true, $label); return; }
    throw new RuntimeException($label);
}
$actor = $DB->get_record('user', ['username' => 'admin.orange'], '*', MUST_EXIST);
\core\session\manager::set_user($actor);
$before = $DB->count_records('user'); $tx = $DB->start_delegated_transaction();
try {
    $suffix = bin2hex(random_bytes(5));
    $cohort = admin::save_cohort(0, 'CSV Centro ' . $suffix);
    $DB->set_field('cohort', 'idnumber', 'csv_' . $suffix, ['id' => $cohort]);
    $courseid = (int)array_key_first(\local_orangeportal\orange_scope::course_ids());
    admin::enrol_cohort($cohort, $courseid);
    $training = [];
    foreach (['INICIAL', 'CONTINUA'] as $type) {
        $course = create_course((object)['category' => (int)get_config('local_orangeportal', 'coursescategory'),
            'fullname' => 'CSV Formación ' . $type . ' ' . $suffix, 'shortname' => 'CSV-' . $type . '-' . $suffix]);
        $instances = array_filter(enrol_get_instances($course->id, true), fn($e) => $e->enrol === 'manual');
        if (!$instances) {
            enrol_get_plugin('manual')->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
            $instances = array_filter(enrol_get_instances($course->id, true), fn($e) => $e->enrol === 'manual');
        }
        $course->testinstance = (int)reset($instances)->id;
        $DB->set_field('enrol', 'roleid', $DB->get_field('role', 'id', ['archetype' => 'editingteacher'], IGNORE_MULTIPLE), ['id' => $course->testinstance]);
        $DB->set_field('enrol', 'enrolperiod', DAYSECS, ['id' => $course->testinstance]);
        $training[] = $course;
    }
    $pwd = 'Test!93aB' . bin2hex(random_bytes(6));
    $rows = [];
    for ($i = 0; $i < 21; $i++) {
        $rows[] = ['username' => "csv_{$suffix}_{$i}", 'firstname' => 'Ángela', 'lastname' => 'Test',
            'email' => "csv_{$suffix}_{$i}@example.invalid", 'password' => $pwd, 'idnumber' => "CE{$i}",
            'cohorts' => 'csv_' . $suffix, 'courses' => $i === 0 ? $training[0]->shortname :
                ($i === 1 ? $training[0]->shortname . '|' . $training[1]->shortname . '|' . $training[0]->shortname : '')];
    }
    $header = implode(';', array_keys($rows[0]));
    $csv = "\xEF\xBB\xBF" . $header . "\n" . implode("\n", array_map(fn($r) => implode(';', $r), $rows));
    $report = importer::parse($csv);
    csv_check(!$report['errors'] && $report['total'] === 21 && $report['rows'][0]->firstname === 'Ángela', 'UTF-8 con BOM, separador y colectivos por identificador');
    $comma = "username,firstname,lastname,email,password\n\"comma_{$suffix}\",\"Nombre, doble\",Test,comma_{$suffix}@example.invalid,{$pwd}\n";
    csv_check(!importer::parse($comma, 'comma')['errors'], 'Comas entrecomilladas y columnas opcionales ausentes');
    csv_check(count($report['rows'][0]->courseids) === 1 && count($report['rows'][1]->courseids) === 2 &&
        !$report['rows'][2]->courseids, 'Una, varias o ninguna formación, sin duplicar cursos');
    foreach (['NO-EXISTE-' . $suffix, get_course(SITEID)->shortname] as $shortname) {
        $badcourse = $rows[0]; $badcourse['courses'] = $shortname;
        csv_check(importer::validate([$badcourse])['errors'] === 1, 'Rechaza formación inexistente o ajena al ámbito Orange');
    }
    $librarycourse = create_course((object)['category' => (int)get_config('local_orangeportal', 'librarycategory'),
        'fullname' => 'CSV Biblioteca ' . $suffix, 'shortname' => 'CSV-LIB-' . $suffix]);
    $badcourse['courses'] = $librarycourse->shortname;
    csv_check(importer::validate([$badcourse])['errors'] === 1, 'No trata una colección de Biblioteca como formación');
    $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $training[0]->testinstance]);
    csv_check(importer::validate([$rows[0]])['errors'] === 1, 'Rechaza formación con matrícula manual deshabilitada');
    $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['id' => $training[0]->testinstance]);
    csv_check($DB->count_records('user') === $before, 'Validación no crea ninguna cuenta');
    csv_denied(fn() => importer::parse($header . ';sysrole1' . "\n" . implode(';', $rows[0]) . ';manager'), 'Rechaza columnas para conceder roles');
    csv_denied(fn() => importer::parse($header . "\n" . implode(';', $rows[0]) . ';extra'), 'Rechaza filas con distinto número de columnas');
    csv_denied(fn() => importer::parse($header), 'Rechaza archivo vacío');
    csv_denied(fn() => importer::parse($header . "\n" . str_repeat(implode(';', $rows[0]) . "\n", 1001)), 'Límite de 1.000 filas');
    $bad = $rows[0]; $bad['cohorts'] = 'Fuera Orange'; $bad['password'] = ''; $bad['username'] = 'admin';
    csv_check(count(importer::validate([$bad])['rows'][0]->errors) >= 3, 'Colisiones globales, contraseña y colectivo ajeno rechazados');
    csv_check(importer::validate([$rows[0], $rows[0]])['errors'] === 1, 'Detecta duplicados dentro del CSV');
    $enrol = $DB->get_record('enrol', ['enrol' => 'cohort', 'customint1' => $cohort], '*', MUST_EXIST);
    $DB->set_field('enrol', 'roleid', $DB->get_field('role', 'id', ['archetype' => 'editingteacher'], IGNORE_MULTIPLE), ['id' => $enrol->id]);
    csv_denied(fn() => importer::parse($csv), 'No concede perfiles privilegiados indirectamente mediante una cohorte');
    $DB->set_field('enrol', 'roleid', $enrol->roleid, ['id' => $enrol->id]);
    importer::stage($report); $state = importer::pending(); $oldtoken = $state['token'];
    csv_check(!str_contains($state['payload'], $pwd) && importer::preview_rows($state)[0]->username === $rows[0]['username'], 'Vista previa cifrada y ligada a sesión');
    $result = importer::process($oldtoken);
    csv_check($result['created'] === 20 && !$result['done'] && $result['token'] !== $oldtoken, 'Primer lote crea 20 cuentas y renueva token');
    csv_denied(fn() => importer::process($oldtoken), 'Una petición repetida no vuelve a crear el lote');
    $result = importer::process($result['token']);
    csv_check($result['done'] && $result['created'] === 21 && !isset(importer::pending()['payload']), 'Segundo lote completa la carga y elimina datos sensibles');
    $user = $DB->get_record('user', ['username' => $rows[0]['username']], '*', MUST_EXIST);
    csv_check($user->idnumber === 'CE0' && admin::owned_account($user->id) &&
        get_user_preferences('auth_forcepasswordchange', 0, $user->id) &&
        $DB->record_exists('cohort_members', ['userid' => $user->id, 'cohortid' => $cohort]) &&
        $DB->record_exists('cohort_members', ['userid' => $user->id, 'cohortid' => (int)get_config('local_orangeportal', 'audiencecohort')]),
        'API nativa conserva CE, cambio de contraseña, Orange y colectivo');
    csv_check(is_enrolled(context_course::instance($courseid), $user->id, '', true), 'Colectivo sincronizado matricula al agente como Estudiante');
    $second = $DB->get_field('user', 'id', ['username' => $rows[1]['username']], MUST_EXIST);
    $third = $DB->get_field('user', 'id', ['username' => $rows[2]['username']], MUST_EXIST);
    $ue = $DB->get_record('user_enrolments', ['enrolid' => $training[0]->testinstance, 'userid' => $user->id], '*', MUST_EXIST);
    csv_check($ue->status == ENROL_USER_ACTIVE && $ue->timeend - $ue->timestart == DAYSECS &&
        is_enrolled(context_course::instance($training[0]->id), $user->id, '', true), 'Matrícula manual activa respeta duración configurada');
    csv_check(is_enrolled(context_course::instance($training[0]->id), $second, '', true) &&
        is_enrolled(context_course::instance($training[1]->id), $second, '', true) &&
        !is_enrolled(context_course::instance($training[0]->id), $third, '', true), 'Cada agente recibe solo las formaciones indicadas en su fila');
    $roles = get_user_roles(context_course::instance($training[0]->id), $user->id, false);
    csv_check(count($roles) === 1 && $DB->get_field('role', 'archetype', ['id' => reset($roles)->roleid]) === 'student' &&
        !has_capability('moodle/course:manageactivities', context_course::instance($training[0]->id), $user->id),
        'CSV fuerza Estudiante aunque la instancia manual tenga Profesor como valor predeterminado');
    csv_check(!has_capability('local/orangeportal:manage', context_system::instance(), $user->id) &&
        !has_capability('local/orangeportal:administer', context_system::instance(), $user->id), 'Cuentas importadas sin privilegios de formación o administración');
    $fresh = $rows[0]; $fresh['username'] .= '_next'; $fresh['email'] = 'next_' . $fresh['email'];
    importer::stage(importer::validate([$fresh])); $token = importer::pending()['token'];
    csv_check(importer::cancel($token) === 0 && !importer::pending(), 'Cancelar antes de confirmar no crea usuarios');
    importer::stage(importer::validate([$fresh])); $SESSION->orangeagentimport['expires'] = time() - 1;
    csv_denied(fn() => importer::process($SESSION->orangeagentimport['token']), 'Vista previa caducada no se procesa');
    importer::stage(importer::validate([$fresh])); $token = importer::pending()['token'];
    $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $training[0]->testinstance]);
    csv_denied(fn() => importer::process($token), 'Antes de crear revalida matrícula y curso disponibles');
    csv_check(!$DB->record_exists('user', ['username' => $fresh['username']]), 'Error de curso no deja una cuenta creada sin su matrícula');
    $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['id' => $training[0]->testinstance]);
    $DB->set_field('cohort', 'idnumber', 'changed_' . $suffix, ['id' => $cohort]);
    csv_denied(fn() => importer::process($token), 'Revalida cambios de colectivos antes de crear cuentas');
    foreach (['formador.orange', 'coordinador.orange', 'usuario.externo'] as $name) {
        \core\session\manager::set_user($DB->get_record('user', ['username' => $name], '*', MUST_EXIST));
        csv_denied(fn() => importer::parse($csv), "$name no importa cuentas");
    }
} finally {
    unset($SESSION->orangeagentimport);
    try { $tx->rollback(new RuntimeException('csv-test-rollback')); }
    catch (RuntimeException $e) { if ($e->getMessage() !== 'csv-test-rollback') { throw $e; } }
}
csv_check($DB->count_records('user') === $before, 'Todos los datos de prueba restaurados');
