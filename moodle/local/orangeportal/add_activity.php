<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
use local_orangeportal\native_content;
require_login();
\local_orangeportal\experience::require_access();
$courseid = required_param('course', PARAM_INT);
$section = optional_param('section', 0, PARAM_INT);
$map = native_content::mapping($courseid);
if ($map) { native_content::author($courseid); }
else { native_content::training_course($courseid); }
$course = get_course($courseid);
require_capability('moodle/course:manageactivities', context_course::instance($courseid));
$sectioninfo = get_fast_modinfo($course)->get_section_info($section, MUST_EXIST);
$url = orange_link('add_activity.php', ['course' => $courseid, 'section' => $section]);
$back = $map && $map->kind === 'notice' ? orange_link('resources.php', ['notice' => $map->noticeid]) :
    ($map ? orange_link('index.php', ['section' => 'library', 'category' => $map->categoryid]) :
    orange_link('collections.php', ['course' => $courseid]));
$portal = $map ? ($map->kind === 'library' ? 'library' : $DB->get_field('local_orange_notice', 'kind', ['id' => $map->noticeid])) : 'courses';
orange_page(get_string('nativeaddactivity', 'local_orangeportal'), $portal, $url);
$PAGE->set_secondary_navigation(false);
echo $OUTPUT->header();
echo html_writer::link($back, get_string('backtolist', 'local_orangeportal'));
echo $OUTPUT->heading(get_string('nativeaddactivity', 'local_orangeportal'));
echo native_content::chooser($courseid, $section, true);
echo $OUTPUT->footer();
