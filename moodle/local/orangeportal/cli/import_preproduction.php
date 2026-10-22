<?php
/** First-install structure loader. No accounts, courses, documents or site settings are copied. */
if (!defined('CLI_SCRIPT')) { define('CLI_SCRIPT', true); }
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
[$options, $unknown] = cli_get_params([
    'file' => null, 'library-root-id' => null, 'training-root-id' => null, 'apply' => false, 'help' => false,
], ['h' => 'help']);
if ($options['help']) {
    echo "Carga inicial Orange en raíces vacías identificadas como orange_pre_library / orange_pre_training.\n";
    echo "php local/orangeportal/cli/import_preproduction.php --file=/ruta/catalogos-orange.json " .
        "--library-root-id=ID --training-root-id=ID [--apply]\nSin --apply solo valida; no escribe.\n";
    return;
}
if ($unknown || !$options['file'] || !is_readable($options['file'])) {
    throw new coding_exception('Indicar --file legible y los dos IDs raíz. Ver --help.');
}
$data = json_decode(file_get_contents($options['file']), true, 512, JSON_THROW_ON_ERROR);
if (($data['format'] ?? null) !== 1) { throw new coding_exception('Formato de estructura no compatible.'); }
$rootids = ['librarycategory' => (int)$options['library-root-id'], 'coursescategory' => (int)$options['training-root-id']];
$markers = ['librarycategory' => 'orange_pre_library', 'coursescategory' => 'orange_pre_training'];
$categoryidnumber = static fn(array $category): string => (string)($category['idnumber'] ?? '') !== '' ?
    (string)$category['idnumber'] : 'orange_pre_category_' . (int)$category['sourceid'];
if (!$rootids['librarycategory'] || !$rootids['coursescategory'] || count(array_unique($rootids)) !== 2) {
    throw new coding_exception('Las dos raíces deben existir y ser distintas.');
}
// Explicit, empty scopes avoid accidentally importing into an existing campus category.
foreach ($rootids as $key => $id) {
    $root = $DB->get_record('course_categories', ['id' => $id], '*', MUST_EXIST);
    if ($root->idnumber !== $markers[$key] || $DB->record_exists('course_categories', ['parent' => $id]) ||
            $DB->record_exists('course', ['category' => $id])) {
        throw new coding_exception('Raíz no vacía o sin el Número ID requerido: ' . $markers[$key]);
    }
    $source = $data['roots'][$key] ?? [];
    $seen = [(int)($source['sourceid'] ?? 0) => true];
    if (isset($seen[0]) || empty($source['categories'])) { throw new coding_exception('Raíz de origen inválida.'); }
    foreach ($source['categories'] as $category) {
        if ((int)$category['sourceid'] === (int)$source['sourceid']) { continue; }
        if ((int)$category['sourceid'] <= 0 || isset($seen[(int)$category['sourceid']]) ||
                !isset($seen[(int)$category['parent']]) || trim($category['name']) === '' ||
                $DB->record_exists('course_categories', ['idnumber' => $categoryidnumber($category)])) {
            throw new coding_exception('Categoría inválida, duplicada o identificador ya existente.');
        }
        $seen[(int)$category['sourceid']] = true;
    }
}
if ($DB->record_exists('local_orange_notice', []) || $DB->record_exists('local_orange_category', [])) {
    throw new coding_exception('Solo instalación inicial: ya existen categorías o publicaciones del portal.');
}
foreach (['states', 'impacts'] as $type) {
    if (get_config('local_orangeportal', 'notice_' . $type) !== false) {
        throw new coding_exception('El catálogo ya está configurado: ' . $type);
    }
    if (empty($data['catalogs'][$type])) { throw new coding_exception('Catálogo vacío.'); }
    foreach ($data['catalogs'][$type] as $code => $record) {
        if (!preg_match('/^[a-z]+$/D', $code) || !is_bool($record['active'] ?? null) || empty($record['name'])) {
            throw new coding_exception('Opción de catálogo inválida.');
        }
    }
}
$pending = $data['noticecategories'] ?? [];
$validated = [];
while ($pending) {
    $remaining = [];
    foreach ($pending as $category) {
        if (!in_array($category['kind'], ['incidents', 'notifications'], true) || trim($category['name']) === '') {
            throw new coding_exception('Categoría de avisos inválida.');
        }
        $parent = (int)$category['parentid'];
        if ($parent && !isset($validated[$parent])) { $remaining[] = $category; continue; }
        if (isset($validated[$category['id']]) || ($parent && $validated[$parent]['kind'] !== $category['kind'])) {
            throw new coding_exception('Jerarquía de avisos inválida.');
        }
        $validated[$category['id']] = $category;
    }
    if (count($remaining) === count($pending)) { throw new coding_exception('Ciclo o padre ausente en avisos.'); }
    $pending = $remaining;
}
foreach ($rootids as $key => $id) {
    echo $key . ': ' . (count($data['roots'][$key]['categories']) - 1) . ' subcategorías nuevas en raíz ' . $id . "\n";
}
echo count($validated) . " categorías de avisos; estados e impactos con su activación actual.\n";
if (!$options['apply']) { echo "Validación correcta. Sin cambios; añadir --apply para cargar.\n"; return; }
\core\session\manager::set_user(get_admin());
$transaction = $DB->start_delegated_transaction();
foreach ($rootids as $key => $id) {
    $source = $data['roots'][$key];
    $mapping = [(int)$source['sourceid'] => $id];
    foreach ($source['categories'] as $category) {
        if ((int)$category['sourceid'] === (int)$source['sourceid']) { continue; }
        $created = \core_course_category::create((object)[
            'name' => $category['name'], 'idnumber' => $categoryidnumber($category),
            'parent' => $mapping[(int)$category['parent']], 'visible' => (int)$category['visible'],
        ]);
        $mapping[(int)$category['sourceid']] = (int)$created->id;
    }
}
$mapping = [0 => 0];
foreach ($validated as $sourceid => $category) {
    $mapping[$sourceid] = $DB->insert_record('local_orange_category', (object)[
        'kind' => $category['kind'], 'name' => $category['name'], 'parentid' => $mapping[(int)$category['parentid']],
    ]);
}
foreach (['states', 'impacts'] as $type) {
    set_config('notice_' . $type, json_encode($data['catalogs'][$type], JSON_UNESCAPED_UNICODE), 'local_orangeportal');
}
$transaction->allow_commit();
echo "Carga inicial completada. Configurar raíces, audiencia, roles y tema desde Moodle.\n";
