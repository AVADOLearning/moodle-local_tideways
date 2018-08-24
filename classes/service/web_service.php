<?php

/**
 * Tideways APM integration for Moodle.
 *
 * @author Luke Carrier <luke@carrier.im>
 * @copyright 2018 AVADO Learning
 */

namespace local_tideways\service;

use core_shutdown_manager;
use Tideways\Profiler;

/**
 * Web service.
 */
class web_service extends abstract_service implements service {
    /**
     * @inheritdoc
     */
    public function pre_setup() {
        $this->start_profiler();
    }

    /**
     * @inheritdoc
     */
    public function post_setup() {
        if (class_exists(core_shutdown_manager::class)) {
            core_shutdown_manager::register_function([$this, 'handle_shutdown']);
        }
    }

    /**
     * Handle shutdown.
     *
     * @return void
     */
    public function handle_shutdown() {
        global $PAGE;
        Profiler::setTransactionName($PAGE->pagetype);
    }
}
