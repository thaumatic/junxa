<?php

namespace Thaumatic\Junxa\Query;

use Thaumatic\Junxa;
use Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException;
use Thaumatic\Junxa\Query as Q;
use Thaumatic\Junxa\Query\Assignment;
use Thaumatic\Junxa\Query\Element;
use Thaumatic\Junxa\Query\Part;
use Thaumatic\Junxa\Table;

/**
 * Models a database query.  Can accept either a full query specification in array form or be configured via fluent interface.
 */
class Builder
{

    private $type;
    private $mode = 0;
    private $database;
    private $table;
    private $select = [];
    private $insert = [];
    private $replace = [];
    private $update = [];
    private $delete = [];
    private $join = [];
    private $where = [];
    private $group = [];
    private $having = [];
    private $order = [];
    private $limit;
    private $options = [];
    private $outputCache = '';
    private $expressed;
    private $tables = [];
    private $nullTables = [];
    private $isMultitable = false;

    /**
     * Static factory method used by database and table models to generate attached queries.
     *
     * @param Thaumatic\Junxa the database the generated query is to be attached to
     * @param Thaumatic\Junxa\Table the table the generated query is to be attached to, if any
     * @return self
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if there is something wrong with the query
     */
    public static function make(Junxa $database, $table = null, $def = null)
    {
        $out = new self($def);
        $out->database = $database;
        $out->table = $table;
        return $out;
    }

    /**
     * @param array the query definition
     * @param bool whether to skip validation of the query configuration
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if there is something wrong with the query
     */
    public function __construct($def = [], $skipValidate = false)
    {
        if(!$def)
            return;
        foreach(['select', 'insert', 'replace', 'update', 'where', 'having', 'order', 'group'] as $item)
            if(isset($def[$item]) && $def[$item] !== [])
                $this->$item = is_array($def[$item]) ? $def[$item] : [$def[$item]];
        foreach(['join', 'delete', 'limit', 'mode'] as $item)
            if(isset($def[$item]) && $def[$item] !== [])
                $this->$item = $def[$item];
        if(isset($def['options']))
            $this->options = is_array($def['options']) ? $def['options'] : [$def['options'] => true];
        if(!$skipValidate)
            $this->validate();
    }

    /**
     * Performs basic validation of the query's configuration.
     *
     * @return $this
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if there is something wrong with the query
     */
    public function validate()
    {
        foreach(['select', 'insert', 'replace', 'update', 'delete'] as $type) {
            if($this->$type) {
                if($this->type && $this->type !== $type)
                    if($this->type === 'insert' && $type === 'update')
                        continue;
                    else
                        throw new JunxaInvalidQueryException('query specifies both ' . $this->type . ' and ' . $type . ' operations');
                $this->type = $type;
            }
        }
        if(!$this->type)
            throw new JunxaInvalidQueryException('query must specify an operation type');
        switch($this->type) {
        case 'select'   :
            break;
        case 'insert'   :
        case 'replace'  :
            foreach(['join', 'where', 'having', 'order', 'group', 'limit'] as $item)
                if($this->$item)
                    throw new JunxaInvalidQueryException($item . ' specification invalid for ' . $this->type . ' query');
            break;
        case 'update'   :
        case 'delete'   :
            foreach(['join', 'having', 'group'] as $item)
                if($this->$item)
                    throw new JunxaInvalidQueryException($item . ' specification invalid for ' . $this->type . ' query');
            break;
        default         :
            throw new JunxaInvalidQueryException("unknown query type $this->type");
        }
        return $this;
    }

    /**
     * Sets the query mode.
     *
     * @param int Junxa::QUERY_* mode constant
     * @return $this
     */
    public function setMode($what)
    {
        $this->mode = $what;
        return $this;
    }

    /**
     * Retrieves the query mode.
     *
     * @return int Junxa::QUERY_* mode constant
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if more than 1 argument is given
     */
    public function getMode()
    {
        return $this->mode;
    }

    public function select()
    {
        $args = func_get_args();
        switch(count($args)) {
        case 0  :
            return new Part($this, 'select');
        case 1  :
            $what = $args[0];
            if(is_string($what) && $this->table)
                $what = $this->table->$what;
            $this->select[] = $what;
            break;
        default :
            throw new JunxaInvalidQueryException('too many arguments (' . count($args) . ')');
        }
        return $this;
    }

    /**
     * Retrieves the select clause.
     *
     * @return array<mixed>
     */
    public function getSelect()
    {
        return $this->select;
    }

    /**
     * Clears the select clause.
     *
     * @return $this
     */
    public function clearSelect()
    {
        $this->select = [];
        return $this;
    }

    /**
     * Alias the most recently added select item.
     *
     * @param string the alias name to use
     * @return $this
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if there are no select items
     */
    public function alias($name)
    {
        $ix = count($this->select) - 1;
        if($ix < 0)
            throw new JunxaInvalidQueryException('no select items');
        $this->select[$ix] = Q::alias($this->select[$ix], $name);
        return $this;
    }

    public function update()
    {
        $args = func_get_args();
        switch(count($args)) {
        case 0  :
            return new Part($this, 'update');
        case 1  :
            $what = $args[0];
            if(!($what instanceof Assignment))
                throw new JunxaInvalidQueryException('single argument to update() must be a column assignment');
            $this->update[] = $what;
            break;
        case 2  :
            $a = $args[0];
            $b = $args[1];
            if(is_string($a) && $this->table)
                $a = $this->table->$a;
            $this->update[] = new Assignment($a, $b);
            break;
        default :
            throw new JunxaInvalidQueryException('too many arguments (' . count($args) . ')');
        }
        return $this;
    }

    /**
     * Retrieves the update clause.
     *
     * @return array<mixed>
     */
    public function getUpdate()
    {
        return $this->update;
    }

    /**
     * Clears the update clause.
     *
     * @return $this
     */
    public function clearUpdate()
    {
        $this->update = [];
        return $this;
    }

    public function insert()
    {
        $args = func_get_args();
        switch(count($args)) {
        case 0  :
            return new Part($this, 'insert');
        case 1  :
            $what = $args[0];
            if(!($what instanceof Assignment))
                throw new JunxaInvalidQueryException('single argument to insert() must be a column assignment');
            $this->insert[] = $what;
            break;
        case 2  :
            $a = $args[0];
            $b = $args[1];
            if(is_string($a) && $this->table)
                $a = $this->table->$a;
            $this->insert[] = new Assignment($a, $b);
            break;
        default :
            throw new JunxaInvalidQueryException('too many arguments (' . count($args) . ')');
        }
        return $this;
    }

    /**
     * Retrieves the insert clause.
     *
     * @return array<mixed>
     */
    public function getInsert()
    {
        return $this->insert;
    }

    /**
     * Clears the update clause.
     *
     * @return $this
     */
    public function clearInsert()
    {
        $this->insert = [];
        return $this;
    }

    public function replace()
    {
        $args = func_get_args();
        switch(count($args)) {
        case 0  :
            return new Part($this, 'replace');
        case 1  :
            $what = $args[0];
            if(!($what instanceof Assignment))
                throw new JunxaInvalidQueryException('single argument to replace() must be a column assignment');
            $this->replace[] = $what;
            break;
        case 2  :
            $a = $args[0];
            $b = $args[1];
            if(is_string($a) && $this->table)
                $a = $this->table->$a;
            $this->replace[] = new Assignment($a, $b);
            break;
        default :
            throw new JunxaInvalidQueryException('too many arguments (' . count($args) . ')');
        }
        return $this;
    }

    /**
     * Retrieves the replace clause.
     *
     * @return array<mixed>
     */
    public function getReplace()
    {
        return $this->replace;
    }

    /**
     * Clears the replace clause.
     *
     * @return $this
     */
    public function clearReplace()
    {
        $this->replace = [];
        return $this;
    }

    public function delete($what = null)
    {
        if($what === null && $this->table)
            $what = $this->table;
        $this->delete[] = $what;
        return $this;
    }

    /**
     * Retrieves the delete clause.
     *
     * @return array<mixed>
     */
    public function getDelete()
    {
        return $this->delete;
    }

    /**
     * Clears the delete clause.
     *
     * @return $this
     */
    public function clearDelete()
    {
        $this->replace = [];
        return $this;
    }

    /**
     * Clears all operation clauses.
     *
     * @return $this
     */
    public function clearOperations()
    {
        return $this
            ->clearSelect()
            ->clearUpdate()
            ->clearInsert()
            ->clearReplace()
            ->clearDelete()
        ;
    }

    public function join($what)
    {
        if(is_array($what)) {
            foreach($what as $value)
                $this->join($value);
        } else {
            if(!$this->join && $this->table)
                $this->join[] = $this->table;
            if(is_string($what) && $this->database)
                $what = Q::innerJoin($this->database->$what);
            $this->join[] = $what;
        }
        return $this;
    }

    public function from($what)
    {
        if(is_array($what)) {
            foreach($what as $value)
                $this->join($value);
        } else {
            if(is_string($what) && $this->database)
                $what = Q::innerJoin($this->database->$what);
            $this->join[] = $what;
        }
        return $this;
    }

    public function on()
    {
        $args = func_get_args();
        switch(count($args)) {
        case 0  :
            return new Part($this, 'on');
        case 1  :
            $what = $args[0];
            $this->join[] = Q::joinOn($what);
            break;
        case 2  :
            $a = $args[0];
            $b = $args[1];
            if(is_string($a) && $this->table)
                $a = $this->table->$a;
            $this->join[] = Q::joinOn(Q::eq($a, $b));
            break;
        default :
            throw new JunxaInvalidQueryException('too many arguments (' . count($args) . ')');
        }
        return $this;
    }

    public function using($what)
    {
        if(is_array($what) && $this->table) {
            $conv = [];
            foreach($what as $item)
                $conv[] = is_string($item) ? $this->table->$item : $item;
            $what = $conv;
        } elseif(is_string($what) && $this->table) {
            $what = $this->table->$what;
        }
        $this->join[] = Q::joinUsing($what);
        return $this;
    }

    public function where()
    {
        $args = func_get_args();
        switch(count($args)) {
        case 0  :
            return new Part($this, 'where');
        case 1  :
            $arg = $args[0];
            if(is_array($arg)) {
                foreach($arg as $key => $value)
                    $this->where($key, $value);
            } else {
                if(is_string($arg) && $this->table)
                    $arg = $this->table->$arg;
                $this->where[] = $arg;
            }
            break;
        case 2  :
            $a = $args[0];
            $b = $args[1];
            if(is_array($a))
                throw new JunxaInvalidQueryException('cannot use array with second argument');
            if(is_string($a) && $this->table)
                $a = $this->table->$a;
            $this->where[] = Q::eq($a, $b);
            break;
        default :
            throw new JunxaInvalidQueryException('too many arguments (' . count($args) . ')');
        }
        return $this;
    }

    /**
     * Retrieves the join clause.
     *
     * @return array<mixed>
     */
    public function getJoin()
    {
        return $this->join;
    }

    /**
     * Retrieves the where clause.
     *
     * @return array<mixed>
     */
    public function getWhere()
    {
        return $this->where;
    }

    /**
     * Clears the where clause.
     *
     * @return $this
     */
    public function clearWhere()
    {
        $this->where = [];
        return $this;
    }

    public function having()
    {
        $args = func_get_args();
        switch(count($args)) {
        case 0  :
            return new Part($this, 'having');
        case 1  :
            $arg = $args[0];
            if(is_array($arg)) {
                foreach($arg as $key => $value)
                    $this->having($key, $value);
            } else {
                if(is_string($arg) && $this->table)
                    $arg = $this->table->$arg;
                $this->having[] = $arg;
            }
            break;
        case 2  :
            $a = $args[0];
            $b = $args[1];
            if(is_array($a))
                throw new JunxaInvalidQueryException('cannot use array with second argument');
            if(is_string($a) && $this->table)
                $a = $this->table->$a;
            $this->having[] = Q::eq($a, $b);
            break;
        default :
            throw new JunxaInvalidQueryException('too many arguments (' . count($args) . ')');
        }
        return $this;
    }

    /**
     * Retrieves the having clause.
     *
     * @return array<mixed>
     */
    public function getHaving()
    {
        return $this->having;
    }

    /**
     * Clears the having clause.
     *
     * @return $this
     */
    public function clearHaving()
    {
        $this->having = [];
        return $this;
    }

    public function shiftHavingToWhere()
    {
        if($this->having) {
            $this->where($this->having);
            $this->having = [];
        }
        return $this;
    }

    public function order()
    {
        $args = func_get_args();
        switch(count($args)) {
        case 0  :
            return new Part($this, 'order');
        case 1  :
            $what = $args[0];
            if(is_array($what)) {
                foreach($what as $item)
                    $this->order($item);
            } else {
                if(is_string($what) && $this->table)
                    $what = $this->table->$what;
                $this->order[] = $what;
            }
            break;
        default :
            throw new JunxaInvalidQueryException('too many arguments (' . count($args) . ')');
        }
        return $this;
    }

    /**
     * Retrieves the order clause.
     *
     * @return array<mixed>
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * Clears the order clause.
     *
     * @return $this
     */
    public function clearOrder()
    {
        $this->order = [];
        return $this;
    }

    /**
     * Converts the most recently added order item to descending sort.
     *
     * @return $this
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if there are no order items
     */
    public function desc()
    {
        $ix = count($this->order) - 1;
        if($ix < 0)
            throw new JunxaInvalidQueryException('no order items');
        $this->order[$ix] = Q::desc($this->order[$ix]);
        return $this;
    }

    public function group()
    {
        $args = func_get_args();
        switch(count($args)) {
        case 0  :
            return new Part($this, 'group');
        case 1  :
            $what = $args[0];
            if(is_array($what)) {
                foreach($what as $item)
                    $this->group($item);
            } else {
                if(is_string($what) && $this->table)
                    $what = $this->table->$what;
                $this->group[] = $what;
            }
            break;
        default :
            throw new JunxaInvalidQueryException('too many arguments (' . count($args) . ')');
        }
        return $this;
    }

    /**
     * Retrieves the group clause.
     *
     * @return array<mixed>
     */
    public function getGroup()
    {
        return $this->group;
    }

    /**
     * Clears the group clause.
     *
     * @return $this
     */
    public function clearGroup()
    {
        $this->group = [];
        return $this;
    }

    public function limit($a, $b = null)
    {
        if($b === null)
            $this->limit = intval($a);
        else
            $this->limit = intval($a) . ', ' . intval($b);
        return $this;
    }

    /**
     * Retrieves the limit clause.
     *
     * @return int|string|null
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * Defines a limit clause according to a desired page size
     * and page number.
     *
     * @param numeric page number desired
     * @param numeric page size desired
     * @return $this
     */
    public function page($pageNum, $pageSize)
    {
        $pageSize = intval($pageSize);
        $this->limit = (($pageSize * max($pageNum - 1, 0))) . ', ' . $pageSize;
        return $this;
    }

    public function options(array $what)
    {
        foreach($what as $key => $value)
            $this->options[$key] = $value;
        return $this;
    }

    public function option()
    {
        $args = func_get_args();
        switch(count($args)) {
        case 0  :
            throw new JunxaInvalidQueryException('not enough arguments (0)');
        case 1  :
            $name = $args[0];
            if(!is_string($name))
                throw new JunxaInvalidQueryException('option name must be string');
            return array_key_exists($name, $this->options) ? $this->options[$name] : null;
        case 2  :
            $name = $args[0];
            if(!is_string($name))
                throw new JunxaInvalidQueryException('option name must be string');
            $value = $args[1];
            if($value === null)
                unset($this->options[$name]);
            else
                $this->options[$name] = $value;
            break;
        }
        return $this;
    }

    public function performTableScan()
    {
        $tables = [];
        $null = [];
        $type = $this->type;
        $main = $this->$type;
        if(is_array($main)) {
            for($i = 0; $i < count($main); $i++)
                if(is_object($main[$i]) && method_exists($main[$i], 'tableScan'))
                    $main[$i]->tableScan($tables, $null);
        } else {
            $main->tableScan($tables, $null);
        }
        foreach(['join', 'where', 'group', 'having', 'order'] as $item) {
            $value = $this->$item;
            if(!empty($value)) {
                if(is_array($value)) {
                    for($i = 0; $i < count($value); $i++)
                        if(is_object($value[$i]) && method_exists($value[$i], 'tableScan'))
                            $value[$i]->tableScan($tables, $null);
                } else {
                    $value->tableScan($tables, $null);
                }
            }
        }
        $this->tables = array_keys($tables);
        $this->nullTables = $null;
        if(count($this->tables) > 1)
            $this->isMultitable = true;
    }

    public function express()
    {
        if($this->outputCache)
            return $this->outputCache;
        $this->expressed = [];
        $type = $this->type;
        $main = $this->$type;
        $this->performTableScan();
        $out = strtoupper($type) . ' ';
        switch($type) {
        case 'select'   :
            if($this->option('distinct'))
                $out .= 'DISTINCT ';
            $out .= Junxa::resolve($main, $this, $type, null, $this);
            if($join = $this->join)
                $out .= "\n\tFROM " . Junxa::resolve($join, $this, 'join', null, $this);
            elseif(count($this->tables))
                $out .= "\n\tFROM `" . join('`, `', $this->tables) . '`';
            break;
        case 'replace'  :
        case 'insert'   :
            if(count($this->tables) != 1)
                throw new JunxaInvalidQueryException($type . ' query requires exactly one table');
            if($this->options) {
                if($this->option('high_priority'))
                    $out .= 'HIGH_PRIORITY ';
                elseif($this->option('low_priority'))
                    $out .= 'LOW_PRIORITY ';
                elseif($this->option('delayed'))
                    $out .= 'DELAYED ';
                if($this->option('ignore'))
                    $out .= 'IGNORE ';
            }
            $fields = [];
            $values = [];
            $out .= "\n\t" . $this->tables[0] . ' ';
            foreach($main as $item) {
                if(!($item instanceof Assignment))
                    throw new JunxaInvalidQueryException($type . ' list elements must be column assignments');
                $fields[] = Junxa::resolve($item->getColumn(), $this, $type, null, $this);
                $values[] = Junxa::resolve($item->getValue(), $this, $type, $item->getColumn(), $this);
            }
            $out .= ' (' . join(', ', $fields) . ")\n\tVALUES\n\t(" . join(', ', $values) . ')';
            if($this->update) {
                $out .= "\n\tON DUPLICATE KEY UPDATE ";
                $elem = [];
                foreach($this->update as $item) {
                    if(!($item instanceof Assignment))
                        throw new JunxaInvalidQueryException('ON DUPLICATE KEY UPDATE list elements must be column assignments');
                    $elem[] =
                        Junxa::resolve($item->getColumn(), $this, $type, null, $this)
                        . ' = '
                        . Junxa::resolve($item->getValue(), $this, $type, $item->getColumn(), $this);
                }
                $out .= join(', ', $elem);
            }
            break;
        case 'update'   :
            if(count($this->tables) != 1)
                throw new JunxaInvalidQueryException($type . ' query requires exactly one table');
            $out .= $this->tables[0] . "\n\tSET ";
            $elem = [];
            foreach($main as $item) {
                if(!($item instanceof Assignment))
                    throw new JunxaInvalidQueryException($type . ' list elements must be column assignments');
                $elem[] =
                    Junxa::resolve($item->column, $this, $type, null, $this)
                    . ' = '
                    . Junxa::resolve($item->value, $this, $type, $item->column, $this);
            }
            $out .= join(', ', $elem);
            break;
        case 'delete'   :
            if(count($this->tables) != 1)
                throw new JunxaInvalidQueryException($type . ' query requires exactly one table');
            $out .= 'FROM ' . $this->tables[0];
            break;
        }
        if($this->where)
            $out .= "\n\tWHERE " . Junxa::resolve(is_array($this->where) ? (count($this->where) > 1 ? Q::andClause($this->where) : $this->where[0]) : $this->where, $this, 'where', null, $this);
        if($this->group)
            $out .= "\n\tGROUP BY " . Junxa::resolve($this->group, $this, 'group', null, $this);
        if($this->having)
            $out .= "\n\tHAVING " . Junxa::resolve(is_array($this->having) ? (count($this->having) > 1 ? Q::andClause($this->having) : $this->having[0]) : $this->having, $this, 'having', null, $this);
        if($this->order)
            $out .= "\n\tORDER BY " . Junxa::resolve($this->order, $this, 'order', null, $this);
        if(isset($this->limit))
            $out .= "\n\tLIMIT " . $this->limit;
        return $this->outputCache = $out;
    }

    public function execute()
    {
        if(!$this->database)
            throw new JunxaInvalidQueryException('cannot call execute() on a query that was not generated from a database');
        $this->validate();
        return $this->database->query($this);
    }

    public function rows()
    {
        if(!$this->table)
            throw new JunxaInvalidQueryException('cannot call rows() on a query that was not generated from a table');
        return $this->table->rows($this);
    }

    public function row()
    {
        if(!$this->table)
            throw new JunxaInvalidQueryException('cannot call row() on a query that was not generated from a table');
        return $this->table->row($this);
    }

    public function count()
    {
        if(!$this->table)
            throw new JunxaInvalidQueryException('cannot call count() on a query that was not generated from a table');
        return $this->table->rowCount($this);
    }

    /**
     * Retrieves whether this query spans multiple tables.
     */
    public function getIsMultitable()
    {
        return $this->isMultitable;
    }

    /**
     * Retrieves the first clause out of the provided list of clauses that
     * has a definition on this query builder, if any.
     *
     * @param array<string> list of clauses
     * @return string|null
     */
    public function checkClauses($clauses)
    {
        foreach($clauses as $clause)
            if(isset($this->$clause) && $this->$clause)
                return $clause;
        return null;
    }

}
