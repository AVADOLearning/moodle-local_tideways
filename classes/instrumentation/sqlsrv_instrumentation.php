<?php

/**
 * Tideways APM integration for Moodle.
 *
 * @author Luke Carrier <luke@carrier.im>
 * @copyright 2018 AVADO Learning
 */

namespace local_tideways\instrumentation;

use coding_exception;
use moodle_database;
use ReflectionException;
use sqlsrv_native_moodle_database;
use Tideways\Profiler;
use Tideways\Traces\Span;

/**
 * sqlsrv extension instrumentation.
 */
class sqlsrv_instrumentation {
    /**
     * Tideways PHP extension name.
     *
     * @var string
     */
    const TIDEWAYS_EXTENSION = 'tideways';

    /**
     * Category for all SQL-related spans.
     *
     * @var string
     */
    const CATEGORY_SQL = 'sql';

    /**
     * Initialise.
     *
     * @return void
     */
    public static function init() {
        $instrumentation = new static();

        Profiler::watchCallback(
                'sqlsrv_native_moodle_database::connect',
                [$instrumentation, 'handle_connect']);
        Profiler::watchCallback(
                'sqlsrv_native_moodle_database::dispose',
                [$instrumentation, 'handle_dispose']);

        Profiler::watchCallback(
                'sqlsrv_native_moodle_database::do_query',
                [$instrumentation, 'handle_do_query']);
    }

    /**
     * Handle connection to a server.
     *
     * @param array $context
     * @return Span
     * @throws ReflectionException
     */
    public function handle_connect($context) {
        $span = Profiler::createSpan(static::CATEGORY_SQL);
        $span->annotate([
            'sql' => 'connect',
            'host' => $context['args'][0],
        ]);

        return $span;
    }

    /**
     * Handle disposing the resource.
     *
     * @param array $context
     * @return Span
     * @throws ReflectionException
     */
    public function handle_dispose($context) {
        $span = Profiler::createSpan(static::CATEGORY_SQL);
        $span->annotate([
            'sql' => 'dispose',
        ]);

        return $span;
    }

    /**
     * Handle a query.
     *
     * @param array $context
     * @return Span
     */
    public function handle_do_query($context) {
        $sql = $this->fix_table_names($context['object'], $context['args'][0]);
        $params = array_key_exists(1, $context['args'])
                ? $context['args'][1] : [];

        $span = Profiler::createSpan(static::CATEGORY_SQL);
        $span->annotate([
            'sql' => $sql,
            'params' => $params,
        ]);
        
        return $span;
    }

    /**
     * Replace placeholder names with prefixed names.
     *
     * @see sqlsrv_native_moodle_database::fix_table_names()
     * @param moodle_database $db
     * @param string $sql
     * @return string
     */
    public function fix_table_names(moodle_database $db, $sql) {
        return preg_replace(
                '/\{([a-z][a-z0-9_]*)\}/', $db->get_prefix() . '$1', $sql);
    }
}
