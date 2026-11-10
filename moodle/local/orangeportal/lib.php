<?php
defined('MOODLE_INTERNAL') || die();
function local_orangeportal_coursemodule_validation($form, $data): array {
    if (empty($data['orangeaudiencepresent'])) { return []; }
    return \local_orangeportal\content_access::validation($data);
}

/** Collective-derived memberships cannot be removed independently of their source cohort. */
function local_orangeportal_allow_group_member_remove($itemid, $groupid, $userid): bool { return false; }
/** Protect native URLs and pluginfile downloads, not only the Orange presentation. */
function local_orangeportal_after_require_login($courseorid, $autologinguest, $cm, $setwantsurltome, $preventredirect): void {
    if ((int)get_config('local_orangeportal', 'version') >= 2026110601) {
        \local_orangeportal\report_scope::guard_native_reports();
    }
    $id = is_object($courseorid) ? (int)$courseorid->id : (int)$courseorid;
    if ($id <= SITEID || (int)get_config('local_orangeportal', 'version') < 2026102700) { return; }
    if ((int)get_config('local_orangeportal', 'version') >= 2026111000) {
        \local_orangeportal\content_access::guard($id, $cm ? (int)$cm->id : 0);
    }
    \local_orangeportal\native_content::guard($id);
}

/** Keep Moodle's native resource forms, returning authors to their publication resources. */
function local_orangeportal_coursemodule_definition_after_data($form, $mform): void {
    if ((int)get_config('local_orangeportal', 'version') < 2026102900) { return; }
    $course = $form->get_course();
    if ((int)get_config('local_orangeportal', 'version') >= 2026111000 &&
            \local_orangeportal\content_access::section((int)$course->id) === 'library' &&
            \local_orangeportal\experience::member() && \local_orangeportal\content_access::author((int)$course->id)) {
        $cm = $form->get_coursemodule();
        \local_orangeportal\content_access::field($mform, $cm ? \local_orangeportal\content_access::selected('module', (int)$cm->id) : [],
            $cm ? \local_orangeportal\content_access::selected('module', (int)$cm->id, 'users') : [],
            $cm ? \local_orangeportal\content_access::selected('module', (int)$cm->id, 'groups') : []);
        $visible = $cm ? (int)$cm->visible : 0;
        if ($mform->elementExists('visible')) { $mform->removeElement('visible'); }
        $mform->addElement('hidden', 'visible', $visible);
        $mform->setType('visible', PARAM_INT); $mform->setConstant('visible', $visible);
        $mform->addElement('advcheckbox', 'orangepublished', get_string('publishlabel', 'local_orangeportal'),
            get_string('visibleforrecipients', 'local_orangeportal'));
        $mform->setDefault('orangepublished', $visible);
        $mform->setType('orangepublished', PARAM_BOOL);
        if (!has_capability('moodle/course:activityvisibility', context_course::instance($course->id))) {
            $mform->setConstant('orangepublished', $visible); $mform->hardFreeze('orangepublished');
        }
        \local_orangeportal\teams::form($mform, 'library', 'orangepublished');
        if ($mform->elementExists('buttonar')) {
            foreach (['orangeaudiencehdr', 'orangeusers', 'orangecohorts', 'orangegroups', 'orangeaudiencehint', 'orangepublished', 'orangenotifyteams', 'orangeteamsdisabled', 'orangeteamschoicehint'] as $name) {
                if ($mform->elementExists($name)) { $mform->insertElementBefore($mform->removeElement($name, false), 'buttonar'); }
            }
        }
        if ($mform->elementExists('return')) { $mform->setConstant('return', 0); }
        if ($mform->elementExists('buttonar')) {
            $mform->getElement('buttonar')->setElements([
                $mform->createElement('submit', 'submitbutton2', get_string('documentsaveback', 'local_orangeportal')),
                $mform->createElement('cancel'),
            ]);
        }
    }
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
    global $SESSION, $DB;
    if ((int)get_config('local_orangeportal', 'version') < 2026102900) { return $moduleinfo; }
    if ((int)get_config('local_orangeportal', 'version') >= 2026111000 &&
            \local_orangeportal\content_access::section((int)$course->id) === 'library' && !empty($moduleinfo->orangeaudiencepresent)) {
        \local_orangeportal\content_access::save('module', (int)$moduleinfo->coursemodule, (array)($moduleinfo->orangecohorts ?? []),
            (array)($moduleinfo->orangeusers ?? []), (array)($moduleinfo->orangegroups ?? []));
        $cmid = (int)$moduleinfo->coursemodule;
        $visible = !empty($moduleinfo->orangepublished);
        if ((bool)$DB->get_field('course_modules', 'visible', ['id' => $cmid]) !== $visible) {
            require_capability('moodle/course:activityvisibility', context_course::instance($course->id));
            set_coursemodule_visible($cmid, $visible);
        }
        \local_orangeportal\teams::choose('library', $cmid, $visible && !empty($moduleinfo->orangenotifyteams));
        unset($moduleinfo->submitbutton);
        if (!empty($moduleinfo->frontend)) {
            $SESSION->orangelibrarysaved[$course->id] = ['id' => (int)$moduleinfo->coursemodule, 'time' => time()];
        }
    }
    $map = \local_orangeportal\native_content::mapping((int)$course->id);
    if ($map && $map->kind === 'notice' && \local_orangeportal\native_content::can_author($map)) {
        unset($moduleinfo->submitbutton);
    }
    return $moduleinfo;
}
