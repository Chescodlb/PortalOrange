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
    /** Include hidden courses and categories when checking whether the category is empty. */
    public static function category_delete_blocker(string $kind, int $id): string {
        global $DB;
        $root = self::root($kind);
        require_capability('moodle/category:manage', \context_coursecat::instance($root->id));
        $category = self::editable($kind, $id);
        if (!$category->can_delete()) {
            throw new \required_capability_exception(\context_coursecat::instance($id),
                'moodle/category:manage', 'nopermissions', '');
        }
        if ($DB->record_exists('course_categories', ['parent' => $id])) { return 'categoryhassubcategories'; }
        if ($DB->record_exists('course', ['category' => $id])) {
            return $kind === 'library' ? 'categoryhascollections' : 'categoryhascourses';
        }
        if (!$category->can_move_content_to((int)$category->parent)) {
            return 'categorydeletefailed';
        }
        return '';
    }
    public static function delete_category(string $kind, int $id): void {
        $blocker = self::category_delete_blocker($kind, $id);
        if ($blocker) { throw new \moodle_exception($blocker, 'local_orangeportal'); }
        $category = self::editable($kind, $id);
        // Native deletion preserves category context content by moving it to its parent.
        if (!$category->delete_move((int)$category->parent, false)) {
            throw new \moodle_exception('categorydeletefailed', 'local_orangeportal');
        }
    }
}
