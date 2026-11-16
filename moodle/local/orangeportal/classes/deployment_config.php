<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** Repeatable CLI provisioning through Moodle APIs; no HTTP calls or cron execution. */
class deployment_config {
    private const PORTAL = ['enabled', 'library', 'training', 'audience', 'managedcohorts', 'landingsection', 'automaticalerts'];
    private static function keys(array $data, array $allowed): void {
        if (array_diff(array_keys($data), $allowed)) { throw new \coding_exception('Campo de configuración desconocido. Revisar la plantilla.'); }
    }
    private static function boolean($value): bool {
        if (!is_bool($value)) { throw new \coding_exception('Las opciones booleanas deben ser true o false.'); } return $value;
    }
    private static function private_file(string $file): string {
        global $CFG;
        $path = realpath($file); $web = realpath($CFG->dirroot);
        if (!$path || !is_file($path) || !is_readable($path) || $path === $web || str_starts_with($path, $web . DIRECTORY_SEPARATOR)) {
            throw new \coding_exception('El archivo debe ser legible y estar fuera del directorio web de Moodle.');
        }
        return $path;
    }
    private static function path(string $file, string $directory): string {
        return self::private_file(str_starts_with($file, '/') ? $file : $directory . '/' . $file);
    }
    public static function read(string $file): array {
        $path = self::private_file($file);
        if (filesize($path) > 65536) { throw new \coding_exception('Archivo de configuración demasiado grande.'); }
        return [json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR), dirname($path)];
    }
    /** A numeric ID selects an existing object; an idnumber descriptor can create only a new one. */
    private static function resource($selector, bool $cohort): array {
        global $DB;
        $table = $cohort ? 'cohort' : 'course_categories';
        if (is_int($selector) && $selector > 0) {
            $record = $DB->get_record($table, ['id' => $selector], '*', MUST_EXIST);
            if ($cohort && ($record->contextid != \context_system::instance()->id || $record->component !== '')) {
                throw new \coding_exception('La audiencia debe ser una cohorte manual de sistema.');
            }
            return ['record' => $record, 'create' => false];
        }
        if (!is_array($selector)) { throw new \coding_exception('Indicar un ID válido o una definición con idnumber.'); }
        self::keys($selector, ['idnumber', 'name', 'create']);
        $idnumber = $selector['idnumber'] ?? '';
        if (!is_string($idnumber) || trim($idnumber) === '' || strlen($idnumber) > 100) { throw new \coding_exception('Número ID no válido.'); }
        $records = $DB->get_records($table, ['idnumber' => $idnumber]);
        if (count($records) > 1) { throw new \coding_exception('Número ID ambiguo: seleccionar por ID numérico.'); }
        if ($records) { return self::resource((int)reset($records)->id, $cohort); }
        if (!self::boolean($selector['create'] ?? false) || empty($selector['name']) || !is_string($selector['name'])) {
            throw new \coding_exception('No existe el Número ID; indicar nombre y create=true para crearlo.');
        }
        return ['record' => (object)['id' => 0, 'idnumber' => $idnumber, 'name' => $selector['name']], 'create' => true];
    }
    public static function plan(array $data, string $directory): array {
        global $CFG, $DB;
        self::keys($data, ['format', 'site_url', 'php_cli', 'portal', 'theme', 'teams', 'catalog_file', 'allow_scope_change', 'administrators']);
        if (($data['format'] ?? null) !== 1 || rtrim($data['site_url'] ?? '', '/') !== rtrim($CFG->wwwroot, '/')) {
            throw new \coding_exception('Formato o site_url incorrectos. El archivo debe identificar exactamente este campus.');
        }
        $portal = $data['portal'] ?? [];
        if (!is_array($portal)) { throw new \coding_exception('portal debe ser un objeto.'); }
        self::keys($portal, self::PORTAL);
        $out = ['settings' => [], 'core' => [], 'lines' => [], 'theme' => false, 'catalog' => null];
        foreach (['library' => 'librarycategory', 'training' => 'coursescategory', 'audience' => 'audiencecohort'] as $name => $setting) {
            $selected = $portal[$name] ?? (int)get_config('local_orangeportal', $setting);
            $resource = self::resource($selected, $name === 'audience');
            $old = (int)get_config('local_orangeportal', $setting);
            if ($old && $old !== (int)$resource['record']->id && !self::boolean($data['allow_scope_change'] ?? false)) {
                throw new \coding_exception('Cambiar raíces o audiencia existentes requiere allow_scope_change=true en el archivo revisado.');
            }
            $out['resources'][$name] = $resource;
            $out['lines'][] = $name . ': ' . ($resource['create'] ? 'crear por Número ID ' . $resource['record']->idnumber : 'usar ID ' . $resource['record']->id);
        }
        $library = $out['resources']['library']['record']; $training = $out['resources']['training']['record'];
        if (($library->id && $library->id === $training->id) || (!$library->id && !$training->id && $library->idnumber === $training->idnumber) ||
                ($library->id && $training->id && (str_starts_with($library->path . '/', $training->path . '/') || str_starts_with($training->path . '/', $library->path . '/')))) {
            throw new \coding_exception('Biblioteca y Formaciones deben ser categorías distintas y no estar anidadas entre sí.');
        }
        foreach (['enabled', 'automaticalerts'] as $key) {
            if (array_key_exists($key, $portal)) { $out['settings'][$key] = (int)self::boolean($portal[$key]); }
        }
        if (isset($portal['landingsection'])) {
            if (!in_array($portal['landingsection'], ['library', 'courses', 'incidents', 'notifications'], true)) { throw new \coding_exception('Sección de entrada incorrecta.'); }
            $out['settings']['landingsection'] = $portal['landingsection'];
        }
        if (array_key_exists('managedcohorts', $portal)) {
            if (!is_array($portal['managedcohorts']) || array_filter($portal['managedcohorts'], fn($v) => !is_int($v) || $v <= 0)) {
                throw new \coding_exception('managedcohorts debe contener IDs numéricos de cohortes.');
            }
            $ids = array_values(array_unique($portal['managedcohorts']));
            foreach ($ids as $id) {
                $cohort = self::resource($id, true);
                $base = (int)$out['resources']['audience']['record']->id;
                if (!$base || $id === $base || $DB->record_exists_sql('SELECT 1 FROM {cohort_members} m WHERE m.cohortid=? AND NOT EXISTS
                        (SELECT 1 FROM {cohort_members} a WHERE a.cohortid=? AND a.userid=m.userid)', [$id, $base])) {
                    throw new \coding_exception('Un colectivo delegado contiene miembros ajenos a la audiencia elegida.');
                }
                foreach ($DB->get_records('enrol', ['enrol' => 'cohort', 'customint1' => $id]) as $instance) {
                    $cat = $DB->get_record_sql('SELECT cat.* FROM {course_categories} cat JOIN {course} c ON cat.id=c.category WHERE c.id=?', [$instance->courseid]);
                    $path = $cat ? array_map('intval', explode('/', $cat->path)) : [];
                    if (!array_intersect(array_filter([(int)$library->id, (int)$training->id]), $path)) {
                        throw new \coding_exception('Un colectivo delegado sincroniza cursos ajenos a las raíces elegidas.');
                    }
                }
            }
            $out['settings']['managedcohorts'] = implode(',', $ids);
        }
        if (isset($data['php_cli'])) {
            $php = $data['php_cli'];
            if (!is_string($php) || !str_starts_with($php, '/') || !is_file($php) || !is_executable($php)) { throw new \coding_exception('php_cli debe ser la ruta absoluta de un ejecutable disponible en este servidor.'); }
            if (isset($CFG->config_php_settings['pathtophp']) && $CFG->config_php_settings['pathtophp'] !== $php) { throw new \coding_exception('pathtophp está forzado en config.php con otro valor.'); }
            $out['core']['pathtophp'] = $php;
        }
        if (isset($data['theme'])) {
            self::keys($data['theme'], ['apply_to_cohort', 'enable_cohort_themes']);
            $out['theme'] = self::boolean($data['theme']['apply_to_cohort'] ?? false);
            if (array_key_exists('enable_cohort_themes', $data['theme'])) { $out['core']['allowcohortthemes'] = (int)self::boolean($data['theme']['enable_cohort_themes']); }
            if ($out['theme']) {
                if (!is_readable($CFG->dirroot . '/theme/orange/config.php') || !is_readable($CFG->dirroot . '/theme/moove/config.php') ||
                        !($out['core']['allowcohortthemes'] ?? $CFG->allowcohortthemes ?? false) ||
                        !$DB->record_exists('config_plugins', ['plugin' => 'theme_orange', 'name' => 'version']) ||
                        !$DB->record_exists('config_plugins', ['plugin' => 'theme_moove', 'name' => 'version'])) {
                    throw new \coding_exception('Instalar Orange/Moove y habilitar temas de cohortes antes de asignar el tema.');
                }
                if (isset($CFG->config_php_settings['allowcohortthemes']) && !$CFG->config_php_settings['allowcohortthemes']) {
                    throw new \coding_exception('Los temas de cohortes están desactivados en config.php.');
                }
            }
        }
        if (isset($data['teams'])) {
            $teams = $data['teams']; self::keys($teams, ['enabled', 'url_file', 'types', 'actions']);
            if (array_key_exists('enabled', $teams)) { $out['settings']['teamsenabled'] = (int)self::boolean($teams['enabled']); }
            if (isset($teams['url_file'])) {
                $file = self::path($teams['url_file'], $directory);
                if (filesize($file) > 16384) { throw new \coding_exception('Archivo de URL demasiado grande.'); }
                $url = trim(file_get_contents($file));
                if ($url !== '' && !teams::valid_endpoint($url)) { throw new \coding_exception('El archivo de URL debe contener una URL HTTPS válida o estar vacío.'); }
                $out['settings']['teamsurl'] = $url;
            }
            if (($out['settings']['teamsenabled'] ?? get_config('local_orangeportal', 'teamsenabled')) &&
                    !teams::valid_endpoint((string)($out['settings']['teamsurl'] ?? get_config('local_orangeportal', 'teamsurl')))) {
                throw new \coding_exception('No se puede activar Teams sin una URL HTTPS válida.');
            }
            foreach (['types' => teams::KINDS, 'actions' => ['created', 'updated']] as $key => $allowed) {
                if (!array_key_exists($key, $teams)) { continue; }
                if (!is_array($teams[$key]) || array_diff($teams[$key], $allowed)) { throw new \coding_exception('Tipo o acción de Teams desconocidos.'); }
                foreach ($allowed as $value) { $out['settings']['teams_' . $value] = (int)in_array($value, $teams[$key], true); }
            }
        }
        if (isset($data['catalog_file'])) {
            $file = self::path($data['catalog_file'], $directory); $hash = hash_file('sha256', $file);
            $previous = get_config('local_orangeportal', 'deploymentcataloghash');
            if ($previous && !hash_equals($previous, $hash)) { throw new \coding_exception('El catálogo inicial ya fue aplicado con otro archivo. Las diferencias requieren una migración revisada.'); }
            if (!$previous) {
                foreach (['library' => 'orange_pre_library', 'training' => 'orange_pre_training'] as $key => $marker) {
                    if ($out['resources'][$key]['record']->idnumber !== $marker) { throw new \coding_exception('Para la carga inicial se requieren los Números ID orange_pre_library y orange_pre_training.'); }
                }
                $out['lines'] = array_merge($out['lines'], initial_catalog::load($file, (int)$library->id, (int)$training->id, false));
                $out['catalog'] = ['file' => $file, 'hash' => $hash];
            } else { $out['lines'][] = 'Catálogo inicial ya aplicado por este configurador: no se duplica.'; }
        }
        $out['administrators'] = [];
        if (isset($data['administrators'])) {
            if (!is_array($data['administrators'])) { throw new \coding_exception('administrators debe ser una lista de usuarios existentes.'); }
            foreach (array_unique($data['administrators']) as $username) {
                if (!is_string($username)) { throw new \coding_exception('Indicar nombres de usuario en administrators.'); }
                $user = $DB->get_record('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0]);
                if (!$user || $user->suspended || !$user->confirmed || isguestuser($user) || is_siteadmin($user->id)) {
                    throw new \coding_exception('Los administradores Orange deben ser cuentas existentes activas sin administración general.');
                }
                $out['administrators'][] = (int)$user->id;
            }
            $out['lines'][] = 'Administradores Orange: ' . count($out['administrators']) . ' cuentas existentes; sin cambiar sus credenciales.';
        }
        foreach ($out['settings'] as $key => $value) {
            if (isset($CFG->forced_plugin_settings['local_orangeportal'][$key]) && (string)$CFG->forced_plugin_settings['local_orangeportal'][$key] !== (string)$value) {
                throw new \coding_exception('Hay un ajuste Orange forzado en config.php que no coincide con el archivo.');
            }
            $out['lines'][] = $key . ': ' . ($key === 'teamsurl' ? '[credencial oculta; no se imprime]' : (string)$value);
        }
        foreach ($out['core'] as $key => $value) {
            if (isset($CFG->config_php_settings[$key]) && (string)$CFG->config_php_settings[$key] !== (string)$value) {
                throw new \coding_exception('Un ajuste general está forzado en config.php con otro valor.');
            }
            $out['lines'][] = $key . ': ' . $value;
        }
        if ($out['theme']) { $out['lines'][] = 'Tema Orange solo en la cohorte elegida; tema general sin cambios.'; }
        return $out;
    }
    public static function apply(array $data, string $directory): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $lock = \core\lock\lock_config::get_lock_factory('local_orangeportal')->get_lock('deployment_config', 30);
        if (!$lock) { throw new \moodle_exception('locktimeout'); }
        $oldcfg = clone $CFG;
        try {
            $plan = self::plan($data, $directory);
            $tx = $DB->start_delegated_transaction();
            \core\session\manager::set_user(get_admin());
            foreach ($plan['core'] as $key => $value) { set_config($key, $value); }
            $ids = [];
            foreach ($plan['resources'] as $name => $resource) {
                $record = $resource['record'];
                if ($resource['create']) {
                    if ($name === 'audience') {
                        $record->contextid = \context_system::instance()->id; $record->description = ''; $record->descriptionformat = FORMAT_HTML;
                        $record->id = cohort_add_cohort($record);
                    } else {
                        $record = \core_course_category::create((object)['name' => $record->name, 'idnumber' => $record->idnumber, 'parent' => 0, 'visible' => 1]);
                    }
                }
                $ids[$name] = (int)$record->id;
            }
            if ($plan['catalog']) {
                initial_catalog::load($plan['catalog']['file'], $ids['library'], $ids['training'], true);
                set_config('deploymentcataloghash', $plan['catalog']['hash'], 'local_orangeportal');
            }
            foreach (['library' => 'librarycategory', 'training' => 'coursescategory', 'audience' => 'audiencecohort'] as $key => $setting) {
                set_config($setting, $ids[$key], 'local_orangeportal');
            }
            foreach ($plan['settings'] as $key => $value) { set_config($key, $value, 'local_orangeportal'); }
            if ($plan['theme']) { cohort_update_cohort((object)['id' => $ids['audience'], 'contextid' => \context_system::instance()->id, 'theme' => 'orange']); }
            // Same project-boundary synchronisation as web settings, plus existing delegated admins.
            content_access::catalogue(); content_access::install_enrol_role();
            $role = $DB->get_field('role', 'id', ['shortname' => 'orange_administrador']);
            foreach ($plan['administrators'] as $userid) {
                cohort_add_member($ids['audience'], $userid);
                role_assign($role, $userid, \context_system::instance()->id);
            }
            foreach ($DB->get_records('role_assignments', ['roleid' => $role, 'contextid' => \context_system::instance()->id]) as $assignment) {
                orange_administration::sync_areas((int)$assignment->userid);
            }
            $tx->allow_commit();
            return $ids;
        } catch (\Throwable $e) {
            foreach (get_object_vars($CFG) as $key => $value) { if (!property_exists($oldcfg, $key)) { unset($CFG->$key); } }
            foreach (get_object_vars($oldcfg) as $key => $value) { $CFG->$key = $value; }
            \cache_helper::purge_by_definition('core', 'config');
            if (isset($tx) && !$tx->is_disposed()) {
                try { $tx->rollback($e); }
                finally { \context_helper::reset_caches(); \cache_helper::purge_by_event('changesincoursecat'); }
            }
            throw $e;
        } finally { $lock->release(); }
    }
}
