<?php

namespace Thaumatic\Junxa;

use Thaumatic\Junxa;
use Thaumatic\Junxa\Column;
use Thaumatic\Junxa\Exceptions\JunxaConfigurationException;
use Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException;
use Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException;
use Thaumatic\Junxa\Exceptions\JunxaQueryExecutionException;
use Thaumatic\Junxa\Exceptions\JunxaReferentialIntegrityException;
use Thaumatic\Junxa\Query as Q;
use Thaumatic\Junxa\Query\Builder as QueryBuilder;
use Thaumatic\Junxa\Table;

/**
 * Models a database row.
 */
class Row
{

    /**
     * @const array<string> query clauses that may not be defined in a query
     * definition passed to Row::find()
     */
    const FIND_INVALID_CLAUSES = [
        'select',
        'insert',
        'replace',
        'update',
        'delete',
        'group',
        'limit',
    ];

    /**
     * @const array<string> query clauses that may not be defined in a query
     * definition passed to Row::update()
     */
    const UPDATE_INVALID_CLAUSES = [
        'select',
        'insert',
        'replace',
        'update',
        'delete',
        'group',
        'having',
    ];

    /**
     * @const array<string> query clauses that may not be defined in a query
     * definition passed to Row::insert()
     */
    const INSERT_INVALID_CLAUSES = [
        'select',
        'insert',
        'replace',
        'update',
        'delete',
        'group',
        'order',
        'having',
        'limit',
    ];

    /**
     * @const array<string> query clauses that may not be defined in a query
     * definition passed to Row::merge()
     */
    const MERGE_INVALID_CLAUSES = [
        'select',
        'insert',
        'replace',
        'delete',
        'group',
        'having',
    ];

    /**
     * @const array<string> query clauses that may not be defined in a query
     * definition passed to Row::replace()
     */
    const REPLACE_INVALID_CLAUSES = [
        'select',
        'insert',
        'replace',
        'update',
        'delete',
        'group',
        'order',
        'having',
        'limit',
    ];

    /**
     * @const array<string> query clauses that may not be defined in a query
     * definition passed to Row::delete()
     */
    const DELETE_INVALID_CLAUSES = [
        'select',
        'insert',
        'replace',
        'update',
        'delete',
        'group',
        'having',
    ];

    /**
     * @const array<string> query clauses that may not be defined in a query
     * definition passed to Row::getChildRows()
     */
    const GET_CHILD_ROWS_INVALID_CLAUSES = [
        'select',
        'insert',
        'replace',
        'update',
        'delete',
        'group',
    ];

    /**
     * The "database values" for the columns on this row; more precisely, the
     * import()ed version of the values obtained from the database for this
     * row when the row was generated.  Will be null for a row generated as
     * empty (rather than from an existing database row).
     *
     * @var array<string:mixed>|null
     */
    private $junxaInternalData;

    /**
     * An array of arbitrary data that can be attached to this model but which
     * will not be persisted to the database.  Applications can use this to
     * attach application-defined data to row models.
     *
     * @var array<string:mixed>
     */
    private $junxaInternalTransientData = [];

    /**
     * @var bool whether this row has been deleted via the delete() method
     * being called on it
     */
    private $junxaInternalDeleted = false;

    /**
     * @var Thaumatic\Junxa\Table the table this row is from
     */
    private $junxaInternalTable;

    /**
     * @param Thaumatic\Junxa\Table table model that this row is attached to
     * @param array<string:mixed> data mapping for this row; null for a new,
     * empty row model without a corresponding database row yet
     */
    final public function __construct(Table $table, array $data = null)
    {
        $this->junxaInternalTable = $table;
        $this->junxaInternalData = $data;
        if ($data) {
            foreach ($table->getPreloadColumns() as $column) {
                $dataItem = $table->$column->import($data[$column]);
                $this->junxaInternalData[$column] = $dataItem;
                $this->$column = $dataItem;
            }
        }
        $this->init();
    }

    /**
     * Initialization function to be called upon the database model being set
     * up.  Intended to be overridden by child classes.
     */
    protected function init()
    {
    }

    /**
     * Accessor for field values.
     *
     * @param string field name
     * @return $this
     */
    final public function setField($name, $value)
    {
        if (property_exists($this, $name)) {
            $this->$name = $value;
        } elseif ($this->junxaInternalTable->hasColumn($name)) {
            if ($this->junxaInternalTable->$name->isDynamic()) {
                throw new JunxaInvalidQueryException(
                    'cannot set value for dynamic column ' . $name
                );
            }
            $this->$name = $value;
        } else {
            throw new JunxaNoSuchColumnException($name);
        }
        return $this;
    }

    /**
     * Accessor for field values.
     *
     * @param string field name
     * @return mixed
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException if the
     * specified field does not exist
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if a
     * demand-loaded column is requested and this row cannot be identified by
     * primary key such that values can be retrieved for it
     */
    final public function getField($name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }
        if (!$this->junxaInternalTable->hasColumn($name)) {
            throw new JunxaNoSuchColumnException($name);
        }
        if ($this->junxaInternalTable->getColumnDemandLoad($name)
            && !$this->getPrimaryKeyUnset()
        ) {
            $this->loadStoredValue($name);
            return $this->$name;
        }
        return null;
    }

    /**
     * Property-mode mutator for field values.
     *
     * @param string field name
     * @param mixed value to assign
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if the
     * specified field is a dynamic column
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException if the
     * specified field does not exist
     */
    final public function __set($name, $value)
    {
        if ($this->junxaInternalTable->hasColumn($name)) {
            if ($this->junxaInternalTable->$name->isDynamic()) {
                throw new JunxaInvalidQueryException(
                    'cannot set value for dynamic column ' . $name
                );
            }
            $this->$name = $value;
        } else {
            throw new JunxaNoSuchColumnException($name);
        }
    }

    /**
     * Accessor for transient data.
     *
     * @param string transient data entry name
     * @return mixed
     */
    final public function getTransientData(string $name)
    {
        return $this->junxaInternalTransientData[$name] ?? null;
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
            unset($this->junxaInternalTransientData[$name]);
        } else {
            $this->junxaInternalTransientData[$name] = $value;
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
        if (!array_key_exists($name, $this->junxaInternalTransientData)) {
            $this->junxaInternalTransientData[$name] = $generate();
        }
        return $this->junxaInternalTransientData[$name];
    }

    /**
     * Property-mode accessor that can retrieve 1) a field value 2) a parent
     * row 3) an array of child rows.
     *
     * @param string field name
     * @return mixed
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException if the
     * specified field does not exist
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if a
     * demand-loaded column is requested and this row cannot be identified by
     * primary key such that values can be retrieved for it
     */
    final public function __get($name)
    {
        if (!$this->junxaInternalTable->hasColumn($name)) {
            $db = $this->junxaInternalTable->getDatabase();
            $foreignKeySuffix = $db->getForeignKeySuffix();
            if ($foreignKeySuffix !== null) {
                $foreignKeyName = $name . $foreignKeySuffix;
                if (property_exists($this, $foreignKeyName)
                    || $this->junxaInternalTable->hasColumn($foreignKeyName)
                ) {
                    return $this->getParentRow($foreignKeyName);
                }
            }
            $childTable = $db->getChildTableFromPropertyName($name);
            if ($childTable) {
                return $this->getChildRows($childTable);
            }
            throw new JunxaNoSuchColumnException($name);
        }
        if ($this->junxaInternalTable->getColumnDemandLoad($name)
            && !$this->getPrimaryKeyUnset()
        ) {
            $this->loadStoredValue($name);
            return $this->$name;
        }
        return null;
    }

    /**
     * Retrieves the table row cache key to use for this row, based on its
     * primary key settings.
     *
     * @return string
     * @throws Thaumatic\Junxa\Exceptions\JunxaConfigurationException if the
     * table we are attached to has no primary key
     */
    final public function getCacheKey()
    {
        $pk = $this->junxaInternalTable->getPrimaryKey();
        switch (count($pk)) {
            case 0:
                throw new JunxaConfigurationException('cannot generate cache key without primary key');
            case 1:
                $key = $pk[0];
                $val = $this->$key;
                return $val === null ? $val : strval($val);
            default:
                foreach ($pk as $key) {
                    $val = $this->$key;
                    if ($val === null) {
                        return null;
                    }
                    $elem[] = $val;
                }
                return join("\0", $args) . '|' . join('', array_map('md5', $args));
        }
    }

    /**
     * @return Thaumatic\Junxa the database model this row is attached to
     */
    final public function getDatabase()
    {
        return $this->junxaInternalTable->getDatabase();
    }

    /**
     * @return Thaumatic\Junxa\Table the table model this row is attached to
     */
    final public function getTable()
    {
        return $this->junxaInternalTable;
    }

    /**
     * Retrieves the column model for the specified column.
     *
     * @param string column name
     * @return Thaumatic\Junxa\Column result column, actual class will be as
     * defined by Junxa::columnClass()
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException if the
     * specified column does not exist
     */
    final public function getColumn($column)
    {
        return $this->junxaInternalTable->$column;
    }

    /**
     * @return array<string> the list of column names for this row's table
     */
    final public function getColumns()
    {
        return $this->junxaInternalTable->getColumns();
    }

    /**
     * @param string column name
     * @return string the specified column's type
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException if the
     * specified column does not exist
     */
    final public function getColumnType($column)
    {
        return $this->getColumn($column)->getType();
    }

    /**
     * @param string column name
     * @return string the specified column's full type specification
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException if the
     * specified column does not exist
     */
    final public function getColumnFullType($column)
    {
        return $this->getColumn($column)->getFullType();
    }

    /**
     * @param string column name
     * @return string the specified column's type class
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException if the
     * specified column does not exist
     */
    final public function getColumnTypeClass($column)
    {
        return $this->getColumn($column)->getTypeClass();
    }

    /**
     * @param string column name
     * @return int|null the specified column's length specification, if any
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException if the
     * specified column does not exist
     */
    final public function getColumnLength($column)
    {
        return $this->getColumn($column)->getLength();
    }

    /**
     * @param string column name
     * @return int|null the specified column's precision specification, if any
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException if the
     * specified column does not exist
     */
    final public function getColumnPrecision($column)
    {
        return $this->getColumn($column)->getPrecision();
    }

    /**
     * @param string column name
     * @return int Thaumatic\Junxa\Column\MYSQL_FLAG_* bitmask for the
     * specified column
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException if the
     * specified column does not exist
     */
    final public function getColumnFlags($column)
    {
        return $this->getColumn($column)->getFlags();
    }

    /**
     * @param string column name
     * @return array<string> the names of the flags on the specified column
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException if the
     * specified column does not exist
     */
    final public function getColumnFlagNames($column)
    {
        return $this->getColumn($column)->getFlagNames();
    }

    /**
     * @param string column name
     * @param int Thaumatic\Junxa\Column\MYSQL_FLAG_*
     * @return bool whether the specified flag is enabled on the specified
     * column, or if a bitmask of multiple flags is sent, whether any of
     * them are enabled
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException if the
     * specified column does not exist
     */
    final public function getColumnFlag($column, $flag)
    {
        return $this->getColumn($column)->getFlag($flag);
    }

    /**
     * @param string column name
     * @param int Thaumatic\Junxa\Column\MYSQL_FLAG_* bitmask
     * @return bool whether all specified flags are enabled on the specified
     * column
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException if the
     * specified column does not exist
     */
    final public function getColumnEachFlag($column, $flags)
    {
        return $this->getColumn($column)->getEachFlag($flags);
    }

    /**
     * @param string column name
     * @return array<string>|null the values the specified column can have,
     * if it is an enum or set
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException if the
     * specified column does not exist
     */
    final public function getColumnValues($column)
    {
        return $this->getColumn($column)->getValues();
    }

    /**
     * @param string column name
     * @return int Thaumatic\Junxa\Column::OPTION_* bitmask for the
     * specified column
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException if the
     * specified column does not exist
     */
    final public function getColumnOptions($column)
    {
        return $this->getColumn($column)->getOptions();
    }

    /**
     * @param string column name
     * @param int Thaumatic\Junxa\Column\OPTION_*
     * @return bool whether the specified option is enabled on the specified
     * column, or if a bitmask of multiple options is specified, whether any
     * of them is enabled
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException if the
     * specified column does not exist
     */
    final public function getColumnOption($column, $option)
    {
        return $this->getColumn($column)->getOption($option);
    }

    /**
     * @param string column name
     * @param int Thaumatic\Junxa\Column\OPTION_* bitmask
     * @return bool whether all specified options are enabled on the specified
     * column
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException if the
     * specified column does not exist
     */
    final public function getColumnEachOption($column, $options)
    {
        return $this->getColumn($column)->getEachOption($options);
    }

    /**
     * Retrieves the value for the specified column on this row.  Generally
     * used to obtain the values of demand-loaded columns.
     *
     * @param string column name
     * @return mixed
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException if the
     * specified column does not exist
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if this
     * row cannot be identified by primary key such that values can be
     * retrieved for it
     */
    final public function getStoredValue($column)
    {
        $cond = $this->getMatchCondition();
        if (!$cond) {
            throw new JunxaInvalidQueryException(
                'cannot generate match condition for ' . $this->junxaInternalTable->getName()
            );
        }
        $value = $this->getDatabase()->query()
            ->select($this->getColumn($column))
            ->where($cond)
            ->setMode(Junxa::QUERY_SINGLE_CELL)
            ->execute();
        return $column->import($value);
    }

    /**
     * Loads the database value for the specified column into this row model.
     *
     * @param string column name
     * @return $this
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException if the
     * specified column does not exist
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if the
     * value passed is not the name of a demand-loaded column
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if the
     * value passed is not the name of a demand-loaded column
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if this
     * row cannot be identified by primary key such that values can be
     * retrieved for it
     * @throws Thaumatic\Junxa\Exceptions\JunxaInternalInconsistencyException
     * if the column name cannot be found in the table's list of demand-loaded
     * columns
     */
    final public function loadStoredValue($column)
    {
        if (!$this->junxaInternalTable->getColumnDemandLoad($column)) {
            $columnModel = $this->getColumn($column);
            throw new JunxaInvalidQueryException(
                'column ' . $column . ' is not demand-loaded'
            );
        }
        $pos = array_search($column, $this->junxaInternalTable->getDemandLoadColumns());
        if ($pos === false) {
            throw new JunxaInternalInconsistencyException(
                'cannot find column ' . $column . ' in table demand-load list'
            );
        }
        $value = $this->getStoredValue($column);
        $this->$column = $value;
        $this->junxaInternalData[$column] = $value;
        return $this;
    }

    /**
     * Retrieves this row's value for the specified column, retrieving a
     * demand-loaded column if necessary.
     *
     * @param string column name
     * @return mixed
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException if the
     * specified column does not exist
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if a
     * demand-loaded column is requested and this row cannot be identified by
     * primary key such that values can be retrieved for it
     */
    final public function getValue($column)
    {
        if (!property_exists($this, $column)) {
            if (!$this->junxaInternalTable->hasColumn($name)) {
                throw new JunxaNoSuchColumnException($name);
            }
            if ($this->junxaInternalTable->getColumnDemandLoad($column)
                && !$this->getPrimaryKeyUnset()
            ) {
                $this->loadStoredValue($column);
            } else {
                return null;
            }
        }
        return $this->$column;
    }

    /**
     * Loads all demand-loaded columns.
     *
     * @return $this
     */
    final public function demandAll()
    {
        foreach ($this->junxaInternalTable->getDemandLoadColumns() as $column) {
            $this->loadStoredValue($column);
        }
        return $this;
    }

    /**
     * Ensures that this row is cached or uncached in its parent table if the
     * database model has row caching enabled.
     *
     * @param bool whether we want to remove this row from the cache rather
     * than add it
     * @return $this|Thaumatic\Junxa\Row the operating version of the row;
     * $this if caching is not enabled or if this row was added to the cache,
     * the previously cached row if one was present in the cache and should
     * be referenced instead
     */
    final public function checkCaching($uncache = false)
    {
        $out = $this;
        if ($this->junxaInternalTable->getDatabase()->getOption(Junxa::DB_CACHE_TABLE_ROWS)
            && $this->getPrimaryKey()
        ) {
            $key = $this->getCacheKey();
            if ($key !== null) {
                if ($uncache) {
                    $this->junxaInternalTable->removeCacheKey($key);
                } else {
                    $cached = $this->junxaInternalTable->getCachedValue($key);
                    if ($cached === null) {
                        $this->junxaInternalTable->setCachedValue($key, $this);
                    } else {
                        $out = $cached;
                    }
                }
            }
        }
        return $out;
    }

    /**
     * Retrieves a matching condition (for a WHERE or HAVING clause) that will
     * match this specific row in the database, if one can be constructed.
     *
     * @return Thaumatic\Junxa\Query\Element|null the match condition, or null
     * if one cannot be constructed
     */
    final public function getMatchCondition()
    {
        $key = $this->junxaInternalTable->getPrimaryKey();
        if (!$key) {
            return null;
        }
        if (count($key) === 1) {
            $column = $key[0];
            $value = $this->$column;
            return $value === null ? null : Q::eq($this->junxaInternalTable->$column, $value);
        } else {
            $what = [];
            foreach ($key as $column) {
                $value = $this->$column;
                if ($value === null) {
                    return null;
                }
                $what[] = Q::eq($this->junxaInternalTable->$column, $value);
            }
            return Q::andClause($what);
        }
    }

    /**
     * Treats the defined properties on this model as search criteria and
     * attempts to find exactly one database row matching them and load its
     * data onto this model.  If multiple rows are found, the first one will
     * be loaded.
     *
     * @param array<string:mixed>|Thaumatic\Junxa\Query\Builder query
     * specification to use instead of default empty query as a base; a
     * query builder passed should be generated using the table's query()
     * method
     * @return int Thaumatic\Junxa::RESULT_SUCCESS if exactly one row is
     * found and loaded; Thaumatic\Junxa::RESULT_FIND_FAIL if no matching
     * rows are found; Thaumatic\Junxa::RESULT_FIND_EXCESS if multiple
     * matching rows are found and the first one is loaded
     */
    final public function find($queryDef = [])
    {
        if ($queryDef) {
            if (is_array($queryDef)) {
                foreach (self::FIND_INVALID_CLAUSES as $clause) {
                    if (isset($queryDef[$clause])) {
                        throw new JunxaInvalidQueryException('query definition for find() may not define ' . $clause);
                    }
                }
                $queryDef = $this->junxaInternalTable->query($queryDef);
            } elseif ($queryDef instanceof QueryBuilder) {
                $clause = $queryDef->checkClauses(self::UPDATE_INVALID_CLAUSES);
                if ($clause) {
                    throw new JunxaInvalidQueryException('query definition for find() may not define ' . $clause);
                }
            } else {
                throw new JunxaInvalidQueryException(
                    'query definition for find() must be a '
                    . 'Thaumatic\Junxa\Query\Builder or an array '
                    . 'query definition'
                );
            }
        } else {
            $queryDef = $this->junxaInternalTable->query();
        }
        $queryDef
            ->select($this->junxaInternalTable->getSelectTarget())
            ->defaultOrder(Q::literal(1))
            ->limit(2)
            ->setMode(Junxa::QUERY_ASSOCS)
        ;
        foreach ($this->junxaInternalTable->getColumns() as $column) {
            if (!isset($this->$column)) {
                continue;
            }
            $queryDef->where($column, $this->$column);
        }
        $rows = $queryDef->execute();
        switch (count($rows)) {
            case 0:
                return Junxa::RESULT_FIND_FAIL;
            case 1:
                $out = Junxa::RESULT_SUCCESS;
                break;
            default:
                $out = Junxa::RESULT_FIND_EXCESS;
                break;
        }
        $data = $rows[0];
        $this->junxaInternalData = $data;
        foreach ($this->junxaInternalTable->getPreloadColumns() as $column) {
            $dataItem = $this->junxaInternalTable->$column->import($data[$column]);
            $this->junxaInternalData[$column] = $dataItem;
            $this->$column = $dataItem;
        }
        foreach ($this->junxaInternalTable->getDemandLoadColumns() as $column) {
            if (property_exists($this, $column)) {
                $this->loadStoredValue($column);
            } else {
                unset($this->junxaInternalData[$column]);
            }
        }
        $this->checkCaching();
        return $out;
    }

    /**
     * Loads this model with the current data for its row from the database.
     *
     * @param bool whether we need current data and therefore reading from a
     * secondary database server would be unacceptable; normally this is
     * because this refresh is a followup to a mutating query
     * @return int
     * Thaumatic\Junxa::RESULT_SUCCESS
     *   if the refresh succeeds
     * Thaumatic\Junxa::RESULT_REFRESH_FAIL
     *   if the row cannot be refreshed because a match condition cannot be
     *   constructed (normally means the row's table has no primary key)
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if the
     * refresh query executes but returns no data
     */
    final public function refresh($needCurrent = false)
    {
        $cond = $this->getMatchCondition();
        if (!$cond) {
            return Junxa::RESULT_REFRESH_FAIL;
        }
        $query = $this->junxaInternalTable->getDatabase()->query()
            ->select($this->junxaInternalTable->getSelectTarget())
            ->setMode(Junxa::QUERY_SINGLE_ASSOC)
            ->where($cond)
        ;
        if ($needCurrent) {
            // If we need current data, we force refresh queries to run
            // against the change handler, because otherwise we would have
            // to sleep for an undeterminable period of time to allow changes
            // to propagate from primary to secondary database servers.
            $query->setOption(
                QueryBuilder::OPTION_FORCE_USE_CHANGE_HANDLER,
                true
            );
        }
        $rowData = $query->execute();
        $this->junxaInternalData = $rowData;
        foreach ($this->junxaInternalTable->getPreloadColumns() as $column) {
            $dataItem = $this->junxaInternalTable->$column->import($rowData[$column]);
            $this->junxaInternalData[$column] = $dataItem;
            $this->$column = $dataItem;
        }
        $this->init();
        $this->checkCaching();
        $this->junxaInternalTransientData = [];
        return Junxa::RESULT_SUCCESS;
    }

    /**
     * Synchronizes this row to the database by issuing an UPDATE query
     * setting any field values that have been changed from the row's state
     * when it was loaded from the database.  After the query is issued, the
     * row model's contents will be refreshed from the database.
     *
     * @param array<string:mixed>|Thaumatic\Junxa\Query\Builder query
     * specification to use instead of default empty query as a base; a
     * query builder passed should be generated using the table's query()
     * method
     * @return int
     * Thaumatic\Junxa::RESULT_SUCCESS
     *   if the update and refresh are both successful
     * Thaumatic\Junxa::RESULT_UPDATE_NOOP
     *   if no fields on this row have been changed
     * Thaumatic\Junxa::RESULT_UPDATE_NOKEY
     *   if an update could not be performed because there wasn't enough
     *   primary key information to reliably match this row in the database
     *   (this being typically the result you will get if you call this method
     *   on a row that was generated as a new, empty row rather than loaded
     *   from the database)
     * Thaumatic\Junxa::RESULT_PREVENTED
     *   if either the update or the refresh was prevented by a listener
     * Thaumatic\Junxa::RESULT_REFRESH_FAIL
     *   if the refresh fails
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if a
     * passed query definition has a clause that is present in
     * Thaumatic\Junxa\Row::UPDATE_INVALID_CLAUSES
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if the
     * passed query definition is not an array or Thaumatic\Junxa\Query\Builder
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if the
     * refresh query executes but returns no data
     */
    final public function update($queryDef = [])
    {
        if ($queryDef) {
            if (is_array($queryDef)) {
                foreach (self::UPDATE_INVALID_CLAUSES as $clause) {
                    if (isset($queryDef[$clause])) {
                        throw new JunxaInvalidQueryException('query definition for update() may not define ' . $clause);
                    }
                }
                $queryDef = $this->junxaInternalTable->query($queryDef);
            } elseif ($queryDef instanceof QueryBuilder) {
                $clause = $queryDef->checkClauses(self::UPDATE_INVALID_CLAUSES);
                if ($clause) {
                    throw new JunxaInvalidQueryException('query definition for update() may not define ' . $clause);
                }
            } else {
                throw new JunxaInvalidQueryException(
                    'query definition for update() must be a '
                    . 'Thaumatic\Junxa\Query\Builder or an array '
                    . 'query definition'
                );
            }
        } else {
            $queryDef = $this->junxaInternalTable->query();
        }
        foreach ($this->junxaInternalTable->getStaticColumns() as $column) {
            if (property_exists($this, $column)
                && (
                    !array_key_exists($column, $this->junxaInternalData)
                    || $this->$column !== $this->junxaInternalData[$column]
                )
            ) {
                $queryDef->update($column, $this->$column);
            }
        }
        if (!$queryDef->getUpdate()) {
            return Junxa::RESULT_UPDATE_NOOP;
        }
        $cond = $this->getMatchCondition();
        if (!$cond) {
            return Junxa::RESULT_UPDATE_NOKEY;
        }
        $queryDef
            ->where($cond)
            ->setMode(Junxa::QUERY_FORGET)
        ;
        $this->junxaInternalTable->getDatabase()->query($queryDef);
        $res = $this->junxaInternalTable->getDatabase()->getQueryStatus();
        return Junxa::OK($res) ? $this->refresh(true) : $res;
    }

    /**
     * Creates a row in the database based on this row model's fields by
     * issuing an INSERT query.  Only fields which have been set or which
     * have dynamic defaults configured will be included in the query.
     * After the query is issued, the row model's contents will be refreshed
     * from the database with the contents of the generated database row.
     *
     * @param array<string:mixed>|Thaumatic\Junxa\Query\Builder query
     * specification to use instead of default empty query as a base; a
     * query builder passed should be generated using the table's query()
     * method
     * @return int
     * Thaumatic\Junxa::RESULT_SUCCESS
     *   if the insert and refresh are both successful
     * Thaumatic\Junxa::RESULT_INSERT_NOOP
     *   if no fields on this row have been set nor have dynamic defaults
     * Thaumatic\Junxa::RESULT_INSERT_FAIL
     *   if an INSERT IGNORE query was executed (because of the option
     *   Thaumatic\Junxa\Query\Builder::OPTION_IGNORE being enabled) and
     *   no rows were affected
     * Thaumatic\Junxa::RESULT_PREVENTED
     *   if either the insert or the refresh was prevented by a listener
     * Thaumatic\Junxa::RESULT_REFRESH_FAIL
     *   if the refresh fails
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if a
     * passed query definition has a clause that is present in
     * Thaumatic\Junxa\Row::INSERT_INVALID_CLAUSES
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if the
     * passed query definition is not an array or Thaumatic\Junxa\Query\Builder
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if the
     * refresh query executes but returns no data
     */
    final public function insert($queryDef = [])
    {
        if ($queryDef) {
            if (is_array($queryDef)) {
                foreach (self::INSERT_INVALID_CLAUSES as $clause) {
                    if (isset($queryDef[$clause])) {
                        throw new JunxaInvalidQueryException('query definition for insert() may not define ' . $clause);
                    }
                }
                $queryDef = $this->junxaInternalTable->query($queryDef);
            } elseif ($queryDef instanceof QueryBuilder) {
                $clause = $queryDef->checkClauses(self::INSERT_INVALID_CLAUSES);
                if ($clause) {
                    throw new JunxaInvalidQueryException('query definition for insert() may not define ' . $clause);
                }
            } else {
                throw new JunxaInvalidQueryException(
                    'query definition for insert() must be a '
                    . 'Thaumatic\Junxa\Query\Builder or an array '
                    . 'query definition'
                );
            }
        } else {
            $queryDef = $this->junxaInternalTable->query($queryDef);
        }
        if ($this->junxaInternalTable->getDynamicDefaultsPresent()) {
            foreach ($this->junxaInternalTable->getStaticColumns() as $column) {
                if (property_exists($this, $column)) {
                    $queryDef->insert($column, $this->$column);
                } else {
                    $default = $this->junxaInternalTable->$column->getDynamicDefault();
                    if ($default) {
                        $queryDef->insert($column, $default);
                    }
                }
            }
        } else {
            foreach ($this->junxaInternalTable->getStaticColumns() as $column) {
                if (property_exists($this, $column)) {
                    $queryDef->insert($column, $this->$column);
                }
            }
        }
        if (!$queryDef->getInsert()) {
            return Junxa::RESULT_INSERT_NOOP;
        }
        $queryDef->setMode(Junxa::QUERY_FORGET);
        $this->junxaInternalTable->getDatabase()->query($queryDef);
        $res = $this->junxaInternalTable->getDatabase()->getQueryStatus();
        if (!Junxa::OK($res)) {
            return $res;
        }
        if ($res === Junxa::RESULT_SUCCESS) {
            $field = $this->junxaInternalTable->getAutoIncrementPrimary();
            if ($field) {
                $this->$field = $this->junxaInternalTable->getDatabase()->getInsertId();
            }
        }
        return $this->refresh(true);
    }

    /**
     * Synchronizes this row model to the database using an INSERT... ON
     * DUPLICATE KEY UPDATE query (an operation also known as upsert or
     * merge).  The INSERT clause is constructed using the fields which
     * have been set or which have a dynamic default configured; the UPDATE
     * clause includes only fields which have been set, which do not
     * have Thaumatic\Junxa\Column::OPTION_MERGE_NO_UPDATE enabled, and
     * for which no updates are already present in the query builder.  (If
     * the UPDATE clause would be empty, this function only performs a
     * non-mutating update to retrieve an auto-increment primary key, if
     * present.)  If the INSERT clause would create a duplicate entry on
     * a unique key, then the UPDATE clause is executed.  After the query
     * is issued, the row model's contents will be refreshed from the
     * database with the contents of the generated or updated database row.
     *
     * @param array<string:mixed>|Thaumatic\Junxa\Query\Builder query
     * specification to use instead of default empty query as a base; a
     * query builder passed should be generated using the table's query()
     * method
     * @return int
     * Thaumatic\Junxa::RESULT_SUCCESS
     *   if the insert and refresh are both successful
     * Thaumatic\Junxa::RESULT_MERGE_NOOP
     *   if no fields on this row have been set nor have dynamic defaults
     * Thaumatic\Junxa::RESULT_PREVENTED
     *   if either the merge or the refresh was prevented by a listener
     * Thaumatic\Junxa::RESULT_REFRESH_FAIL
     *   if the refresh fails
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if a
     * passed query definition has a clause that is present in
     * Thaumatic\Junxa\Row::MERGE_INVALID_CLAUSES
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if the
     * passed query definition is not an array or Thaumatic\Junxa\Query\Builder
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if the
     * refresh query executes but returns no data
     */
    final public function merge($queryDef = [])
    {
        if ($queryDef) {
            if (is_array($queryDef)) {
                foreach (self::MERGE_INVALID_CLAUSES as $clause) {
                    if (isset($queryDef[$clause])) {
                        throw new JunxaInvalidQueryException('query definition for merge() may not define ' . $clause);
                    }
                }
                $queryDef = $this->junxaInternalTable->query($queryDef);
            } elseif ($queryDef instanceof QueryBuilder) {
                $clause = $queryDef->checkClauses(self::MERGE_INVALID_CLAUSES);
                if ($clause) {
                    throw new JunxaInvalidQueryException('query definition for merge() may not define ' . $clause);
                }
            } else {
                throw new JunxaInvalidQueryException(
                    'query definition for merge() must be a '
                    . 'Thaumatic\Junxa\Query\Builder or an array '
                    . 'query definition'
                );
            }
        } else {
            $queryDef = $this->junxaInternalTable->query();
        }
        $autoInc = $this->junxaInternalTable->getAutoIncrementPrimary();
        if ($autoInc) {
            $autoIncCol = $this->junxaInternalTable->$autoInc;
            $queryDef->update($autoIncCol, Q::func('LAST_INSERT_ID', $autoIncCol));
        }
        foreach ($this->junxaInternalTable->getStaticColumns() as $column) {
            $columnModel = $this->junxaInternalTable->$column;
            if (property_exists($this, $column)) {
                $queryDef->insert($columnModel, $this->$column);
                if (!$columnModel->getOption(Column::OPTION_MERGE_NO_UPDATE)
                    && !$queryDef->hasUpdateOnColumn($columnModel)
                ) {
                    $queryDef->update($columnModel, $this->$column);
                }
            } else {
                $default = $columnModel->getDynamicDefault();
                if ($default) {
                    $queryDef->insert($column, $default);
                }
            }
        }
        if (!$queryDef->getInsert()) {
            return Junxa::RESULT_MERGE_NOOP;
        }
        $queryDef->setMode(Junxa::QUERY_FORGET);
        $this->junxaInternalTable->getDatabase()->query($queryDef);
        $res = $this->junxaInternalTable->getDatabase()->getQueryStatus();
        if (!Junxa::OK($res)) {
            return $res;
        }
        if ($res === Junxa::RESULT_SUCCESS && $autoInc) {
            $id = $this->junxaInternalTable->getDatabase()->getInsertId();
            if ($id) {
                $this->$autoInc = $id;
            }
        }
        return $this->refresh(true);
    }

    /**
     * Creates a row in the database based on this row model's fields by
     * issuing a REPLACE query.  Only fields which have been set will be
     * included in the query.  After the query is issued, the row model's
     * contents will be refreshed from the database with the contents of
     * the generated database row.
     *
     * @param array<string:mixed>|Thaumatic\Junxa\Query\Builder query
     * specification to use instead of default empty query as a base; a
     * query builder passed should be generated using the table's query()
     * method
     * @return int
     * Thaumatic\Junxa::RESULT_SUCCESS
     *   if the replace and refresh are both successful
     * Thaumatic\Junxa::RESULT_REPLACE_NOOP
     *   if no fields on this row have been set
     * Thaumatic\Junxa::RESULT_PREVENTED
     *   if either the replace or the refresh was prevented by a listener
     * Thaumatic\Junxa::RESULT_REFRESH_FAIL
     *   if the refresh fails
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if a
     * passed query definition has a clause that is present in
     * Thaumatic\Junxa\Row::REPLACE_INVALID_CLAUSES
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if the
     * passed query definition is not an array or Thaumatic\Junxa\Query\Builder
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if the
     * refresh query executes but returns no data
     */
    final public function replace($queryDef = [])
    {
        if ($queryDef) {
            if (is_array($queryDef)) {
                foreach (self::REPLACE_INVALID_CLAUSES as $clause) {
                    if (isset($queryDef[$clause])) {
                        throw new JunxaInvalidQueryException(
                            'query definition for replace() may not define '
                            . $clause
                        );
                    }
                }
                $queryDef = $this->junxaInternalTable->query($queryDef);
            } elseif ($queryDef instanceof QueryBuilder) {
                $clause = $queryDef->checkClauses(self::REPLACE_INVALID_CLAUSES);
                if ($clause) {
                    throw new JunxaInvalidQueryException(
                        'query definition for replace() may not define '
                        . $clause
                    );
                }
            } else {
                throw new JunxaInvalidQueryException(
                    'query definition for replace() must be a '
                    . 'Thaumatic\Junxa\Query\Builder or an array '
                    . 'query definition'
                );
            }
        } else {
            $queryDef = $this->junxaInternalTable->query($queryDef);
        }
        foreach ($this->junxaInternalTable->getStaticColumns() as $column) {
            if (property_exists($this, $column)) {
                $queryDef->replace($column, $this->$column);
            }
        }
        if (!$queryDef->getReplace()) {
            return Junxa::RESULT_REPLACE_NOOP;
        }
        $queryDef->setMode(Junxa::QUERY_FORGET);
        $this->junxaInternalTable->getDatabase()->query($queryDef);
        $res = $this->junxaInternalTable->getDatabase()->getQueryStatus();
        if (!Junxa::OK($res)) {
            return $res;
        }
        if ($res === Junxa::RESULT_SUCCESS) {
            $field = $this->junxaInternalTable->getAutoIncrementPrimary();
            if ($field) {
                $this->$field = $this->junxaInternalTable->getDatabase()->getInsertId();
            }
        }
        return $this->refresh(true);
    }

    /**
     * Persists the data on this row model to the database, via insert if this
     * is a new row or update if it is an existing row.
     *
     * @param array<string:mixed>|Thaumatic\Junxa\Query\Builder query
     * specification to use instead of default empty query as a base; a
     * query builder passed should be generated using the table's query()
     * method
     * @return int Thaumatic\Junxa::RESULT_* value for operation
     */
    final public function save($queryDef = [])
    {
        return $this->junxaInternalData ? $this->update($queryDef) : $this->insert($queryDef);
    }

    /**
     * Calls {see save()}.
     *
     * @param array<string:mixed>|Thaumatic\Junxa\Query\Builder query
     * specification to use instead of default empty query as a base; a
     * query builder passed should be generated using the table's query()
     * method
     * @return $this
     * @throws Thaumatic\Junxa\Exceptions\JunxaQueryExecutionException if the
     * return value from save() isn't okay according to {see Junxa::OK()}.
     */
    final public function performSave($queryDef = [])
    {
        $result = $this->save($queryDef);
        if (!Junxa::OK($result)) {
            throw new JunxaQueryExecutionException(
                'result from save() was '
                . (
                    array_key_exists($result, Junxa::RESULT_NAMES)
                    ? Junxa::RESULT_NAMES[$result]
                    : ('unknown ' . gettype($result))
                )
            );
        }
        return $this;
    }

    /**
     * @return bool whether this row has been changed from the version of it
     * in the database; a new row model with no corresponding database row
     * is always considered changed
     */
    final public function hasChanged()
    {
        if (!$this->junxaInternalData) {
            return true;
        }
        foreach ($this->junxaInternalTable->getStaticColumns() as $column) {
            if (property_exists($this, $column)
                && (
                    !array_key_exists($column, $this->junxaInternalData)
                    || $this->$column !== $this->junxaInternalData[$column]
                )
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array<string> the names of fields that have been changed in
     * this row model
     */
    final public function getChangedFields()
    {
        $out = [];
        if ($this->junxaInternalData) {
            foreach ($this->junxaInternalTable->getStaticColumns() as $column) {
                if (property_exists($this, $column)
                    && (
                        !array_key_exists($column, $this->junxaInternalData)
                        || $this->$column !== $this->junxaInternalData[$column]
                    )
                ) {
                    $out[] = $column;
                }
            }
        } else {
            foreach ($this->junxaInternalTable->getStaticColumns() as $column) {
                if (property_exists($this, $column)) {
                    $out[] = $column;
                }
            }
        }
        return $out;
    }

    /**
     * Deletes the database row corresponding to this row model and
     * marks this row model as deleted.
     *
     * @param array<string:mixed>|Thaumatic\Junxa\Query\Builder query
     * specification to use instead of default empty query as a base; a
     * query builder passed should be generated using the table's query()
     * method
     * @return int
     * Thaumatic\Junxa::RESULT_SUCCESS
     *   if the query was successful
     * Thaumatic\Junxa::RESULT_DELETE_FAIL
     *   if the query could not be issued because not enough primary key
     *   information was available to reliably identified the database row
     *   corresponding to this row model
     * Thaumatic\Junxa::RESULT_PREVENTED
     *   if the query was prevented by a listener
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if a
     * passed query definition has a clause that is present in
     * Thaumatic\Junxa\Row::DELETE_INVALID_CLAUSES
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if this
     * row is already marked deleted and the working query definition does
     * not have Thaumatic\Junxa\Query\Builder::OPTION_REDELETE_OKAY enabled
     */
    final public function delete($queryDef = [])
    {
        if ($queryDef) {
            if (is_array($queryDef)) {
                foreach (self::DELETE_INVALID_CLAUSES as $clause) {
                    if (isset($queryDef[$clause])) {
                        throw new JunxaInvalidQueryException('query definition for delete() may not define ' . $clause);
                    }
                }
                $queryDef = $this->junxaInternalTable->query($queryDef);
            } elseif ($queryDef instanceof QueryBuilder) {
                $clause = $queryDef->checkClauses(self::DELETE_INVALID_CLAUSES);
                if ($clause) {
                    throw new JunxaInvalidQueryException('query definition for delete() may not define ' . $clause);
                }
            } else {
                throw new JunxaInvalidQueryException(
                    'query definition for delete() must be a '
                    . 'Thaumatic\Junxa\Query\Builder or an array '
                    . 'query definition'
                );
            }
        } else {
            $queryDef = $this->junxaInternalTable->query();
        }
        if ($this->getDeleted() && !$queryDef->getOption(QueryBuilder::OPTION_REDELETE_OKAY)) {
            throw new JunxaInvalidQueryException('row has already been deleted');
        }
        $cond = $this->getMatchCondition();
        if (!$cond) {
            return Junxa::RESULT_DELETE_NOKEY;
        }
        $queryDef
            ->where($cond)
            ->delete($this->junxaInternalTable)
            ->setMode(Junxa::QUERY_FORGET)
        ;
        $db = $this->junxaInternalTable->getDatabase();
        $db->query($queryDef);
        $res = $db->getQueryStatus();
        if (Junxa::OK($res)) {
            $this->junxaInternalDeleted = true;
            $this->checkCaching(true);
            $this->afterDelete();
        }
        return $res;
    }

    /**
     * Called after a successful delete() call on this row.  Can be overridden
     * by child classes to handle this event.
     */
    public function afterDelete()
    {
    }

    /**
     * @return bool whether this row has been deleted via the delete() call on
     * itself
     */
    final public function getDeleted()
    {
        return $this->junxaInternalDeleted;
    }

    /**
     * @return array<string> the column names of of this row's table's primary
     * key
     */
    final public function getPrimaryKey()
    {
        return $this->junxaInternalTable->getPrimaryKey();
    }

    /**
     * @return bool whether any of the primary key columns for this row's table
     * are not set on this row
     */
    final public function getPrimaryKeyUnset()
    {
        foreach ($this->junxaInternalTable->getPrimaryKey() as $column) {
            if (!isset($this->$column)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Retrieves the single foreign row corresponding to the specified column.
     * This is only valid if an association with one foreign row may be
     * expected.
     *
     * @param string column name
     * @return Thaumatic\Junxa\Row|null foreign row, or null if the field value
     * in the local row is null
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException if the
     * specified column does not exist
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if the
     * specified column is not a foreign key
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if the
     * foreign column is not part of a primary key or unique key
     * @throws Thaumatic\Junxa\Exceptions\JunxaReferentialIntegrityException
     * if there is no row in the foreign table corresponding to a non-null
     * value in the local field
     */
    final public function getParentRow($columnName)
    {
        $column = $this->junxaInternalTable->$columnName;
        $localValue = $this->$columnName;
        if ($localValue === null) {
            return null;
        }
        $foreignColumn = $column->getForeignColumn();
        if (!$foreignColumn) {
            throw new JunxaInvalidQueryException(
                $columnName
                . ' on '
                . $this->junxaInternalTable->getName()
                . ' is not a foreign key'
            );
        }
        $foreignTable = $foreignColumn->getTable();
        $toPrimary = $foreignColumn->getFlag(Column::MYSQL_FLAG_PRI_KEY);
        if (!$toPrimary && !$foreignColumn->getFlag(Column::MYSQL_FLAG_UNIQUE_KEY)) {
            throw new JunxaInvalidQueryException(
                'foreign column '
                . $foreignColumn->getName()
                . ' on '
                . $foreignColumn->getTable()->getName()
                . ' is not part of a primary or unique key'
            );
        }
        if ($toPrimary) {
            $foreignPrimary = $foreignTable->getPrimaryKey();
            if (count($foreignPrimary) === 1) {
                $out = $foreignTable->row($localValue);
                if (!$out) {
                    throw new JunxaReferentialIntegrityException(
                        $this->junxaInternalTable,
                        $column,
                        $foreignTable,
                        $foreignColumn,
                        $localValue
                    );
                }
                return $out;
            } else {
                throw new JunxaInvalidQueryException(
                    'foreign row retrieval by multipart primary key '
                    . 'not presently supported'
                );
            }
        } else {
            $possible = [];
            foreach ($foreignTable->getKeys() as $key) {
                if (!$key->getUnique()) {
                    continue;
                }
                if (!$key->isColumnInKey($foreignColumn->getName())) {
                    continue;
                }
                if (count($key->getParts()) === 1) {
                    $out = $foreignTable->query()
                        ->where($foreignColumn, $localValue)
                        ->row()
                    ;
                    if (!$out) {
                        throw new JunxaReferentialIntegrityException(
                            $this->junxaInternalTable,
                            $column,
                            $foreignTable,
                            $foreignColumn,
                            $localValue
                        );
                    }
                    return $out;
                }
                $possible[] = $key;
            }
            if ($possible) {
                throw new JunxaInvalidQueryException(
                    'foreign row retrieval by multipart unique key '
                    . 'not presently supported'
                );
            } else {
                throw new JunxaInvalidQueryException(
                    'no unique key exists that would allow a foreign row from '
                    . $foreignColumn->getTable()->getName()
                    . ' to be retrieved based on '
                    . $foreignColumn->getName()
                );
            }
        }
    }

    /**
     * Retrieves all rows from the specified table which have this row as a
     * parent row.
     *
     * @param Thaumatic\Junxa\Table foreign table
     * @param array<string:mixed>|Thaumatic\Junxa\Query\Builder query
     * specification to use instead of default empty query as a base; a
     * query builder passed should be generated using the *child* table's
     * query() method
     * @return array<Thaumatic\Junxa\Row>
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if the
     * specified table has no relationships with this table such that its rows
     * would be child rows of this table's rows
     */
    final public function getChildRows(Table $childTable, $queryDef = [])
    {
        $field = $this->junxaInternalTable->getAutoIncrementPrimary();
        if (!$field) {
            throw new JunxaInvalidQueryException(
                'cannot retrieve child rows without auto-increment primary key'
            );
        }
        $useColumn = null;
        foreach ($childTable->getColumnModels() as $columnName => $column) {
            $foreignColumn = $column->getForeignColumn();
            if ($foreignColumn
                && $foreignColumn->getName() === $field
                && $this->junxaInternalTable->isSame($foreignColumn->getTable())
            ) {
                $useColumn = $column;
                break;
            }
        }
        if (!$useColumn) {
            throw new JunxaInvalidQueryException(
                'no foreign keys found on '
                . $childTable->getName()
                . ' that imply a child table relationship with '
                . $this->junxaInternalTable->getName()
            );
        }
        if ($queryDef) {
            if (is_array($queryDef)) {
                foreach (self::GET_CHILD_ROWS_INVALID_CLAUSES as $clause) {
                    if (isset($queryDef[$clause])) {
                        throw new JunxaInvalidQueryException(
                            'query definition for getChildRows() may not define '
                            . $clause
                        );
                    }
                }
                $queryDef = $childTable->query($queryDef);
            } elseif ($queryDef instanceof QueryBuilder) {
                $clause = $queryDef->checkClauses(self::GET_CHILD_ROWS_INVALID_CLAUSES);
                if ($clause) {
                    throw new JunxaInvalidQueryException(
                        'query definition for getChildRows() may not define '
                        . $clause
                    );
                }
            } else {
                throw new JunxaInvalidQueryException(
                    'query definition for getChildRows() must be a '
                    . 'Thaumatic\Junxa\Query\Builder or an array '
                    . 'query definition'
                );
            }
        } else {
            $queryDef = $childTable->query();
        }
        $childField = $childTable->getAutoIncrementPrimary();
        if ($childField) {
            $queryDef->defaultOrder($childField);
        }
        return $queryDef
            ->where($useColumn, $this->$field)
            ->rows();
    }

    /**
     * Retrieves all rows from the named table which have this row as a
     * parent row.
     *
     * @param string foreign table name
     * @param array<string:mixed>|Thaumatic\Junxa\Query\Builder query
     * specification to use instead of default empty query as a base; a
     * query builder passed should be generated using the *child* table's
     * query() method
     * @return array<Thaumatic\Junxa\Row>
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if the
     * specified table has no relationships with this table such that its rows
     * would be child rows of this table's rows
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchTableException if the
     * named table does not exist
     */
    final public function getChildRowsByTableName($tableName, $queryDef = [])
    {
        return $this->getChildRows($this->getDatabase()->$tableName, $queryDef);
    }

}
