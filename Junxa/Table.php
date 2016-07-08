<?php

namespace Thaumatic\Junxa;

use Thaumatic\Junxa;
use Thaumatic\Junxa\Column;
use Thaumatic\Junxa\Exceptions\JunxaConfigurationException;
use Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException;
use Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException;
use Thaumatic\Junxa\Query as Q;
use Thaumatic\Junxa\Query\Builder as QueryBuilder;
use Thaumatic\Junxa\Query\Element;

/**
 * Models a database table, providing access to columns and rows.
 */
class Table
{

    /**
     * @var Thaumatic\Junxa the database model this table is attached to
     */
    private $database;

    /**
     * @var string the name of the table
     */
    private $name;

    /**
     * @var array<string> the names of the columns in the table
     */
    private $columns = [];

    /**
     * @var array<string:Thaumatic\Junxa\Column> map of column names to their
     * models
     */
    private $columnModels = [];

    /**
     * @var array<string> the names of the columns in the table's primary key
     */
    private $primary = [];

    /**
     * @var string if the table has a fully auto-incrementing (therefore
     * single-column) primary key, the name of the column
     */
    private $autoIncrementPrimary;

    /**
     * @var array<string> the names of all non-dynamic columns (referencing
     * actual database columns) in the table
     */
    private $staticColumns = [];

    /**
     * @var array<string> the names of all dynamic columns (virtually
     * constructed from SQL) in the table
     */
    private $dynamicColumns = [];

    /**
     * @var array<string> the names of demand-only columns (not loaded when
     * row models are generated)
     */
    private $demandOnlyColumns = [];

    /**
     * @var array<string:Thaumatic\Junxa\Row> row cached used when
     * Junxa::DB_CACHE_TABLE_ROWS is on
     */
    private $cache = [];

    /**
     * @param Thaumatic\Junxa the database model this table model is attached to
     * @param string the table name
     * @param int the number of columns in the modeled table, if known
     * @param array<stdClass> if available, field info objects for the table's
     * columns, as returned by mysqli::fetch_field()
     */
    public function __construct(Junxa $database, $name, $columnCount = null, array $fields = [])
    {
        $this->database = $database;
        $this->name = $name;
        $this->determineColumns($columnCount, $fields);
        $this->init();
    }

    /**
     * Initialization function to be called upon the table model being set up.
     * Intended to be overridden by child classes.
     */
    protected function init()
    {
    }

    /**
     * Processes raw information about the table's column configuration
     * into tracking information and column models.
     *
     * @param int the number of columns in the table
     * @param array field information on the table's columns
     */
    private function determineColumns($columnCount = null, $fields = [])
    {
        $index = 0;
        foreach ($this->database->query('SHOW COLUMNS FROM ' . $this->getName()) as $row) {
            $colinfo[$index++] = $row;
        }
        if ($columnCount === null) {
            $fields = [];
            $res = $this->database->query("SELECT *\n\tFROM " . $this->getName() . "\n\tLIMIT 0", Junxa::QUERY_RAW);
            $columnCount = $res->field_count;
            for ($i = 0; $i < $columnCount; $i++) {
                $fields[] = $res->fetch_field();
            }
            $res->free();
        }
        $autoIncPrimary = false;
        for ($i = 0; $i < $columnCount; $i++) {
            $field = $fields[$i];
            $column = $field->name;
            $this->columns[] = $column;
            $this->staticColumns[] = $column;
            $class = $this->database->columnClass($this->name);
            $columnModel = new $class($this, $column, $field, $colinfo[$i], null);
            $this->columnModels[$column] = $columnModel;
            if ($columnModel->flag(Column::MYSQL_FLAG_PRI_KEY)) {
                $this->primary[] = $column;
                if ($columnModel->flag(Column::MYSQL_FLAG_AUTO_INCREMENT)) {
                    $autoIncPrimary = true;
                }
            }
        }
        if ($autoIncPrimary && count($this->primary) === 1) {
            $this->autoIncrementPrimary = $this->primary[0];
        }
    }

    /**
     * Add a dynamic column to the table.  This is a virtual column that
     * is calculated according to (abstractly-modeled) SQL provided and
     * aliased to a given name.
     *
     * @param string the name of the virtual column
     * @param mixed any content that can be rendered as SQL by the Junxa query engine
     */
    public function addDynamicColumn($name, $content)
    {
        if (isset($this->columnModels[$name])) {
            throw new JunxaConfigurationException(
                'Dynamic column name "'
                . $name
                . '" duplicates existing column'
            );
        }
        $alias = Q::alias($content, $name);
        $this->columns[] = $name;
        $this->dynamicColumns[] = $alias;
        $res = $this->database->query(['select' => $alias, 'limit' => 0], Junxa::QUERY_RAW);
        $class = $this->database->columnClass($this->name);
        $columnModel = new $class($this, $name, $res->fetch_field(), $res->fetch_field_direct(0), null, $alias);
        $this->columnModels[$name] = $columnModel;
        $res->free();
    }

    /**
     * Sets whether a specified column is demand-only.  Demand-only columns
     * are not retrieved when the overall row they are a part of is retrieved,
     * only when they are specifically requested.
     *
     * @param string the name of the column
     * @param bool the setting desired
     * @return $this
     */
    public function setColumnDemandOnly($name, $flag)
    {
        if ($this->demandOnlyColumns) {
            $pos = array_search($name, $this->demandOnlyColumns);
        } else {
            $pos = false;
        }
        if ($flag) {
            if (in_array($name, $this->primary)) {
                throw new JunxaConfigurationException(
                    'Cannot set primary key column "'
                    . $name
                    . '" as demand-only'
                );
            }
            if ($pos === false) {
                $this->demandOnlyColumns[] = $name;
            }
        } else {
            if ($pos !== false) {
                array_splice($this->demandOnlyColumns, $pos, 1);
            }
        }
        return $this;
    }

    /**
     * Retrieves whether the specified column is demand-only.
     *
     * @param string the name of the column
     * @return bool
     */
    public function getColumnDemandOnly($name)
    {
        return $this->demandOnlyColumns && in_array($name, $this->demandOnlyColumns);
    }

    /**
     * Property-mode accessor for column models.
     *
     * @param string the column name
     * @return Thaumatic\Junxa\Column column result, actual class will be as defined by Junxa::columnClass()
     */
    public function __get($property)
    {
        if (isset($this->columnModels[$property])) {
            return $this->columnModels[$property];
        } else {
            throw new JunxaNoSuchColumnException($property);
        }
    }

    /**
     * Retrieves the Junxa database model this table is attached to.
     *
     * @return Thaumatic\Junxa
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * Retrieves the list of column names for this table.
     *
     * @return array<string>
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Retrieves whether the table has a column of a given name.
     *
     * @param string column name
     * @return bool
     */
    public function hasColumn($name)
    {
        return in_array($name, $this->columns);
    }

    /**
     * Retrieves the list of non-dynamic (i.e. referencing an actual database column) column names for this table.
     *
     * @return array<string>
     */
    public function getStaticColumns()
    {
        return $this->staticColumns;
    }

    /**
     * Retrieves the list of dynamic (i.e. constructed virtually from SQL) column names for this table.
     *
     * @return array<string>
     */
    public function getDynamicColumns()
    {
        return $this->dynamicColumns;
    }

    /**
     * Retrieves the list of demand-only columns for this table.
     *
     * @return array<string>
     */
    public function getDemandOnlyColumns()
    {
        return $this->demandOnlyColumns;
    }

    /**
     * Retrieve the table name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the names of the columns in the table's primary key.  If there
     * is no primary key, returns an empty array.
     *
     * @return array<string>
     */
    public function getPrimaryKey()
    {
        return $this->primary;
    }

    /**
     * If the table has a fully auto-incrementing (and therefore single-column)
     * primary key, returns the name of it.
     *
     * @return string|null
     */
    public function getAutoIncrementPrimary()
    {
        return $this->autoIncrementPrimary;
    }

    /**
     * Returns the abstract representation of what should be selected
     * from the database in order to generate a row for this table.
     *
     * @return mixed
     */
    public function getSelectTarget()
    {
        if ($this->dynamicColumns) {
            $out = $this->dynamicColumns;
            array_unshift($out, $this);
            return $out;
        } else {
            return $this;
        }
    }

    /**
     * Flushes the table's row cache.
     *
     * @return $this
     */
    public function flushCache()
    {
        $this->cache = [];
        return $this;
    }

    /**
     * Removes the specified key from the cache.
     *
     * @param string the cache key
     * @return $this
     */
    public function removeCacheKey($key)
    {
        unset($this->cache[$key]);
    }

    /**
     * Retreives the cached value for the specified key.
     *
     * @param string the cache key
     * @return mixed
     */
    public function getCachedValue($key, $value)
    {
        return array_key_exists($key, $this->cache) ? $this->cache[$key] : null;
    }

    /**
     * Sets the cached value for the specified key.
     *
     * @param string the cache key
     * @param mixed the value
     * @return $this
     */
    public function setCachedValue($key, $value)
    {
        $this->cache[$key] = $value;
    }

    /**
     * @throws JunxaConfigurationException if the database model was not configured for row caching
     */
    public function cachedRow()
    {
        $args = func_get_args();
        $argc = count($args);
        if ($argc !== count($this->primary)) {
            throw new \Exception('row must be identified by same number of arguments as columns in primary key');
        }
        $key = self::argsCacheKey($args);
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        if (!$this->database->getOption(Junxa::DB_CACHE_TABLE_ROWS)) {
            throw new JunxaConfigurationException('DB_CACHE_TABLE_ROWS option not enabled');
        }
    }

    /**
     * Retrieves a row model, either an empty one (when no arguments are
     * passed) or a populated one via either of two modes of operation.  The
     * first mode is retrieval by primary key, in which case the function takes
     * the same number of arguments as the number of columns in the primary key
     * and returns the row, if any, which matches those values.  The second
     * mode is retrieval by query, where only a single argument is provided,
     * which must be a Junxa query builder, a Junxa query element, or an array,
     * which will be used as a query builder configuration specification.  The
     * query provided will automatically have a limit of 1 applied to it.
     *
     * It is not safe to send Web user input directly to this function because
     * it accepts an array argument; PHP's [] request data syntax means that a
     * user could construct a custom query, possibly a dangerous one.  Use
     * rowByPrimaryKey() if you need this.
     *
     * @param mixed either a primary key part, a Junxa query builder, a Junxa
     * query element, or an array query specification
     * @param scalar... additional primary key parts, if the table has a
     * multi-part primary key
     * @return Thaumatic\Junxa\Row row result, actual class will be as defined
     * by Junxa::rowClass()
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if primary
     * key retrieval is used and the number of arguments doesn't match
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if an
     * invalid query definition is provided
     */
    public function row()
    {
        $args = func_get_args();
        $argc = count($args);
        $class = $this->database->rowClass($this->name);
        if (!$argc) {
            return new $class($this, null);
        }
        $target = $this->getSelectTarget();
        if ($argc === 1 && !is_scalar($args[0])) {
            $what = $args[0];
            if ($what instanceof QueryBuilder) {
                $query = $what;
            } elseif ($what instanceof Element) {
                $query = $this->query()->where($what);
            } elseif (is_array($what)) {
                $query = new QueryBuilder($what, $this);
            } else {
                throw new JunxaInvalidQueryException(
                    'condition for table row must be a '
                    . 'Thaumatic\Junxa\Query\Element or a '
                    . 'Thaumatic\Junxa\Query\Builder or a '
                    . 'query array, got '
                    . (is_object($what) ? get_class($what) : gettype($what))
                );
            }
        } else {
            if ($argc !== count($this->primary)) {
                throw new JunxaInvalidQueryException(
                    'row must be identified by same number of arguments as columns in primary key'
                );
            }
            if ($this->database->getOption(Junxa::DB_CACHE_TABLE_ROWS)) {
                $key = self::argsCacheKey($args);
                if (!empty($this->cache[$key])) {
                    return $this->cache[$key];
                }
            }
            $query = $this->query();
            for ($i = 0; $i < $argc; $i++) {
                $query->where($this->primary[$i], $args[$i]);
            }
        }
        $query
            ->clearSelect()
            ->select($target)
            ->limit(1)
            ->option('emptyOkay', true)
            ->setMode(Junxa::QUERY_SINGLE_ARRAY)
        ;
        $row = $this->database->query($query);
        if (!$row) {
            return null;
        }
        $out = new $class($this, $row);
        if ($this->database->getOption(Junxa::DB_CACHE_TABLE_ROWS)) {
            if (!isset($key)) {
                $key = $out->cacheKey();
            }
            if (empty($this->cache[$key])) {
                $this->cache[$key] = $out;
            }
            return $this->cache[$key];
        }
        return $out;
    }

    /**
     * Identical to row(), but only allows retrieval by primary key.  This is
     * essentially so that it is safe to send Web user input directly to this
     * function.
     *
     * @param scalar a primary key part
     * @param scalar... additional primary key parts, if the table has a
     * multi-part primary key
     * @return Thaumatic\Junxa\Row row result, actual class will be as defined
     * by Junxa::rowClass()
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if primary
     * key retrieval is used and the number of arguments doesn't match
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if an
     * invalid query definition is provided
     */
    public function rowByPrimaryKey()
    {
        $args = func_get_args();
        foreach ($args as $arg) {
            if (!is_scalar($arg)) {
                throw new JunxaInvalidQueryException('non-scalar argument');
            }
        }
        return call_user_func_array([$this, 'row'], $args);
    }

    /**
     * Retrieves a set of rows matching a specification, which may be a Junxa
     * query builder, a Junxa query element, a Junxa column, or an array, which
     * will be treated as the specification for a query builder.
     *
     * @param mixed query builder or query element or column or array query
     * specification, defaults to all rows
     * @return array<Thaumatic\Junxa\Row> row results, actual class will be as
     * defined by Junxa::rowClass()
     */
    public function rows($what = [])
    {
        switch (gettype($what)) {
            case 'object':
                if ($what instanceof QueryBuilder) {
                    $query = $what;
                } else {
                    if (!($what instanceof Element || $what instanceof Column)) {
                        throw new JunxaInvalidQueryException(
                            'object type for row query must be '
                            . 'Thaumatic\Junxa\Query\Builder or '
                            . 'Thaumatic\Junxa\Query\Element or '
                            . 'Thaumatic\Junxa\Column, got '
                            . get_class($what)
                        );
                    }
                    $query = $this->query()->where($what);
                }
                break;
            case 'array':
                $query = new QueryBuilder($what, $this);
                break;
            default:
                throw new JunxaInvalidQueryException('invalid query for table row retrieval');
        }
        $query
            ->clearSelect()
            ->select($this->selectTarget())
            ->setMode(Junxa::QUERY_ARRAYS)
        ;
        $class = $this->database->rowClass($this->name);
        $rows = $this->database->query($query);
        $out = [];
        if ($this->database->getOption(Junxa::DB_CACHE_TABLE_ROWS) && count($this->primary) && !$query->option('nocache')) {
            foreach ($rows as $data) {
                $row = new $class($this, $data);
                $key = $row->cacheKey();
                if (empty($this->cache[$key])) {
                    $this->cache[$key] = $row;
                }
                $out[] = $this->cache[$key];
            }
        } else {
            foreach ($rows as $data) {
                $out[] = new $class($this, $data);
            }
        }
        return $out;
    }

    /**
     * Executes an optimization query against this table.
     */
    public function optimize()
    {
        $this->database->query('OPTIMIZE TABLE `' . $this->getName() . '`', Junxa::QUERY_FORGET);
    }

    /**
     * Retrieves the count of rows for a specified query.
     *
     * @param mixed a Junxa query builder, or a Junxa query element, or an array specification for a query builder
     */
    public function rowCount($query = [])
    {
        if ($query instanceof QueryBuilder) {
            $query = clone($query);
        } elseif ($query instanceof Element) {
            $query = $this->query()->where($query);
        } elseif (is_array($query)) {
            $query = new QueryBuilder($query, $this);
        } else {
            throw new JunxaInvalidQueryException(
                'query for rowCount() must be a '
                . 'Thaumatic\Junxa\Query\Builder or a '
                . 'Thaumatic\Junxa\Query\Element or an '
                . 'array query specification'
            );
        }
        $query
            ->clearOrder()
            ->clearOperations()
        ;
        if ($query->group || $query->having) {
            $query
                ->select(1)
                ->validate()
            ;
            $query = $this->query()
                ->select(Q::func('COUNT', Q::literal('*')))
                ->from(Q::alias(Q::paren($query), 'Junxa_counted_query'))
            ;
        } else {
            $query->select()->func('COUNT', $this);
        }
        $query->setMode(Junxa::QUERY_SINGLE_CELL);
        return $this->database->query($query);
    }

    public function tableScan(&$tables, &$null)
    {
        $tables[$this->name] = true;
    }

    public function express($query, $context, $column, $parent)
    {
        if ($context === 'join') {
            return '`' . $this->name . '`';
        }
        if ($query->isMultitable()
            && !($context === 'function' && $parent instanceof Element && $parent->type === 'COUNT')
        ) {
            if ($this->demandOnlyColumns && $context !== 'function') {
                $items = [];
                foreach ($this->columns as $column) {
                    if (!in_array($column, $this->demandOnlyColumns)) {
                        $items[] = '`' . $this->name . '`.`' . $column . '`';
                    }
                }
                return join(', ', $items);
            } else {
                return '`' . $this->name . '`.*';
            }
        }
        if ($this->demandOnlyColumns && $context !== 'function') {
            $items = [];
            foreach ($this->columns as $column) {
                if (!in_array($column, $this->demandOnlyColumns)) {
                    $items[] = '`' . $column . '`';
                }
            }
            return join(', ', $items);
        } else {
            return '*';
        }
    }

    /**
     * Returns a Junxa query builder, configured with this table as its
     * parent table and this table's database as its parent database and
     * otherwise empty.
     *
     * @param array query definition to provide to the query builder constructor
     * @return Thaumatic\Junxa\Query\Builder
     */
    public function query(array $def = null)
    {
        return QueryBuilder::make($this->database, $this, $def);
    }

    /**
     * Returns a serialization representation for this table.
     *
     * @return string
     */
    public function serialize()
    {
        return 'table:' . $this->getName();
    }

    /**
     * Retrieves a row cache key to use for the specified list of
     * primary-key-matching arguments.
     *
     * @param array<scalar> an array of primary key values
     */
    private static function argsCacheKey(array $args)
    {
        return
            count($args) > 1
            ? join("\0", $args) . '|' . join('', array_map('md5', $args))
            : strval($args[0]);
    }

}
