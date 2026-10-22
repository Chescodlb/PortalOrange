<?php
namespace local_orangeportal;
defined('MOODLE_INTERNAL') || die();

/** Server-side paging shared by reading tables and filtered CSV exports. */
class reading_report {
    public static function data(string $kind, int $noticeid = 0, int $userid = 0, string $reading = '',
            int $page = 0, int $perpage = 25, int $since = 0): array {
        global $DB;
        [$from, $where, $params, $readtime] = self::query($kind, $noticeid, $userid, $reading, $since);
        $total = $DB->count_records_sql("SELECT COUNT(1) $from WHERE $where", $params);
        $page = $perpage > 0 ? min(max(0, $page), max(0, (int)ceil($total / $perpage) - 1)) : 0;
        $rowkey = $DB->sql_concat('n.id', "':'", 'u.id');
        $names = implode(',', array_map(static fn($field) => 'u.' . $field, \core_user\fields::get_name_fields()));
        $rows = $DB->get_records_sql("SELECT $rowkey AS rowkey, n.id AS noticeid, n.title, n.kind,
            u.id AS userid, $names, n.impact, n.state, n.categoryid, n.timecreated,
            COALESCE(v.timecreated,n.timecreated) AS publicationdate, r.received, ($readtime) AS readtime
            $from WHERE $where ORDER BY COALESCE(v.timecreated,n.timecreated) DESC,n.id DESC,u.lastname,u.firstname,u.id",
            $params, $page * $perpage, $perpage);
        return ['total' => $total, 'page' => $page, 'rows' => $rows];
    }
    /** One row per published notice, including notices with no current recipients. */
    public static function summary(string $kind, int $page = 0, int $perpage = 25, int $noticeid = 0, int $since = 0): array {
        global $DB;
        [$from, $where, $params, $readtime] = self::query($kind, $noticeid, 0, '', $since);
        if (!in_array($kind, ['incidents', 'notifications'], true)) { throw new \invalid_parameter_exception('Invalid report section'); }
        $noticeparams = ['summarykind' => $kind];
        $noticewhere = 'n.kind=:summarykind AND n.published=1 AND n.deleted=0';
        if ($noticeid) { $noticewhere .= ' AND n.id=:summaryid'; $noticeparams['summaryid'] = $noticeid; }
        if ($since) { $noticewhere .= ' AND COALESCE(v.timecreated,n.timecreated)>=:summarysince'; $noticeparams['summarysince'] = $since; }
        $noticefrom = 'FROM {local_orange_notice} n LEFT JOIN {local_orange_revision} v ON v.noticeid=n.id AND v.revision=n.revision';
        $total = $DB->count_records_sql("SELECT COUNT(1) $noticefrom WHERE $noticewhere", $noticeparams);
        $page = min(max(0, $page), max(0, (int)ceil($total / $perpage) - 1));
        $notices = $DB->get_records_sql("SELECT n.id,n.title,n.revision,n.timecreated,
            COALESCE(v.timecreated,n.timecreated) AS publicationdate $noticefrom WHERE $noticewhere
            ORDER BY COALESCE(v.timecreated,n.timecreated) DESC,n.id DESC", $noticeparams, $page * $perpage, $perpage);
        $stats = [];
        if ($notices) {
            [$insql, $inparams] = $DB->get_in_or_equal(array_keys($notices), SQL_PARAMS_NAMED, 'summarynotice');
            $stats = $DB->get_records_sql("SELECT n.id, COUNT(1) AS recipients,
                SUM(CASE WHEN ($readtime)>0 THEN 1 ELSE 0 END) AS readcount
                $from WHERE $where AND n.id $insql GROUP BY n.id", $params + $inparams);
        }
        foreach ($notices as $id => $notice) {
            $notice->recipients = (int)($stats[$id]->recipients ?? 0);
            $notice->readcount = (int)($stats[$id]->readcount ?? 0);
            $notice->unreadcount = $notice->recipients - $notice->readcount;
        }
        return ['total' => $total, 'page' => $page, 'rows' => $notices];
    }

    /** Rolling windows concern the current revision, never unrelated state changes. */
    public static function since(string $period, ?int $now = null): int {
        if (!in_array($period, ['week', 'month', 'all'], true)) { throw new \invalid_parameter_exception('Invalid report period'); }
        return $period === 'all' ? 0 : ($now ?? time()) - ($period === 'week' ? 7 : 30) * DAYSECS;
    }

    public static function ratio(int $part, int $total): string {
        return $total ? get_string('reportratio', 'local_orangeportal', (object)[
            'percent' => format_float(100 * $part / $total, 1), 'part' => $part, 'total' => $total]) :
            get_string('reportnotapplicable', 'local_orangeportal');
    }

    /** Aggregate current assignments without materialising all recipient/publication pairs in PHP. */
    public static function user_stats(int $since = 0, int $userid = 0): array {
        global $DB;
        [$from, $where, $params, $readtime] = self::query('', 0, $userid, '', $since);
        return $DB->get_records_sql("SELECT u.id,
            SUM(CASE WHEN n.kind='incidents' THEN 1 ELSE 0 END) AS incidents_total,
            SUM(CASE WHEN n.kind='incidents' AND ($readtime)>0 THEN 1 ELSE 0 END) AS incidents_read,
            SUM(CASE WHEN n.kind='notifications' THEN 1 ELSE 0 END) AS notifications_total,
            SUM(CASE WHEN n.kind='notifications' AND ($readtime)>0 THEN 1 ELSE 0 END) AS notifications_read
            $from WHERE $where GROUP BY u.id", $params);
    }

    /** Unread critical publications across the whole history, independent of the selected window. */
    public static function critical_pending(int $userid = 0): array {
        global $DB;
        [$from, $where, $params, $readtime] = self::query('', 0, $userid, 'unread');
        $closed = array_keys(array_filter(notice_options::records('states'), static fn($r, $code) =>
            notice_options::is_closed($code), ARRAY_FILTER_USE_BOTH));
        if ($closed) {
            [$sql, $extra] = $DB->get_in_or_equal($closed, SQL_PARAMS_NAMED, 'criticalclosed', false);
            $where .= " AND (n.kind<>'incidents' OR n.state $sql)";
            $params += $extra;
        }
        return $DB->get_records_sql("SELECT u.id,COUNT(1) AS pending $from
            WHERE $where AND n.impact='critical' GROUP BY u.id", $params);
    }

    /** Users are paged after aggregating their pending readings, so ordering is global. */
    public static function directory(string $search, int $cohortid, string $sort,
            int $since, int $page = 0, int $perpage = 25): array {
        global $DB;
        if (!in_array($sort, ['pending', 'name'], true)) { throw new \invalid_parameter_exception('Invalid user order'); }
        if ($cohortid && !isset(orange_scope::cohorts()[$cohortid])) { throw new \moodle_exception('outsideorange', 'local_orangeportal'); }
        [$from, $where, $params, $readtime] = self::query('', 0, 0, '');
        $window = '1=1';
        if ($since) { $window = 'COALESCE(v.timecreated,n.timecreated)>=:userssince'; $params['userssince'] = $since; }
        // Use separate bound parameters wherever Moodle's driver cannot reuse a named placeholder.
        $window2 = str_replace(':userssince', ':userssince2', $window);
        $window3 = str_replace(':userssince', ':userssince3', $window);
        $window4 = str_replace(':userssince', ':userssince4', $window);
        if ($since) { $params += ['userssince2' => $since, 'userssince3' => $since, 'userssince4' => $since]; }
        $stats = "SELECT u.id,
            SUM(CASE WHEN n.kind='incidents' AND $window THEN 1 ELSE 0 END) AS incidents_total,
            SUM(CASE WHEN n.kind='incidents' AND ($readtime)>0 AND $window2 THEN 1 ELSE 0 END) AS incidents_read,
            SUM(CASE WHEN n.kind='notifications' AND $window3 THEN 1 ELSE 0 END) AS notifications_total,
            SUM(CASE WHEN n.kind='notifications' AND ($readtime)>0 AND $window4 THEN 1 ELSE 0 END) AS notifications_read
            $from WHERE $where GROUP BY u.id";
        $outerparams = ['directorycohort' => (int)get_config('local_orangeportal', 'audiencecohort')];
        $outerwhere = 'a.cohortid=:directorycohort AND p.deleted=0 AND p.id>1';
        if ($search !== '') {
            $terms = [];
            foreach (['firstname', 'lastname', 'idnumber'] as $i => $field) {
                $terms[] = $DB->sql_like('p.' . $field, ':usersearch' . $i, false);
                $outerparams['usersearch' . $i] = '%' . $DB->sql_like_escape($search) . '%';
            }
            $terms[] = $DB->sql_like($DB->sql_concat('p.firstname', "' '", 'p.lastname'), ':usersearchfull', false);
            $outerparams['usersearchfull'] = '%' . $DB->sql_like_escape($search) . '%';
            $outerwhere .= ' AND (' . implode(' OR ', $terms) . ')';
        }
        if ($cohortid) {
            $outerwhere .= ' AND EXISTS (SELECT 1 FROM {cohort_members} cm WHERE cm.userid=p.id AND cm.cohortid=:filtercohort)';
            $outerparams['filtercohort'] = $cohortid;
        }
        $base = 'FROM {user} p JOIN {cohort_members} a ON a.userid=p.id';
        $total = $DB->count_records_sql("SELECT COUNT(1) $base WHERE $outerwhere", $outerparams);
        $page = min(max(0, $page), max(0, (int)ceil($total / $perpage) - 1));
        $names = implode(',', array_map(static fn($f) => 'p.' . $f, \core_user\fields::get_name_fields()));
        $order = $sort === 'pending' ? 'pending DESC,' : '';
        $rows = $DB->get_records_sql("SELECT p.id,$names,p.idnumber,
            COALESCE(s.incidents_total,0) AS incidents_total, COALESCE(s.incidents_read,0) AS incidents_read,
            COALESCE(s.notifications_total,0) AS notifications_total, COALESCE(s.notifications_read,0) AS notifications_read,
            COALESCE(s.incidents_total,0)+COALESCE(s.notifications_total,0)-COALESCE(s.incidents_read,0)-COALESCE(s.notifications_read,0) AS pending
            $base LEFT JOIN ($stats) s ON s.id=p.id WHERE $outerwhere ORDER BY {$order}p.lastname,p.firstname,p.id",
            $params + $outerparams, $page * $perpage, $perpage);
        return ['rows' => $rows, 'total' => $total, 'page' => $page];
    }

    private static function query(string $kind, int $noticeid, int $userid, string $reading, int $since = 0): array {
        global $DB;
        experience::require_access();
        require_capability('local/orangeportal:viewreports', \context_system::instance());
        if (!in_array($kind, ['', 'incidents', 'notifications'], true) || !in_array($reading, ['', 'read', 'unread'], true)) {
            throw new \invalid_parameter_exception('Invalid reading report filter');
        }
        [$assigned, $params] = service::audience_sql('n.id', 'u.id');
        $params['audience'] = (int)get_config('local_orangeportal', 'audiencecohort');
        $from = "FROM {local_orange_notice} n JOIN {cohort_members} membership ON membership.cohortid=:audience
            JOIN {user} u ON u.id=membership.userid
            LEFT JOIN {local_orange_revision} v ON v.noticeid=n.id AND v.revision=n.revision
            LEFT JOIN {local_orange_receipt} r ON r.noticeid=n.id AND r.userid=u.id AND r.revision=n.revision";
        $where = "n.published=1 AND n.deleted=0 AND u.deleted=0 AND u.id>1 AND $assigned";
        if ($since) { $where .= ' AND COALESCE(v.timecreated,n.timecreated)>=:since'; $params['since'] = $since; }
        if ($kind !== '') { $where .= ' AND n.kind=:kind'; $params['kind'] = $kind; }
        if ($noticeid) { $where .= ' AND n.id=:noticeid'; $params['noticeid'] = $noticeid; }
        if ($userid) { $where .= ' AND u.id=:userid'; $params['userid'] = $userid; }
        // Legacy confirmation evidence remains readable; no new confirmations are written.
        $readtime = 'CASE WHEN r.readtime>0 THEN r.readtime ELSE COALESCE(r.confirmed,0) END';
        if ($reading !== '') { $where .= " AND ($readtime)" . ($reading === 'read' ? '>0' : '=0'); }
        return [$from, $where, $params, $readtime];
    }

}
