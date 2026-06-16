<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
  [
    'eventname' => '\core\event\user_loggedin',
    'callback'  => '\local_dashboard\observers::on_login',
    'priority'  => 9999
  ],
  [
    'eventname' => '\core\event\message_sent',
    'callback'  => '\local_dashboard\observers::on_message_sent',
    'priority'  => 500
  ],
  [
    'eventname' => '\core\event\message_viewed',
    'callback'  => '\local_dashboard\observers::on_message_viewed',
    'priority'  => 500
  ],
];
