<?php
defined('MOODLE_INTERNAL') || die();
/** Protect native URLs and pluginfile downloads, not only the Orange presentation. */
function local_orangeportal_after_require_login($courseorid, $autologinguest, $cm, $setwantsurltome, $preventredirect): void {
    $id = is_object($courseorid) ? (int)$courseorid->id : (int)$courseorid;
    if ($id <= SITEID || (int)get_config('local_orangeportal', 'version') < 2026102700) { return; }
    \local_orangeportal\native_content::guard($id);
}

/** Keep Moodle's native resource forms, returning authors to their publication resources. */
function local_orangeportal_coursemodule_definition_after_data($form, $mform): void {
    if ((int)get_config('local_orangeportal', 'version') < 2026102900) { return; }
    $course = $form->get_course();
    $map = \local_orangeportal\native_content::mapping((int)$course->id);
    if (!$map || $map->kind !== 'notice' || !\local_orangeportal\native_content::can_author($map)) { return; }
    if ($mform->elementExists('return')) { $mform->setConstant('return', 0); }
    if ($mform->elementExists('buttonar')) {
        $buttons = $mform->getElement('buttonar');
        $buttons->setElements([
            $mform->createElement('submit', 'submitbutton2', get_string('noticesaveandreturnresources', 'local_orangeportal')),
            $mform->createElement('cancel'),
        ]);
    }
}

/** Even a form opened before the update must return after a successful native save. */
function local_orangeportal_coursemodule_edit_post_actions($moduleinfo, $course) {
    if ((int)get_config('local_orangeportal', 'version') < 2026102900) { return $moduleinfo; }
    $map = \local_orangeportal\native_content::mapping((int)$course->id);
    if ($map && $map->kind === 'notice' && \local_orangeportal\native_content::can_author($map)) {
        unset($moduleinfo->submitbutton);
    }
    return $moduleinfo;
}
