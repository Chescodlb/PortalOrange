<?php
defined('MOODLE_INTERNAL') || die();
function xmldb_local_orangeportal_install(): void {
    update_capabilities('local_orangeportal');
    \local_orangeportal\orange_administration::install_roles();
}
