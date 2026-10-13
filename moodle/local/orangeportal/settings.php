<?php
defined('MOODLE_INTERNAL') || die();
$ADMIN->add('root', new admin_category('orangeportal', 'Portal Orange'));
$page = new admin_settingpage('local_orangeportal', 'Configuración Orange');
$page->add(new admin_setting_configcheckbox('local_orangeportal/enabled', 'Navegación Orange',
    'Muestra las cuatro secciones y dirige la entrada a la página seleccionada en Navegación.', 0));
$page->add(new admin_setting_configtext('local_orangeportal/librarycategory', 'Categoría raíz de Biblioteca',
    'ID de la categoría nativa de Moodle.', 0, PARAM_INT));
$page->add(new admin_setting_configtext('local_orangeportal/coursescategory', 'Categoría raíz de Cursos',
    'ID de la categoría nativa de Moodle para formación.', 0, PARAM_INT));
$page->add(new admin_setting_configcheckbox('local_orangeportal/automaticalerts', 'Alertas automáticas de formación',
    'Crea avisos privados al completar un curso o al actualizar una calificación visible.', 1));
$ADMIN->add('orangeportal', $page);
$ADMIN->add('orangeportal', new admin_externalpage('orangeportalmanage', 'Publicar y gestionar avisos',
    new moodle_url('/local/orangeportal/manage.php'), 'local/orangeportal:manage'));
$ADMIN->add('orangeportal', new admin_externalpage('orangeportalcategories', 'Categorías de incidencias y notificaciones',
    new moodle_url('/local/orangeportal/categories.php'), 'local/orangeportal:manage'));
$ADMIN->add('orangeportal', new admin_externalpage('orangeportalreports', 'Resultados y confirmaciones',
    new moodle_url('/local/orangeportal/reports.php'), 'local/orangeportal:viewreports'));
