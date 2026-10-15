<?php
defined('MOODLE_INTERNAL') || die();
function xmldb_local_orangeportal_upgrade($oldversion): bool {
    global $DB;
    if ($oldversion < 2026101500) {
        update_capabilities('local_orangeportal');
        foreach (['orange_publicador', 'orange_seguimiento'] as $shortname) {
            if ($roleid = $DB->get_field('role', 'id', ['shortname' => $shortname])) {
                assign_capability('local/orangeportal:manageoptions', CAP_ALLOW, $roleid, context_system::instance()->id);
            }
        }
        upgrade_plugin_savepoint(true, 2026101500, 'local', 'orangeportal');
    }
    return true;
}
