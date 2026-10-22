<?php
defined('MOODLE_INTERNAL') || die();
$capabilities = [
    'local/orangeportal:administer' => [
        'captype' => 'write', 'contextlevel' => CONTEXT_SYSTEM,
        'riskbitmask' => RISK_PERSONAL, 'archetypes' => [],
    ],
    'local/orangeportal:exportreports' => [
        'captype' => 'read', 'contextlevel' => CONTEXT_SYSTEM,
        'riskbitmask' => RISK_PERSONAL, 'archetypes' => [],
    ],
    'local/orangeportal:viewallincidents' => [
        'captype' => 'read', 'contextlevel' => CONTEXT_SYSTEM,
        'riskbitmask' => RISK_PERSONAL, 'archetypes' => ['manager' => CAP_ALLOW],
    ],
    'local/orangeportal:manageoptions' => [
        'captype' => 'write', 'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['manager' => CAP_ALLOW],
    ],
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
        'archetypes' => [],
    ],
];
