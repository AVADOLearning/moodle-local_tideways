<?php

/**
 * Tideways APM integration for Moodle.
 *
 * @author Luke Carrier <luke@carrier.im>
 * @copyright 2018 AVADO Learning
 */

namespace local_tideways\service;

use core\task\adhoc_task;
use core\task\scheduled_task;
use Tideways\Profiler;

/**
 * Cron/task processing service.
 */
class cron_service extends abstract_service implements service {
    /**
     * @inheritdoc
     */
    public function get_service_name() {
        return 'cron';
    }

    /**
     * @inheritdoc
     */
    public function pre_setup() {}

    /**
     * @inheritdoc
     */
    public function post_setup() {}

    /**
     * Handle scheduled task start.
     *
     * @param scheduled_task $task
     * @return void
     */
    public function pre_cron_run_inner_scheduled_task(scheduled_task $task) {
        $this->start_profiler();
        Profiler::setTransactionName(get_class($task));
        Profiler::setCustomVariable('component', $task->get_component());
    }

    /**
     * Handle ad hoc task start.
     *
     * @param adhoc_task $task
     * @return void
     */
    public function pre_cron_run_inner_adhoc_task(adhoc_task $task) {
        $this->start_profiler();
        Profiler::setTransactionName(get_class($task));
        Profiler::setCustomVariable('component', $task->get_component());
        Profiler::setCustomVariable('id', $task->get_id());
        Profiler::setCustomVariable('userid', $task->get_userid());
        Profiler::setCustomVariable(
                'customdata', $task->get_custom_data_as_string());
    }

    /**
     * Stop the profiler and submit tracing data.
     *
     * @return void
     */
    public function stop_profiler() {
        Profiler::stop();
    }
}
