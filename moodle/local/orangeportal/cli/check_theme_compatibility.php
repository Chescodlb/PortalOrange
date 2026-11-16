<?php
/** Structural preflight for Orange and the installed Moove. Does not upgrade or change settings. */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unknown] = cli_get_params(['help' => false], ['h' => 'help']);
if ($options['help']) {
    echo "Comprobar dependencias y contratos PHP de Orange/Moove sin actualizar temas.\n" .
        "php local/orangeportal/cli/check_theme_compatibility.php\n" .
        "Salida 0: comprobaciones estructurales correctas; 1: incompatibilidad o error.\n" .
        "No sustituye las pruebas funcionales/visuales ni modifica Moove, configuración o tareas.\n";
    exit(0);
}
if ($unknown) {
    cli_error('Opciones desconocidas. Consultar --help.');
}

$errors = [];
$check = static function(bool $ok, string $message) use (&$errors): void {
    echo ($ok ? 'OK: ' : 'ERROR: ') . $message . "\n";
    if (!$ok) {
        $errors[] = $message;
    }
};
$readversion = static function(string $path): stdClass {
    $plugin = new stdClass();
    if (!is_readable($path)) {
        throw new RuntimeException('Falta el archivo de versión de un tema requerido.');
    }
    require($path);
    return $plugin;
};

try {
    $orange = $readversion($CFG->dirroot . '/theme/orange/version.php');
    $moove = $readversion($CFG->dirroot . '/theme/moove/version.php');
    echo 'Moodle: ' . $CFG->release . "\n";
    echo 'Moove: ' . $moove->release . ' (' . $moove->version . ")\n";
    echo 'Orange: ' . $orange->release . ' (' . $orange->version . ")\n";
    $check($moove->component === 'theme_moove', 'El tema padre es theme_moove.');
    $check((int)$moove->version >= (int)$orange->dependencies['theme_moove'],
        'Moove cumple la versión mínima declarada por Orange.');
    $check((float)$CFG->version >= (float)$moove->requires && (float)$CFG->version >= (float)$orange->requires,
        'Moodle cumple las versiones mínimas declaradas por ambos temas.');

    require_once($CFG->dirroot . '/theme/moove/lib.php');
    foreach (['theme_moove_get_main_scss_content', 'theme_moove_get_pre_scss'] as $function) {
        $check(function_exists($function), 'Función SCSS disponible: ' . $function);
    }
    $theme = theme_config::load('orange');
    $check(in_array('moove', $theme->parents, true) && in_array('boost', $theme->parents, true),
        'Orange carga sus temas padre Moove y Boost.');
    $check(is_subclass_of(\theme_orange\output\core_renderer::class, \theme_moove\output\core_renderer::class),
        'El renderer de Orange puede heredar del renderer de Moove.');

    // Moove 4.5.2 introduced this call without increasing its declared Moodle minimum.
    // Inspect the effective implementation so a downstream fix in Moove is also respected.
    require_once($CFG->dirroot . '/admin/renderer.php');
    $classname = \theme_moove\output\core\admin_renderer::class;
    $method = new ReflectionMethod($classname, 'admin_notifications_page');
    $lines = file($method->getFileName());
    $source = implode('', array_slice($lines, $method->getStartLine() - 1,
        $method->getEndLine() - $method->getStartLine() + 1));
    $needswarning = (bool)preg_match('/\$this\s*->\s*moodlenet_removal_warning\s*\(/', $source);
    $check(!$needswarning || method_exists($classname, 'moodlenet_removal_warning'),
        'La página de Notificaciones de Moove dispone de moodlenet_removal_warning si lo utiliza.');
} catch (Throwable $e) {
    // Do not print a bootstrap/DB trace or configuration values.
    $check(false, 'No se pudo cargar o comprobar un componente de los temas. Revisar instalación y dependencias.');
}

if ($errors) {
    echo "No desplegar automáticamente: revisar los errores anteriores. Moove no se ha modificado.\n";
    exit(1);
}
echo "Comprobación estructural correcta. Validar además navegación, CSS, móvil y administración en una copia del campus.\n";
exit(0);
