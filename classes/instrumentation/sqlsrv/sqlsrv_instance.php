<?php

/**
 * Tideways APM integration for Moodle.
 *
 * @author Luke Carrier <luke@carrier.im>
 * @copyright 2018 AVADO Learning
 */

namespace local_tideways\instrumentation\sqlsrv;

use coding_exception;
use Exception;
use ReflectionMethod;
use ReflectionProperty;
use sqlsrv_native_moodle_database;
use sqlsrv_native_moodle_temptables;

/**
 * Individual sqlsrv driver instance.
 */
class sqlsrv_instance {
    /**
     * Database instance.
     *
     * @var sqlsrv_native_moodle_database
     */
    protected $db;

    /**
     * Table prefix.
     *
     * @var string
     */
    protected $prefix;

    /**
     * Temporary tables controller.
     *
     * @var sqlsrv_native_moodle_temptables
     */
    protected $temptables;

    /**
     * Reserved words.
     *
     * @var string[]
     */
    protected $reservewords;

    /**
     * Server information.
     *
     * @var mixed[]
     */
    protected $serverinfo;

    /**
     * Initialise an instance of the driver.
     *
     * @param sqlsrv_native_moodle_database $db
     * @throws \ReflectionException
     */
    public function __construct(sqlsrv_native_moodle_database $db) {
        $this->db = $db;

        $this->prefix = $this->db->get_prefix();

        $reflector = new ReflectionProperty(
                sqlsrv_native_moodle_database::class, 'reservewords');
        $reflector->setAccessible(true);
        $this->reservewords = $reflector->getValue($this->db);
        $reflector->setAccessible(false);

        $reflector = new ReflectionProperty(
                sqlsrv_native_moodle_database::class, 'temptables');
        $reflector->setAccessible(true);
        $this->temptables = $reflector->getValue($this->db);
        $reflector->setAccessible(false);
    }

    /**
     * Have we collected server information?
     *
     * @return bool
     */
    public function has_server_info() {
        return $this->serverinfo !== null;
    }

    /**
     * Gather server information.
     *
     * We can only do this after {@link sqlsrv_native_moodle_database::connect()},
     * at which point we'll have a resource.
     *
     * @return void
     */
    public function gather_server_info() {
        $this->serverinfo = $this->db->get_server_info();
    }

    /**
     * Generate SQL for {@link moodle_database::delete_records_select()}.
     *
     * @param string $table
     * @param string|null $select
     * @return string
     */
    public function make_delete_records_select_sql($table, $select=null) {
        $sql = sprintf('DELETE FROM {%s}', $table);
        if (strlen(trim($select))) {
            $sql .= sprintf('WHERE %s', $select);
        }

        return $this->fix_table_names($sql);
    }

    /**
     * Generate SQL for {@link moodle_database::get_recordset_sql()}.
     *
     * @param string $sql
     * @param int|null $limitfrom
     * @param int|null $limitnum
     * @return string
     */
    public function make_get_recordset_sql_sql($sql, $limitfrom=null, $limitnum=null) {
        list($limitfrom, $limitnum) = $this->normalise_limit_from_num(
                $limitfrom, $limitnum);
        if ($limitfrom || $limitnum) {
            if ($this->supports_offset_fetch()) {
                $sql = (substr($sql, -1) === ';')
                        ? substr($sql, 0, -1) : $sql;
                if (!strpos(strtoupper($sql), 'ORDER BY')) {
                    $sql .= ' ORDER BY 1';
                }
                $sql .= sprintf(' OFFSET %d ROWS', $limitfrom);
                if ($limitnum > 0) {
                    $sql .= sprintf(' FETCH NEXT %d ROWS ONLY', $limitnum);
                }
            } else {
                if ($limitnum >= 1) {
                    $fetch = $limitfrom + $limitnum;
                    if (PHP_INT_MAX - $limitnum < $limitfrom) {
                        $fetch = PHP_INT_MAX;
                    }
                    $sql = preg_replace(
                            '/^([\s(])*SELECT([\s]+(DISTINCT|ALL))?(?!\s*TOP\s*\()/i',
                            sprintf('\\1SELECT\\2 TOP %d', $fetch), $sql);
                }
            }
        }

        $sql = $this->add_no_lock_to_temp_tables($sql);

        return $this->fix_table_names($sql);
    }

    /**
     * Generate SQL for {@link moodle_database::insert_record_raw()}.
     *
     * @param string $table
     * @param mixed[] $params
     * @param bool $customsequence
     * @return string
     * @throws coding_exception
     */
    public function make_insert_record_raw_sql($table, $params, $customsequence) {
        if (!is_array($params)) {
            $params = (array)$params;
        }
        if (empty($params)) {
            throw new coding_exception('can\'t insert a record with no params');
        }
        $isidentity = false;

        $sql = '';

        if ($customsequence) {
            if (!isset($params['id'])) {
                throw new coding_exception('null value in sequence column forbidden');
            }

            $columns = $this->db->get_columns($table);
            if (isset($columns['id']) && $columns['id']->auto_increment) {
                $isidentity = true;
            }

            if ($isidentity) {
                $sql .= sprintf('SET IDENTITY_INSERT {%s} ON;%s', $table, PHP_EOL);
            }
        }

        $fields = implode(',', array_keys($params));
        $qms = array_fill(0, count($params), '?');
        $qms = implode(',', $qms);
        $sql .= sprintf('INSERT INTO {%s} (%s) VALUES(%s)', $table, $fields, $qms);

        if ($customsequence && $isidentity) {
            $sql .= sprintf(';%sSET IDENTITY_INSERT {%s} OFF', PHP_EOL, $table);
        }

        return $this->fix_table_names($sql);
    }

    /**
     * Add NOLOCK hint to all temporary tables.
     *
     * @see sqlsrv_native_moodle_database::add_no_lock_to_temp_tables()
     * @param string $sql
     * @return string
     */
    protected function add_no_lock_to_temp_tables($sql) {
        return preg_replace_callback('/(\{([a-z][a-z0-9_]*)\})(\s+(\w+))?/', function($matches) {
            $table = $matches[1];
            $name = $matches[2];
            $tail = isset($matches[3]) ? $matches[3] : '';
            $replacement = $matches[0];

            if ($this->temptables && $this->temptables->is_temptable($name)) {
                if (!empty($tail)) {
                    if (in_array(strtolower(trim($tail)), $this->reservewords)) {
                        return sprintf('%s WITH (NOLOCK)%s', $table, $tail);
                    }
                }
                return sprintf('%s WITH (NOLOCK)', $replacement);
            } else {
                return $replacement;
            }
        }, $sql);
    }

    /**
     * Generate SQL for {@link moodle_database::set_field_select()}.
     *
     * @param string $table
     * @param string $newfield
     * @param mixed $newvalue
     * @param string $select
     * @param mixed[] $params
     * @return string
     * @throws coding_exception
     * @throws \ReflectionException
     */
    public function make_set_field_select_sql($table, $newfield, $newvalue, $select, $params) {
        $sql = '';

        try {
            list($select, $params, ) = $this->db->fix_sql_params($select, $params);
        } catch (Exception $e) {
            throw new coding_exception($e->getMessage());
        }

        // Get column metadata
        $columns = $this->db->get_columns($table);
        $column = $columns[$newfield];

        $reflector = new ReflectionMethod(
                sqlsrv_native_moodle_database::class, 'normalise_value');
        $reflector->setAccessible(true);
        $newvalue = $reflector->invoke($this->db, $column, $newvalue);
        $reflector->setAccessible(false);

        if ($newvalue === null) {
            $newfield = sprintf('%s = NULL', $newfield);
        } else {
            $newfield = sprintf('%S = ?', $newfield);
            array_unshift($params, $newvalue);
        }

        $sql = sprintf('UPDATE {%s} SET %s', $table, $newfield);
        if ($select) {
            $sql .= sprintf(' WHERE %s', $select);
        }

        return $sql;
    }

    /**
     * Generate SQL for {@link moodle_database::update_record_raw()}.
     *
     * @param string $table
     * @param mixed[] $params
     * @return string
     * @throws coding_exception
     */
    public function make_update_record_raw_sql($table, $params) {
        $params = (array)$params;

        if (!isset($params['id'])) {
            throw new coding_exception('id field must be specified');
        }
        $id = $params['id'];
        unset($params['id']);
        $params[] = $id;

        if (empty($params)) {
            throw new coding_exception('no fields found');
        }

        $sets = [];
        foreach ($params as $field => $value) {
            $sets[] = sprintf('%s = ?', $field);
        }
        $sets = implode(',', $sets);

        $sql = sprintf('UPDATE {%s} SET %s WHERE id = ?', $table, $sets);
        return $this->fix_table_names($sql);
    }

    /**
     * Replace placeholder names with prefixed names.
     *
     * @see sqlsrv_native_moodle_database::fix_table_names()
     * @param string $sql
     * @return string
     */
    public function fix_table_names($sql) {
        return preg_replace(
            '/\{([a-z][a-z0-9_]*)\}/', $this->prefix . '$1', $sql);
    }

    /**
     * Normalise $limitfrom and $limitnum parameter values.
     *
     * @see \moodle_database::normalise_limit_from_num()
     * @param int $limitnum
     * @param int $limitfrom
     * @return int[]
     */
    protected function normalise_limit_from_num($limitfrom, $limitnum) {
        if ($limitfrom === null || $limitfrom === '' || $limitfrom === -1) {
            $limitfrom = 0;
        }
        if ($limitnum === null || $limitnum === '' || $limitnum === -1) {
            $limitnum = 0;
        }

        $limitfrom = (int)$limitfrom;
        $limitnum  = (int)$limitnum;
        $limitfrom = max(0, $limitfrom);
        $limitnum  = max(0, $limitnum);

        return [$limitfrom, $limitnum];
    }

    /**
     * Does the server support OFFSET/FETCH clauses?
     *
     * @return bool
     */
    protected function supports_offset_fetch() {
        return $this->serverinfo['version'] > 11;
    }
}
