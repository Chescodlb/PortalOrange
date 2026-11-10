<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** Optional signed URL; hidden by default and editable only by site configuration administrators. */
class admin_setting_teams_url extends \admin_setting_configpasswordunmask {
    public function validate($data) {
        return $data === '' || teams::valid_endpoint($data) ? true : get_string('teamsurlinvalid', 'local_orangeportal');
    }
}
