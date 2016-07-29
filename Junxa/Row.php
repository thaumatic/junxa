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
    private $data;

    /**
     * @var array<string:mixed> the "working values" for the columns on this
     * row; when a value is set on this row after creation, it is set here
     */
    private $fields = [];

    /**
     * @var bool whether this row has been deleted via the delete() method
     * being called on it
     */
    private $deleted = false;

    /**
     * @var Thaumatic\Junxa\Table the table this row is from
     */
    private $table;

    public function __construct(Table $table, array $data = null)
    {
        $this->table = $table;
        $this->data = $data;
        if ($data) {
            $demandOnlyColumns = $table->getDemandOnlyColumns();
            if ($demandOnlyColumns) {
                $columns = [];
                foreach ($table->getColumns() as $column) {
                    if (!in_array($column, $demandOnlyColumns)) {
                        $columns[] = $column;
                    }
                }
            } else {
                $columns = $table->getColumns();
            }
            for ($i = 0; $i < count($columns); $i++) {
                $column = $columns[$i];
                $dataItem = $table->$column->import($data[$i]);
                $this->data[$i] = $dataItem;
                $this->fields[$column] = $dataItem;
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
     */
    public function __get($name)
    {
        if (array_key_exists($name, $this->fields)) {
            return $this->fields[$name];
        }
        if (!$this->table->hasColumn($name)) {
            throw new JunxaNoSuchColumnException($name);
        }
        return null;
    }

    /**
     * Property-mode mutator for field values.
     *
     * @param string field name
     * @param mixed value to assign
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException if the
     * specified field does not exist
     */
    public function __set($name, $value)
    {
        if (array_key_exists($name, $this->fields) || $this->table->hasColumn($name)) {
            $this->fields[$name] = $value;
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
    public function cacheKey()
    {
        switch (count($this->table->primary)) {
            case 0:
                throw new JunxaConfigurationException('cannot generate cache key without primary key');
            case 1:
                $key = $this->table->primary[0];
                return strval($this->fields[$key]);
            default:
                foreach ($this->table->primary as $key) {
                    $elem[] = $this->fields[$key];
                }
                return join("\0", $args) . '|' . join('', array_map('md5', $args));
        }
    }

    /**
     * @return Thaumatic\Junxa the database model this row is attached to
     */
    public function getDatabase()
    {
        return $this->table->getDatabase();
    }

    /**
     * @return Thaumatic\Junxa\Table the table model this row is attached to
     */
    public function getTable()
    {
        return $this->table;
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
        if (!in_array($column, $this->table->columns)) {
            throw new JunxaNoSuchColumnException($column);
        }
        return $this->table->$column;
    }

    public function columns()
    {
        return $this->table->columns;
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
     * Retrieves the value for the specified column on this row.  This is
     * the mechanism intended to be used for obtaining the values of
     * demand-only columns.
     *
     * @param string column name
     * @return mixed
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
                'cannot generate match condition for ' . $this->table->getName()
            );
        }
        $value = $this->getDatabase()->query([
            'select'     => $column,
            'where'      => $cond,
        ], Junxa::QUERY_SINGLE_CELL);
        return $column->import($value);
    }

    public function value($column)
    {
        if (empty($this->fields[$column])) {
            if ($this->table->queryColumnDemandOnly($column) && !$this->getPrimaryKeyUnset()) {
                $this->fields[$column] = $this->getStoredValue($column);
            }
        }
        return isset($this->fields[$column]) ? $this->fields[$column] : null;
    }

    public function demandAll()
    {
        $demandOnlyColumns = $this->table->getDemandOnlyColumns();
        if ($demandOnlyColumns) {
            foreach ($demandOnlyColumns as $column) {
                $this->value($column);
            }
        }
    }

    public function checkCaching($uncache = false)
    {
        if ($this->table->getDatabase()->getOption(Junxa::DB_CACHE_TABLE_ROWS) && $this->getPrimaryKey()) {
            $key = $this->cacheKey();
            if ($uncache) {
                $this->table->removeCacheKey($key);
            } elseif ($this->table->getCachedValue($key) === null) {
                $this->table->setCachedValue($key, $this);
            }
        }
    }

    public function getMatchCondition()
    {
        $key = $this->table->getPrimaryKey();
        if (!$key) {
            return null;
        }
        $what = [];
        foreach ($key as $column) {
            if (!isset($this->fields[$column])) {
                return null;
            }
            $what[] = Q::eq($this->table->$column, $this->fields[$column]);
        }
        return $what;
    }

    public function find()
    {
        $target = $this->table->getSelectTarget();
        $query = [
            'select'    => $target,
            'limit'     => 2,
        ];
        foreach ($this->table->columns as $column) {
            if (empty($this->fields[$column])) {
                continue;
            }
            $cond[] = Q::eq($this->table->$column, $this->fields[$column]);
        }
        if (count($cond)) {
            $query['where'] = $cond;
        }
        $rows = $this->table->getDatabase()->query($query, Junxa::QUERY_ARRAYS);
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
        $this->data = $data;
        $demandOnlyColumns = $this->table->getDemandOnlyColumns();
        if ($demandOnlyColumns) {
            $columns = [];
            foreach ($this->table->columns as $column) {
                if (!in_array($column, $demandOnlyColumns)) {
                    $columns[] = $column;
                }
            }
        } else {
            $columns = $this->table->columns;
        }
        for ($i = 0; $i < count($columns); $i++) {
            $column = $columns[$i];
            $dataItem = $this->table->$column->import($data[$i]);
            $this->data[$i] = $dataItem;
            $this->fields[$column] = $dataItem;
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
        $target = $this->table->getSelectTarget();
        if ($this->table->getDatabase()->getChangeHandlerObject()) {
            usleep(200000);
        }
        $row = $this->table->getDatabase()->query([
            'select'    => $target,
            'where'     => $cond,
        ], Junxa::QUERY_SINGLE_ARRAY);
        if (!$row) {
            throw new JunxaInvalidQueryException('table refresh query returned no data');
        }
        $this->data = $row;
        $demandOnlyColumns = $this->table->getDemandOnlyColumns();
        if ($demandOnlyColumns) {
            $columns = [];
            foreach ($this->table->getColumns() as $column) {
                if (!in_array($column, $demandOnlyColumns)) {
                    $columns[] = $column;
                }
            }
        } else {
            $columns = $this->table->getColumns();
        }
        for ($i = 0; $i < count($columns); $i++) {
            $column = $columns[$i];
            $dataItem = $this->table->$column->import($row[$i]);
            $this->data[$i] = $dataItem;
            $this->fields[$column] = $dataItem;
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
                $queryDef = $this->table->query($queryDef);
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
            $queryDef = $this->table->query();
        }
        $demandOnlyColumns = $this->table->getDemandOnlyColumns();
        if ($demandOnlyColumns) {
            $columns = [];
            foreach ($this->table->getStaticColumns() as $column) {
                if (!in_array($column, $demandOnlyColumns)) {
                    $columns[] = $column;
                }
            }
            foreach ($demandOnlyColumns as $column) {
                $value = $this->getStoredValue($column);
                if ($this->fields[$column] !== $value
                    && (
                        !is_numeric($value)
                        || !is_numeric($this->fields[$column])
                        || $this->fields[$column] != $value
                    )
                ) {
                    $queryDef->update($column, $this->fields[$column]);
                }
            }
        } else {
            $columns = $this->table->getStaticColumns();
        }
        for ($i = 0; $i < count($columns); $i++) {
            $column = $columns[$i];
            if ($this->fields[$column] !== $this->data[$i]) {
                $queryDef->update($column, $this->fields[$column]);
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
        $this->table->getDatabase()->query($queryDef, Junxa::QUERY_FORGET);
        $res = $this->table->getDatabase()->getQueryStatus();
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
                $queryDef = $this->table->query($queryDef);
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
            $queryDef = $this->table->query($queryDef);
        }
        if ($this->table->getDynamicDefaultsPresent()) {
            foreach ($this->table->getStaticColumns() as $column) {
                if (array_key_exists($column, $this->fields)) {
                    $queryDef->insert($column, $this->fields[$column]);
                } else {
                    $default = $this->table->$column->getDynamicDefault();
                    if ($default) {
                        $queryDef->insert($column, $default);
                    }
                }
            }
        } else {
            foreach ($this->table->getStaticColumns() as $column) {
                if (array_key_exists($column, $this->fields)) {
                    $queryDef->insert($column, $this->fields[$column]);
                }
            }
        }
        if (!$queryDef->getInsert()) {
            return Junxa::RESULT_INSERT_NOOP;
        }
        $this->table->getDatabase()->query($queryDef, Junxa::QUERY_FORGET);
        $res = $this->table->getDatabase()->getQueryStatus();
        if (!Junxa::OK($res)) {
            return $res;
        }
        if ($res === Junxa::RESULT_SUCCESS) {
            if ($field = $this->table->getAutoIncrementPrimary()) {
                $this->fields[$field] = $this->table->getDatabase()->getInsertId();
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
                $queryDef = $this->table->query($queryDef);
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
            $queryDef = $this->table->query();
        }
        $foundUniqueKeyMember = false;
        foreach ($this->table->getStaticColumns() as $column) {
            $columnModel = $this->table->$column;
            if (array_key_exists($column, $this->fields)) {
                if (!$foundUniqueKeyMember && $columnModel->getFlag(Column::MYSQL_FLAG_UNIQUE_KEY)) {
                    $foundUniqueKeyMember = true;
                }
                $queryDef->insert($column, $this->fields[$column]);
                if (!$columnModel->getOption(Column::OPTION_MERGE_NO_UPDATE)) {
                    $queryDef->update($column, $this->fields[$column]);
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
        $this->table->getDatabase()->query($queryDef, Junxa::QUERY_FORGET);
        $res = $this->table->getDatabase()->getQueryStatus();
        if (!Junxa::OK($res)) {
            return $res;
        }
        if ($res === Junxa::RESULT_SUCCESS) {
            if ($field = $this->table->getAutoIncrementPrimary()) {
                if ($id = $this->table->getDatabase()->getInsertId()) {
                    $this->fields[$field] = $id;
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
                $queryDef = $this->table->query($queryDef);
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
            $queryDef = $this->table->query($queryDef);
        }
        foreach ($this->table->getStaticColumns() as $column) {
            if (array_key_exists($column, $this->fields)) {
                $queryDef->replace($column, $this->fields[$column]);
            }
        }
        if (!$queryDef->getReplace()) {
            return Junxa::RESULT_REPLACE_NOOP;
        }
        $this->table->getDatabase()->query($queryDef, Junxa::QUERY_FORGET);
        $res = $this->table->getDatabase()->getQueryStatus();
        if (!Junxa::OK($res)) {
            return $res;
        }
        if ($res === Junxa::RESULT_SUCCESS) {
            if ($field = $this->table->getAutoIncrementPrimary()) {
                $this->fields[$field] = $this->table->getDatabase()->getInsertId();
            }
        }
        return $this->refresh();
    }

    public function save($queryDef = [])
    {
        return $this->data ? $this->update($queryDef) : $this->insert($queryDef);
    }

    public function changed()
    {
        if (!$this->data) {
            return true;
        }
        $demandOnlyColumns = $table->getDemandOnlyColumns();
        if ($demandOnlyColumns) {
            foreach ($demandOnlyColumns as $column) {
                if ($this->fields[$column] !== $this->getStoredValue($column)) {
                    return true;
                }
            }
            $columns = [];
            foreach ($this->table->getStaticColumns() as $column) {
                if (!in_array($column, $demandOnlyColumns)) {
                    $columns[] = $column;
                }
            }
        } else {
            $columns = $this->table->getStaticColumns();
        }
        for ($i = 0; $i < count($columns); $i++) {
            $column = $columns[$i];
            if ($this->fields[$column] !== $this->data[$i]) {
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
                $queryDef = $this->table->query($queryDef);
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
            $queryDef = $this->table->query();
        }
        $cond = $this->getMatchCondition();
        if (!$cond) {
            return Junxa::RESULT_DELETE_FAIL;
        }
        foreach ($cond as $item) {
            $queryDef->where($item);
        }
        $queryDef->delete($this->table);
        $this->table->getDatabase()->query($queryDef, Junxa::QUERY_FORGET);
        $res = $this->table->getDatabase()->getQueryStatus();
        if (Junxa::OK($res)) {
            $this->deleted = true;
            $this->checkCaching(true);
        }
        return $res;
    }

    /**
     * Retrieves whether this row has been deleted via the delete() call on
     * itself.
     *
     * @return bool
     */
    public function getDeleted()
    {
        return $this->deleted;
    }

    /**
     * Retrieves an array of the column names of of this row's table's primary
     * key.
     *
     * @return array<string>
     */
    public function getPrimaryKey()
    {
        return $this->table->getPrimaryKey();
    }

    /**
     * Retrieves whether any of the primary key columns for this row's table
     * are not set on this row.
     *
     * @return bool
     */
    public function getPrimaryKeyUnset()
    {
        foreach ($this->table->getPrimaryKey() as $column) {
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
        $column = $this->table->$columnName;
        $localValue = $this->$columnName;
        $foreignColumn = $column->getForeignColumn();
        if (!$foreignColumn) {
            throw new InvalidQueryException(
                $columnName
                . ' on '
                . $this->table->getName()
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
