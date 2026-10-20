<?php
namespace theme_orange\output;
defined('MOODLE_INTERNAL') || die();

/** Keep Orange's logo independent of the existing site's Moove settings. */
class core_renderer extends \theme_moove\output\core_renderer {
    /** Orange indicators replace the native messaging/notification icons only in Orange. */
    public function navbar_plugin_output() {
        if (isloggedin() && !isguestuser() && \local_orangeportal\experience::member()) {
            return \local_orangeportal\personal_updates::navbar();
        }
        return parent::navbar_plugin_output();
    }

    public function get_theme_logo_url() {
        return \theme_config::load('orange')->setting_file_url('logo', 'logo') ?:
            $this->image_url('logo', 'theme_orange');
    }
}
