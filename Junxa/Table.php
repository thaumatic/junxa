<?php

declare(strict_types = 1);

namespace Thaumatic\Junxa;

use Thaumatic\Junxa;
use Thaumatic\Junxa\Column;
use Thaumatic\Junxa\Exceptions\JunxaConfigurationException;
use Thaumatic\Junxa\Exceptions\JunxaDatabaseModelingException;
use Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException;
use Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException;
use Thaumatic\Junxa\Exceptions\JunxaNoSuchKeyException;
use Thaumatic\Junxa\Key;
use Thaumatic\Junxa\KeyPart;
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
     * @var array<string> the names of preload columns (loaded when row models
     * are generated)
     */
    private $preloadColumns = [];

    /**
     * @var array<string> the names of demand-loaded columns (not loaded when
     * row models are generated)
     */
    private $demandLoadColumns = [];

    /**
     * @var array<string:Thaumatic\Junxa\Row> row cached used when
     * Junxa::DB_CACHE_TABLE_ROWS is on
     */
    private $cache = [];

    /**
     * @var array<string:Thaumatic\Junxa\Key> map of the names of the keys in
     * the table to the key models for them
     */
    private $keys;

    /**
     * @var bool whether any columns on the table have dynamic defaults
     */
    private $dynamicDefaultsPresent = false;

    /**
     * An array of arbitrary data that can be attached to this model but which
     * will not be persisted to the database.  Applications can use this to
     * attach application-defined data to table models.
     *
     * @var array<string:mixed>
     */
    private $transientData = [];

    /**
     * @param Thaumatic\Junxa the database model this table model is attached to
     * @param string the table name
     * @param int the number of columns in the modeled table, if known
     * @param array<stdClass> if available, field info objects for the table's
     * columns, as returned by mysqli::fetch_field()
     */
    final public function __construct(Junxa $database, string $name, int $columnCount = null, array $fields = [])
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
     * @return $this
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidIdentifierException if
     * a column name fails validation via
     * {@see Thaumatic\Junxa::validateIdentifier()}
     */
    private function determineColumns(int $columnCount = null, array $fields = []) : self
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
            Junxa::validateIdentifier($column);
            $this->columns[] = $column;
            $this->staticColumns[] = $column;
            $this->preloadColumns[] = $column;
            $class = $this->database->columnClass($column);
            $columnModel = new $class($this, $column, $field, $colinfo[$i], null);
            $this->columnModels[$column] = $columnModel;
            if ($columnModel->getFlag(Column::MYSQL_FLAG_PRI_KEY)) {
                $this->primary[] = $column;
                if ($columnModel->getFlag(Column::MYSQL_FLAG_AUTO_INCREMENT)) {
                    $autoIncPrimary = true;
                }
            }
        }
        if ($autoIncPrimary && count($this->primary) === 1) {
            $this->autoIncrementPrimary = $this->primary[0];
        }
        return $this;
    }

    /**
     * Processes raw information about the table's key configuration into key
     * models.
     *
     * @return $this
     * @throws Thaumatic\Junxa\JunxaDatabaseModelingException if unexpected
     * data is encountered in retrieving key information
     */
    private function determineKeys() : self
    {
        $this->keys = [];
        foreach ($this->database->query('SHOW KEYS FROM ' . $this->getName()) as $row) {
            $columnName = $row->Column_name;
            $cardinality =
                $row->Cardinality === null
                ? null
                : intval($row->Cardinality)
            ;
            $length =
                $row->Sub_part === null
                ? null
                : intval($row->Sub_part)
            ;
            $nulls = ($row->Null === 'YES');
            $keyPart = new KeyPart($columnName, $cardinality, $length, $nulls);
            $name = $row->Key_name;
            if (isset($this->keys[$name])) {
                $key = $this->keys[$name];
            } else {
                $unique = ($row->Non_unique === '0');
                switch ($row->Collation) {
                    case 'A':
                        $collation = Key::COLLATION_ASCENDING;
                        break;
                    case null:
                        $collation = Key::COLLATION_NONE;
                        break;
                    default:
                        throw new JunxaDatabaseModelingException(
                            'unknown collation ' . $row->Collation
                        );
                }
                switch ($row->Index_type) {
                    case 'BTREE':
                        $indexType = Key::INDEX_TYPE_BTREE;
                        break;
                    case 'FULLTEXT':
                        $indexType = Key::INDEX_TYPE_FULLTEXT;
                        break;
                    case 'HASH':
                        $indexType = Key::INDEX_TYPE_HASH;
                        break;
                    case 'RTREE':
                        $indexType = Key::INDEX_TYPE_RTREE;
                        break;
                    default:
                        throw new JunxaDatabaseModelingException(
                            'unknown index type ' . $row->Index_type
                        );
                }
                $comment = $row->Comment;
                $indexComment = $row->Index_comment;
                $key = new Key(
                    $name,
                    $unique,
                    $collation,
                    $indexType,
                    $comment,
                    $indexComment
                );
                $this->keys[$name] = $key;
            }
            $seq = intval($row->Seq_in_index);
            $key->addKeyPart($seq, $keyPart);
        }
        return $this;
    }

    /**
     * Ensures that key information for the table is loaded.
     *
     * @return $this
     */
    private function verifyKeys() : self
    {
        if ($this->keys === null) {
            $this->determineKeys();
        }
        return $this;
    }

    /**
     * @return array<string:Thaumatic\Junxa\Key> map of the names of the keys
     * in the table to the key models for them
     */
    final public function getKeys() : array
    {
        $this->verifyKeys();
        return $this->keys;
    }

    /**
     * Retrieves the named key on this column.
     *
     * @param string key name
     * @return Thaumatic\Junxa\Key
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchKeyException if the
     * specified key does not exist
     */
    final public function getKey(string $keyName) : Key
    {
        $this->verifyKeys();
        if (!isset($this->keys[$keyName])) {
            throw new JunxaNoSuchKeyException($keyName);
        }
        return $this->keys[$keyName];
    }

    /**
     * Retrieves the keys on this table, if any, that index the specified column.
     *
     * @param string column name
     * @return array<Thaumatic\Junxa\Key> the keys that index the given column
     */
    final public function getColumnKeys(string $columnName) : array
    {
        $out = [];
        foreach ($this->getKeys() as $keyName => $key) {
            if ($key->isColumnInKey($columnName)) {
                $out[] = $key;
            }
        }
        return $out;
    }

    /**
     * @param bool whether any columns on this table have dynamic defaults
     * @return $this
     */
    final public function setDynamicDefaultsPresent(bool $val) : self
    {
        $this->dynamicDefaultsPresent = $val;
        return $this;
    }

    /**
     * @return bool whether any columns on this table have dynamic defaults
     */
    final public function getDynamicDefaultsPresent() : bool
    {
        return $this->dynamicDefaultsPresent;
    }

    /**
     * Add a dynamic column to the table.  This is a virtual column that
     * is calculated according to (abstractly-modeled) SQL provided and
     * aliased to a given name.
     *
     * @param string the name of the virtual column
     * @param mixed any content that can be rendered as SQL by the Junxa query engine
     * @return $this
     */
    final public function addDynamicColumn(string $name, $content) : self
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
        $columnModel = new $class($this, $name, $res->fetch_field(), $res->fetch_field_direct(0), $alias);
        $this->columnModels[$name] = $columnModel;
        $res->free();
        return $this;
    }

    /**
     * Sets whether a specified column is demand-loaded.  Demand-loaded columns
     * are not retrieved when the overall row they are a part of is retrieved,
     * only when they are specifically requested.
     *
     * @param string column name
     * @param bool the setting desired
     * @return $this
     * @throws Thaumatic\Junxa\JunxaNoSuchColumnException if the column
     * specified does not exist
     */
    final public function setColumnDemandLoad(string $name, bool $flag) : self
    {
        $mainPos = array_search($name, $this->columns);
        if ($mainPos === false) {
            throw new JunxaNoSuchColumnException($name);
        }
        if ($this->demandLoadColumns) {
            $pos = array_search($name, $this->demandLoadColumns);
        } else {
            $pos = false;
        }
        if ($flag) {
            if (in_array($name, $this->primary)) {
                throw new JunxaConfigurationException(
                    'Cannot set primary key column "'
                    . $name
                    . '" as demand-loaded'
                );
            }
            if ($pos === false) {
                $this->demandLoadColumns[] = $name;
            }
            unset($preloadColumns[$mainPos]);
        } else {
            if ($pos !== false) {
                array_splice($this->demandLoadColumns, $pos, 1);
            }
            $preloadColumns[$mainPos] = $name;
        }
        return $this;
    }

    /**
     * @param string column name
     * @return bool whether the specified column is preloaded
     */
    final public function getColumnPreload($name) : bool
    {
        return in_array($name, $this->preloadColumns);
    }

    /**
     * @param string the name of the column
     * @return bool whether the specified column is demand-loaded
     */
    final public function getColumnDemandLoad($name)
    {
        return in_array($name, $this->demandLoadColumns);
    }

    /**
     * Property-mode accessor for column models.
     *
     * @param string the column name
     * @return Thaumatic\Junxa\Column column result, actual class will be as defined by Junxa::columnClass()
     */
    final public function __get(string $property) : Column
    {
        if (isset($this->columnModels[$property])) {
            return $this->columnModels[$property];
        } else {
            throw new JunxaNoSuchColumnException($property);
        }
    }

    /**
     * @return Thaumatic\Junxa the Junxa database model this table is attached to
     */
    final public function getDatabase() : Junxa
    {
        return $this->database;
    }

    /**
     * @return array<string> the list of column names for this table
     */
    final public function getColumns() : array
    {
        return $this->columns;
    }

    /**
     * @return array<string:Thaumatic\Junxa\Column> the map (by name) of column
     * models for this table
     */
    final public function getColumnModels() : array
    {
        return $this->columnModels;
    }

    /**
     * @param string column name
     * @return bool whether the table has a column of a given name
     */
    final public function hasColumn(string $name) : bool
    {
        return in_array($name, $this->columns);
    }

    /**
     * @return array<string> the list of non-dynamic (i.e. referencing an
     * actual database column) column names for this table
     */
    final public function getStaticColumns() : array
    {
        return $this->staticColumns;
    }

    /**
     * @return array<string> the list of dynamic (i.e. constructed virtually from
     * SQL) column names for this table
     */
    final public function getDynamicColumns() : array
    {
        return $this->dynamicColumns;
    }

    /**
     * @return array<string> the list of preloaded columns for this table
     */
    final public function getPreloadColumns() : array
    {
        return $this->preloadColumns;
    }

    /**
     * @return array<string> the list of demand-loaded columns for this table
     */
    final public function getDemandLoadColumns() : array
    {
        return $this->demandLoadColumns;
    }

    /**
     * @return string the table name
     */
    final public function getName() : string
    {
        return $this->name;
    }

    /**
     * Retrieves the names of the columns in the table's primary key.  If there
     * is no primary key, returns an empty array.
     *
     * @return array<string>
     */
    final public function getPrimaryKey() : array
    {
        return $this->primary;
    }

    /**
     * If the table has a fully auto-incrementing (and therefore single-column)
     * primary key, returns the name of it.
     *
     * @return string|null
     */
    final public function getAutoIncrementPrimary()
    {
        return $this->autoIncrementPrimary;
    }

    /**
     * Returns the abstract representation of what should be selected
     * from the database in order to generate a row for this table.
     *
     * @return mixed
     */
    final public function getSelectTarget()
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
    final public function flushCache() : self
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
    final public function removeCacheKey(string $key) : self
    {
        unset($this->cache[$key]);
        return $this;
    }

    /**
     * Retreives the cached value for the specified key.
     *
     * @param string the cache key
     * @return mixed
     */
    final public function getCachedValue(string $key)
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
    final public function setCachedValue(string $key, $value) : self
    {
        $this->cache[$key] = $value;
        return $this;
    }

    /**
     * @throws JunxaConfigurationException if the database model was not configured for row caching
     */
    final public function cachedRow()
    {
        $args = func_get_args();
        $argc = count($args);
        if ($argc !== count($this->primary)) {
            throw new \Exception('row must be identified by same number of arguments as columns in primary key');
        }
        $key = self::argsCacheKey($args);
        if ($key !== null && array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        if (!$this->database->getOption(Junxa::DB_CACHE_TABLE_ROWS)) {
            throw new JunxaConfigurationException('DB_CACHE_TABLE_ROWS option not enabled');
        }
        return null;
    }

    /**
     * Generates and returns a new row model for the table.
     *
     * @param array<string:mixed> array of data to populate row with
     * @return Thaumatic\Junxa\Row row result, actual class will be as defined
     * by Junxa::rowClass()
     */
    final public function newRow(array $data = null) : Row
    {
        $class = $this->database->rowClass($this->name, $data);
        $out = new $class($this, null);
        if ($data !== null) {
            foreach ($data as $field => $value) {
                $out->$field = $value;
            }
        }
        return $out;
    }

    /**
     * Retrieves a row model via either of two modes of operation.  The first
     * mode is retrieval by primary key, in which case the function takes the
     * same number of arguments as the number of columns in the primary key
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
    final public function row()
    {
        $args = func_get_args();
        $argc = count($args);
        $target = $this->getSelectTarget();
        if ($argc === 1 && !is_scalar($args[0])) {
            $what = $args[0];
            if ($what instanceof QueryBuilder) {
                $query = $what;
            } elseif ($what instanceof Element) {
                $query = $this->baseQuery()->where($what);
            } elseif (is_array($what)) {
                $query = $this->query($what, true);
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
                if ($key !== null && isset($this->cache[$key])) {
                    return $this->cache[$key];
                }
            }
            $query = $this->baseQuery();
            for ($i = 0; $i < $argc; $i++) {
                $query->where($this->primary[$i], $args[$i]);
            }
        }
        $query
            ->clearSelect()
            ->select($target)
            ->limit(1)
            ->setOption(QueryBuilder::OPTION_EMPTY_OKAY, true)
            ->setMode(Junxa::QUERY_SINGLE_ASSOC)
        ;
        $rowData = $this->database->query($query);
        if (!$rowData) {
            return null;
        }
        $class = $this->database->rowClass($this->name, $rowData);
        $row = new $class($this, $rowData);
        if ($query->getOption(QueryBuilder::OPTION_SUPPRESS_CACHING)) {
            return $row;
        } else {
            return $row->checkCaching();
        }
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
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if the
     * number of arguments does not match the size of the primary key
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if an
     * invalid query definition is provided
     */
    final public function rowByPrimaryKey()
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
     * Identical to row(), but only allows retrieval by primary key packed
     * into an array.
     *
     * @param array<scalar> primary key values
     * @return Thaumatic\Junxa\Row row result, actual class will be as defined
     * by Junxa::rowClass()
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if the
     * size of the specified array does not match the size of the primary key
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if an
     * invalid query definition is provided
     */
    final public function rowByArrayPrimaryKey(array $args)
    {
        foreach ($args as $arg) {
            if (!is_scalar($arg)) {
                throw new JunxaInvalidQueryException('non-scalar key value');
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
    final public function rows($what = []) : array
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
                    $query = $this->baseQuery()->where($what);
                }
                break;
            case 'array':
                $query = $this->query($what, true);
                break;
            default:
                throw new JunxaInvalidQueryException('invalid query for table row retrieval');
        }
        $query
            ->clearSelect()
            ->select($this->getSelectTarget())
            ->setMode(Junxa::QUERY_ASSOCS)
            ->validate()
        ;
        $class = $this->database->rowClass($this->name);
        $rows = $this->database->query($query);
        $out = [];
        if ($this->database->getOption(Junxa::DB_CACHE_TABLE_ROWS)
            && $this->primary
            && !$query->getOption(QueryBuilder::OPTION_SUPPRESS_CACHING)
        ) {
            foreach ($rows as $data) {
                $row = new $class($this, $data);
                $out[] = $row->checkCaching();
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
    final public function optimize()
    {
        $this->database->query('OPTIMIZE TABLE `' . $this->getName() . '`', Junxa::QUERY_FORGET);
    }

    /**
     * Retrieves the count of rows for a specified query.
     *
     * @param mixed a Junxa query builder, or a Junxa query element, or an array specification for a query builder
     */
    final public function rowCount($query = []): int
    {
        if ($query instanceof QueryBuilder) {
            $query = clone($query);
        } elseif ($query instanceof Element) {
            $query = $this->baseQuery()->where($query);
        } elseif (is_array($query)) {
            $query = $this->query($query, true);
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
        if ($query->group() || $query->having()) {
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
        $query
            ->setMode(Junxa::QUERY_SINGLE_CELL)
            ->validate()
        ;
        return intval($this->database->query($query));
    }

    final public function tableScan(array &$tables, array &$null)
    {
        $tables[$this->name] = true;
    }

    final public function express(QueryBuilder $query, string $context, Column $forColumn = null, $parent = null) : string
    {
        if ($context === 'join') {
            return '`' . $this->name . '`';
        }
        if ($query->isMultitable()
            && !($context === 'function' && $parent instanceof Element && $parent->type === 'COUNT')
        ) {
            if ($this->demandLoadColumns && $context !== 'function') {
                $items = [];
                foreach ($this->columns as $column) {
                    if (!in_array($column, $this->demandLoadColumns)) {
                        $items[] = '`' . $this->name . '`.`' . $column . '`';
                    }
                }
                return join(', ', $items);
            } else {
                return '`' . $this->name . '`.*';
            }
        }
        if ($this->demandLoadColumns && $context !== 'function') {
            $items = [];
            foreach ($this->columns as $column) {
                if (!in_array($column, $this->demandLoadColumns)) {
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
     * @param bool whether to skip validation of the query builder
     * @return Thaumatic\Junxa\Query\Builder
     */
    final public function query(array $def = [], bool $skipValidate = false) : QueryBuilder
    {
        return new QueryBuilder($this->database, $this, $def, $skipValidate);
    }

    /**
     * As {@see query()}, but returning a builder based on an empty query.
     *
     * @return Thaumatic\Junxa\Query\Builder
     */
    final public function baseQuery() : QueryBuilder
    {
        return new QueryBuilder($this->database, $this, [], true);
    }

    /**
     * Retrieves whether a given table model addresses the same table as
     * this model.
     *
     * @param Thaumatic\Junxa\Table table model to check
     * @return bool
     */
    final public function isSame(Table $table) : bool
    {
        if ($this === $table) {
            return true;
        }
        if ($this->getName() !== $table->getName()) {
            return false;
        }
        return $this->getDatabase()->isSame($table->getDatabase());
    }

    /**
     * Returns a serialization representation for this table.
     *
     * @return string
     */
    final public function serialize() : string
    {
        return 'table:' . $this->getName();
    }

    /**
     * Retrieves a row cache key to use for the specified list of
     * primary-key-matching arguments.
     *
     * @param array<scalar> an array of primary key values
     */
    private static function argsCacheKey(array $args) : string
    {
        foreach ($args as $arg) {
            if ($arg === null) {
                return null;
            }
        }
        return
            count($args) > 1
            ? join("\0", $args) . '|' . join('', array_map('md5', $args))
            : strval($args[0]);
    }

    /**
     * Accessor for transient data.
     *
     * @param string transient data entry name
     * @return mixed
     */
    final public function getTransientData(string $name)
    {
        return $this->transientData[$name] ?? null;
    }

    /**
     * Mutator for transient data.
     *
     * @param string transient data entry name
     * @param mixed transient data entry value
     * @return $this
     */
    final public function setTransientData(string $name, $value)
    {
        if ($value === null) {
            unset($this->transientData[$name]);
        } else {
            $this->transientData[$name] = $value;
        }
        return $this;
    }

    /**
     * If the named transient data is present, returns it, otherwise
     * generates it by calling the provided callback, stores it, and
     * returns it.
     *
     * @param string transient data entry name
     * @param callable callback to generate data
     * @return mixed
     */
    final public function requireTransientData(string $name, callable $generate)
    {
        if (!array_key_exists($name, $this->transientData)) {
            $this->transientData[$name] = $generate();
        }
        return $this->transientData[$name];
    }

}
