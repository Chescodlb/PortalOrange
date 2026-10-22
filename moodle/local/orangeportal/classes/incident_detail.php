<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** Incident presentation: title, structured metadata, then full description. */
class incident_detail {
    public static function render(\stdClass $notice): string {
        global $DB, $OUTPUT;
        $id = (int)$notice->id;
        $url = \orange_link('view.php', ['id' => $id]);
        $identifier = 'INC' . str_pad((string)$id, 8, '0', STR_PAD_LEFT);
        $actions = '';
        if (incident_workflow::all()) {
            $actions .= \html_writer::link(\orange_link('incident_tracking.php', ['id' => $id]),
                get_string('incidenttracking', 'local_orangeportal'), ['class' => 'btn btn-outline-secondary']);
        }
        if (service::manager()) {
            $actions .= \html_writer::link(\orange_link('manage.php', ['id' => $id, 'kind' => 'incidents']),
                get_string('editpublication', 'local_orangeportal'), ['class' => 'btn btn-outline-secondary']);
        }
        $title = \html_writer::tag('h2', s($notice->title), ['id' => 'orange-incident-detail-title']);
        $heading = \html_writer::div(\html_writer::span($identifier, 'orange-incident-identifier') . $title .
            ($actions ? \html_writer::div($actions, 'orange-incident-detail-actions') : ''), 'orange-incident-detail-heading');
        $cats = $DB->get_records('local_orange_category', ['kind' => 'incidents']);
        $closed = notice_options::is_closed($notice->state);
        $data = [
            'incidentopened' => s(userdate($notice->timecreated, '%d/%m/%Y %H:%M')),
            'incidentcloseddate' => $notice->timeclosed ? s(userdate($notice->timeclosed, '%d/%m/%Y %H:%M')) : '—',
            'incidentcolumnimpact' => s(service::impacts()[$notice->impact] ?? $notice->impact),
            'incidentcolumnstate' => \html_writer::span(s(service::states()[$notice->state] ?? $notice->state),
                'orange-status ' . ($closed ? 'orange-status-closed' : 'orange-status-open')),
            'incidentclient' => s($notice->client ?: '—'),
            'incidentproduct' => s($notice->product ?: '—'),
            'incidentcolumncategory' => \orange_category_label((int)$notice->categoryid, $cats),
        ];
        $headers = ''; $cells = '';
        foreach ($data as $key => $value) {
            $headers .= \html_writer::tag('th', get_string($key, 'local_orangeportal'), ['scope' => 'col']);
            $cells .= \html_writer::tag('td', $value);
        }
        $table = \html_writer::tag('table',
            \html_writer::tag('caption', get_string('incidentdetaildata', 'local_orangeportal'), ['class' => 'sr-only']) .
            \html_writer::tag('thead', \html_writer::tag('tr', $headers)) .
            \html_writer::tag('tbody', \html_writer::tag('tr', $cells)), ['class' => 'orange-incident-data-table']);
        $metadata = \html_writer::div($table, 'orange-incident-data-scroll', ['tabindex' => '0', 'role' => 'region',
            'aria-label' => get_string('incidentdetaildata', 'local_orangeportal')]);
        $management = '';
        if (incident_workflow::all() || has_capability('local/orangeportal:changestate', \context_system::instance())) {
            $tools = '';
            if (has_capability('local/orangeportal:changestate', \context_system::instance())) {
                $form = \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
                    \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'state']) .
                    \html_writer::tag('label', get_string('newstatus', 'local_orangeportal'), ['for' => 'incident-detail-state']) .
                    \html_writer::select(service::states(true, $notice->state), 'state', $notice->state, false,
                        ['id' => 'incident-detail-state', 'class' => 'custom-select']) .
                    \html_writer::tag('button', get_string('updatestatusforall', 'local_orangeportal'), ['type' => 'submit', 'class' => 'btn btn-primary']);
                $tools .= \html_writer::tag('form', $form, ['method' => 'post', 'action' => $url->out(false), 'class' => 'orange-incident-state-form']);
            }
            $logs = $DB->get_records('local_orange_state_log', ['noticeid' => $id], 'timecreated DESC, id DESC');
            foreach ($logs as $log) {
                $actor = \core_user::get_user($log->userid);
                $tools .= \html_writer::tag('p', s(($actor ? fullname($actor) : get_string('deleteduser', 'local_orangeportal')) . ' · ' .
                    (service::states()[$log->oldstate] ?? $log->oldstate) . ' → ' . (service::states()[$log->newstate] ?? $log->newstate) .
                    ' · ' . userdate($log->timecreated, '%d/%m/%Y %H:%M')));
            }
            if ($tools !== '') {
                $management = \html_writer::tag('details', \html_writer::tag('summary', get_string('incidentdetailmanagement', 'local_orangeportal')) . $tools,
                    ['class' => 'orange-incident-detail-management']);
            }
        }
        $description = \html_writer::tag('section',
            \html_writer::tag('h3', get_string('description'), ['id' => 'orange-incident-description-title']) .
            \html_writer::div(format_text($notice->content, FORMAT_HTML, ['context' => \context_system::instance()]),
                'orange-notice-content orange-incident-description-content'),
            ['class' => 'orange-incident-description', 'aria-labelledby' => 'orange-incident-description-title']);
        return \html_writer::tag('article', $heading . $metadata . $management . $description,
            ['class' => 'orange-incident-detail', 'aria-labelledby' => 'orange-incident-detail-title']);
    }
}
