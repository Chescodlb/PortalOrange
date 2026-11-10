<?php
/** Delete only explicitly named local browser fixtures; never touches existing content. */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
if (!str_starts_with($CFG->wwwroot, 'http://localhost:')) { throw new coding_exception('Local only'); }
$CFG->forced_plugin_settings['local_orangeportal']['teamsenabled'] = 0;
\core\session\manager::set_user(get_admin());
foreach ($DB->get_records_select('course', 'shortname LIKE ?', ['TESTWEB-FLOW-%']) as $course) { delete_course($course, false); }
foreach ($DB->get_records_select('course', 'shortname LIKE ?', ['TEST-ACCESS-%']) as $course) { delete_course($course, false); }
foreach ($DB->get_records_select('course', 'shortname LIKE ?', ['TEST-PUBLICATION-%']) as $course) { delete_course($course, false); }
foreach ($DB->get_records_sql('SELECT cm.id FROM {course_modules} cm JOIN {modules} m ON m.id=cm.module JOIN {url} u ON u.id=cm.instance
    WHERE m.name=? AND u.name LIKE ?', ['url', 'TESTWEB FLOW Document %']) as $cm) { course_delete_module($cm->id); }
cache::make('core', 'coursemodinfo')->purge(); course_modinfo::clear_instance_cache();
echo "Local content flow fixtures removed\n";
