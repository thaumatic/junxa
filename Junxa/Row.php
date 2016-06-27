<?php

namespace Thaumatic\Junxa;

use Thaumatic\Junxa;
use Thaumatic\Junxa\Column;
use Thaumatic\Junxa\Exceptions\JunxaConfigurationException;
use Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException;
use Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException;
use Thaumatic\Junxa\Query as Q;
use Thaumatic\Junxa\Query\Builder as QueryBuilder;

/**
 * Models a database row.
 */
class Row
{

    private $data;
    private $fields = [];
    private $deleted;
    private $table;

    public function __construct($table, $data)
    {
        $this->table = $table;
        $this->data = $data;
        if($data) {
            $demandOnlyColumns = $table->getDemandOnlyColumns();
            if($demandOnlyColumns) {
                $columns = [];
                foreach($table->columns as $column)
                    if(!in_array($column, $demandOnlyColumns))
                        $columns[] = $column;
            } else {
                $columns = $table->columns;
            }
            for($i = 0; $i < count($columns); $i++) {
                $column = $columns[$i];
                $this->fields[$column] = $table->$column->import($data[$i]);
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
        if(array_key_exists($name, $this->fields))
            return $this->fields[$name];
        throw new JunxaNoSuchColumnException($name);
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
        if(array_key_exists($name, $this->fields))
            $this->fields[$name] = $value;
        else
            throw new JunxaNoSuchColumnException($name);
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
        switch(count($this->table->primary)) {
        case 0  :
            throw new JunxaConfigurationException('cannot generate cache key without primary key');
        case 1  :
            $key = $this->table->primary[0];
            return strval($this->fields[$key]);
        default :
            foreach($this->table->primary as $key)
                $elem[] = $this->fields[$key];
            return join("\0", $args) . '|' . join('', array_map('md5', $args));
        }
    }

    public function db()
    {
        return $this->table->db;
    }

    public function table()
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
    public function column($column)
    {
        if(!in_array($column, $this->table->columns))
            throw new JunxaNoSuchColumnException($column);
        return $this->table->$column;
    }

    public function columns()
    {
        return $this->table->columns;
    }

    public function type($column)
    {
        return $this->column($column)->type;
    }

    public function fullType($column)
    {
        return $this->column($column)->fullType;
    }

    public function typeClass($column)
    {
        return $this->column($column)->typeClass;
    }

    public function length($column)
    {
        return $this->column($column)->length;
    }

    public function precision($column)
    {
        return $this->column($column)->precision;
    }

    public function flags($column)
    {
        return $this->column($column)->flags;
    }

    public function values($column)
    {
        $col = $this->column($column);
        return $col->values;
    }

    public function backendValue($column)
    {
        $db = $this->db();
        $row = $db->query([
            'select'     => $this->column($column),
            'where'      => $this->getMatchCondition(),
        ], Junxa::QUERY_SINGLE_ARRAY);
        return $row[0];
    }

    public function value($column)
    {
        if(empty($this->fields[$column])) {
            $table = $this->table();
            if($table->queryColumnDemandOnly($column) && !$this->primaryKeyUnset())
                $this->fields[$column] = $this->backendValue($column);
        }
        return isset($this->fields[$column]) ? $this->fields[$column] : null;
    }

    public function demandAll()
    {
        $demandOnlyColumns = $this->table->getDemandOnlyColumns();
        if($demandOnlyColumns)
            foreach($demandOnlyColumns as $column)
                $this->value($column);
    }

    public function checkCaching($uncache = false)
    {
        if(($this->table->db()->options & Junxa::DB_CACHE_TABLE_ROWS) && count($this->table->primary)) {
            $key = $this->cacheKey();
            if($uncache)
                unset($this->table->cache[$key]);
            elseif(empty($this->table->cache[$key]))
                $this->table->cache[$key] = $this;
        }
    }

    public function getMatchCondition()
    {
        $key = $this->table->primaryKey();
        if(!$key)
            return 0;
        $what = [];
        foreach($key as $column) {
            if(empty($this->fields[$column]))
                return 0;
            $what[] = Q::eq($this->table->$column, $this->fields[$column]);
        }
        return $what;
    }

    public function find()
    {
        $target = $this->table->selectTarget();
        $query = [
            'select'    => $target,
            'limit'     => 2,
        ];
        foreach($this->table->columns as $column) {
            if(empty($this->fields[$column]))
                continue;
            $cond[] = Q::eq($this->table->$column, $this->fields[$column]);
        }
        if(count($cond))
            $query['where'] = $cond;
        $rows = $this->table->db()->query($query, Junxa::QUERY_ARRAYS);
        switch(count($rows)) {
        case 0  :
            return Junxa::RESULT_FIND_FAIL;
        case 1  :
            $out = Junxa::RESULT_SUCCESS;
            break;
        default :
            $out = Junxa::RESULT_FIND_EXCESS;
            break;
        }
        $data = $rows[0];
        $this->data = $data;
        $demandOnlyColumns = $this->table->getDemandOnlyColumns();
        if($demandOnlyColumns) {
            $columns = [];
            foreach($this->table->columns as $column)
                if(!in_array($column, $demandOnlyColumns))
                    $columns[] = $column;
        } else {
            $columns = $this->table->columns;
        }
        for($i = 0; $i < count($columns); $i++) {
            $column = $columns[$i];
            $this->fields[$column] = $data[$i];
        }
        $this->checkCaching();
        return $out;
    }

    public function refresh()
    {
        $cond = $this->getMatchCondition();
        if(!$cond)
            return Junxa::RESULT_REFRESH_FAIL;
        $target = $this->table->select_target();
        if($this->table->db()->change_handler())
            usleep(200000);
        $row = $this->table->db()->query([
            'select'    => $target,
            'where'     => $cond,
        ], Junxa::QUERY_SINGLE_ARRAY);
        if(!$row)
            throw new JunxaInvalidQueryException('table refresh query returned no data');
        $this->data = $row;
        $demandOnlyColumns = $this->table->getDemandOnlyColumns();
        if($demandOnlyColumns) {
            $columns = [];
            foreach($this->table->columns as $column)
                if(!in_array($column, $demandOnlyColumns))
                    $columns[] = $column;
        } else {
            $columns = $this->table->columns;
        }
        for($i = 0; $i < count($columns); $i++) {
            $column = $columns[$i];
            $this->fields[$column] = $row[$i];
        }
        $this->init();
        $this->checkCaching();
        return Junxa::RESULT_SUCCESS;
    }

    public function update($queryDef = [])
    {
        static $badClauses = ['select', 'insert', 'replace', 'update', 'delete', 'group', 'order', 'having'];
        if($queryDef) {
            if(is_array($queryDef)) {
                foreach($badClauses as $clause)
                    if(isset($queryDef[$clause]))
                        throw new JunxaInvalidQueryException('query definition for update() may not define ' . $clause);
            } elseif($queryDef instanceof QueryBuilder) {
                if($clause = $queryDef->checkClauses($badClauses))
                    throw new JunxaInvalidQueryException('query definition for update() may not define ' . $clause);
            } else {
                throw new JunxaInvalidQueryException(
                    'query definition for update() must be a '
                    . 'Thaumatic\Junxa\Query\Builder or an array '
                    . 'query definition'
                );
            }
        }
        $queryDef = $this->table->query($queryDef);
        $demandOnlyColumns = $this->table->getDemandOnlyColumns();
        if($demandOnlyColumns) {
            $columns = [];
            foreach($this->table->getStaticColumns() as $column)
                if(!in_array($column, $demandOnlyColumns))
                    $columns[] = $column;
            foreach($demandOnlyColumns as $column) {
                $value = $this->backendValue($column);
                if(
                    $this->fields[$column] !== $value
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
        for($i = 0; $i < count($columns); $i++) {
            $column = $columns[$i];
            $value = $this->data[$i];
            if(
                $this->fields[$column] !== $value
                && (
                    !is_numeric($value)
                    || !is_numeric($this->fields[$column])
                    || $this->fields[$column] != $value
                )
            ) {
                $queryDef->update($column, $this->fields[$column]);
            }
        }
        if(!$queryDef->getUpdate())
            return Junxa::RESULT_UPDATE_NOOP;
        $cond = $this->getMatchCondition();
        if(!$cond)
            return Junxa::RESULT_UPDATE_NOKEY;
        foreach($cond as $item)
            $queryDef->where($item);
        $this->table->db()->query($queryDef, Junxa::QUERY_FORGET);
        $res = $this->table->db()->queryStatus();
        return Junxa::OK($res) ? $this->refresh() : $res;
    }

    public function insert($queryDef = [])
    {
        $fields = [];
        foreach($this->table->getStaticColumns() as $column)
            if(array_key_exists($column, $this->fields))
                $fields[] = Q::set($this->table->$column, $this->fields[$column]);
        if(!$fields)
            return Junxa::RESULT_INSERT_NOOP;
        if($queryDef)
            foreach(['select', 'insert', 'replace', 'update', 'delete', 'group', 'order', 'having', 'limit'] as $item)
                if(isset($queryDef[$item]))
                    throw new JunxaInvalidQueryException('query definition for insert() may not define ' . $item);
        $queryDef['insert'] = $fields;
        $query = $this->table->db()->query($queryDef, Junxa::QUERY_FORGET);
        $res = $this->table->db()->queryStatus();
        if(!Junxa::OK($res))
            return $res;
        if($res === Junxa::RESULT_SUCCESS)
            if($field = $this->table->autoIncrementPrimary())
                $this->fields[$field] = $this->table->db()->insertId();
        return $this->refresh();
    }

    public function merge($queryDef = [])
    {
        $insertFields = [];
        $updateFields = [];
        $foundUniqueKeyMember = false;
        foreach($this->table->getStaticColumns() as $column) {
            if(array_key_exists($column, $this->fields)) {
                $columnModel = $this->table->$column;
                if(!$foundUniqueKeyMember && $columnModel->getFlag(Column::MYSQL_FLAG_UNIQUE))
                    $foundUniqueKeyMember = true;
                $operation = Q::set($columnModel, $this->fields[$column]);
                $insertFields[] = $operation;
                if(!$columnModel->getOption(Column::OPTION_MERGE_NO_UPDATE))
                    $updateFields[] = $operation;
            }
        }
        if(!$fields)
            return Junxa::RESULT_MERGE_NOOP;
        if(!$foundUniqueKeyMember)
            return Junxa::RESULT_MERGE_NOKEY;
        if($queryDef)
            foreach(['select', 'insert', 'replace', 'update', 'delete', 'group', 'order', 'having', 'limit'] as $item)
                if(isset($queryDef[$item]))
                    throw new JunxaInvalidQueryException('query definition for merge() may not define ' . $item);
        $queryDef['insert'] = $insertFields;
        $queryDef['update'] = $updateFields;
        $query = $this->table->db()->query($queryDef, Junxa::QUERY_FORGET);
        $res = $this->table->db()->queryStatus();
        if(!Junxa::OK($res))
            return $res;
        if($res === Junxa::RESULT_SUCCESS)
            if($field = $this->table->autoIncrementPrimary())
                if($id = $this->table->db()->insertId())
                    $this->fields[$field] = $id;
        return $this->refresh();
    }

    public function replace($queryDef = [])
    {
        $fields = [];
        foreach($this->table->getStaticColumns() as $column)
            if(array_key_exists($column, $this->fields))
                $fields[] = Q::set($this->table->$column, $this->fields[$column]);
        if(!$fields)
            return Junxa::RESULT_REPLACE_NOOP;
        if($queryDef)
            foreach(['select', 'insert', 'replace', 'update', 'delete', 'group', 'order', 'having', 'limit'] as $item)
                if(isset($queryDef[$item]))
                    throw new JunxaInvalidQueryException('query definition for replace() may not define ' . $item);
        $queryDef['replace'] = $fields;
        $query = $this->table->db()->query($queryDef, Junxa::QUERY_FORGET);
        $res = $this->table->db()->queryStatus();
        if(!Junxa::OK($res))
            return $res;
        if($res === Junxa::RESULT_SUCCESS)
            if($field = $this->table->autoIncrementPrimary())
                $this->fields[$field] = $this->table->db()->insertId();
        return $this->refresh();
    }

    public function save($queryDef = [])
    {
        return $this->data ? $this->update($queryDef) : $this->insert($queryDef);
    }

    public function changed()
    {
        if(!$this->data)
            return true;
        $demandOnlyColumns = $table->getDemandOnlyColumns();
        if($demandOnlyColumns) {
            foreach($demandOnlyColumns as $column)
                if($this->fields[$column] !== $this->backendValue($column))
                    return true;
            $columns = [];
            foreach($this->table->getStaticColumns() as $column)
                if(!in_array($column, $demandOnlyColumns))
                    $columns[] = $column;
        } else {
            $columns = $this->table->getStaticColumns();
        }
        for($i = 0; $i < count($columns); $i++) {
            $column = $columns[$i];
            if($this->fields[$column] !== $this->data[$i])
                return true;
        }
        return false;
    }

    public function delete($queryDef = [])
    {
        $cond = $this->getMatchCondition();
        if(!$cond)
            return Junxa::RESULT_DELETE_FAIL;
        if($queryDef)
            foreach(['select', 'insert', 'replace', 'update', 'delete', 'group', 'order', 'having'] as $item)
                if(isset($queryDef[$item]))
                    throw new JunxaInvalidQueryException('query definition for delete() may not define ' . $item);
        $queryDef['delete'] = $this->table;
        if(isset($queryDef['where']))
            $queryDef['where'] = array_merge(is_array($cond) ? $cond : [$cond], is_array($queryDef['where']) ? $queryDef['where'] : [$queryDef['where']]);
        else
            $queryDef['where'] = $cond;
        $res = $this->table->db()->query($queryDef, Junxa::QUERY_FORGET);
        if(Junxa::OK($res)) {
            $this->deleted = true;
            $this->checkCaching(true);
        }
        return $res;
    }

    public function getDeleted()
    {
        return $this->deleted;
    }

    public function primaryKey()
    {
        $table = $this->table();
        return $table->primaryKey();
    }

    public function primaryKeyUnset()
    {
        $table = $this->table();
        foreach($table->primaryKey() as $column)
            if(empty($this->fields[$column]))
                return true;
        return false;
    }

}
