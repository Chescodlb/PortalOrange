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
    if ($oldversion < 2026101900) {
        // No global theme or navigation changes on upgrade. Choose the audience explicitly.
        upgrade_plugin_savepoint(true, 2026101900, 'local', 'orangeportal');
    }
    if ($oldversion < 2026102000) {
        // Library column navigation uses existing categories/modules; no automatic content moves.
        upgrade_plugin_savepoint(true, 2026102000, 'local', 'orangeportal');
    }
    if ($oldversion < 2026102001) {
        $table = new xmldb_table('local_orange_doc_read');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('readtime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('module', XMLDB_KEY_FOREIGN, ['cmid'], 'course_modules', ['id']);
        $table->add_index('usermodule', XMLDB_INDEX_UNIQUE, ['userid', 'cmid']);
        if (!$DB->get_manager()->table_exists($table)) { $DB->get_manager()->create_table($table); }
        upgrade_plugin_savepoint(true, 2026102001, 'local', 'orangeportal');
    }
    if ($oldversion < 2026102002) {
        $table = new xmldb_table('local_orange_notice');
        foreach (['client', 'product'] as $name) {
            $field = new xmldb_field($name, XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
            if (!$DB->get_manager()->field_exists($table, $field)) { $DB->get_manager()->add_field($table, $field); }
        }
        foreach (['timeclosed', 'closedby', 'deleted', 'deletedby'] as $name) {
            $field = new xmldb_field($name, XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            if (!$DB->get_manager()->field_exists($table, $field)) { $DB->get_manager()->add_field($table, $field); }
        }
        update_capabilities('local_orangeportal');
        $contextid = context_system::instance()->id;
        foreach ($DB->get_records('role', ['archetype' => 'user']) as $role) {
            unassign_capability('local/orangeportal:changestate', $role->id, $contextid);
        }
        $formador = $DB->get_record('role', ['shortname' => 'orange_publicador']);
        if ($formador) {
            $DB->set_field('role', 'name', 'Formador Orange', ['id' => $formador->id]);
            foreach (['manage', 'changestate', 'viewallincidents', 'viewreports', 'manageoptions'] as $cap) {
                assign_capability('local/orangeportal:' . $cap, CAP_ALLOW, $formador->id, $contextid);
            }
            $creator = $DB->get_field('role', 'id', ['shortname' => 'orange_creadorcontenidos']);
            if ($creator) {
                foreach ($DB->get_records('role_assignments', ['roleid' => $creator]) as $assignment) {
                    role_assign($formador->id, $assignment->userid, $contextid);
                }
            }
            $categoryrole = $DB->get_field('role', 'id', ['shortname' => 'orange_gestorcategorias']);
            if ($categoryrole) {
                foreach ($DB->get_records('role_assignments', ['roleid' => $formador->id]) as $assignment) {
                    foreach (['librarycategory', 'coursescategory'] as $setting) {
                        if ($id = (int)get_config('local_orangeportal', $setting)) {
                            role_assign($categoryrole, $assignment->userid, context_coursecat::instance($id)->id);
                        }
                    }
                }
            }
        }
        if ($coordinator = $DB->get_field('role', 'id', ['shortname' => 'orange_seguimiento'])) {
            assign_capability('local/orangeportal:viewallincidents', CAP_ALLOW, $coordinator, $contextid);
            unassign_capability('local/orangeportal:changestate', $coordinator, $contextid);
        }
        foreach ($DB->get_records('local_orange_notice', ['kind' => 'incidents']) as $notice) {
            if (\local_orangeportal\notice_options::is_closed($notice->state) && !$notice->timeclosed) {
                $last = $DB->get_records('local_orange_state_log', ['noticeid' => $notice->id, 'newstate' => $notice->state],
                    'timecreated DESC, id DESC', '*', 0, 1);
                $log = reset($last);
                $DB->update_record('local_orange_notice', (object)['id' => $notice->id,
                    'timeclosed' => $log ? $log->timecreated : $notice->timemodified, 'closedby' => $log ? $log->userid : 0]);
            }
        }
        upgrade_plugin_savepoint(true, 2026102002, 'local', 'orangeportal');
    }
    return true;
}
