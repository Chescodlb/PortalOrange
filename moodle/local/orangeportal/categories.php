<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once($CFG->libdir . '/adminlib.php');
require_login();
$kind = optional_param('kind', 'incidents', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$deleteid = optional_param('delete', 0, PARAM_INT);
if (!in_array($kind, ['library', 'courses', 'incidents', 'notifications'])) { throw new invalid_parameter_exception(get_string('unknownsection', 'local_orangeportal')); }
$native = in_array($kind, ['library', 'courses']);
$url = orange_link('categories.php', ['kind' => $kind]);
if ($native) {
    $root = \local_orangeportal\native_categories::root($kind);
    require_capability('moodle/category:manage', context_coursecat::instance($root->id));
    $record = $id ? \local_orangeportal\native_categories::editable($kind, $id)->get_db_record() : null;
    $options = \local_orangeportal\native_categories::options($kind, $id);
    $excluded = [];
} else {
    require_capability('local/orangeportal:manage', context_system::instance());
    $cats = $DB->get_records('local_orange_category', ['kind' => $kind]);
    $record = $id ? $DB->get_record('local_orange_category', ['id' => $id, 'kind' => $kind], '*', MUST_EXIST) : null;
    $excluded = $id ? \local_orangeportal\service::category_descendants($id, $cats) : [];
    $options = [0 => get_string('categoryroot', 'local_orangeportal')] + orange_category_options($kind);
    foreach ($excluded as $excludedid) { unset($options[$excludedid]); }
}
orange_page(get_string('managecategories', 'local_orangeportal'), $kind, $url);
$PAGE->set_primary_active_tab('siteadminnode');
$PAGE->set_secondary_navigation(false);
if ($deleteid) {
    $categoryservice = $native ? \local_orangeportal\native_categories::class : \local_orangeportal\service::class;
    $deletecategory = $native ? \local_orangeportal\native_categories::editable($kind, $deleteid)->get_db_record() :
        $DB->get_record('local_orange_category', ['id' => $deleteid, 'kind' => $kind], '*', MUST_EXIST);
    $blocker = $categoryservice::category_delete_blocker($kind, $deleteid);
    if ($blocker) {
        redirect($url, get_string($blocker, 'local_orangeportal'), null, \core\output\notification::NOTIFY_ERROR);
    }
    if (data_submitted() && optional_param('confirm', 0, PARAM_BOOL)) {
        require_sesskey();
        $categoryservice::delete_category($kind, $deleteid);
        redirect($url, get_string('categorydeleted', 'local_orangeportal'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    echo $OUTPUT->header();
    $confirmurl = orange_link('categories.php', ['kind' => $kind, 'delete' => $deleteid, 'confirm' => 1]);
    echo $OUTPUT->confirm(get_string('deletecategoryconfirm', 'local_orangeportal', s($deletecategory->name)),
        new single_button($confirmurl, get_string('delete'), 'post'), $url);
    echo $OUTPUT->footer();
    exit;
}
$form = new \local_orangeportal\form\category($url, ['kind' => $kind, 'excluded' => $excluded, 'options' => $options]);
if ($form->is_cancelled()) { redirect($url); }
if ($data = $form->get_data()) {
    if ($data->kind !== $kind) { throw new invalid_parameter_exception(get_string('invalidsection', 'local_orangeportal')); }
    if ($native) {
        \local_orangeportal\native_categories::save($kind, (int)$data->id, $data->name, (int)$data->parentid);
    } else {
        if ($data->parentid) { \local_orangeportal\service::validate_category($kind, (int)$data->parentid); }
        $excluded = $data->id ? \local_orangeportal\service::category_descendants((int)$data->id, $cats) : [];
        if (in_array($data->parentid, $excluded)) { throw new invalid_parameter_exception(get_string('categorycycle', 'local_orangeportal')); }
        $save = (object)['kind' => $kind, 'name' => trim($data->name), 'parentid' => $data->parentid];
        if ($data->id) {
            $DB->get_record('local_orange_category', ['id' => $data->id, 'kind' => $kind], '*', MUST_EXIST);
            $save->id = $data->id; $DB->update_record('local_orange_category', $save);
        } else { $DB->insert_record('local_orange_category', $save); }
    }
    redirect($url, get_string('categorysaved', 'local_orangeportal'), null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($record) {
    if ($native) { $record->parentid = $record->parent == $root->id ? 0 : $record->parent; }
    $form->set_data($record);
}
echo $OUTPUT->header();
$labels = ['library' => get_string('library', 'local_orangeportal'), 'courses' => get_string('courses', 'local_orangeportal'), 'incidents' => get_string('incidents', 'local_orangeportal'), 'notifications' => get_string('notifications', 'local_orangeportal')];
echo orange_intro(get_string('administration'), get_string('categoriesfor', 'local_orangeportal', $labels[$kind]),
    get_string('categoryintro', 'local_orangeportal'));
echo orange_admin_navigation('categories', $kind);
$categorylinks = '';
foreach ($labels as $key => $label) {
    if (in_array($key, ['library', 'courses'])) {
        $rootid = (int)get_config('local_orangeportal', $key === 'library' ? 'librarycategory' : 'coursescategory');
        if (!$rootid || !has_capability('moodle/category:manage', context_coursecat::instance($rootid))) { continue; }
    } else if (!has_capability('local/orangeportal:manage', context_system::instance())) { continue; }
    $attrs = ['class' => 'orange-admin-link' . ($kind === $key ? ' active' : '')];
    if ($kind === $key) { $attrs['aria-current'] = 'page'; }
    $categorylinks .= html_writer::link(orange_link('categories.php', ['kind' => $key]), $label, $attrs);
}
echo html_writer::tag('nav', $categorylinks, ['class' => 'orange-admin-links orange-category-tabs', 'aria-label' => get_string('categorytypes', 'local_orangeportal')]);
$form->display();
$table = new html_table(); $table->head = [get_string('category'), get_string('actions')];
foreach (($native ? array_filter(\local_orangeportal\native_categories::options($kind), fn($id) => $id !== 0, ARRAY_FILTER_USE_KEY) : orange_category_options($kind)) as $catid => $name) {
    $actions = html_writer::link(orange_link('categories.php', ['kind' => $kind, 'id' => $catid]), get_string('editmove', 'local_orangeportal'));
    $actions .= ' · ' . html_writer::link(orange_link('categories.php', ['kind' => $kind, 'delete' => $catid]),
        get_string('delete'), ['aria-label' => get_string('deletecategorynamed', 'local_orangeportal', $name)]);
    $table->data[] = [s($name), $actions];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
