<?php
defined('MOODLE_INTERNAL') || die();
/** Protect native URLs and pluginfile downloads, not only the Orange presentation. */
function local_orangeportal_after_require_login($courseorid, $autologinguest, $cm, $setwantsurltome, $preventredirect): void {
    $id = is_object($courseorid) ? (int)$courseorid->id : (int)$courseorid;
    if ($id <= SITEID || (int)get_config('local_orangeportal', 'version') < 2026102700) { return; }
    \local_orangeportal\native_content::guard($id);
}
