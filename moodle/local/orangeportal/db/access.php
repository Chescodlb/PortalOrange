<?php
defined('MOODLE_INTERNAL') || die();
$capabilities = [
    'local/orangeportal:manage' => [
        'captype' => 'write', 'contextlevel' => CONTEXT_SYSTEM,
        'riskbitmask' => RISK_PERSONAL,
        'archetypes' => ['manager' => CAP_ALLOW],
    ],
    'local/orangeportal:viewreports' => [
        'captype' => 'read', 'contextlevel' => CONTEXT_SYSTEM,
        'riskbitmask' => RISK_PERSONAL,
        'archetypes' => ['manager' => CAP_ALLOW],
    ],
    'local/orangeportal:changestate' => [
        'captype' => 'write', 'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['user' => CAP_ALLOW],
    ],
];
