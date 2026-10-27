<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** An unset optional selection is empty, including before any cohorts exist. */
class admin_setting_optional_multiselect extends \admin_setting_configmultiselect {
    public function get_setting() {
        // The native control renders nothing and cannot save when choices are empty.
        // Returning its optional default avoids an invisible pending upgrade setting.
        return parent::get_setting() ?? [];
    }
}
