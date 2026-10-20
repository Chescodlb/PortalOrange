<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
use local_orangeportal\notice_options;
require_login();
\local_orangeportal\experience::require_access();
require_capability('local/orangeportal:manageoptions', context_system::instance());
$type = optional_param('type', 'states', PARAM_ALPHA);
$code = optional_param('code', '', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHA);
$records = notice_options::records($type);
if ($code !== '' && !isset($records[$code])) { throw new invalid_parameter_exception('Unknown option'); }
$url = orange_link('options.php', ['type' => $type]);
orange_page(get_string('manageoptions', 'local_orangeportal'), 'incidents', $url);
$PAGE->set_primary_active_tab('siteadminnode');
$PAGE->set_secondary_navigation(false);
if ($action === 'delete') {
    if ($code === '') { throw new invalid_parameter_exception('Unknown option'); }
    if (notice_options::used($type, $code)) {
        redirect($url, get_string('optioninuse', 'local_orangeportal'), null, \core\output\notification::NOTIFY_ERROR);
    }
    if (data_submitted() && optional_param('confirm', 0, PARAM_BOOL)) {
        require_sesskey();
        try { notice_options::delete($type, $code); } catch (moodle_exception $e) {
            redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
        }
        redirect($url, get_string('optiondeleted', 'local_orangeportal'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    echo $OUTPUT->header();
    $confirmurl = orange_link('options.php', ['type' => $type, 'code' => $code, 'action' => 'delete', 'confirm' => 1]);
    echo $OUTPUT->confirm(get_string('deleteoptionconfirm', 'local_orangeportal', s($records[$code]['name'])),
        new single_button($confirmurl, get_string('delete'), 'post'), $url);
    echo $OUTPUT->footer(); exit;
}
$form = new \local_orangeportal\form\notice_option(null, ['type' => $type, 'code' => $code]);
if ($form->is_cancelled()) { redirect($url); }
if ($data = $form->get_data()) {
    if ($data->type !== $type || $data->code !== $code) { throw new invalid_parameter_exception('Invalid option'); }
    try {
        notice_options::save($type, $code, $data->name, (bool)$data->active, $type === 'states' ? (bool)$data->closed : null);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
    redirect($url, get_string('optionsaved', 'local_orangeportal'), null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($code !== '') { $form->set_data(['name' => $records[$code]['name'], 'active' => (int)$records[$code]['active'],
    'closed' => $type === 'states' && notice_options::is_closed($code) ? 1 : 0]); }
echo $OUTPUT->header();
echo orange_intro('ORANGE', get_string('manageoptions', 'local_orangeportal'), get_string('optionsdescription', 'local_orangeportal'));
echo orange_admin_navigation('options');
$tabs = '';
foreach (['states', 'impacts'] as $tab) {
    $tabs .= html_writer::link(orange_link('options.php', ['type' => $tab]), get_string('option' . $tab, 'local_orangeportal'),
        ['class' => 'orange-admin-link' . ($type === $tab ? ' active' : '')] + ($type === $tab ? ['aria-current' => 'page'] : []));
}
echo html_writer::tag('nav', $tabs, ['class' => 'orange-admin-links', 'aria-label' => get_string('manageoptions', 'local_orangeportal')]);
echo $OUTPUT->heading(get_string($code === '' ? 'newoption' : 'editoption', 'local_orangeportal'), 3);
$form->display();
$table = new html_table();
$table->head = [get_string('optionname', 'local_orangeportal'), get_string('optionavailability', 'local_orangeportal')];
if ($type === 'states') { $table->head[] = get_string('incidentstatekind', 'local_orangeportal'); }
$table->head[] = get_string('actions');
foreach ($records as $key => $record) {
    $actions = html_writer::link(orange_link('options.php', ['type' => $type, 'code' => $key]), get_string('edit')) . ' · ' .
        html_writer::link(orange_link('options.php', ['type' => $type, 'code' => $key, 'action' => 'delete']), get_string('delete'),
            ['aria-label' => get_string('deleteoptionnamed', 'local_orangeportal', $record['name'])]);
    $row = [s($record['name']), get_string($record['active'] ? 'optionactive' : 'optioninactive', 'local_orangeportal')];
    if ($type === 'states') { $row[] = get_string(notice_options::is_closed($key) ? 'incidentclosed' : 'incidentopen', 'local_orangeportal'); }
    $row[] = $actions;
    $table->data[] = $row;
}
echo html_writer::table($table);
echo $OUTPUT->footer();
