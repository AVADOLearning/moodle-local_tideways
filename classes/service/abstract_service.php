<?php

/**
 * Tideways APM integration for Moodle.
 *
 * @author Luke Carrier <luke@carrier.im>
 * @copyright 2018 AVADO Learning
 */

namespace local_tideways\service;

use Tideways\Profiler;

abstract class abstract_service implements service {
    /**
     * Configuration.
     *
     * @var array
     */
    protected $config;

    /**
     * Initialiser.
     *
     * @param array $config
     */
    public function __construct($config) {
        $this->config = $config;
    }

    /**
     * Start the profiler, respecting development mode.
     *
     * @return void
     */
    protected function start_profiler() {
        if ($this->config['development']) {
            Profiler::startDevelopment($this->config['profiler_options']);
        } else {
            Profiler::start($this->config['profiler_options']);
        }
    }
}
