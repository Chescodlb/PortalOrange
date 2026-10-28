<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/formslib.php');
use local_orangeportal\native_content;
require_login();
\local_orangeportal\experience::require_access();
$id = optional_param('course', 0, PARAM_INT);
$url = orange_link('collections.php', $id ? ['course' => $id] : []);
$course = $id ? native_content::training_course($id) : null;
if ($course) { require_login($course); }
$context = $course ? context_course::instance($id) : context_system::instance();
$manage = $course && has_capability('moodle/course:manageactivities', $context);
class orange_training_collection_form extends moodleform {
    public function definition(): void {
        $m = $this->_form;
        $m->addElement('text', 'name', get_string('nativecollectionname', 'local_orangeportal'), ['maxlength' => 255]);
        $m->setType('name', PARAM_TEXT); $m->addRule('name', null, 'required', null, 'client');
        $this->add_action_buttons(false, get_string('nativenewcollection', 'local_orangeportal'));
    }
}
$form = $manage ? new orange_training_collection_form($url) : null;
if ($form && ($data = $form->get_data())) {
    require_capability('moodle/course:manageactivities', $context);
    $name = trim($data->name);
    if (!$name || core_text::strlen($name) > 255) { throw new invalid_parameter_exception('Invalid collection name'); }
    $section = course_create_section($course);
    course_update_section($course, $section, (object)['name' => $name]);
    redirect(new moodle_url($url, ['collection' => $section->id]));
}
orange_page(get_string('nativecollections', 'local_orangeportal'), 'courses', $url);
$PAGE->set_secondary_navigation(false);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('nativecollections', 'local_orangeportal'));
if (!$course) {
    echo html_writer::tag('p', get_string('nativechooseformation', 'local_orangeportal'));
    foreach (orange_native_courses((int)get_config('local_orangeportal', 'coursescategory')) as $c) {
        echo html_writer::tag('p', html_writer::link(orange_link('collections.php', ['course' => $c->id]), format_string($c->fullname)));
    }
} else {
    echo $OUTPUT->heading(format_string($course->fullname), 3);
    echo html_writer::link(new moodle_url('/course/view.php', ['id' => $id]), get_string('trainingopen', 'local_orangeportal'), ['class' => 'btn btn-primary']);
    if ($manage) {
        echo orange_post_button(new moodle_url('/course/view.php', ['id' => $id]), get_string('nativeeditblocks', 'local_orangeportal'), ['edit' => 1]);
    }
    $info = get_fast_modinfo($course);
    foreach ($info->get_section_info_all() as $section) {
        if (!$section->uservisible) { continue; }
        $title = $section->name ?: get_section_name($course, $section);
        echo html_writer::start_tag('section', ['class' => 'orange-training-collection', 'id' => 'collection-' . $section->id]);
        echo $OUTPUT->heading(format_string($title), 3);
        $summary = file_rewrite_pluginfile_urls($section->summary, 'pluginfile.php', $context->id, 'course', 'section', $section->id);
        echo format_text($summary, $section->summaryformat, ['context' => $context]);
        if ($manage) {
            echo native_content::chooser($id, (int)$section->section);
            echo ' ' . html_writer::link(new moodle_url('/course/editsection.php', ['id' => $section->id]), get_string('edit'), ['class' => 'btn btn-primary']);
        }
        echo native_content::list_html($id, (int)$section->section, false, 'btn btn-primary');
        echo html_writer::end_tag('section');
    }
    if ($form) { echo $OUTPUT->heading(get_string('nativenewcollection', 'local_orangeportal'), 3); $form->display(); }
}
echo $OUTPUT->footer();
