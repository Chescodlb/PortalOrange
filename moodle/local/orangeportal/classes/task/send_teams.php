<?php
namespace local_orangeportal\task;
defined('MOODLE_INTERNAL') || die();

/** Moodle owns persistence, logs and bounded exponential retry of the outbound job. */
class send_teams extends \core\task\adhoc_task {
    public function get_name(): string { return get_string('teamstask', 'local_orangeportal'); }
    public function execute(): void {
        global $CFG;
        $data = $this->get_custom_data();
        $endpoint = (string)get_config('local_orangeportal', 'teamsurl');
        if (!\local_orangeportal\teams::enabled($data->kind, $data->action) ||
                !hash_equals((string)$data->endpoint, hash('sha256', $endpoint))) { return; }
        if ($data->kind === 'courses' && (int)($data->generation ?? 0) !==
                \local_orangeportal\training_publication::generation((int)$data->id)) { return; }
        $payload = \local_orangeportal\teams::payload($data->kind, (int)$data->id, $data->action);
        if (!$payload || !hash_equals((string)$data->contenthash, hash('sha256', json_encode($payload)))) { return; }
        $eventid = hash('sha256', $CFG->wwwroot . ':teams:' . $this->get_id());
        \local_orangeportal\teams::post($endpoint, $payload, $eventid);
        mtrace('Teams: HTTP request accepted.');
    }
}
