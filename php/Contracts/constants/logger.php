<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * Log level by name - [RFC 5424](https://datatracker.ietf.org/doc/html/rfc5424)
 */
const
    LOG_DEBUG = 'debug',
    LOG_INFO = 'info',
    LOG_NOTICE = 'notice',
    LOG_WARNING = 'warning',
    LOG_ERROR = 'error',
    LOG_CRITICAL = 'critical',
    LOG_ALERT = 'alert',
    LOG_EMERGENCY = 'emergency'
;

/**
 * Log levels, following [Monolog](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Level.php).
 *
 * - `100` `Debug` debug-level messages
 * - `200` `Informational` informational messages
 * - `250` `Notice` normal but significant condition
 * - `300` `Warning` warning conditions
 * - `400` `Error` error conditions
 * - `500` `Critical` critical conditions
 * - `550` `Alert` action must be taken immediately
 * - `600` `Emergency` system is unusable
 */
const LOG_LEVEL = [
    'debug'     => 100,
    100         => 'debug',
    'info'      => 200,
    200         => 'info',
    'notice'    => 250,
    250         => 'notice',
    'warning'   => 300,
    300         => 'warning',
    'error'     => 400,
    400         => 'error',
    'critical'  => 500,
    500         => 'critical',
    'alert'     => 550,
    550         => 'alert',
    'emergency' => 600,
    600         => 'emergency',
];
