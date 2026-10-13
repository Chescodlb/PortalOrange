<?php
defined('MOODLE_INTERNAL') || die();
function orange_page(string $title, string $section, moodle_url $url): void {
    global $PAGE;
    require_login();
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url($url);
    $PAGE->set_pagelayout('standard');
    $PAGE->set_title($title . ' · Orange');
    $PAGE->set_heading($title);
    $PAGE->set_primary_active_tab('orange_' . $section);
    $PAGE->requires->css('/local/orangeportal/styles.css');
}
function orange_link(string $file, array $params = []): moodle_url {
    return new moodle_url('/local/orangeportal/' . $file, $params);
}
function orange_intro(string $eyebrow, string $title, string $description): string {
    return html_writer::div(html_writer::span(s($eyebrow), 'orange-eyebrow') .
        html_writer::tag('h2', s($title)) . html_writer::tag('p', s($description)), 'orange-intro');
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
function orange_library_tree(int $rootid, int $selected, array $courses): string {
    global $DB;
    if (!$root = $DB->get_record('course_categories', ['id' => $rootid])) { return ''; }
    $cats = $DB->get_records_sql('SELECT * FROM {course_categories} WHERE id = :id OR path LIKE :path ORDER BY sortorder',
        ['id' => $rootid, 'path' => $root->path . '/%']);
    $manager = has_capability('moodle/category:manage', context_coursecat::instance($rootid));
    $ancestry = [];
    foreach ($courses as $course) {
        foreach (explode('/', $cats[$course->category]->path) as $id) { $ancestry[(int)$id] = true; }
    }
    $selectedpath = isset($cats[$selected]) ? explode('/', $cats[$selected]->path) : [];
    $walk = function(int $parent) use (&$walk, $cats, $manager, $ancestry, $selectedpath, $rootid): string {
        $html = '';
        foreach ($cats as $c) {
            if ($c->parent != $parent || (!$manager && !isset($ancestry[$c->id])) ||
                    !core_course_category::get($c->id)->is_uservisible()) { continue; }
            $children = $walk((int)$c->id);
            $link = html_writer::link(orange_link('index.php', ['section' => 'library', 'category' => $c->id]),
                s($c->name), ['class' => 'orange-tree-link']);
            if ($children) {
                $attrs = ['class' => 'orange-tree-node'];
                if ($c->id == $rootid || in_array($c->id, $selectedpath)) { $attrs['open'] = 'open'; }
                $html .= html_writer::tag('details', html_writer::tag('summary', $link) .
                    html_writer::div($children, 'orange-tree-children'), $attrs);
            } else { $html .= html_writer::div($link, 'orange-tree-leaf'); }
        }
        return $html;
    };
    return $walk((int)$root->parent);
}
function orange_notice_tree(string $section, array $notices, int $selected): string {
    global $DB;
    $cats = $DB->get_records('local_orange_category', ['kind' => $section], 'name');
    $walk = function(int $parent) use (&$walk, $cats, $notices, $section, $selected): string {
        $out = '';
        foreach ($cats as $c) {
            if ($c->parentid != $parent) { continue; }
            $ids = \local_orangeportal\service::category_descendants((int)$c->id, $cats);
            $count = count(array_filter($notices, fn($n) => in_array($n->categoryid, $ids)));
            $link = html_writer::link(orange_link('index.php', ['section' => $section, 'category' => $c->id]),
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
