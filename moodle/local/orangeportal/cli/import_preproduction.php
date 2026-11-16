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
if ($unknown || !$options['file'] || !is_readable($options['file']) || empty($options['library-root-id']) || empty($options['training-root-id'])) {
    throw new coding_exception('Indicar --file legible y los dos IDs raíz. Ver --help.');
}
foreach (\local_orangeportal\initial_catalog::load($options['file'], (int)$options['library-root-id'],
        (int)$options['training-root-id'], (bool)$options['apply']) as $line) { echo $line . "\n"; }
