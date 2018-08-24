<?php

/**
 * Tideways APM integration for Moodle.
 *
 * @author Luke Carrier <luke@carrier.im>
 * @copyright 2018 AVADO Learning
 */

use core\task\manager;
use local_tideways\service\cron_service;

defined('MOODLE_INTERNAL') || die;

/**
 * Execute cron tasks, but keep Tideways in the loop.
 *
 * @see cron_run()
 * @throws coding_exception
 * @throws moodle_exception
 */
function local_tideways_cron_run() {
    /** @var cron_service $LOCAL_TIDEWAYS_SERVICE */
    global $CFG, $DB, $LOCAL_TIDEWAYS_SERVICE;

    if (CLI_MAINTENANCE) {
        mtrace('CLI maintenance mode active, cron execution suspended.');
        exit(1);
    }

    if (moodle_needs_upgrading()) {
        mtrace('Moodle upgrade pending, cron execution suspended.');
        exit(1);
    }

    require_once "{$CFG->libdir}/adminlib.php";

    if (!empty($CFG->showcronsql)) {
        $DB->set_debug(true);
    }
    if (!empty($CFG->showcrondebugging)) {
        set_debugging(DEBUG_DEVELOPER, true);
    }

    core_php_time_limit::raise();
    raise_memory_limit(MEMORY_EXTRA);
    cron_setup_user();

    $starttime = microtime();
    $timenow = time();
    mtrace(sprintf('Server time: %s', date('r', $timenow)));

    while (!manager::static_caches_cleared_since($timenow)
            && $task = manager::get_next_scheduled_task($timenow)) {
        $LOCAL_TIDEWAYS_SERVICE->pre_cron_run_inner_scheduled_task($task);
        cron_run_inner_scheduled_task($task);
        unset($task);
        $LOCAL_TIDEWAYS_SERVICE->stop_profiler();
    }

    while (!manager::static_caches_cleared_since($timenow)
            && $task = manager::get_next_adhoc_task($timenow)) {
        $LOCAL_TIDEWAYS_SERVICE->pre_cron_run_inner_adhoc_task($task);
        cron_run_inner_adhoc_task($task);
        unset($task);
        $LOCAL_TIDEWAYS_SERVICE->stop_profiler();
    }

    mtrace('Cron script completed correctly');

    gc_collect_cycles();
    $difftime = microtime_diff($starttime, microtime());
    mtrace(sprintf('Cron completed at %s', date('H:i:s')));
    mtrace(sprintf('Memory used %s', display_size(memory_get_usage())));
    mtrace(sprintf('Execution took %s seconds', $difftime));
}
