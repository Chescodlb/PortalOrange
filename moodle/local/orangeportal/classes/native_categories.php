<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();
/** Portal form backed by Moodle course categories, scoped to one configured root. */
class native_categories {
    public static function root(string $kind): \core_course_category {
        if (!in_array($kind, ['library', 'courses'])) { throw new \invalid_parameter_exception('Sección desconocida'); }
        $id = (int)get_config('local_orangeportal', $kind === 'library' ? 'librarycategory' : 'coursescategory');
        if (!$id) { throw new \invalid_parameter_exception('Falta la categoría raíz'); }
        return \core_course_category::get($id, MUST_EXIST, true);
    }
    public static function records(string $kind): array {
        global $DB;
        $root = self::root($kind);
        return $DB->get_records_sql('SELECT * FROM {course_categories} WHERE path LIKE ? ORDER BY sortorder, id',
            [$root->path . '/%']);
    }
    public static function editable(string $kind, int $id): \core_course_category {
        $records = self::records($kind);
        if (!isset($records[$id])) { throw new \invalid_parameter_exception('La categoría no pertenece a esta sección'); }
        require_capability('moodle/category:manage', \context_coursecat::instance($id));
        return \core_course_category::get($id, MUST_EXIST, true);
    }
    public static function options(string $kind, int $editing = 0): array {
        $root = self::root($kind);
        $records = self::records($kind);
        $blockedpath = $editing && isset($records[$editing]) ? $records[$editing]->path : null;
        $walk = function(int $parent, string $prefix = '') use (&$walk, $records, $blockedpath): array {
            $options = [];
            foreach ($records as $c) {
                if ($c->parent != $parent || ($blockedpath &&
                        ($c->path === $blockedpath || strpos($c->path, $blockedpath . '/') === 0))) { continue; }
                if (has_capability('moodle/category:manage', \context_coursecat::instance($c->id))) {
                    $options[$c->id] = $prefix . $c->name;
                }
                $options += $walk((int)$c->id, $prefix . '— ');
            }
            return $options;
        };
        return [0 => $root->name . ' · raíz de la sección'] + $walk((int)$root->id);
    }
    public static function save(string $kind, int $id, string $name, int $parentid): int {
        $root = self::root($kind);
        require_capability('moodle/category:manage', \context_coursecat::instance($root->id));
        $category = $id ? self::editable($kind, $id) : null;
        if (!array_key_exists($parentid, self::options($kind, $id))) {
            throw new \invalid_parameter_exception('Categoría superior fuera de la sección o ciclo no permitido');
        }
        $parentid = $parentid ?: (int)$root->id;
        require_capability('moodle/category:manage', \context_coursecat::instance($parentid));
        $name = trim($name);
        if ($name === '' || \core_text::strlen($name) > 255) { throw new \invalid_parameter_exception('Nombre no válido'); }
        $data = (object)['name' => $name, 'parent' => $parentid];
        if ($category) {
            $category->update($data);
            return (int)$category->id;
        }
        return (int)\core_course_category::create($data)->id;
    }
}
