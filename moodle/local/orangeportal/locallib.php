<?php
defined('MOODLE_INTERNAL') || die();
function orange_page(string $title, string $section, moodle_url $url): void {
    global $PAGE;
    require_login();
    \local_orangeportal\experience::require_access();
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url($url);
    $PAGE->set_pagelayout('standard');
    $PAGE->set_title($title . ' · Orange');
    $PAGE->set_heading($title);
    $PAGE->set_primary_active_tab('orange_' . $section);
    $PAGE->requires->css(new \moodle_url('/local/orangeportal/styles.css', ['v' => filemtime(__DIR__ . '/styles.css')]));
}
function orange_link(string $file, array $params = []): moodle_url {
    return new moodle_url('/local/orangeportal/' . $file, $params);
}
function orange_intro(string $eyebrow, string $title, string $description): string {
    return html_writer::div(($eyebrow !== '' ? html_writer::span(s($eyebrow), 'orange-eyebrow') : '') .
        html_writer::tag('h2', s($title)) . html_writer::tag('p', s($description)), 'orange-intro');
}
function orange_admin_navigation(string $active, string $kind = 'incidents'): string {
    $links = [];
    if (\local_orangeportal\incident_workflow::all()) {
        $links['incidenttracking'] = [get_string('incidenttracking', 'local_orangeportal'), orange_link('incident_tracking.php')];
    }
    if (has_capability('local/orangeportal:manage', context_system::instance())) {
        $links += [
            'incidents' => [get_string('incidents', 'local_orangeportal'), orange_link('manage.php', ['kind' => 'incidents'])],
            'notifications' => [get_string('notifications', 'local_orangeportal'), orange_link('manage.php', ['kind' => 'notifications'])],
        ];
    }
    $cangestion = has_capability('local/orangeportal:manage', context_system::instance());
    foreach (['librarycategory', 'coursescategory'] as $setting) {
        $rootid = (int)get_config('local_orangeportal', $setting);
        if ($rootid && has_capability('moodle/category:manage', context_coursecat::instance($rootid))) {
            $cangestion = true;
        }
    }
    if ($cangestion) { $links['categories'] = [get_string('managecategories', 'local_orangeportal'), orange_link('categories.php', ['kind' => $kind])]; }
    if (has_capability('local/orangeportal:viewreports', context_system::instance())) {
        $links['reports'] = [get_string('confirmationsresults', 'local_orangeportal'), orange_link('reports.php')];
    }
    if (has_capability('local/orangeportal:manageoptions', context_system::instance())) {
        $links['options'] = [get_string('manageoptions', 'local_orangeportal'), orange_link('options.php')];
    }
    $html = '';
    foreach ($links as $key => [$label, $url]) {
        $attrs = ['class' => 'orange-admin-link' . ($key === $active ? ' active' : '')];
        if ($key === $active) { $attrs['aria-current'] = 'page'; }
        $html .= html_writer::link($url, $label, $attrs);
    }
    return html_writer::tag('nav', $html, ['class' => 'orange-admin-links', 'aria-label' => get_string('portalmanagement', 'local_orangeportal')]);
}
function orange_category_options(string $kind): array {
    global $DB;
    $cats = $DB->get_records('local_orange_category', ['kind' => $kind], 'name');
    $walk = function(int $parent, string $prefix = '') use (&$walk, $cats): array {
        $out = [];
        foreach ($cats as $c) {
            if ($c->parentid == $parent) {
                $out[$c->id] = $prefix . $c->name;
                $out += $walk((int)$c->id, $prefix . '— ');
            }
        }
        return $out;
    };
    return $walk(0);
}
/** Category name and a compact ancestor path for administrative tables. */
function orange_category_label(int $id, array $categories): string {
    if (!isset($categories[$id])) { return ''; }
    $category = $categories[$id];
    $ancestors = [];
    $visited = [$id => true];
    $parent = (int)$category->parentid;
    while ($parent && isset($categories[$parent]) && !isset($visited[$parent])) {
        $visited[$parent] = true;
        array_unshift($ancestors, $categories[$parent]->name);
        $parent = (int)$categories[$parent]->parentid;
    }
    $label = html_writer::tag('strong', s($category->name), ['class' => 'orange-category-name']);
    if (!$ancestors) { return $label; }
    $fullpath = implode(' › ', $ancestors);
    $truncated = count($ancestors) > 2;
    $shortpath = ($truncated ? '… › ' : '') . implode(' › ', array_slice($ancestors, -2));
    $attrs = ['class' => 'orange-category-path'];
    $path = s($shortpath);
    if ($truncated) {
        $attrs += ['tabindex' => '0', 'aria-label' => $fullpath];
        $path = html_writer::span($path, '', ['aria-hidden' => 'true']) .
            html_writer::span(s($fullpath), 'orange-category-fullpath', ['aria-hidden' => 'true']);
    }
    return $label . html_writer::tag('span', $path, $attrs);
}
function orange_native_courses(int $rootid): array {
    global $DB, $USER;
    if (!$rootid || !$root = $DB->get_record('course_categories', ['id' => $rootid])) { return []; }
    $categories = $DB->get_records_sql('SELECT * FROM {course_categories} WHERE id = :id OR path LIKE :path',
        ['id' => $rootid, 'path' => $root->path . '/%']);
    $allowed = [];
    foreach ($DB->get_records_list('course', 'category', array_keys($categories), 'fullname') as $course) {
        $context = context_course::instance($course->id);
        if ((is_enrolled($context, $USER, '', true) || has_capability('moodle/course:view', $context)) &&
                core_course_category::get($course->category, IGNORE_MISSING)->is_uservisible() &&
                ($course->visible || has_capability('moodle/course:viewhiddencourses', $context))) {
            $allowed[$course->id] = $course;
        }
    }
    return $allowed;
}
function orange_library_tree(int $rootid, int $selected, array $courses, string $section = 'library'): string {
    global $DB;
    if (!$root = $DB->get_record('course_categories', ['id' => $rootid])) { return ''; }
    $cats = $DB->get_records_sql('SELECT * FROM {course_categories} WHERE id = :id OR path LIKE :path ORDER BY sortorder',
        ['id' => $rootid, 'path' => $root->path . '/%']);
    // Category structure is shared; access is enforced when listing/opening content.
    $selectedpath = isset($cats[$selected]) ? explode('/', $cats[$selected]->path) : [];
    $walk = function(int $parent) use (&$walk, $cats, $selectedpath, $rootid, $section): string {
        $html = '';
        foreach ($cats as $c) {
            if ($c->parent != $parent) { continue; }
            $children = $walk((int)$c->id);
            $link = html_writer::link(orange_link('index.php', ['section' => $section, 'category' => $c->id]),
                s($c->name), ['class' => 'orange-tree-link']);
            if ($children) {
                $attrs = ['class' => 'orange-tree-node'];
                if ($c->parent == $rootid || in_array($c->id, $selectedpath)) { $attrs['open'] = 'open'; }
                $html .= html_writer::tag('details', html_writer::tag('summary', $link) .
                    html_writer::div($children, 'orange-tree-children'), $attrs);
            } else { $html .= html_writer::div($link, 'orange-tree-leaf'); }
        }
        return $html;
    };
    return $walk($rootid);
}
function orange_notice_tree(string $section, array $notices, int $selected, array $filters = []): string {
    global $DB;
    $cats = $DB->get_records('local_orange_category', ['kind' => $section], 'name');
    $walk = function(int $parent) use (&$walk, $cats, $notices, $section, $selected, $filters): string {
        $out = '';
        foreach ($cats as $c) {
            if ($c->parentid != $parent) { continue; }
            $ids = \local_orangeportal\service::category_descendants((int)$c->id, $cats);
            $count = count(array_filter($notices, fn($n) => in_array($n->categoryid, $ids)));
            $link = html_writer::link(orange_link('index.php', ['section' => $section, 'category' => $c->id] + $filters),
                s($c->name) . html_writer::span((string)$count, 'orange-count'),
                ['class' => 'orange-tree-link' . ($selected == $c->id ? ' selected' : '')]);
            $children = $walk((int)$c->id);
            $out .= $children ? html_writer::tag('details', html_writer::tag('summary', $link) .
                html_writer::div($children, 'orange-tree-children'), ['open' => 'open', 'class' => 'orange-tree-node']) :
                html_writer::div($link, 'orange-tree-leaf');
        }
        return $out;
    };
    return $walk(0);
}
function orange_post_button(moodle_url $url, string $label, array $values): string {
    $html = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    foreach ($values as $name => $value) {
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
    }
    $html .= html_writer::tag('button', s($label), ['type' => 'submit', 'class' => 'btn btn-primary']);
    return html_writer::tag('form', $html, ['action' => $url->out(false), 'method' => 'post']);
}
