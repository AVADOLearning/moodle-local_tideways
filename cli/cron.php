<?php

/**
 * Tideways APM integration for Moodle.
 *
 * @author Luke Carrier <luke@carrier.im>
 * @copyright 2018 AVADO Learning
 */

define('CLI_SCRIPT', true);
define('LOCAL_TIDEWAYS_CRON', true);

require dirname(dirname(dirname(__DIR__))) . '/config.php';
require_once "{$CFG->libdir}/clilib.php";
require_once "{$CFG->libdir}/cronlib.php";
require_once dirname(__DIR__) . '/locallib.php';

list($options, $unrecognised) = cli_get_params(array('help'=>false),
    array('h'=>'help'));

if ($unrecognised) {
    $unrecognised = implode("\n  ", $unrecognised);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognised));
}

if ($options['help']) {
    $help = <<<HELP
Execute periodic cron actions.

Options:
-h, --help            Print out this help

Example:
$ sudo -u www-data /usr/bin/php admin/cli/cron.php
HELP;

    echo $help;
    die;
}

local_tideways_cron_run();
