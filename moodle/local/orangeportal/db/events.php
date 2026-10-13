<?php
defined('MOODLE_INTERNAL') || die();
$observers = [
    ['eventname' => '\\core\\event\\course_completed', 'callback' => '\\local_orangeportal\\observer::course_completed'],
    ['eventname' => '\\core\\event\\user_graded', 'callback' => '\\local_orangeportal\\observer::user_graded'],
];
