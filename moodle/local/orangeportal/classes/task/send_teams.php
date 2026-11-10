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
        mtrace('Teams: publicación ' . $data->kind . ' #' . (int)$data->id . ' · ' . $data->action . '.');
        if (!\local_orangeportal\teams::enabled($data->kind, $data->action)) {
            mtrace('Teams: omitido; integración, tipo o acción desactivados, o URL no válida.'); return;
        }
        if (!hash_equals((string)$data->endpoint, hash('sha256', $endpoint))) {
            mtrace('Teams: omitido; la URL configurada cambió desde que se creó la tarea.'); return;
        }
        if ($data->kind === 'courses' && (int)($data->generation ?? 0) !==
                \local_orangeportal\training_publication::generation((int)$data->id)) {
            mtrace('Teams: omitido; corresponde a una publicación anterior de la formación.'); return;
        }
        $payload = \local_orangeportal\teams::payload($data->kind, (int)$data->id, $data->action);
        if (!$payload) {
            mtrace('Teams: omitido; el contenido ya no está publicado, visible o disponible.'); return;
        }
        if (!hash_equals((string)$data->contenthash, hash('sha256', json_encode($payload)))) {
            mtrace('Teams: omitido; el contenido cambió después del guardado que solicitó el aviso.'); return;
        }
        $eventid = hash('sha256', $CFG->wwwroot . ':teams:' . $this->get_id());
        \local_orangeportal\teams::post($endpoint, $payload, $eventid);
        mtrace('Teams: HTTP request accepted.');
    }
}
