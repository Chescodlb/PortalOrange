<?php
defined('MOODLE_INTERNAL') || die();
$observers = [
    ['eventname' => '\\core\\event\\role_assigned', 'callback' => '\\local_orangeportal\\orange_administration::role_changed'],
    ['eventname' => '\\core\\event\\role_unassigned', 'callback' => '\\local_orangeportal\\orange_administration::role_changed'],
    ['eventname' => '\\core\\event\\cohort_member_added', 'callback' => '\\local_orangeportal\\orange_administration::membership_changed'],
    ['eventname' => '\\core\\event\\cohort_member_removed', 'callback' => '\\local_orangeportal\\orange_administration::membership_changed'],
    ['eventname' => '\\core\\event\\course_module_viewed', 'callback' => '\\local_orangeportal\\document_reads::viewed'],
    ['eventname' => '\\core\\event\\course_module_deleted', 'callback' => '\\local_orangeportal\\document_reads::deleted'],
    ['eventname' => '\\core\\event\\course_completed', 'callback' => '\\local_orangeportal\\observer::course_completed'],
    ['eventname' => '\\core\\event\\user_graded', 'callback' => '\\local_orangeportal\\observer::user_graded'],
];
