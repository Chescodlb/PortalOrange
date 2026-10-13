<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once($CFG->libdir . '/adminlib.php');
require_login();
require_capability('local/orangeportal:manage', context_system::instance());
$kind = optional_param('kind', 'incidents', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
if (!in_array($kind, ['incidents', 'notifications'])) { throw new invalid_parameter_exception('Sección desconocida'); }
admin_externalpage_setup('orangeportalcategories');
$cats = $DB->get_records('local_orange_category', ['kind' => $kind]);
$excluded = $id ? \local_orangeportal\service::category_descendants($id, $cats) : [];
$form = new \local_orangeportal\form\category(null, ['kind' => $kind, 'excluded' => $excluded]);
if ($form->is_cancelled()) { redirect(orange_link('manage.php', ['kind' => $kind])); }
if ($data = $form->get_data()) {
    if ($data->parentid) { \local_orangeportal\service::validate_category($kind, (int)$data->parentid); }
    $excluded = $data->id ? \local_orangeportal\service::category_descendants((int)$data->id, $cats) : [];
    if (in_array($data->parentid, $excluded) || $data->kind !== $kind) {
        throw new invalid_parameter_exception('No se puede crear un ciclo en el árbol');
    }
    $record = (object)['kind' => $kind, 'name' => trim($data->name), 'parentid' => $data->parentid];
    if ($data->id) {
        $DB->get_record('local_orange_category', ['id' => $data->id, 'kind' => $kind], '*', MUST_EXIST);
        $record->id = $data->id; $DB->update_record('local_orange_category', $record);
    } else { $DB->insert_record('local_orange_category', $record); }
    redirect(orange_link('categories.php', ['kind' => $kind]), 'Categoría guardada', null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($id) { $form->set_data($DB->get_record('local_orange_category', ['id' => $id, 'kind' => $kind], '*', MUST_EXIST)); }
echo $OUTPUT->header();
echo orange_intro('ADMINISTRACIÓN', 'Categorías de ' . ($kind == 'incidents' ? 'incidencias' : 'notificaciones'),
    'Puedes crear, renombrar y reorganizar las categorías del árbol.');
echo html_writer::div(html_writer::link(orange_link('categories.php', ['kind' => 'incidents']), 'Incidencias') .
    html_writer::link(orange_link('categories.php', ['kind' => 'notifications']), 'Notificaciones'), 'orange-admin-links');
$form->display();
$table = new html_table(); $table->head = ['Categoría', 'Acciones'];
foreach (orange_category_options($kind) as $catid => $name) {
    $table->data[] = [s($name), html_writer::link(orange_link('categories.php', ['kind' => $kind, 'id' => $catid]), 'Editar / mover')];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
