<?php

namespace Thaumatic\Junxa;

use Thaumatic\Junxa;
use Thaumatic\Junxa\Column;
use Thaumatic\Junxa\Exceptions\JunxaConfigurationException;
use Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException;
use Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException;
use Thaumatic\Junxa\Query as Q;
use Thaumatic\Junxa\Query\Builder as QueryBuilder;
use Thaumatic\Junxa\Table;

/**
 * Models a database row.
 */
class Row
{

    /**
     * The "database values" for the columns on this row; more precisely, the
     * import()ed version of the values obtained from the database for this
     * row when the row was generated.  Will be null for a row generated as
     * empty (rather than from an existing database row).
     *
     * @var array<string:mixed>|null
     */
    private $_data;

    /**
     * @var bool whether this row has been deleted via the delete() method
     * being called on it
     */
    private $_deleted = false;

    /**
     * @var Thaumatic\Junxa\Table the table this row is from
     */
    private $_table;

    /**
     * @param Thaumatic\Junxa\Table table model that this row is attached to
     * @param array<string:mixed> data mapping for this row; null for a new,
     * empty row model without a corresponding database row yet
     */
    public function __construct(Table $table, array $data = null)
    {
        $this->_table = $table;
        $this->_data = $data;
        if ($data) {
            foreach ($table->getPreloadColumns() as $column) {
                $dataItem = $table->$column->import($data[$column]);
                $this->_data[$column] = $dataItem;
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
     * Property-mode accessor for field values.
     *
     * @param string field name
     * @return mixed
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException if the
     * specified field does not exist
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if a
     * demand-loaded column is requested and this row cannot be identified by
     * primary key such that values can be retrieved for it
     */
    public function __get($name)
    {
        if (!$this->_table->hasColumn($name)) {
            throw new JunxaNoSuchColumnException($name);
        }
        if ($this->_table->queryColumnDemandLoad($name)
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
    public function __set($name, $value)
    {
        if (property_exists($this, $name)) {
            $this->$name = $value;
        } elseif($this->_table->hasColumn($name)) {
            if ($this->_table->$name->isDynamic()) {
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
     * Retrieves the table row cache key to use for this row, based on its
     * primary key settings.
     *
     * @return string
     * @throws Thaumatic\Junxa\Exceptions\JunxaConfigurationException if the
     * table we are attached to has no primary key
     */
    public function getCacheKey()
    {
        switch (count($this->_table->primary)) {
            case 0:
                throw new JunxaConfigurationException('cannot generate cache key without primary key');
            case 1:
                $key = $this->_table->primary[0];
                return strval($this->$key);
            default:
                foreach ($this->_table->primary as $key) {
                    $elem[] = $this->$key;
                }
                return join("\0", $args) . '|' . join('', array_map('md5', $args));
        }
    }

    /**
     * @return Thaumatic\Junxa the database model this row is attached to
     */
    public function getDatabase()
    {
        return $this->_table->getDatabase();
    }

    /**
     * @return Thaumatic\Junxa\Table the table model this row is attached to
     */
    public function getTable()
    {
        return $this->_table;
    }

    /**
     * Retrieves the column model for the specified column.
     *
     * @param string column name
     * @return Thaumatic\Junxa\Column result column, actual class will be as
     * defined by Junxa::columnClass()
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException
     */
    public function getColumn($column)
    {
        return $this->_table->$column;
    }

    /**
     * @return array<string> the list of column names for this row's table
     */
    public function getColumns()
    {
        return $this->_table->getColumns();
    }

    public function type($column)
    {
        return $this->getColumn($column)->type;
    }

    public function fullType($column)
    {
        return $this->getColumn($column)->fullType;
    }

    public function typeClass($column)
    {
        return $this->getColumn($column)->typeClass;
    }

    public function length($column)
    {
        return $this->getColumn($column)->length;
    }

    public function precision($column)
    {
        return $this->getColumn($column)->precision;
    }

    public function flags($column)
    {
        return $this->getColumn($column)->flags;
    }

    public function values($column)
    {
        $col = $this->getColumn($column);
        return $col->values;
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
    public function getStoredValue($column)
    {
        $column = $this->getColumn($column);
        $cond = $this->getMatchCondition();
        if (!$cond) {
            throw new JunxaInvalidQueryException(
                'cannot generate match condition for ' . $this->_table->getName()
            );
        }
        $value = $this->getDatabase()->query([
            'select'     => $column,
            'where'      => $cond,
        ], Junxa::QUERY_SINGLE_CELL);
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
    public function loadStoredValue($column)
    {
        if (!$this->_table->getColumnDemandLoad($column)) {
            $columnModel = $this->getColumn($column);
            throw new JunxaInvalidQueryException(
                'column ' . $column . ' is not demand-loaded'
            );
        }
        $pos = array_search($column, $this->_table->getDemandLoadColumns());
        if ($pos === false) {
            throw new JunxaInternalInconsistencyException(
                'cannot find column ' . $column . ' in table demand-load list'
            );
        }
        $value = $this->getStoredValue($column);
        $this->$column = $value;
        $this->_data[$column] = $value;
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
    public function getValue($column)
    {
        if (!property_exists($this, $column)) {
            if (!$this->_table->hasColumn($name)) {
                throw new JunxaNoSuchColumnException($name);
            }
            if (
                $this->_table->queryColumnDemandLoad($column)
                && !$this->getPrimaryKeyUnset()
            ) {
                $this->loadStoredValue($column);
            } else {
                return null;
            }
        }
        return $this->$column;
    }

    public function demandAll()
    {
        foreach ($this->_table->getDemandLoadColumns() as $column) {
            $this->loadStoredValue($column);
        }
    }

    public function checkCaching($uncache = false)
    {
        if ($this->_table->getDatabase()->getOption(Junxa::DB_CACHE_TABLE_ROWS) && $this->getPrimaryKey()) {
            $key = $this->getCacheKey();
            if ($uncache) {
                $this->_table->removeCacheKey($key);
            } elseif ($this->_table->getCachedValue($key) === null) {
                $this->_table->setCachedValue($key, $this);
            }
        }
    }

    public function getMatchCondition()
    {
        $key = $this->_table->getPrimaryKey();
        if (!$key) {
            return null;
        }
        $what = [];
        foreach ($key as $column) {
            if (!isset($this->$column)) {
                return null;
            }
            $what[] = Q::eq($this->_table->$column, $this->$column);
        }
        return $what;
    }

    public function find()
    {
        $query = $this->_table->query()
            ->select($this->_table->getSelectTarget())
            ->limit(2);
        foreach ($this->_table->columns as $column) {
            if (!isset($this->$column)) {
                continue;
            }
            $query->where($column, $this->$column);
        }
        $rows = $this->_table->getDatabase()->query($query, Junxa::QUERY_ASSOCS);
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
        $this->_data = $data;
        foreach($this->_table->getPreloadColumns() as $column) {
            $dataItem = $this->_table->$column->import($data[$column]);
            $this->_data[$column] = $dataItem;
            $this->$column = $dataItem;
        }
        foreach($this->_table->getDemandLoadColumns() as $column) {
            if (property_exists($this, $column)) {
                $this->loadStoredValue($column);
            } else {
                unset($this->_data[$column]);
            }
        }
        $this->checkCaching();
        return $out;
    }

    public function refresh()
    {
        $cond = $this->getMatchCondition();
        if (!$cond) {
            return Junxa::RESULT_REFRESH_FAIL;
        }
        $target = $this->_table->getSelectTarget();
        if ($this->_table->getDatabase()->getChangeHandlerObject()) {
            usleep(200000);
        }
        $row = $this->_table->getDatabase()->query([
            'select'    => $target,
            'where'     => $cond,
        ], Junxa::QUERY_SINGLE_ASSOC);
        if (!$row) {
            throw new JunxaInvalidQueryException('table refresh query returned no data');
        }
        $this->_data = $row;
        foreach($this->_table->getPreloadColumns() as $column) {
            $dataItem = $this->_table->$column->import($row[$column]);
            $this->_data[$column] = $dataItem;
            $this->$column = $dataItem;
        }
        $this->init();
        $this->checkCaching();
        return Junxa::RESULT_SUCCESS;
    }

    public function update($queryDef = [])
    {
        static $badClauses = [
            'select',
            'insert',
            'replace',
            'update',
            'delete',
            'group',
            'having',
        ];
        if ($queryDef) {
            if (is_array($queryDef)) {
                foreach ($badClauses as $clause) {
                    if (isset($queryDef[$clause])) {
                        throw new JunxaInvalidQueryException('query definition for update() may not define ' . $clause);
                    }
                }
                $queryDef = $this->_table->query($queryDef);
            } elseif ($queryDef instanceof QueryBuilder) {
                if ($clause = $queryDef->checkClauses($badClauses)) {
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
            $queryDef = $this->_table->query();
        }
        foreach ($this->_table->getStaticColumns() as $column) {
            if (property_exists($this, $column)
                && (
                    !array_key_exists($column, $this->_data)
                    || $this->$column !== $this->_data[$column]
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
        foreach ($cond as $item) {
            $queryDef->where($item);
        }
        $this->_table->getDatabase()->query($queryDef, Junxa::QUERY_FORGET);
        $res = $this->_table->getDatabase()->getQueryStatus();
        return Junxa::OK($res) ? $this->refresh() : $res;
    }

    public function insert($queryDef = [])
    {
        static $badClauses = [
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
        if ($queryDef) {
            if (is_array($queryDef)) {
                foreach ($badClauses as $clause) {
                    if (isset($queryDef[$clause])) {
                        throw new JunxaInvalidQueryException('query definition for insert() may not define ' . $clause);
                    }
                }
                $queryDef = $this->_table->query($queryDef);
            } elseif ($queryDef instanceof QueryBuilder) {
                if ($clause = $queryDef->checkClauses($badClauses)) {
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
            $queryDef = $this->_table->query($queryDef);
        }
        if ($this->_table->getDynamicDefaultsPresent()) {
            foreach ($this->_table->getStaticColumns() as $column) {
                if (property_exists($this, $column)) {
                    $queryDef->insert($column, $this->$column);
                } else {
                    $default = $this->_table->$column->getDynamicDefault();
                    if ($default) {
                        $queryDef->insert($column, $default);
                    }
                }
            }
        } else {
            foreach ($this->_table->getStaticColumns() as $column) {
                if (property_exists($this, $column)) {
                    $queryDef->insert($column, $this->$column);
                }
            }
        }
        if (!$queryDef->getInsert()) {
            return Junxa::RESULT_INSERT_NOOP;
        }
        $this->_table->getDatabase()->query($queryDef, Junxa::QUERY_FORGET);
        $res = $this->_table->getDatabase()->getQueryStatus();
        if (!Junxa::OK($res)) {
            return $res;
        }
        if ($res === Junxa::RESULT_SUCCESS) {
            if ($field = $this->_table->getAutoIncrementPrimary()) {
                $this->$field = $this->_table->getDatabase()->getInsertId();
            }
        }
        return $this->refresh();
    }

    public function merge($queryDef = [])
    {
        static $badClauses = [
            'select',
            'insert',
            'replace',
            'update',
            'delete',
            'group',
            'having',
        ];
        if ($queryDef) {
            if (is_array($queryDef)) {
                foreach ($badClauses as $clause) {
                    if (isset($queryDef[$clause])) {
                        throw new JunxaInvalidQueryException('query definition for merge() may not define ' . $clause);
                    }
                }
                $queryDef = $this->_table->query($queryDef);
            } elseif ($queryDef instanceof QueryBuilder) {
                if ($clause = $queryDef->checkClauses($badClauses)) {
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
            $queryDef = $this->_table->query();
        }
        $foundUniqueKeyMember = false;
        foreach ($this->_table->getStaticColumns() as $column) {
            $columnModel = $this->_table->$column;
            if (property_exists($this, $column)) {
                if (!$foundUniqueKeyMember && $columnModel->getFlag(Column::MYSQL_FLAG_UNIQUE_KEY)) {
                    $foundUniqueKeyMember = true;
                }
                $queryDef->insert($column, $this->$column);
                if (!$columnModel->getOption(Column::OPTION_MERGE_NO_UPDATE)) {
                    $queryDef->update($column, $this->$column);
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
        if (!$foundUniqueKeyMember) {
            return Junxa::RESULT_MERGE_NOKEY;
        }
        $this->_table->getDatabase()->query($queryDef, Junxa::QUERY_FORGET);
        $res = $this->_table->getDatabase()->getQueryStatus();
        if (!Junxa::OK($res)) {
            return $res;
        }
        if ($res === Junxa::RESULT_SUCCESS) {
            if ($field = $this->_table->getAutoIncrementPrimary()) {
                if ($id = $this->_table->getDatabase()->getInsertId()) {
                    $this->$field = $id;
                }
            }
        }
        return $this->refresh();
    }

    public function replace($queryDef = [])
    {
        static $badClauses = [
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
        if ($queryDef) {
            if (is_array($queryDef)) {
                foreach ($badClauses as $clause) {
                    if (isset($queryDef[$clause])) {
                        throw new JunxaInvalidQueryException(
                            'query definition for replace() may not define '
                            . $clause
                        );
                    }
                }
                $queryDef = $this->_table->query($queryDef);
            } elseif ($queryDef instanceof QueryBuilder) {
                if ($clause = $queryDef->checkClauses($badClauses)) {
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
            $queryDef = $this->_table->query($queryDef);
        }
        foreach ($this->_table->getStaticColumns() as $column) {
            if (property_exists($this, $column)) {
                $queryDef->replace($column, $this->$column);
            }
        }
        if (!$queryDef->getReplace()) {
            return Junxa::RESULT_REPLACE_NOOP;
        }
        $this->_table->getDatabase()->query($queryDef, Junxa::QUERY_FORGET);
        $res = $this->_table->getDatabase()->getQueryStatus();
        if (!Junxa::OK($res)) {
            return $res;
        }
        if ($res === Junxa::RESULT_SUCCESS) {
            if ($field = $this->_table->getAutoIncrementPrimary()) {
                $this->$field = $this->_table->getDatabase()->getInsertId();
            }
        }
        return $this->refresh();
    }

    /**
     * Persists the data on this row model to the database, via insert if this
     * is a new row or update if it is an existing row.
     *
     * @param mixed base query definition to use
     * @return int Thaumatic\Junxa::RESULT_* value for operation
     */
    public function save($queryDef = [])
    {
        return $this->_data ? $this->update($queryDef) : $this->insert($queryDef);
    }

    /**
     * @return bool whether this row has been changed from the version of it
     * in the database; a new row model with no corresponding database row
     * is always considered changed
     */
    public function changed()
    {
        if (!$this->_data) {
            return true;
        }
        foreach ($this->_table->getStaticColumns() as $column) {
            if (property_exists($this, $column)
                && (
                    !array_key_exists($column, $this->_data)
                    || $this->$column !== $this->_data[$column]
                )
            ) {
                return true;
            }
        }
        return false;
    }

    public function delete($queryDef = [])
    {
        static $badClauses = [
            'select',
            'insert',
            'replace',
            'update',
            'delete',
            'group',
            'having',
        ];
        if ($queryDef) {
            if (is_array($queryDef)) {
                foreach ($badClauses as $clause) {
                    if (isset($queryDef[$clause])) {
                        throw new JunxaInvalidQueryException('query definition for delete() may not define ' . $clause);
                    }
                }
                $queryDef = $this->_table->query($queryDef);
            } elseif ($queryDef instanceof QueryBuilder) {
                if ($clause = $queryDef->checkClauses($badClauses)) {
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
            $queryDef = $this->_table->query();
        }
        $cond = $this->getMatchCondition();
        if (!$cond) {
            return Junxa::RESULT_DELETE_FAIL;
        }
        foreach ($cond as $item) {
            $queryDef->where($item);
        }
        $queryDef->delete($this->_table);
        $this->_table->getDatabase()->query($queryDef, Junxa::QUERY_FORGET);
        $res = $this->_table->getDatabase()->getQueryStatus();
        if (Junxa::OK($res)) {
            $this->_deleted = true;
            $this->checkCaching(true);
        }
        return $res;
    }

    /**
     * @return bool whether this row has been deleted via the delete() call on
     * itself
     */
    public function getDeleted()
    {
        return $this->_deleted;
    }

    /**
     * @return array<string> the column names of of this row's table's primary
     * key
     */
    public function getPrimaryKey()
    {
        return $this->_table->getPrimaryKey();
    }

    /**
     * @return bool whether any of the primary key columns for this row's table
     * are not set on this row
     */
    public function getPrimaryKeyUnset()
    {
        foreach ($this->_table->getPrimaryKey() as $column) {
            if (!isset($this->fields[$column])) {
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
     * @throws Thaumatic\Junxa\NoSuchColumnException if the specified column
     * does not exist
     * @throws Thaumatic\Junxa\InvalidQueryException if the specified column
     * is not a foreign key
     * @throws Thaumatic\Junxa\InvalidQueryException if the foreign column is
     * is not part of a primary key or unique key
     */
    public function getForeignRow($columnName)
    {
        $column = $this->_table->$columnName;
        $localValue = $this->$columnName;
        $foreignColumn = $column->getForeignColumn();
        if (!$foreignColumn) {
            throw new InvalidQueryException(
                $columnName
                . ' on '
                . $this->_table->getName()
                . ' is not a foreign key'
            );
        }
        $foreignTable = $foreignColumn->getTable();
        $toPrimary = $foreignColumn->getFlag(Column::MYSQL_FLAG_PRI_KEY);
        if (!$toPrimary && !$foreignColumn->getFlag(Column::MYSQL_FLAG_UNIQUE_KEY)) {
            throw new InvalidQueryException(
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
                return $foreignTable->row($localValue);
            } else {
                throw new InvalidQueryException(
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
                    return
                        $foreignTable->query()
                            ->where($foreignColumn->getName(), $localValue)
                            ->row()
                    ;
                }
                $possible[] = $key;
            }
            if ($possible) {
                throw new InvalidQueryException(
                    'foreign row retrieval by multipart unique key '
                    . 'not presently supported'
                );
            } else {
                throw new InvalidQueryException(
                    'no unique key exists that would allow a foreign row from '
                    . $foreignColumn->getTable()->getName()
                    . ' to be retrieved based on '
                    . $foreignColumn->getName()
                );
            }
        }
    }

}
