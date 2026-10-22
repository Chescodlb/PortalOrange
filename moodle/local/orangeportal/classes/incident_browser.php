<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** Incident list with recipient-safe filters and a separate staff tracking entry. */
class incident_browser {
    private static function field(string $name, string $label, string $control): string {
        return \html_writer::div(\html_writer::tag('label', s($label), ['for' => 'incident-' . $name]) . $control,
            'orange-incident-filter');
    }
    private static function select(string $name, array $options, $value): string {
        return \html_writer::select($options, $name, $value, false,
            ['id' => 'incident-' . $name, 'class' => 'custom-select']);
    }
    public static function render(): string {
        global $DB, $USER, $OUTPUT;
        $all = incident_workflow::all();
        $scope = service::visible('incidents');
        $q = optional_param('q', '', PARAM_TEXT);
        $state = optional_param('incidentstate', $all ? 'all' : 'openonly', PARAM_ALPHANUMEXT);
        $impact = optional_param('impact', '', PARAM_ALPHA);
        $client = optional_param('incidentclient', '', PARAM_TEXT);
        $product = optional_param('incidentproduct', '', PARAM_TEXT);
        $category = optional_param('category', 0, PARAM_INT);
        $unread = optional_param('unread', 0, PARAM_BOOL);
        $personal = optional_param('personal', 0, PARAM_BOOL);
        $priority = optional_param('priority', '', PARAM_ALPHA);
        if (!in_array($priority, ['', 'normal', 'critical'], true)) { $priority = ''; }
        $from = optional_param('datefrom', '', PARAM_RAW_TRIMMED);
        $to = optional_param('dateto', '', PARAM_RAW_TRIMMED);
        $options = $all ? ['all' => get_string('incidentallstates', 'local_orangeportal')] : [];
        $options['openonly'] = get_string('incidentopen', 'local_orangeportal');
        if ($all) { $options['closedonly'] = get_string('incidentclosed', 'local_orangeportal'); }
        foreach (service::states() as $code => $name) {
            if ($all || !notice_options::is_closed($code)) { $options['state_' . $code] = $name; }
        }
        if (!isset($options[$state])) { $state = $all ? 'all' : 'openonly'; }
        $clients = []; $products = [];
        foreach ($scope as $n) {
            if ($n->client !== '') { $clients[$n->client] = $n->client; }
            if ($n->product !== '') { $products[$n->product] = $n->product; }
        }
        natcasesort($clients); natcasesort($products);
        $cats = $DB->get_records('local_orange_category', ['kind' => 'incidents']);
        $categoryids = $category && isset($cats[$category]) ? service::category_descendants($category, $cats) : [];
        $bounds = []; $error = '';
        foreach (['datefrom' => $from, 'dateto' => $to] as $key => $value) {
            if ($value === '') { continue; }
            $date = preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value) ?
                \DateTimeImmutable::createFromFormat('!Y-m-d', $value, \core_date::get_user_timezone_object()) : false;
            if (!$date || $date->format('Y-m-d') !== $value) { $error = get_string('invalidnotificationdate', 'local_orangeportal'); continue; }
            $bounds[$key] = ($key === 'dateto' ? $date->modify('+1 day') : $date)->getTimestamp();
        }
        if ($from !== '' && $to !== '' && $from > $to) { $error = get_string('invalidnotificationrange', 'local_orangeportal'); }
        $form = \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'section', 'value' => 'incidents']);
        if ($personal) { $form .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'personal', 'value' => 1]); }
        $form .= self::field('priority', get_string('updates_priority', 'local_orangeportal'), self::select('priority',
            ['' => get_string('updates_allpriority', 'local_orangeportal'), 'normal' => get_string('updates_noncritical', 'local_orangeportal'),
                'critical' => get_string('updates_critical', 'local_orangeportal')], $priority));
        $form .= self::field('q', get_string('search'), \html_writer::empty_tag('input', ['name' => 'q', 'type' => 'search',
            'id' => 'incident-q', 'class' => 'form-control', 'value' => $q]));
        $form .= self::field('incidentstate', get_string('incidentcolumnstate', 'local_orangeportal'), self::select('incidentstate', $options, $state));
        $form .= self::field('category', get_string('category'), self::select('category',
            [0 => get_string('incidentallcategories', 'local_orangeportal')] + \orange_category_options('incidents'), $category));
        $form .= self::field('impact', get_string('incidentcolumnimpact', 'local_orangeportal'), self::select('impact',
            ['' => get_string('allimpacts', 'local_orangeportal')] + service::impacts(), $impact));
        $form .= self::field('incidentclient', get_string('incidentclient', 'local_orangeportal'), self::select('incidentclient',
            ['' => get_string('incidentallclients', 'local_orangeportal')] + $clients, $client));
        $form .= self::field('incidentproduct', get_string('incidentproduct', 'local_orangeportal'), self::select('incidentproduct',
            ['' => get_string('incidentallproducts', 'local_orangeportal')] + $products, $product));
        foreach (['datefrom' => $from, 'dateto' => $to] as $key => $value) {
            $form .= self::field($key, get_string($key, 'local_orangeportal'), \html_writer::empty_tag('input',
                ['type' => 'date', 'name' => $key, 'id' => 'incident-' . $key, 'value' => $value, 'class' => 'form-control']));
        }
        $form .= \html_writer::tag('label', \html_writer::checkbox('unread', 1, (bool)$unread,
            get_string('incidentunreadonly', 'local_orangeportal')), ['class' => 'orange-incident-unread-filter']);
        $form .= \html_writer::tag('button', get_string('incidentapplyfilters', 'local_orangeportal'), ['class' => 'btn btn-primary', 'type' => 'submit']);
        $form .= \html_writer::link(\orange_link('index.php', ['section' => 'incidents']), get_string('incidentclearfilters', 'local_orangeportal'),
            ['class' => 'orange-incident-reset']);
        $sidebar = \html_writer::tag('aside', \html_writer::tag('h3', get_string('incidentfilters', 'local_orangeportal')) .
            \html_writer::tag('form', $form, ['method' => 'get', 'action' => \orange_link('index.php')->out(false)]),
            ['class' => 'orange-incidents-filters', 'aria-label' => get_string('incidentfilters', 'local_orangeportal')]);
        $rows = ''; $count = 0;
        foreach ($scope as $n) {
            if ($personal && !personal_updates::recipient($n)) { continue; }
            if (($priority === 'critical' && $n->impact !== 'critical') || ($priority === 'normal' && $n->impact === 'critical')) { continue; }
            if ($error) { continue; }
            $closed = notice_options::is_closed($n->state);
            if (($state === 'openonly' && $closed) || ($state === 'closedonly' && !$closed) ||
                    (strpos($state, 'state_') === 0 && substr($state, 6) !== $n->state)) { continue; }
            if ($impact !== '' && $impact !== $n->impact) { continue; }
            if ($client !== '' && $client !== $n->client) { continue; }
            if ($product !== '' && $product !== $n->product) { continue; }
            if ($category && !in_array($n->categoryid, $categoryids)) { continue; }
            if (isset($bounds['datefrom']) && $n->timecreated < $bounds['datefrom']) { continue; }
            if (isset($bounds['dateto']) && $n->timecreated >= $bounds['dateto']) { continue; }
            $identifier = 'INC' . str_pad((string)$n->id, 8, '0', STR_PAD_LEFT);
            if ($q !== '' && \core_text::strpos(\core_text::strtolower($identifier . ' ' . $n->title . ' ' . strip_tags($n->content)),
                    \core_text::strtolower($q)) === false) { continue; }
            $receipt = service::get_receipt($n, (int)$USER->id);
            $isread = $receipt && ($receipt->readtime || $receipt->confirmed);
            if ($unread && $isread) { continue; }
            service::receipt($n, 'received');
            $count++;
            $icon = $isread ? \html_writer::span('', 'orange-incident-unread', ['aria-hidden' => 'true']) :
                \html_writer::span('!', 'orange-incident-unread', ['role' => 'img',
                    'aria-label' => get_string('incidentunread', 'local_orangeportal'), 'title' => get_string('incidentunread', 'local_orangeportal')]);
            $title = \html_writer::link(\orange_link('view.php', ['id' => $n->id]),
                \html_writer::span($identifier, 'orange-incident-identifier') . \html_writer::span(s($n->title)),
                ['class' => 'orange-incident-title orange-incident-title-row']);
            if (!$n->published) { $title .= \html_writer::span(get_string('draft', 'local_orangeportal'), 'orange-incident-draft'); }
            $title .= \html_writer::div(\orange_category_label((int)$n->categoryid, $cats), 'orange-incident-category-label');
            $cells = \html_writer::tag('td', $icon) . \html_writer::tag('td', $title);
            $cells .= \html_writer::tag('td', s(userdate($n->timecreated, '%d/%m/%Y')));
            $cells .= \html_writer::tag('td', $n->timeclosed ? s(userdate($n->timeclosed, '%d/%m/%Y')) : '—');
            $cells .= \html_writer::tag('td', s(service::impacts()[$n->impact] ?? $n->impact));
            $cells .= \html_writer::tag('td', \html_writer::span(s(service::states()[$n->state] ?? $n->state),
                'orange-status ' . ($closed ? 'orange-status-closed' : 'orange-status-open')));
            $cells .= \html_writer::tag('td', s($n->client ?: '—')) . \html_writer::tag('td', s($n->product ?: '—'));
            $rows .= \html_writer::tag('tr', $cells, ['data-incident-id' => $n->id]);
        }
        $actions = '';
        if ($all) {
            $actions .= \html_writer::link((has_capability('local/orangeportal:viewreports', \context_system::instance()) ?
                \orange_link('reports.php', ['section' => 'incidents']) : \orange_link('incident_tracking.php')), get_string('incidenttracking', 'local_orangeportal'),
                ['class' => 'btn btn-outline-secondary orange-incident-tracking-link']);
        }
        if (service::manager() && has_capability('local/orangeportal:changestate', \context_system::instance())) {
            $actions .= \html_writer::link(\orange_link('manage.php', ['kind' => 'incidents', 'new' => 1]),
                get_string('incidentcreate', 'local_orangeportal'), ['class' => 'btn btn-primary']);
        }
        $heading = \html_writer::div(\html_writer::tag('h2', get_string('incidentlisting', 'local_orangeportal')) .
            \html_writer::span(get_string('incidenttotal', 'local_orangeportal', $count), 'orange-incident-total') .
            \html_writer::div($actions, 'orange-incident-list-actions'), 'orange-incident-list-heading');
        $headers = \html_writer::tag('th', \html_writer::span(get_string('incidentcolumnreading', 'local_orangeportal'), 'sr-only'), ['scope' => 'col']);
        foreach (['incidentidentification', 'incidentopened', 'incidentcloseddate', 'incidentcolumnimpact', 'incidentcolumnstate', 'incidentclient', 'incidentproduct'] as $key) {
            $headers .= \html_writer::tag('th', get_string($key, 'local_orangeportal'), ['scope' => 'col']);
        }
        $table = $rows ? \html_writer::div(\html_writer::tag('table',
            \html_writer::tag('caption', get_string('incidents', 'local_orangeportal'), ['class' => 'sr-only']) .
            \html_writer::tag('thead', \html_writer::tag('tr', $headers)) . \html_writer::tag('tbody', $rows),
            ['class' => 'orange-notices-table orange-incidents-table']), 'orange-table-scroll',
            ['tabindex' => 0, 'role' => 'region', 'aria-label' => get_string('incidents', 'local_orangeportal')]) :
            \html_writer::div(get_string('incidentempty', 'local_orangeportal'), 'orange-empty');
        return \html_writer::div($sidebar . \html_writer::tag('section', $heading . ($error ? $OUTPUT->notification($error, 'error') : '') . $table,
            ['class' => 'orange-incidents-results']), 'orange-incidents-layout');
    }
}
