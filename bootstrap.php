<?php

/**
 * Tideways APM integration for Moodle.
 *
 * @author Luke Carrier <luke@carrier.im>
 * @copyright 2018 AVADO Learning
 */

use local_tideways\instrumentation\sqlsrv\sqlsrv_instrumentation;
use local_tideways\service\cron_service;
use local_tideways\service\service;
use local_tideways\service\web_service;
use Tideways\Profiler;

// Don't allow direct access to this script.
(__FILE__ !== $_SERVER['SCRIPT_FILENAME']) || die;

// Abort unless the profiler API is available.
if (!class_exists(Profiler::class)) {
    return;
}

// Since the autoloader won't yet be available, manually source our
// dependencies.
require_once __DIR__ . '/classes/service/service.php';
require_once __DIR__ . '/classes/service/abstract_service.php';
require_once __DIR__ . '/classes/service/cron_service.php';
require_once __DIR__ . '/classes/service/web_service.php';
require_once __DIR__ . '/classes/instrumentation/sqlsrv/sqlsrv_instance.php';
require_once __DIR__ . '/classes/instrumentation/sqlsrv/sqlsrv_instrumentation.php';

/**
 * Return "complete" configuration, with default values.
 *
 * @return array
 */
function local_tideways_config() {
    global $CFG;

    $config = property_exists($CFG, 'local_tideways')
            ? $CFG->local_tideways : [];

    $config['development'] = array_key_exists('development', $config)
            && $config['development'];

    $profilerdefaults = [
        'framework' => null,
    ];
    $profileroptions = array_key_exists('profiler_options', $CFG->local_tideways)
            ? $config['profiler_options'] : [];
    $config['profiler_options'] = array_merge($profilerdefaults, $profileroptions);

    return $config;
}

/**
 * Determine and initialise the service and install instrumentation.
 *
 * @return void
 */
function local_tideways_pre_setup() {
    /** @var service $LOCAL_TIDEWAYS_SERVICE */
    global $LOCAL_TIDEWAYS_SERVICE;

    $iscron = defined('LOCAL_TIDEWAYS_CRON') && LOCAL_TIDEWAYS_CRON;

    $config = local_tideways_config();
    if ($iscron) {
        $LOCAL_TIDEWAYS_SERVICE = new cron_service($config);
    } else {
        $LOCAL_TIDEWAYS_SERVICE = new web_service($config);
    }
    Profiler::setServiceName($LOCAL_TIDEWAYS_SERVICE->get_service_name());
    $LOCAL_TIDEWAYS_SERVICE->pre_setup();

    sqlsrv_instrumentation::init();
}

/**
 * Let the service perform post-setup actions.
 *
 * @return void
 */
function local_tideways_post_setup() {
    /** @var service $LOCAL_TIDEWAYS_SERVICE */
    global $LOCAL_TIDEWAYS_SERVICE;
    $LOCAL_TIDEWAYS_SERVICE->post_setup();
}
