<?php

/**
 * Tideways APM integration for Moodle.
 *
 * @author Luke Carrier <luke@carrier.im>
 * @copyright 2018 AVADO Learning
 */

namespace local_tideways;

use Tideways\Profiler;

/**
 * Derive the transaction name from moodle_page->_pagetype.
 */
class page_type_transaction_namer {
    /**
     * Have we handled a moodle_page->set_pagetype() call?
     *
     * If so, we want to avoid handling a later
     * moodle_page::initialise_default_pagetype() call.
     *
     * @var bool
     */
    protected $handledsetpagetype;

    /**
     * Initialise.
     *
     * @return void
     */
    public static function init() {
        $namer = new static();

        Profiler::watchCallback(
                'moodle_page::initialise_default_pagetype',
                [$namer, 'handle_initialise_default_pagetype']);
        Profiler::watchCallback(
                'moodle_page::set_pagetype',
                [$namer, 'handle_set_pagetype']);
    }

    /**
     * Handle moodle_page::initialise_default_pagetype() call.
     *
     * @param array $context
     *
     * @return void
     */
    public function handle_initialise_default_pagetype($context) {
        global $CFG, $SCRIPT;

        if ($this->handledsetpagetype) {
            return;
        }

        // Based on moodle_page::initialise_default_pagetype(), since there's no
        // easy way to obtain the result of the call.
        $script = $context['args'][0];
        if (isset($CFG->pagepath)) {
            $script = $CFG->pagepath;
        }

        if (is_null($script)) {
            $script = ltrim($SCRIPT, '/');
            $len = strlen($CFG->admin);
            if (substr($script, 0, $len) == $CFG->admin) {
                $script = 'admin' . substr($script, $len);
            }
        }

        $path = str_replace('.php', '', $script);
        if (substr($path, -1) == '/') {
            $path .= 'index';
        }

        $pagetype = (empty($path) || $path == 'index')
                ? 'site-index' : str_replace('/', '-', $path);

        $this->set_name($pagetype);
    }

    /**
     * Handle moodle_page::set_pagetype() call.
     *
     * @param array $context
     *
     * @return void
     */
    public function handle_set_pagetype($context) {
        $this->handledsetpagetype = true;
        $this->set_name($context['args'][0]);
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
