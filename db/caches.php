<?php
/**
 * Cache definitions for PrimeiraPagina Pro plugin
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    'unread_messages' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'ttl' => 300, // 5 minutos
        'staticacceleration' => true,
        'staticaccelerationsize' => 100,
    ],
];
