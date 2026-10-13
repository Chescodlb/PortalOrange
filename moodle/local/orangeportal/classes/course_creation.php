<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** Scope the native new-course form to the portal section that opened it. */
class course_creation {
    public static function section(): ?string {
        global $PAGE, $CFG;
        if (!get_config('local_orangeportal', 'enabled') || $PAGE->url->get_param('id') ||
                $PAGE->url->get_path() !== parse_url($CFG->wwwroot . '/course/edit.php', PHP_URL_PATH) ||
                $PAGE->url->get_param('returnto') !== 'url') {
            return null;
        }
        $returnurl = (string)$PAGE->url->get_param('returnurl');
        if (parse_url($returnurl, PHP_URL_PATH) !==
                parse_url($CFG->wwwroot . '/local/orangeportal/index.php', PHP_URL_PATH)) {
            return null;
        }
        parse_str(parse_url($returnurl, PHP_URL_QUERY) ?? '', $params);
        $section = $params['section'] ?? null;
        return in_array($section, ['library', 'courses'], true) ? $section : null;
    }

    public static function options(string $section): array {
        global $DB;
        $root = native_categories::root($section);
        $ids = $DB->get_fieldset_select('course_categories', 'id', 'id = ? OR path LIKE ?',
            [$root->id, $root->path . '/%']);
        return array_intersect_key(\core_course_category::make_categories_list('moodle/course:create'),
            array_fill_keys($ids, true));
    }

    public static function definition(\core_course\hook\after_form_definition $hook): void {
        $section = self::section();
        if ($section === 'library') {
            foreach (['fullname', 'shortname', 'category', 'summary_editor', 'visible', 'idnumber',
                    'overviewfiles_filemanager', 'downloadcontent', 'showreports', 'tagshdr', 'tags'] as $name) {
                if ($hook->mform->elementExists($name)) {
                    $label = in_array($name, ['tagshdr', 'tags']) ? get_string('tags', 'tag') :
                        get_string('collection_' . $name, 'local_orangeportal');
                    $hook->mform->getElement($name)->setLabel($label);
                    if (in_array($name, ['fullname', 'shortname', 'category', 'summary_editor', 'visible',
                            'idnumber', 'overviewfiles_filemanager', 'downloadcontent', 'showreports'])) {
                        $hook->mform->addHelpButton($name, 'collection_' . $name, 'local_orangeportal');
                    }
                }
            }
            self::document_defaults($hook->mform);
        }
        if (!$section || !$hook->mform->elementExists('category')) { return; }
        $element = $hook->mform->getElement('category');
        if ($element->getType() !== 'autocomplete') { return; }
        $element->removeOptions();
        $element->loadArray(self::options($section));
    }

    private static function hidden(\MoodleQuickForm $form, string $name, $value, string $type = PARAM_INT): void {
        if ($form->elementExists($name)) { $form->removeElement($name); }
        $form->addElement('hidden', $name, $value);
        $form->setType($name, $type);
        $form->setConstant($name, $value);
    }

    private static function document_defaults(\MoodleQuickForm $form): void {
        // Academic settings are not part of the new documentary collection workflow.
        foreach (['startdate' => 0, 'enddate' => 0, 'newsitems' => 0, 'showgrades' => 0,
                'showactivitydates' => 0, 'enablecompletion' => 0, 'showcompletionconditions' => 0] as $name => $value) {
            self::hidden($form, $name, $value);
        }
        self::hidden($form, 'format', 'topics', PARAM_PLUGIN);
        foreach (['courseformathdr', 'completionhdr', 'updatecourseformat', 'relativedatesmodegroup'] as $name) {
            if ($form->elementExists($name)) { $form->removeElement($name); }
        }
    }

    public static function after_data(\core_course\hook\after_form_definition_after_data $hook): void {
        if (self::section() !== 'library') { return; }
        // Native formats may add fields after the first definition hook.
        foreach (['numsections' => 1, 'hiddensections' => 1, 'coursedisplay' => 0] as $name => $value) {
            self::hidden($hook->mform, $name, $value);
        }
    }

    public static function presentation(): void {
        global $PAGE;
        $section = self::section();
        if (!$section) { return; }
        $library = $section === 'library';
        $title = $library ? get_string('createcollection', 'local_orangeportal') : get_string('addnewcourse');
        $PAGE->set_secondary_navigation(false);
        $PAGE->set_primary_active_tab('orange_' . $section);
        $PAGE->navbar->ignore_active();
        $PAGE->navbar->children = [];
        // Moodle always prepends its navigation root, even when active nodes are ignored.
        // Replace that root for this page instead of adding a misleading home link.
        breadcrumb::root($section);
        $PAGE->navbar->add($title);
        if ($library) {
            $PAGE->set_title($title);
            $PAGE->requires->js('/local/orangeportal/course_creation.js');
            $PAGE->requires->js_init_call('OrangePortalCreation.init', [$title, get_string('addnewcourse')]);
        }
    }

    public static function validation(\core_course\hook\after_form_validation $hook): void {
        $section = self::section();
        if (!$section) { return; }
        $data = $hook->get_data();
        if (!isset(self::options($section)[(int)($data['category'] ?? 0)])) {
            $hook->add_errors(['category' => get_string('invalidcreationcategory', 'local_orangeportal',
                get_string($section, 'local_orangeportal'))]);
        }
    }
}
