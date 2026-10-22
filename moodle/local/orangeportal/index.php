<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
use local_orangeportal\service;
$section = optional_param('section', 'library', PARAM_ALPHA);
$category = optional_param('category', 0, PARAM_INT);
$query = optional_param('q', '', PARAM_TEXT);
$document = $section === 'library' ? optional_param('document', 0, PARAM_INT) : 0;
$status = optional_param('state', '', PARAM_ALPHA);
$impact = optional_param('impact', '', PARAM_ALPHA);
$unread = optional_param('unread', 0, PARAM_BOOL);
$personal = optional_param('personal', 0, PARAM_BOOL);
$datefrom = in_array($section, ['incidents', 'notifications']) ? optional_param('datefrom', '', PARAM_RAW_TRIMMED) : '';
$dateto = in_array($section, ['incidents', 'notifications']) ? optional_param('dateto', '', PARAM_RAW_TRIMMED) : '';
$titles = array_combine(['library', 'courses', 'incidents', 'notifications'], array_map(
    fn($key) => get_string($key, 'local_orangeportal'), ['library', 'courses', 'incidents', 'notifications']));
if (!isset($titles[$section])) { throw new invalid_parameter_exception('Sección desconocida'); }
$url = orange_link('index.php', ['section' => $section, 'category' => $category, 'q' => $query,
    'state' => $status, 'impact' => $impact, 'datefrom' => $datefrom, 'dateto' => $dateto]);
orange_page($titles[$section], $section, $url);
if (in_array($section, ['library', 'courses'])) { $PAGE->requires->js('/local/orangeportal/library_browser.js'); }
if ($document) { $PAGE->url->param('document', $document); }
if (in_array($section, ['incidents', 'notifications'])) {
    $PAGE->add_body_class('orange-wide-listing');
}
if ($section === 'incidents') {
    echo $OUTPUT->header();
    echo \local_orangeportal\incident_browser::render();
    echo $OUTPUT->footer();
    exit;
}
$dateerror = '';
$datebounds = [];
foreach (['datefrom' => $datefrom, 'dateto' => $dateto] as $field => $value) {
    if ($value === '') { continue; }
    $date = preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value) ?
        DateTimeImmutable::createFromFormat('!Y-m-d', $value, core_date::get_user_timezone_object()) : false;
    if (!$date || $date->format('Y-m-d') !== $value) {
        $dateerror = get_string('invalidnotificationdate', 'local_orangeportal');
        continue;
    }
    $datebounds[$field] = ($field === 'dateto' ? $date->modify('+1 day') : $date)->getTimestamp();
}
if (!$dateerror && $datefrom !== '' && $dateto !== '' && $datefrom > $dateto) {
    $dateerror = get_string('invalidnotificationrange', 'local_orangeportal');
}
echo $OUTPUT->header();
$descriptions = ['library' => 'Procedimientos, manuales y guías para tu operativa diaria.',
    'courses' => 'Aprende, evalúa tus conocimientos y comparte tu valoración.',
    'incidents' => 'Consulta los avisos que afectan a tu equipo y sigue su evolución.',
    'notifications' => 'Noticias, comunicados y alertas dirigidos a ti.'];
echo orange_intro('', $titles[$section], $descriptions[$section]);
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
            (has_capability('mod/resource:addinstance', context_course::instance($c->id)) ||
                has_capability('mod/page:addinstance', context_course::instance($c->id))) &&
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
                get_string($section === 'library' ? 'newcollection' : 'newcourse', 'local_orangeportal'),
                ['class' => 'btn btn-primary orange-create-course']);
        }
    }
    if ($actions) { echo html_writer::div($actions, 'orange-toolbar orange-content-actions'); }
}
if ($section === 'library') {
    echo \local_orangeportal\library_browser::render($category, $query, $document, optional_param('order', '', PARAM_ALPHA));
    echo $OUTPUT->footer();
    exit;
}
if ($section === 'courses') {
    if (has_capability('local/orangeportal:viewreports', context_system::instance())) {
        echo html_writer::div(html_writer::link(orange_link('reports.php', ['section' => 'courses']), get_string('trainingresults', 'local_orangeportal')), 'orange-toolbar');
    }
    echo \local_orangeportal\training_browser::render($category, $query, optional_param('course', 0, PARAM_INT), optional_param('order', '', PARAM_ALPHA));
    echo $OUTPUT->footer();
    exit;
}
$filterfield = function(string $id, string $label, string $control, string $extra = ''): string {
    return html_writer::div(html_writer::tag('label', s($label), ['for' => $id]) . $control,
        'orange-filter-field' . ($extra ? ' ' . $extra : ''));
};
$search = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'section', 'value' => $section]) .
    $filterfield('orange-search', get_string('search'), html_writer::empty_tag('input', ['id' => 'orange-search', 'name' => 'q', 'type' => 'search',
        'value' => $query, 'placeholder' => 'Buscar en ' . strtolower($titles[$section]) . '…', 'class' => 'form-control']), 'orange-filter-search');
if ($section === 'notifications') {
    $categories = $DB->get_records('local_orange_category', ['kind' => 'notifications'], 'name');
    $options = [0 => get_string('allnotificationcategories', 'local_orangeportal')];
    $categoryoptions = function(int $parent, string $prefix = '') use (&$categoryoptions, &$options, $categories): void {
        foreach ($categories as $cat) {
            if ((int)$cat->parentid !== $parent) { continue; }
            $path = $prefix . $cat->name;
            $options[$cat->id] = $path;
            $categoryoptions((int)$cat->id, $path . ' / ');
        }
    };
    $categoryoptions(0);
    $search .= html_writer::div(html_writer::tag('label', get_string('incidentcolumncategory', 'local_orangeportal'),
        ['for' => 'orange-category']) . html_writer::select($options, 'category', $category, false,
            ['id' => 'orange-category', 'class' => 'custom-select']), 'orange-filter-field');
} else {
    $search .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'category', 'value' => $category]);
}
if ($section == 'incidents') {
    $search .= $filterfield('orange-state', get_string('incidentcolumnstate', 'local_orangeportal'),
        html_writer::select(['' => get_string('allstates', 'local_orangeportal')] + service::states(),
            'state', $status, false, ['id' => 'orange-state', 'class' => 'custom-select']));
}
if (in_array($section, ['incidents', 'notifications'])) {
    $search .= $filterfield('orange-impact', get_string('incidentcolumnimpact', 'local_orangeportal'),
        html_writer::select(['' => get_string('allimpacts', 'local_orangeportal')] + service::impacts(),
            'impact', $impact, false, ['id' => 'orange-impact', 'class' => 'custom-select']));
}
if (in_array($section, ['incidents', 'notifications'])) {
    foreach (['datefrom' => $datefrom, 'dateto' => $dateto] as $field => $value) {
        $search .= $filterfield('orange-' . $field, get_string($field, 'local_orangeportal'),
            html_writer::empty_tag('input', ['type' => 'date', 'name' => $field, 'id' => 'orange-' . $field,
                'value' => $value, 'class' => 'form-control']));
    }

}
if ($section === 'notifications') {
    $search .= $filterfield('orange-unread', get_string('updates_reading', 'local_orangeportal'),
        html_writer::select([0 => get_string('updates_allreading', 'local_orangeportal'),
            1 => get_string('updates_unread', 'local_orangeportal')], 'unread', $unread, false,
            ['id' => 'orange-unread', 'class' => 'custom-select']));
}
if ($personal) { $search .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'personal', 'value' => 1]); }
if ($section === 'courses' && $unread) {
    $search .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'unread', 'value' => 1]);
    $search .= html_writer::link(orange_link('index.php', ['section' => 'courses']), get_string('updates_showall', 'local_orangeportal'));
}
$search .= html_writer::tag('button', get_string('search'), ['type' => 'submit', 'class' => 'btn btn-primary']);
$searchform = html_writer::tag('form', $search, ['method' => 'get', 'action' => orange_link('index.php')->out(false),
    'class' => $section === 'notifications' ? 'orange-communications-filter-form' : 'orange-search orange-list-filters']);
if ($section !== 'notifications') { echo $searchform; }
if ($dateerror) { echo $OUTPUT->notification($dateerror, 'error'); }
$tree = ''; $cards = ''; $rows = ''; $count = 0;
if (in_array($section, ['library', 'courses'])) {
    $rootid = (int)get_config('local_orangeportal', $section == 'library' ? 'librarycategory' : 'coursescategory');
    $courses = orange_native_courses($rootid);
    $tree = orange_library_tree($rootid, $category, $courses, $section);
    $access = \local_orangeportal\personal_updates::course_access();
    $joined = \local_orangeportal\personal_updates::joined();
    foreach ($courses as $course) {
        if ($unread && !\local_orangeportal\personal_updates::new_course($course, $access, $joined)) { continue; }
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
        echo html_writer::div(html_writer::link(orange_link('reports.php', ['section' => 'courses']), 'Ver resultados y seguimiento →'), 'orange-toolbar');
    }
} else {
    $notices = service::visible($section);
    $tree = orange_notice_tree($section, $notices, $category,
        ['q' => $query, 'datefrom' => $datefrom, 'dateto' => $dateto,
            'state' => $status, 'impact' => $impact]);
    $cats = $DB->get_records('local_orange_category', ['kind' => $section]);
    $selectedids = $category && isset($cats[$category]) ? service::category_descendants($category, $cats) : [];
    foreach ($notices as $notice) {
        if ($personal && !\local_orangeportal\personal_updates::recipient($notice)) { continue; }
        if ($dateerror) { continue; }
        if (isset($datebounds['datefrom']) && $notice->timecreated < $datebounds['datefrom']) { continue; }
        if (isset($datebounds['dateto']) && $notice->timecreated >= $datebounds['dateto']) { continue; }
        if ($category && !in_array($notice->categoryid, $selectedids)) { continue; }
        if ($query && stripos($notice->title . ' ' . strip_tags($notice->content), $query) === false) { continue; }
        if ($status && $notice->state !== $status) { continue; }
        if ($impact && $notice->impact !== $impact) { continue; }
        $receipt = service::get_receipt($notice, (int)$USER->id);
        if ($unread && $receipt && ($receipt->readtime || $receipt->confirmed)) { continue; }
        service::receipt($notice, 'received');
        $count++;
        $detailurl = orange_link('view.php', ['id' => $notice->id]);
        $title = html_writer::link($detailurl, s($notice->title),
            ['class' => $section === 'incidents' ? 'orange-incident-title' : 'orange-notification-title']);
        if (!$notice->published) {
            $title .= html_writer::span(get_string('incidentdraft', 'local_orangeportal'), 'orange-incident-draft');
        }
        $titlecontent = $title . html_writer::div(
            s(shorten_text(strip_tags($notice->content), 120)), 'orange-incident-summary');
        if (in_array($section, ['incidents', 'notifications'])) {
            $isread = $receipt && ($receipt->readtime || $receipt->confirmed);
            $iconname = $section === 'incidents' ? 'incident' : 'envelope';
            $readingicon = $section === 'incidents' && $isread ?
                html_writer::span('', 'orange-incident-icon', ['aria-hidden' => 'true']) :
                html_writer::empty_tag('img', [
                'src' => $OUTPUT->image_url($iconname . ($isread ? '-read' : '-unread'), 'local_orangeportal'),
                'alt' => get_string($isread ? 'libraryread' : 'incidentunread', 'local_orangeportal'),
                'title' => get_string($isread ? 'libraryread' : 'incidentunread', 'local_orangeportal'), 'width' => 28, 'height' => 28,
                'class' => $section === 'incidents' ? 'orange-incident-icon' : 'orange-notification-envelope',
            ]);
            $titlecontent = html_writer::div($readingicon . html_writer::div($titlecontent),
                $section === 'incidents' ? 'orange-incident-heading' : 'orange-notification-heading');
        }
        $cells = html_writer::tag('td', $titlecontent);
        $cells .= html_writer::tag('td', orange_category_label((int)$notice->categoryid, $cats));
        $cells .= html_writer::tag('td', s(userdate($notice->timecreated, '%d/%m/%Y')));
        if ($section === 'incidents') {
            $cells .= html_writer::tag('td', s(service::impacts()[$notice->impact]));
            $cells .= html_writer::tag('td', html_writer::span(s(service::states()[$notice->state]),
                'orange-status orange-status-' . $notice->state));
        }
        $rows .= html_writer::tag('tr', $cells);
    }
}
if ($rows) {
    $headers = '';
    $columns = $section === 'incidents' ? ['title', 'category', 'date', 'impact', 'state', 'reading'] :
        ['title', 'category', 'date'];
    foreach ($columns as $column) {
        $key = $section === 'notifications' && $column === 'title' ? 'communicationcolumntitle' : 'incidentcolumn' . $column;
        $headers .= html_writer::tag('th', get_string($key, 'local_orangeportal'), ['scope' => 'col']);
    }
    $table = html_writer::tag('caption', $titles[$section], ['class' => 'sr-only']) .
        html_writer::tag('thead', html_writer::tag('tr', $headers)) . html_writer::tag('tbody', $rows);
    $listing = html_writer::div(html_writer::tag('table', $table, ['class' => 'orange-notices-table orange-' . $section . '-table']),
        'orange-table-scroll', ['tabindex' => '0', 'role' => 'region', 'aria-label' => $titles[$section]]);
} else {
    if (!$cards) { $cards = html_writer::div('No hay contenido disponible para esta selección.', 'orange-empty'); }
    $listing = html_writer::div($cards, in_array($section, ['incidents', 'notifications']) ? 'orange-notices-empty' : 'orange-cards');
}
$content = html_writer::tag('p', $count . ' ' . ($section == 'library' ? 'colecciones disponibles' : 'resultados'), ['class' => 'orange-result-count']) .
    $listing;
if ($section === 'notifications') {
    $filtertitle = get_string('communicationfilters', 'local_orangeportal');
    $reset = html_writer::link(orange_link('index.php', ['section' => 'notifications']),
        get_string('incidentclearfilters', 'local_orangeportal'), ['class' => 'orange-incident-reset']);
    echo html_writer::div(html_writer::tag('aside', html_writer::tag('h3', $filtertitle) . $searchform . $reset,
        ['class' => 'orange-incidents-filters orange-communications-filters', 'aria-label' => $filtertitle]) .
        html_writer::div($content, 'orange-incidents-results'), 'orange-incidents-layout orange-communications-layout');
} else if ($tree) {
    $tree = html_writer::tag('h3', $section == 'library' ? 'Explorar biblioteca' : 'Categorías') .
        html_writer::link(orange_link('index.php', ['section' => $section]), 'Ver todo', ['class' => 'orange-tree-all']) . $tree;
    echo html_writer::div(html_writer::tag('aside', $tree, ['class' => 'orange-sidebar', 'aria-label' => 'Árbol de categorías']) .
        html_writer::div($content, 'orange-results'), 'orange-layout');
} else { echo $content; }
echo $OUTPUT->footer();
