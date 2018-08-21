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
 * Configure the profiler and begin profiling.
 *
 * @return void
 */
function local_tideways_bootstrap() {
    Profiler::start([
        'framework' => null,
    ]);

    page_type_transaction_namer::init();
}

local_tideways_bootstrap();
