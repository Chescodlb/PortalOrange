<?php
// Local bootstrap uses Moodle APIs. Daily management is through the web administration.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->libdir . '/grouplib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/feedback/lib.php');
[$options, $unrecognised] = cli_get_params(['assets' => '/tmp/orange-assets', 'help' => false, 'debug' => false], ['h' => 'help']);
if ($options['debug']) { $CFG->debug = DEBUG_DEVELOPER; $CFG->debugdisplay = 1; }
if ($options['help']) { echo "Configura el portal y ejemplos locales. Requiere ORANGE_DEMO_PASSWORD y --assets=/ruta.\n"; exit; }
if (!str_starts_with($CFG->wwwroot, 'http://localhost:') && !str_starts_with($CFG->wwwroot, 'http://127.0.0.1:')) {
    cli_error('Este script está limitado a la instalación local.');
}
$assetdir = rtrim($options['assets'], '/');
$password = getenv('ORANGE_DEMO_PASSWORD');
if (!$password || strlen($password) < 12) { cli_error('Define ORANGE_DEMO_PASSWORD con al menos 12 caracteres.'); }
if (!is_readable($assetdir . '/library-matrix-rows.json')) { cli_error('Falta la extracción local de la matriz.'); }
if (!is_readable($assetdir . '/orange-logo.png')) { cli_error('Faltan los recursos de marca.'); }
\core\session\manager::set_user(get_admin());
if (!get_string_manager()->translation_exists('es')) {
    cli_error('Instala el paquete español desde Administración > Idioma > Paquetes de idioma antes de cargar los ejemplos.');
}
$passworderror = '';
if (!check_password_policy($password, $passworderror)) { cli_error('La contraseña de demostración debe cumplir la política de Moodle (mayúscula, minúscula, número y símbolo).'); }
$rows = json_decode(file_get_contents($assetdir . '/library-matrix-rows.json'), true, 512, JSON_THROW_ON_ERROR);
function orange_demo_category(string $name, int $parent, string $key): \core_course_category {
    global $DB;
    $idnumber = 'orange_' . substr(hash('sha256', $key), 0, 32);
    if ($record = $DB->get_record('course_categories', ['idnumber' => $idnumber])) {
        return \core_course_category::get($record->id);
    }
    return \core_course_category::create((object)['name' => $name, 'parent' => $parent, 'idnumber' => $idnumber]);
}
$library = orange_demo_category('Biblioteca', 0, 'library');
$branch = orange_demo_category('RPV', $library->id, 'library/RPV-CENTREX');
if ($branch->name === 'RPV/CENTREX') { $branch->update(['name' => 'RPV']); }
$centrextop = orange_demo_category('CENTREX', $library->id, 'library/CENTREX');
$training = orange_demo_category('Cursos', 0, 'training');
$process = ''; $subprocess = ''; $processcat = null; $subcat = null; $leafcats = []; $paths = [];
foreach ($rows as $row) {
    $fields = [];
    foreach ($row as $ref => $value) { $fields[$ref[0]] = trim($value); }
    if (($fields['C'] ?? '') === 'PROCESO') { continue; }
    if (!empty($fields['C'])) {
        $process = $fields['C']; $subprocess = ''; $subcat = null;
        $processcat = orange_demo_category($process, $branch->id, 'library/' . $process);
    }
    if (!$processcat) { continue; }
    if (!empty($fields['D'])) {
        $subprocess = $fields['D'];
        $subcat = orange_demo_category($subprocess, $processcat->id, 'library/' . $process . '/' . $subprocess);
    }
    $leaf = $subcat ?? $processcat;
    if (!empty($fields['E'])) {
        $leaf = orange_demo_category($fields['E'], $leaf->id, 'library/' . $process . '/' . $subprocess . '/' . $fields['E']);
    }
    $leafcats[] = $leaf->id;
    $paths[$process . '/' . $subprocess . '/' . ($fields['E'] ?? '')] = $leaf->id;
}
set_config('librarycategory', $library->id, 'local_orangeportal');
set_config('coursescategory', $training->id, 'local_orangeportal');
function orange_demo_course(string $name, string $shortname, int $categoryid, string $summary): \stdClass {
    global $DB;
    if ($course = $DB->get_record('course', ['shortname' => $shortname])) { return $course; }
    return create_course((object)['fullname' => $name, 'shortname' => $shortname, 'category' => $categoryid,
        'summary' => $summary, 'summaryformat' => FORMAT_HTML, 'format' => 'topics', 'numsections' => 1,
        'visible' => 1, 'enablecompletion' => 1, 'showgrades' => 1, 'groupmode' => SEPARATEGROUPS]);
}
$manual1 = orange_demo_course('Guía de alta con terminal · SIM física', 'ORANGE-BIB-ALTA',
    $paths['ALTA/Alta con terminal/SIM Física'], '<p>Manual de ejemplo · v1.0 · Responsable: Formación Orange. Consulta, validación y registro de un alta.</p>');
$manual2 = orange_demo_course('Procedimiento de gestión de incidencias Centrex', 'ORANGE-BIB-CENTREX',
    $paths['GESTIÓN DE INCIDENCIAS/Gestión incidencias Centrex/'], '<p>Guía de ejemplo · v1.0 · Responsable: Operaciones. Pasos para identificar, comunicar y seguir una incidencia.</p>');
$course = orange_demo_course('Operativa Orange B2B · Formación inicial', 'ORANGE-FORMACION-INICIAL', $training->id,
    '<p>Curso de ejemplo con contenido, evaluación automática y encuesta de valoración. Duración orientativa: 20 minutos.</p>');
function orange_demo_page(\stdClass $course, string $name, string $content): void {
    global $DB;
    if ($DB->record_exists('page', ['course' => $course->id, 'name' => $name])) { return; }
    create_module((object)['modulename' => 'page', 'course' => $course->id, 'section' => 1, 'visible' => 1,
        'name' => $name, 'introeditor' => ['text' => 'Contenido demostrativo; sustituir por el procedimiento aprobado.', 'format' => FORMAT_HTML, 'itemid' => 0],
        'content' => $content, 'contentformat' => FORMAT_HTML, 'display' => 0, 'displayoptions' => serialize(['printintro' => 0, 'printlastmodified' => 1]),
        'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1]);
}
orange_demo_page($manual1, 'Manual · Alta con terminal', '<h2>Objetivo</h2><p>Ejemplo para validar el formato del portal; no es un procedimiento operativo aprobado.</p><h2>Pasos operativos</h2><ol><li>Comprobar la solicitud y los datos necesarios.</li><li>Consultar el procedimiento vigente.</li><li>Registrar la gestión y comunicar el resultado.</li></ol><h2>Control documental</h2><p>Versión 1.0 · 13/10/2026 · Responsable: Formación Orange.</p><h2>Histórico</h2><p>1.0: publicación del ejemplo.</p>');
orange_demo_page($manual2, 'Guía · Incidencias Centrex', '<h2>Identificación</h2><p>Ejemplo: identifica el sistema, alcance e impacto. Consulta el aviso activo antes de informar.</p><h2>Seguimiento</h2><p>Abre la incidencia asignada, confirma su lectura y actualiza el estado común cuando proceda.</p><h2>Control documental</h2><p>Versión 1.0 · 13/10/2026 · Responsable: Operaciones. Ejemplo para demostración.</p>');
orange_demo_page($course, 'Antes de empezar', '<h2>Bienvenido a Orange B2B</h2><p>Encuentra los procedimientos en Biblioteca, consulta Incidencias y confirma los Comunicados.</p><p>Completa el test de ejemplo y la encuesta para probar el circuito de formación.</p>');
// Native quiz and question bank APIs, no alternative evaluation engine.
$quiz = $DB->get_record('quiz', ['course' => $course->id, 'name' => 'Evaluación de ejemplo']);
if (!$quiz) {
    $module = (object)['modulename' => 'quiz', 'course' => $course->id, 'section' => 1, 'visible' => 1,
        'name' => 'Evaluación de ejemplo', 'introeditor' => ['text' => 'Una pregunta de ejemplo, dos intentos. Aprobado: 70%.', 'format' => FORMAT_HTML, 'itemid' => 0],
        'timeopen' => 0, 'timeclose' => 0, 'timelimit' => 0, 'overduehandling' => 'autosubmit', 'graceperiod' => 0,
        'preferredbehaviour' => 'deferredfeedback', 'attempts' => 2, 'attemptonlast' => 0, 'grademethod' => 1,
        'decimalpoints' => 2, 'questiondecimalpoints' => -1, 'questionsperpage' => 1, 'navmethod' => 'free',
        'shuffleanswers' => 1, 'grade' => 100, 'gradepass' => 70, 'sumgrades' => 0, 'quizpassword' => '', 'subnet' => '',
        'browsersecurity' => '-', 'delay1' => 0, 'delay2' => 0, 'showuserpicture' => 0, 'showblocks' => 0,
        'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionusegrade' => 1, 'completionpassgrade' => 1,
        'completionattemptsexhausted' => 0, 'completionminattempts' => 0, 'completionunlocked' => 1];
    foreach (['attempt', 'correctness', 'maxmarks', 'marks', 'specificfeedback', 'generalfeedback', 'rightanswer', 'overallfeedback'] as $field) {
        foreach (['immediately', 'open', 'closed'] as $when) { $module->{$field . $when} = 1; }
    }
    $created = create_module($module);
    $quiz = $DB->get_record('quiz', ['id' => $created->instance], '*', MUST_EXIST);
}
if (!$DB->record_exists('quiz_slots', ['quizid' => $quiz->id])) {
    $context = context_course::instance($course->id);
    $qcategory = question_make_default_categories([$context]);
    $editor = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0];
    $form = (object)['category' => $qcategory->id . ',' . $context->id, 'name' => 'Confirmar lectura de un aviso',
        'questiontext' => ['text' => '<p>Abrir un comunicado y confirmar explícitamente su lectura son acciones distintas.</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
        'generalfeedback' => $editor, 'defaultmark' => 1, 'penalty' => 0, 'correctanswer' => 1,
        'feedbacktrue' => ['text' => 'Correcto: abrirlo registra lectura; el botón registra la confirmación.', 'format' => FORMAT_HTML, 'itemid' => 0],
        'feedbackfalse' => ['text' => 'Recuerda pulsar Confirmar para dejar constancia explícita.', 'format' => FORMAT_HTML, 'itemid' => 0]];
    $question = question_bank::get_qtype('truefalse')->save_question((object)['qtype' => 'truefalse'], $form);
    quiz_add_quiz_question($question->id, $quiz, 0, 1);
}
\mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
// Native Feedback activity; one demonstration item stored in its standard schema.
$feedback = $DB->get_record('feedback', ['course' => $course->id, 'name' => 'Valoración de la formación']);
if (!$feedback) {
    $created = create_module((object)['modulename' => 'feedback', 'course' => $course->id, 'section' => 1, 'visible' => 1,
        'name' => 'Valoración de la formación', 'introeditor' => ['text' => 'Encuesta de ejemplo sobre la claridad del contenido.', 'format' => FORMAT_HTML, 'itemid' => 0],
        'anonymous' => 2, 'email_notification' => 0, 'multiple_submit' => 0, 'autonumbering' => 1,
        'site_after_submit' => '', 'page_after_submit' => 'Gracias por tu valoración.', 'page_after_submitformat' => FORMAT_HTML,
        'page_after_submit_editor' => ['text' => 'Gracias por tu valoración.', 'format' => FORMAT_HTML, 'itemid' => 0],
        'publish_stats' => 0, 'timeopen' => 0, 'timeclose' => 0, 'completion' => COMPLETION_TRACKING_AUTOMATIC,
        'completionsubmit' => 1]);
    $feedback = $DB->get_record('feedback', ['id' => $created->instance], '*', MUST_EXIST);
}
if (!$DB->record_exists('feedback_item', ['feedback' => $feedback->id])) {
    require_once($CFG->dirroot . '/mod/feedback/item/multichoice/lib.php');
    $DB->insert_record('feedback_item', (object)['feedback' => $feedback->id, 'template' => 0,
        'name' => '¿Cómo valoras la claridad de la formación?', 'label' => 'claridad', 'position' => 1,
        'typ' => 'multichoice', 'hasvalue' => 1, 'presentation' => 'r' . FEEDBACK_MULTICHOICE_TYPE_SEP .
            implode(FEEDBACK_MULTICHOICE_LINE_SEP, ['1 · Muy baja', '2 · Baja', '3 · Adecuada', '4 · Buena', '5 · Muy buena']),
        'required' => 1, 'dependitem' => 0, 'dependvalue' => '', 'options' => '']);
}
// Course completion uses the native all-activities criterion.
require_once($CFG->dirroot . '/completion/criteria/completion_criteria_activity.php');
$modinfo = get_fast_modinfo($course->id);
foreach ($modinfo->get_cms() as $cm) {
    if ($cm->completion && !$DB->record_exists('course_completion_criteria', ['course' => $course->id, 'moduleinstance' => $cm->id])) {
        $criteria = new completion_criteria_activity();
        $criteriadata = (object)['id' => $course->id, 'criteria_activity' => [$cm->id => 1]];
        $criteria->update_config($criteriadata);
    }
}
function orange_demo_user(string $username, string $firstname, string $lastname, string $password): \stdClass {
    global $DB;
    if ($u = $DB->get_record('user', ['username' => $username, 'mnethostid' => 1])) { return $u; }
    $id = user_create_user((object)['username' => $username, 'password' => $password, 'auth' => 'manual',
        'firstname' => $firstname, 'lastname' => $lastname, 'email' => $username . '@localhost.invalid',
        'confirmed' => 1, 'lang' => 'es', 'timezone' => 'Europe/Madrid', 'mnethostid' => 1]);
    return $DB->get_record('user', ['id' => $id], '*', MUST_EXIST);
}
$agent = orange_demo_user('agente.orange', 'Agente', 'Orange', $password);
$outsider = orange_demo_user('agente.otro', 'Agente', 'Otro equipo', $password);
$coordinator = orange_demo_user('coordinador.orange', 'Coordinación', 'Orange', $password);
$publisher = orange_demo_user('editor.orange', 'Responsable', 'Comunicaciones', $password);
$trainer = orange_demo_user('formador.orange', 'Formación', 'Orange', $password);
// A restricted system role gives publication access without granting full Moodle administration.
function orange_demo_role(string $shortname, string $name, array $caps, array $contextlevels = [CONTEXT_SYSTEM]): int {
    global $DB;
    $role = $DB->get_record('role', ['shortname' => $shortname]);
    if ($role) { return (int)$role->id; }
    $id = create_role($name, $shortname, 'Perfil Orange de demostración. Editable desde Definir roles.');
    set_role_contextlevels($id, $contextlevels);
    foreach ($caps as $cap) { assign_capability($cap, CAP_ALLOW, $id, context_system::instance()->id); }
    return $id;
}
$publishrole = orange_demo_role('orange_publicador', 'Formador Orange', ['local/orangeportal:manage', 'local/orangeportal:changestate', 'local/orangeportal:viewallincidents', 'local/orangeportal:viewreports']);
$reportrole = orange_demo_role('orange_seguimiento', 'Coordinación Orange', ['local/orangeportal:viewreports']);
assign_capability('local/orangeportal:manageoptions', CAP_ALLOW, $publishrole, context_system::instance()->id);
unassign_capability('local/orangeportal:manageoptions', $reportrole, context_system::instance()->id);
role_assign($publishrole, $publisher->id, context_system::instance()->id);
role_assign($publishrole, $trainer->id, context_system::instance()->id);
foreach (['manage', 'changestate', 'viewallincidents', 'viewreports'] as $cap) {
    assign_capability('local/orangeportal:' . $cap, CAP_ALLOW, $publishrole, context_system::instance()->id);
}
assign_capability('local/orangeportal:viewallincidents', CAP_ALLOW, $reportrole, context_system::instance()->id);
role_assign($reportrole, $coordinator->id, context_system::instance()->id);
$categoryrole = orange_demo_role('orange_gestorcategorias', 'Gestión de categorías Orange',
    ['moodle/category:manage'], [CONTEXT_COURSECAT]);
foreach (['librarycategory', 'coursescategory'] as $setting) {
    foreach ([$publisher, $trainer] as $u) {
        role_assign($categoryrole, $u->id, context_coursecat::instance((int)get_config('local_orangeportal', $setting))->id);
    }
}
$contentrole = orange_demo_role('orange_creadorcontenidos', 'Creación de contenidos Orange',
    ['moodle/course:create'], [CONTEXT_COURSECAT]);
foreach (['librarycategory', 'coursescategory'] as $setting) {
    role_assign($contentrole, $trainer->id, context_coursecat::instance((int)get_config('local_orangeportal', $setting))->id);
}
$cohort = $DB->get_record('cohort', ['idnumber' => 'ORANGE-EQUIPO-DEMO']);
if (!$cohort) {
    $id = cohort_add_cohort((object)['contextid' => context_system::instance()->id, 'name' => 'Equipo Orange · demo',
        'idnumber' => 'ORANGE-EQUIPO-DEMO', 'description' => 'Colectivo global para probar permisos y asignaciones.', 'descriptionformat' => FORMAT_HTML, 'visible' => 1]);
    $cohort = $DB->get_record('cohort', ['id' => $id]);
}
foreach ([$agent, $coordinator, $trainer] as $u) { cohort_add_member($cohort->id, $u->id); }
$studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
$teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
$nonteacherrole = $DB->get_record('role', ['shortname' => 'teacher'], '*', MUST_EXIST);
foreach ([$manual1, $manual2, $course] as $c) {
    $enrol = enrol_get_plugin('cohort');
    if (!$DB->record_exists('enrol', ['courseid' => $c->id, 'enrol' => 'cohort', 'customint1' => $cohort->id])) {
        $enrol->add_instance($c, ['customint1' => $cohort->id, 'roleid' => $studentrole->id, 'status' => ENROL_INSTANCE_ENABLED]);
    }
    $manual = enrol_get_plugin('manual');
    $instance = $DB->get_record('enrol', ['courseid' => $c->id, 'enrol' => 'manual']);
    if (!$instance) { $id = $manual->add_instance($c); $instance = $DB->get_record('enrol', ['id' => $id]); }
    $manual->enrol_user($instance, $coordinator->id, $nonteacherrole->id);
    $manual->enrol_user($instance, $trainer->id, $teacherrole->id);
}
require_once($CFG->dirroot . '/enrol/cohort/locallib.php');
enrol_cohort_sync(new null_progress_trace(), null);
$group = $DB->get_record('groups', ['courseid' => $course->id, 'idnumber' => 'ORANGE-DEMO-GRUPO']);
if (!$group) {
    $id = groups_create_group((object)['courseid' => $course->id, 'name' => 'Equipo de formación · demo',
        'idnumber' => 'ORANGE-DEMO-GRUPO', 'description' => '', 'descriptionformat' => FORMAT_HTML]);
    $group = $DB->get_record('groups', ['id' => $id]);
}
groups_add_member($group->id, $agent->id);
function orange_demo_notice_category(string $kind, string $name, int $parent = 0): int {
    global $DB;
    $r = $DB->get_record('local_orange_category', ['kind' => $kind, 'name' => $name, 'parentid' => $parent]);
    return $r ? (int)$r->id : $DB->insert_record('local_orange_category', (object)['kind' => $kind, 'name' => $name, 'parentid' => $parent]);
}
$systems = orange_demo_notice_category('incidents', 'Sistemas');
$centrex = orange_demo_notice_category('incidents', 'Centrex', $systems);
$network = orange_demo_notice_category('incidents', 'Red móvil');
$news = orange_demo_notice_category('notifications', 'Noticias');
$registry = orange_demo_notice_category('notifications', 'Registro');
$communications = orange_demo_notice_category('notifications', 'Comunicado');
$examples = [
    ['incidents', $centrex, 'Ejemplo · Intermitencia en consultas Centrex', '<p>Aviso de ejemplo: algunas consultas pueden tardar más de lo habitual.</p><p><strong>Impacto:</strong> consulta de servicios. El equipo responsable mantiene el seguimiento.</p><p>Confirma la lectura y utiliza el estado compartido para probar la operativa.</p>', 'high', ['cohort' => [$cohort->id]]],
    ['incidents', $network, 'Ejemplo · Revisión de conectividad de tu equipo', '<p>Aviso de ejemplo asignado individualmente al agente Orange.</p>', 'normal', ['user' => [$agent->id]]],
    ['notifications', $communications, 'Ejemplo · Nuevo procedimiento de altas', '<p>Hay una guía de ejemplo disponible en Biblioteca. Consulta el contenido y confirma este comunicado.</p>', 'normal', ['cohort' => [$cohort->id]]],
    ['notifications', $news, 'Ejemplo · Bienvenido al portal Orange', '<p>Tu punto de acceso a documentación, cursos y avisos. Esta noticia está dirigida al grupo del curso.</p>', 'normal', ['group' => [$group->id]]],
];
foreach ($examples as [$kind, $categoryid, $title, $content, $impact, $targets]) {
    if (!$DB->record_exists('local_orange_notice', ['title' => $title, 'kind' => $kind])) {
        \local_orangeportal\service::save_notice((object)['kind' => $kind, 'categoryid' => $categoryid, 'title' => $title,
            'content' => $content, 'impact' => $impact, 'state' => 'open', 'published' => 1], $targets);
    }
}
// Orange is an opt-in experience; never write brand settings to the site's Moove theme.
set_config('brandcolor', '#FF7900', 'theme_orange');
set_config('secondarymenucolor', '#000000', 'theme_orange');
set_config('fontsite', 'Moodle', 'theme_orange');
$orangecohort = $DB->get_record('cohort', ['idnumber' => 'orange_portal_users']);
if (!$orangecohort) {
    $orangecohort = (object)['contextid' => context_system::instance()->id,
        'name' => 'Usuarios Orange', 'idnumber' => 'orange_portal_users', 'visible' => 1];
    $orangecohort->id = cohort_add_cohort($orangecohort);
}
foreach (['agente.orange', 'agente.otro', 'editor.orange', 'coordinador.orange', 'formador.orange'] as $username) {
    cohort_add_member($orangecohort->id, $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST));
}
set_config('allowcohortthemes', 1);
$orangecohort->theme = 'orange';
cohort_update_cohort($orangecohort);
set_config('audiencecohort', $orangecohort->id, 'local_orangeportal');
set_config('automaticalerts', 1, 'local_orangeportal');
set_config('enabled', 1, 'local_orangeportal');
require(__DIR__ . '/configure_consultation_demo.php');
theme_reset_all_caches();
echo "Portal local configurado. Biblioteca raíz: {$library->id}; rama RPV: {$branch->id}.\n";
echo 'Categorías de biblioteca: ' . $DB->count_records_select('course_categories', 'path LIKE ?', [$library->path . '/%']) . "\n";
echo "Usuarios de prueba: agente.orange, agente.otro, coordinador.orange, editor.orange, formador.orange.\n";
echo "Contraseña: ORANGE_DEMO_PASSWORD de .env (no se muestra).\n";
