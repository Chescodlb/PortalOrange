<?php
/** Render the real native URL resource form without creating any activity. */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/url/mod_form.php');
if (!str_starts_with($CFG->wwwroot, 'http://localhost:')) { throw new coding_exception('Local only'); }
$user = $DB->get_record('user', ['username' => 'formador.orange'], '*', MUST_EXIST);
\core\session\manager::set_user($user);
$maps = $DB->get_records('local_orange_container', ['kind' => 'library'], 'id DESC', '*', 0, 1);
$map = reset($maps);
if (!$map) { throw new coding_exception('A library container is required'); }
\local_orangeportal\native_content::author((int)$map->courseid);
$course = get_course($map->courseid);
$PAGE->set_course($course); $PAGE->set_context(context_course::instance($course->id));
$PAGE->set_url('/course/modedit.php', ['course' => $course->id, 'add' => 'url', 'section' => 0]);
$data = (object)['course' => $course->id, 'add' => 'url', 'section' => 0, 'visible' => 1, 'display' => 0,
    'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0]];
$form = new mod_url_mod_form($data, 0, null, $course); $form->set_data($data);
$html = $form->render();
$dom = new DOMDocument(); @$dom->loadHTML($html); $xpath = new DOMXPath($dom);
$selected = $xpath->query('//select[@name="orangecohorts[]"]')->length;
$hint = str_contains($html, 'cohorte Orange') || str_contains($html, 'Orange cohort');
$selectnames = []; foreach ($xpath->query('//select') as $select) { $selectnames[] = $select->getAttribute('name'); }
echo json_encode(['collectivefield' => $selected, 'hint' => $hint, 'section' => \local_orangeportal\content_access::section($course->id),
    'member' => \local_orangeportal\experience::member(), 'author' => \local_orangeportal\content_access::author($course->id), 'selectnames' => $selectnames]) . PHP_EOL;
if (!$selected || !$xpath->query('//select[@name="orangeusers[]"]')->length || !$xpath->query('//select[@name="orangegroups[]"]')->length || !$hint) { throw new RuntimeException('Native library audience field missing'); }
echo "PASS Native resource form contains collective selection and Orange boundary hint\n";
