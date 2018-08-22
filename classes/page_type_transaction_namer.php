<?php

/**
 * Tideways APM integration for Moodle.
 *
 * @author Luke Carrier <luke@carrier.im>
 * @copyright 2018 AVADO Learning
 */

namespace local_tideways;

use core_shutdown_manager;
use Tideways\Profiler;

/**
 * Derive the transaction name from moodle_page->_pagetype.
 */
class page_type_transaction_namer {
    /**
     * Initialise.
     *
     * @return void
     */
    public static function init() {
        $namer = new static();

        if (class_exists(core_shutdown_manager::class)) {
            core_shutdown_manager::register_function([$namer, 'handle_shutdown']);
        }
    }

    /**
     * Handle shutdown.
     *
     * @return void
     */
    public function handle_shutdown() {
        global $PAGE;
        $this->set_name($PAGE->pagetype);
    }

    /**
     * Set the transaction name.
     *
     * @param string $name
     */
    protected function set_name($name) {
        Profiler::setTransactionName($name);
    }
}
