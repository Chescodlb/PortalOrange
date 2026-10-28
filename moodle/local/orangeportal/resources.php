<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
use local_orangeportal\native_content;
use local_orangeportal\service;
require_login();
\local_orangeportal\experience::require_access();
require_capability('local/orangeportal:manage', context_system::instance());
$id = required_param('notice', PARAM_INT);
$notice = $DB->get_record('local_orange_notice', ['id' => $id], '*', MUST_EXIST);
service::require_view($notice);
$url = orange_link('resources.php', ['notice' => $id]);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey(); native_content::notice_container($id); redirect($url);
}
$map = native_content::notice_mapping($id);
if ($map) { native_content::author((int)$map->courseid); }
orange_page(get_string('nativemanagepublication', 'local_orangeportal'), $notice->kind, $url);
$PAGE->set_secondary_navigation(false);
echo $OUTPUT->header();
echo orange_admin_navigation($notice->kind, $notice->kind);
echo html_writer::link(orange_link('manage.php', ['kind' => $notice->kind, 'id' => $id]), get_string('editpublication', 'local_orangeportal'));
echo $OUTPUT->heading(s($notice->title));
echo html_writer::tag('p', get_string('nativenoticeinstructions', 'local_orangeportal'));
if ($map) {
    echo native_content::chooser((int)$map->courseid);
    echo native_content::list_html((int)$map->courseid);
} else {
    echo orange_post_button($url, get_string('nativeprepareresources', 'local_orangeportal'));
}
echo html_writer::link(orange_link('view.php', ['id' => $id]), get_string('viewnotice', 'local_orangeportal'), ['class' => 'btn btn-outline-secondary']);
echo $OUTPUT->footer();
