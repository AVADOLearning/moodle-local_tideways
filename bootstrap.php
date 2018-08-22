<?php

/**
 * Tideways APM integration for Moodle.
 *
 * @author Luke Carrier <luke@carrier.im>
 * @copyright 2018 AVADO Learning
 */

use local_tideways\page_type_transaction_namer;
use Tideways\Profiler;

// Don't allow direct access to this script.
(__FILE__ !== $_SERVER['SCRIPT_FILENAME']) || die;

// Abort unless the profiler API is available.
if (!class_exists(Profiler::class)) {
    return;
}

// Since the autoloader won't yet be available, manually source our
// dependencies.
require_once __DIR__ . '/classes/page_type_transaction_namer.php';

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
 * Configure the profiler and begin profiling.
 *
 * @return void
 */
function local_tideways_bootstrap() {
    $config = local_tideways_config();

    if ($config['development']) {
        Profiler::startDevelopment($config['profiler_options']);
    } else {
        Profiler::start($config['profiler_options']);
    }

    page_type_transaction_namer::init();
}

local_tideways_bootstrap();
