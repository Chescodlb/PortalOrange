<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
use local_orangeportal\service;
$section = optional_param('section', 'library', PARAM_ALPHA);
$category = optional_param('category', 0, PARAM_INT);
$query = optional_param('q', '', PARAM_TEXT);
$status = optional_param('state', '', PARAM_ALPHA);
$pending = optional_param('pending', 0, PARAM_BOOL);
$titles = ['library' => 'Biblioteca', 'courses' => 'Cursos', 'incidents' => 'Incidencias', 'notifications' => 'Notificaciones'];
if (!isset($titles[$section])) { throw new invalid_parameter_exception('Sección desconocida'); }
$url = orange_link('index.php', ['section' => $section, 'category' => $category, 'q' => $query,
    'state' => $status, 'pending' => $pending]);
orange_page($titles[$section], $section, $url);
echo $OUTPUT->header();
$descriptions = ['library' => 'Procedimientos, manuales y guías para tu operativa diaria.',
    'courses' => 'Aprende, evalúa tus conocimientos y comparte tu valoración.',
    'incidents' => 'Consulta los avisos que afectan a tu equipo y sigue su evolución.',
    'notifications' => 'Noticias, comunicados y alertas dirigidos a ti.'];
echo orange_intro('ORANGE · PORTAL DE AGENTES', $titles[$section], $descriptions[$section]);
if (in_array($section, ['library', 'courses']) ?
        has_capability('moodle/category:manage', context_coursecat::instance((int)get_config('local_orangeportal',
            $section == 'library' ? 'librarycategory' : 'coursescategory'))) : service::manager()) {
    $manageurl = in_array($section, ['library', 'courses']) ? orange_link('categories.php', ['kind' => $section]) :
        orange_link('manage.php', ['kind' => $section]);
    echo html_writer::div(html_writer::link($manageurl, 'Gestionar ' . strtolower($titles[$section]),
        ['class' => 'btn btn-outline-secondary']), 'orange-toolbar');
}
if (in_array($section, ['library', 'courses'])) {
    $rootid = (int)get_config('local_orangeportal', $section === 'library' ? 'librarycategory' : 'coursescategory');
    $root = core_course_category::get($rootid, MUST_EXIST, true);
    $actions = '';
    if ($section === 'library' && array_filter(orange_native_courses($rootid), fn($c) =>
            has_capability('mod/resource:addinstance', context_course::instance($c->id)) &&
            has_capability('moodle/course:manageactivities', context_course::instance($c->id)))) {
        $actions .= html_writer::link(orange_link('add_document.php'), 'Nuevo documento', ['class' => 'btn btn-primary']);
    }
    if (has_capability('moodle/course:create', context_coursecat::instance($rootid))) {
        $destination = $category && $DB->record_exists_select('course_categories',
            'id = ? AND (id = ? OR path LIKE ?)', [$category, $rootid, $root->path . '/%']) ? $category : $rootid;
        if (has_capability('moodle/course:create', context_coursecat::instance($destination))) {
            $actions .= html_writer::link(new moodle_url('/course/edit.php', ['category' => $destination,
                'returnto' => 'url', 'returnurl' => orange_link('index.php', ['section' => $section])->out(false),
                'sesskey' => sesskey()]),
                $section === 'library' ? 'Nueva colección documental' : 'Nuevo curso',
                ['class' => 'btn btn-primary orange-create-course']);
        }
    }
    if ($actions) { echo html_writer::div($actions, 'orange-toolbar orange-content-actions'); }
}
$search = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'section', 'value' => $section]) .
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'category', 'value' => $category]) .
    html_writer::tag('label', 'Buscar en ' . strtolower($titles[$section]), ['for' => 'orange-search', 'class' => 'sr-only']) .
    html_writer::empty_tag('input', ['id' => 'orange-search', 'name' => 'q', 'type' => 'search',
        'value' => $query, 'placeholder' => 'Buscar en ' . strtolower($titles[$section]) . '…', 'class' => 'form-control']);
if ($section == 'incidents') {
    $search .= html_writer::select(['' => 'Todos los estados'] + service::states(), 'state', $status, false,
        ['aria-label' => 'Estado', 'class' => 'custom-select']);
}
if (in_array($section, ['incidents', 'notifications'])) {
    $search .= html_writer::select([0 => 'Todas las confirmaciones', 1 => 'Pendientes de confirmar'], 'pending', $pending,
        false, ['aria-label' => 'Confirmaciones', 'class' => 'custom-select']);
}
$search .= html_writer::tag('button', 'Buscar', ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::tag('form', $search, ['method' => 'get', 'action' => orange_link('index.php')->out(false), 'class' => 'orange-search']);
$tree = ''; $cards = ''; $count = 0;
if (in_array($section, ['library', 'courses'])) {
    $rootid = (int)get_config('local_orangeportal', $section == 'library' ? 'librarycategory' : 'coursescategory');
    $courses = orange_native_courses($rootid);
    $tree = orange_library_tree($rootid, $category, $courses, $section);
    foreach ($courses as $course) {
        $cat = $DB->get_record('course_categories', ['id' => $course->category]);
        if ($category && !in_array($category, explode('/', $cat->path))) { continue; }
        if ($query && stripos($course->fullname . ' ' . strip_tags($course->summary), $query) === false) { continue; }
        $count++;
        $badge = $section == 'library' ? 'DOCUMENTACIÓN' : 'FORMACIÓN';
        $editbutton = has_capability('moodle/course:manageactivities', context_course::instance($course->id)) ?
            html_writer::div(orange_post_button(new moodle_url('/course/view.php', ['id' => $course->id]),
                $section == 'library' ? 'Editar documentos' : 'Editar curso', ['edit' => 1]), 'orange-card-edit') : '';
        if ($section === 'courses' && has_capability('mod/quiz:addinstance', context_course::instance($course->id)) &&
                has_capability('moodle/course:manageactivities', context_course::instance($course->id))) {
            $editbutton .= html_writer::link(new moodle_url('/course/modedit.php', ['add' => 'quiz',
                'course' => $course->id, 'section' => 0, 'return' => 0]), 'Nueva evaluación',
                ['class' => 'btn btn-outline-secondary orange-create-quiz']);
        }
        $cards .= html_writer::tag('article', html_writer::span($badge, 'orange-eyebrow') .
            html_writer::tag('h3', html_writer::link(new moodle_url('/course/view.php', ['id' => $course->id]), s($course->fullname))) .
            format_text($course->summary, $course->summaryformat, ['context' => context_course::instance($course->id)]) .
            html_writer::link(new moodle_url('/course/view.php', ['id' => $course->id]),
                $section == 'library' ? 'Consultar documentación →' : 'Abrir curso →', ['class' => 'orange-card-link']) . $editbutton,
            ['class' => 'orange-card']);
    }
    if ($section == 'courses' && has_capability('local/orangeportal:viewreports', context_system::instance())) {
        echo html_writer::div(html_writer::link(orange_link('reports.php'), 'Ver resultados y seguimiento →'), 'orange-toolbar');
    }
} else {
    $notices = service::visible($section);
    $tree = orange_notice_tree($section, $notices, $category);
    $cats = $DB->get_records('local_orange_category', ['kind' => $section]);
    $selectedids = $category && isset($cats[$category]) ? service::category_descendants($category, $cats) : [];
    foreach ($notices as $notice) {
        if ($category && !in_array($notice->categoryid, $selectedids)) { continue; }
        if ($query && stripos($notice->title . ' ' . strip_tags($notice->content), $query) === false) { continue; }
        if ($status && $notice->state !== $status) { continue; }
        $receipt = service::get_receipt($notice, (int)$USER->id);
        if ($pending && $receipt && $receipt->confirmed) { continue; }
        service::receipt($notice, 'received');
        $count++;
        $label = $receipt && $receipt->confirmed ? 'Confirmado' : ($receipt && $receipt->readtime ? 'Leído · pendiente de confirmar' : 'Pendiente de lectura');
        $meta = s($cats[$notice->categoryid]->name) . ' · ' . userdate($notice->timecreated, '%d/%m/%Y');
        if ($section == 'incidents') { $meta .= ' · ' . service::impacts()[$notice->impact]; }
        $cards .= html_writer::tag('article', html_writer::span($notice->published ? s($meta) : 'BORRADOR · ' . s($meta), 'orange-card-meta') .
            html_writer::tag('h3', html_writer::link(orange_link('view.php', ['id' => $notice->id]), s($notice->title))) .
            html_writer::tag('p', s(shorten_text(strip_tags($notice->content), 180))) .
            html_writer::div(($section == 'incidents' ? html_writer::span(service::states()[$notice->state],
                'orange-status orange-status-' . $notice->state) : '') . html_writer::span(s($label), 'orange-reading'), 'orange-card-footer') .
            html_writer::link(orange_link('view.php', ['id' => $notice->id]), 'Leer aviso →', ['class' => 'orange-card-link']),
            ['class' => 'orange-card']);
    }
}
if (!$cards) { $cards = html_writer::div('No hay contenido disponible para esta selección.', 'orange-empty'); }
$content = html_writer::tag('p', $count . ' ' . ($section == 'library' ? 'colecciones disponibles' : 'resultados'), ['class' => 'orange-result-count']) .
    html_writer::div($cards, 'orange-cards');
if ($tree) {
    $tree = html_writer::tag('h3', $section == 'library' ? 'Explorar biblioteca' : 'Categorías') .
        html_writer::link(orange_link('index.php', ['section' => $section]), 'Ver todo', ['class' => 'orange-tree-all']) . $tree;
    echo html_writer::div(html_writer::tag('aside', $tree, ['class' => 'orange-sidebar', 'aria-label' => 'Árbol de categorías']) .
        html_writer::div($content, 'orange-results'), 'orange-layout');
} else { echo $content; }
echo $OUTPUT->footer();
