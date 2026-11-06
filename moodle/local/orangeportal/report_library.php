<?php
defined('MOODLE_INTERNAL') || die();
use local_orangeportal\library_report;
use local_orangeportal\reading_report;
use local_orangeportal\report_scope;
$label = static fn($key) => get_string($key, 'local_orangeportal');
$documentid = $reportparams['library_document'];
$search = $reportparams['library_search'];
$usersearch = $reportparams['library_usersearch'];
$reading = $reportparams['library_reading'];
$fields = '';
foreach ($reportparams as $key => $value) {
    if (in_array($key, ['library_search', 'library_usersearch', 'library_reading', 'library_page'])) { continue; }
    $fields .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $key, 'value' => $value]);
}
$input = $documentid ? 'library_usersearch' : 'library_search';
$fields .= html_writer::div(html_writer::tag('label', $label($documentid ? 'reportsearchuser' : 'libraryreportsearch'), ['for' => $input]) .
    html_writer::empty_tag('input', ['type' => 'search', 'name' => $input, 'id' => $input,
        'value' => $documentid ? $usersearch : $search, 'class' => 'form-control']), 'orange-filter-field');
if ($documentid) {
    echo html_writer::link($reporturl(['library_document' => 0, 'library_page' => 0]), $label('backtolist'));
    $document = library_report::document($documentid);
    echo $OUTPUT->heading(s($document->name), 3);
    echo html_writer::tag('p', get_string('reportreadingsummary', 'local_orangeportal', (object)[
        'total' => $document->recipients, 'unread' => reading_report::ratio($document->recipients - $document->readcount, $document->recipients),
        'read' => reading_report::ratio($document->readcount, $document->recipients)]));
    $fields .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'library_search', 'value' => $search]);
    $fields .= html_writer::div(html_writer::tag('label', $label('updates_reading'), ['for' => 'library_reading']) .
        html_writer::select(['' => $label('updates_allreading'), 'unread' => $label('libraryunread'), 'read' => $label('libraryread')],
            'library_reading', $reading, false, ['id' => 'library_reading', 'class' => 'custom-select']), 'orange-filter-field');
    $rows = library_report::readers($document, $reading, $usersearch);
    $table = new html_table();
    $table->head = [get_string('fullnameuser'), get_string('idnumber'), $label('orangecollectives'), $label('updates_reading'), $label('libraryreportfirstread')];
    $total = count($rows);
} else {
    $rows = library_report::documents($search);
    $table = new html_table();
    $table->head = [$label('libraryreportdocument'), $label('incidentcolumncategory'), $label('libraryreportadded'), $label('reportrecipients'),
        $label('reportunreadcount'), $label('reportreadcount')];
    $total = count($rows);
}
echo html_writer::tag('form', $fields . html_writer::tag('button', get_string('search'), ['class' => 'btn btn-primary']),
    ['method' => 'get', 'class' => 'orange-report-filters']);
$page = min(max(0, $reportparams['library_page']), max(0, (int)ceil($total / $perpage) - 1));
$table->attributes['class'] = 'generaltable ' . ($documentid ? 'orange-library-reading-detail' : 'orange-library-reading-summary');
$root = (int)get_config('local_orangeportal', 'librarycategory');
$categories = $root ? \local_orangeportal\library_browser::categories($root) : [];
foreach (array_slice($rows, $page * $perpage, $perpage, true) as $row) {
    if ($documentid) {
        $table->data[] = [s(fullname($row->user)), s($row->user->idnumber), s(report_scope::user_cohorts($row->user->id)),
            $label($row->readtime ? 'libraryread' : 'libraryunread'), $row->readtime ? userdate($row->readtime) : '—'];
    } else {
        $trail = \local_orangeportal\library_browser::trail($row->category, $root, $categories);
        $table->data[] = [html_writer::link($reporturl(['library_document' => $row->id, 'library_page' => 0,
            'library_usersearch' => '', 'library_reading' => '']), s($row->name)),
            s(implode(' › ', array_map(fn($c) => $c->name, $trail)) ?: $label('library')), $row->added ? userdate($row->added) : '—',
            $row->recipients, reading_report::ratio($row->recipients - $row->readcount, $row->recipients), reading_report::ratio($row->readcount, $row->recipients)];
    }
}
echo html_writer::div(html_writer::table($table), 'orange-table-scroll');
if (!$total) { echo html_writer::tag('p', $label('reportnoresults')); }
echo $OUTPUT->paging_bar($total, $page, $perpage, $reporturl(), 'library_page');
if ($canexport) {
    echo html_writer::link(orange_link('export.php', ['type' => 'library'] + $reportparams), $label('trackingexport'), ['class' => 'btn btn-primary']);
}
