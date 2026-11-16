<?php
/** Configure an environment from a private JSON file. Dry run unless --apply is supplied. */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
[$options, $unknown] = cli_get_params(['file' => null, 'apply' => false, 'help' => false], ['h' => 'help']);
if ($options['help']) {
    echo "Configurar Orange desde un JSON privado. No ejecuta cron ni envía mensajes.\n" .
        "php local/orangeportal/cli/configure.php --file=/ruta/privada/configuracion-orange.json [--apply]\n" .
        "Sin --apply valida y muestra un plan sin credenciales. Requiere actualizar Moodle antes.\n";
    exit(0);
}
try {
    if ($unknown || !$options['file']) { throw new coding_exception('Indicar --file. Ver --help.'); }
    if ((int)get_config('local_orangeportal', 'version') < 2026111007) { throw new coding_exception('Ejecutar primero admin/cli/upgrade.php con el código actualizado.'); }
    [$data, $directory] = \local_orangeportal\deployment_config::read($options['file']);
    $plan = \local_orangeportal\deployment_config::plan($data, $directory);
    foreach ($plan['lines'] as $line) { echo $line . "\n"; }
    if (!$options['apply']) { echo "Validación correcta. Sin cambios; añadir --apply para configurar.\n"; exit(0); }
    $ids = \local_orangeportal\deployment_config::apply($data, $directory);
    echo 'Configuración aplicada. IDs: ' . json_encode($ids) . "\n";
    echo "Cron no ejecutado ni programado por este script. Conservar la programación del servidor.\n";
} catch (Throwable $e) {
    // Do not let a native database exception/trace reveal configuration values or the signed URL.
    $message = $e instanceof coding_exception ? $e->getMessage() : 'No se pudo completar la configuración. Revisar el archivo, recursos y permisos.';
    cli_error($message);
}
