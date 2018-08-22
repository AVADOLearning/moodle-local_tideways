<?php

/**
 * Tideways APM integration for Moodle.
 *
 * @author Luke Carrier <luke@carrier.im>
 * @copyright 2018 AVADO Learning
 */

namespace local_tideways;

use coding_exception;
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
     * Known database connections.
     *
     * @var sqlsrv_instance[]
     */
    protected $instances = [];

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
                'sqlsrv_native_moodle_database::execute',
                [$instrumentation, 'handle_execute']);

        Profiler::watchCallback(
                'sqlsrv_native_moodle_database::get_recordset_sql',
                [$instrumentation, 'handle_get_recordset_sql']);

        Profiler::watchCallback(
                'sqlsrv_native_moodle_database::delete_records_select',
                [$instrumentation, 'handle_delete_records_select']);
        Profiler::watchCallback(
                'sqlsrv_native_moodle_database::set_field_select',
                [$instrumentation, 'handle_set_field_select']);
        Profiler::watchCallback(
                'sqlsrv_native_moodle_database::insert_record_raw',
                [$instrumentation, 'handle_insert_record_raw']);
        Profiler::watchCallback(
                'sqlsrv_native_moodle_database::update_record_raw',
                [$instrumentation, 'handle_update_record_raw']);
    }

    /**
     * Handle connection to a server.
     *
     * @param array $context
     * @return Span
     * @throws ReflectionException
     */
    public function handle_connect($context) {
        $this->get_instance($context['object'], false);

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
        $this->get_instance($context['object']);

        $span = Profiler::createSpan(static::CATEGORY_SQL);
        $span->annotate([
            'sql' => 'dispose',
        ]);

        return $span;
    }

    /**
     * Handle deletion of records.
     *
     * @param array $context
     * @return Span
     * @throws ReflectionException
     */
    public function handle_delete_records_select($context) {
        $instance = $this->get_instance($context['object']);
        $sql = $instance->make_delete_records_select_sql(
                $context['args'][0], $context['args'][0]);
        $params = array_key_exists(2, $context['args'])
                ? $context['args'][2] : [];

        $span = Profiler::createSpan(static::CATEGORY_SQL);
        $span->annotate([
            'sql' => $sql,
            'params' => $params,
        ]);
        
        return $span;
    }

    /**
     * Handle arbitrary query execution.
     *
     * @param array $context
     * @return Span
     * @throws ReflectionException
     */
    public function handle_execute($context) {
        $instance = $this->get_instance($context['object']);
        $sql = $instance->fix_table_names($context['args'][0]);
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
     * Handle opening of a recordset.
     *
     * @param array $context
     * @return Span
     * @throws ReflectionException
     */
    public function handle_get_recordset_sql($context) {
        $instance = $this->get_instance($context['object']);
        $params = array_key_exists(1, $context['args'])
                ? $context['args'][1] : null;
        $limitfrom = array_key_exists(2, $context['args'])
                ? $context['args'][2] : 0;
        $limitnum = array_key_exists(3, $context['args'])
                ? $context['args'][3] : 0;
        $sql = $instance->make_get_recordset_sql_sql(
                $context['args'][0], $limitfrom, $limitnum);

        $span = Profiler::createSpan(static::CATEGORY_SQL);
        $span->annotate([
            'sql' => $sql,
            'params' => $params,
        ]);

        return $span;
    }

    /**
     * Handle insertion of a record.
     *
     * @param array $context
     * @return Span|null
     * @throws ReflectionException
     */
    public function handle_insert_record_raw($context) {
        $instance = $this->get_instance($context['object']);
        $table = $context['args'][0];
        $params = $context['args'][1];
        $bulk = array_key_exists(3, $context['args'])
                ? $context['args'][3] : false;
        $customsequence = array_key_exists(4, $context['args'])
                ? $context['args'][3] : false;
        try {
            $sql = $instance->make_insert_record_raw_sql($table, $params, $customsequence);

            $span = Profiler::createSpan(static::CATEGORY_SQL);
            $span->annotate([
                'sql' => $sql,
                'params' => $params,
                'bulk' => $bulk,
            ]);

            return $span;
        } catch (coding_exception $e) {}
    }

    /**
     * Handle setting of a field on a set of matching records.
     *
     * @param $context
     * @return Span
     * @throws ReflectionException
     * @throws coding_exception
     */
    public function set_field_select($context) {
        $instance = $this->get_instance($context['object']);
        $table = $context['args'][0];
        $newfield = $context['args'][1];
        $newvalue = $context['args'][2];
        $select = $context['args'][3];
        $params = array_key_exists(4, $context['args'])
                ? $context['args'][4] : [];
        $sql = $instance->make_set_field_select_sql(
                $table, $newfield, $newvalue, $select, $params);

        $span = Profiler::createSpan(static::CATEGORY_SQL);
        $span->annotate([
            'sql' => $sql,
            'params' => $params,
        ]);

        return $span;
    }

    /**
     * Handle updating a record.
     *
     * @param $context
     * @return Span
     * @throws ReflectionException
     * @throws coding_exception
     */
    public function handle_update_record_raw($context) {
        $instance = $this->get_instance($context['object']);
        $table = $context['args'][0];
        $params = $context['args'][1];
        $bulk = array_key_exists(2, $context['args'])
                ? $context['args'][2] : false;
        $sql = $instance->make_update_record_raw_sql($table, $params);

        $span = Profiler::createSpan(static::CATEGORY_SQL);
        $span->annotate([
            'sql' => $sql,
            'params' => $params,
            'bulk' => $bulk,
        ]);

        return $span;
    }

    /**
     * Get the database instance.
     *
     * @param sqlsrv_native_moodle_database $db
     * @param bool $connected
     * @return sqlsrv_instance
     * @throws ReflectionException
     */
    protected function get_instance(sqlsrv_native_moodle_database $db, $connected=null) {
        $instanceid = spl_object_hash($db);

        if (!array_key_exists($instanceid, $this->instances)) {
            $this->instances[$instanceid] = new sqlsrv_instance($db);
        }

        $instance = $this->instances[$instanceid];
        if ($connected && !$instance->has_server_info()) {
            $instance->gather_server_info();
        }

        return $instance;
    }
}
