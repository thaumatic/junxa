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
 * Models a database query.  Can accept either a full query specification in
 * array form or be configured via fluent interface.
 */
class Builder
{

    /**
     * @const int option: INSERT and REPLACE clauses generated from this query
     * should use the DELAYED modifier
     */
    const OPTION_DELAYED                    = 0x00000001;

    /**
     * @const int option: a SELECT clause generated from this query should
     * use the DISTINCT modifier
     */
    const OPTION_DISTINCT                   = 0x00000002;

    /**
     * @const int option: single element result sets should result in a null
     * return value rather than raising an exception if they find no results
     */
    const OPTION_EMPTY_OKAY                 = 0x00000004;

    /**
     * @const int option: don't raise exceptions on query failures
     */
    const OPTION_ERROR_OKAY                 = 0x00000008;

    /**
     * @const int option: force this query to be sent to the database's
     * change handler, if any
     */
    const OPTION_FORCE_USE_CHANGE_HANDLER   = 0x00000010;

    /**
     * @const int option: INSERT and REPLACE clauses generated from this query
     * should use the HIGH_PRIORITY modifier (overrides OPTION_LOW_PRIORITY
     * and OPTION_DELAYED)
     */
    const OPTION_HIGH_PRIORITY              = 0x00000020;

    /**
     * @const int option: INSERT and REPLACE clauses generated from this query
     * should use the IGNORE modifier
     */
    const OPTION_IGNORE                     = 0x00000040;

    /**
     * @const int option: INSERT and REPLACE clauses generated from this query
     * should use the LOW_PRIORITY modifier (overrides OPTION_DELAYED)
     */
    const OPTION_LOW_PRIORITY               = 0x00000080;

    /**
     * @const int option: don't cache rows retrieved with this query
     */
    const OPTION_SUPPRESS_CACHING           = 0x00000100;

    /**
     * @const array<string> the clauses for which incoming values need to be
     * loaded into an array if they aren't one already
     */
    const ARRAY_CLAUSES = [
        'select',
        'insert',
        'replace',
        'update',
        'where',
        'having',
        'order',
        'group',
    ];

    /**
     * @const array<string> the clauses for which incoming values can be used
     * directly
     */
    const DIRECT_CLAUSES = [
        'join',
        'delete',
        'limit',
        'mode',
    ];

    /**
     * @const string the "clause" in an array query specification which should
     * be interpreted as a list of options
     */
    const OPTIONS_CLAUSE = 'options';

    /**
     * @var string the main clause of the query, set by validate()
     */
    private $type;

    /**
     * @var int Junxa::QUERY_* mode indicating how query results should be
     * handled
     */
    private $mode = 0;

    /**
     * @var Thaumatic\Junxa database model this query is attached to
     */
    private $database;

    /**
     * @var Thaumatic\Junxa\Table table model this query is attached
     * to, if any
     */
    private $table;

    /**
     * @var array<mixed> SELECT clause contents
     */
    private $select = [];

    /**
     * @var array<mixed> INSERT clause contents
     */
    private $insert = [];

    /**
     * @var array<mixed> REPLACE clause contents
     */
    private $replace = [];

    /**
     * @var array<mixed> UPDATE clause contents
     */
    private $update = [];

    /**
     * @var array<mixed> DELETE clause contents
     */
    private $delete = [];

    /**
     * @var array<mixed> JOIN clause contents
     */
    private $join = [];

    /**
     * @var array<mixed> WHERE clause contents
     */
    private $where = [];

    /**
     * @var array<mixed> GROUP BY clause contents
     */
    private $group = [];

    /**
     * @var array<mixed> HAVING clause contents
     */
    private $having = [];

    /**
     * @var array<mixed> ORDER BY clause contents
     */
    private $order = [];

    /**
     * @var mixed LIMIT clause contents
     */
    private $limit;

    /**
     * @var string cache of rendered SQL for this query
     */
    private $outputCache;

    /**
     * @var array<string> list of names of tables this query interacts with
     */
    private $tables = [];

    /**
     * @var array<string:true> map of names of tables that can be assigned
     * null fields because of their JOIN position
     */
    private $nullTables = [];

    /**
     * @var bool whether this query interacts with multiple tables
     */
    private $isMultitable = false;

    /**
     * @var bool whether this query has been validated
     */
    private $validated = false;

    /**
     * @var int Thaumatic\Junxa\OPTION_* bitmask of enabled query options
     */
    private $options = 0;

    /**
     * @var callable call this with the final SQL text as argument
     */
    private $callOnSql;

    /**
     * @param Thaumatic\Junxa the database the generated query is to be
     * attached to
     * @param Thaumatic\Junxa\Table the table the generated query is to be
     * attached to, if any
     * @param array<string:mixed> query definition
     * @param bool whether to skip immediate query validation on construction
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if there
     * is something wrong with the query
     */
    public function __construct(
        Junxa $database,
        Table $table = null,
        array $def = [],
        $skipValidate = false
    ) {
        $this->database = $database;
        $this->table = $table;
        if (!$def) {
            return;
        }
        foreach (self::ARRAY_CLAUSES as $clause) {
            if (isset($def[$clause]) && $def[$clause] !== []) {
                $this->$clause = is_array($def[$clause]) ? $def[$clause] : [$def[$clause]];
            }
        }
        foreach (self::DIRECT_CLAUSES as $clause) {
            if (isset($def[$clause]) && $def[$clause] !== []) {
                $this->$clause = $def[$clause];
            }
        }
        if (isset($def[self::OPTIONS_CLAUSE])) {
            $options = $def[self::OPTIONS_CLAUSE];
            if (is_array($options)) {
                foreach ($options as $option) {
                    if (!is_int($option)) {
                        throw new JunxaInvalidQueryException(
                            'invalid type for value in '
                            . self::OPTIONS_CLAUSE
                            . ': '
                            . gettype($option)
                        );
                    }
                    $this->setOption($option, true);
                }
            } elseif (is_int($options)) {
                $this->setOption($options, true);
            } else {
                throw new JunxaInvalidQueryException(
                    'invalid type for '
                    . self::OPTIONS_CLAUSE
                    . ': '
                    . gettype($options)
                );
            }
        }
        if (!$skipValidate) {
            $this->validate();
        }
    }

    /**
     * Retrieves the database model this query is attached to.
     *
     * @return Thaumatic\Junxa
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * Retrieves the table model this query is attached to, if any.
     *
     * @return Thaumatic\Junxa\Table|null
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Called when an alteration is made to the query definition.  Marks the
     * query as not validated and clears any output caching.
     */
    private function changed()
    {
        $this->outputCache = null;
        $this->validated = false;
    }

    /**
     * Retrieves the query type.  $this->validate() must have been
     * called first for the result to be meaningful.
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Performs basic validation of the query's configuration.
     *
     * @return $this
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if there
     * is something wrong with the query
     */
    public function validate()
    {
        if ($this->validated) {
            return $this;
        }
        foreach (['select', 'insert', 'replace', 'update', 'delete'] as $type) {
            if ($this->$type) {
                if ($this->type && $this->type !== $type) {
                    if ($this->type === 'insert' && $type === 'update') {
                        continue;
                    } else {
                        throw new JunxaInvalidQueryException(
                            'query specifies both '
                            . $this->type
                            . ' and '
                            . $type
                            . ' operations'
                        );
                    }
                }
                $this->type = $type;
            }
        }
        if (!$this->type) {
            throw new JunxaInvalidQueryException(
                'query must specify an operation type'
            );
        }
        switch ($this->type) {
            case 'select':
                break;
            case 'insert':
            case 'replace':
                foreach (['join', 'where', 'having', 'order', 'group', 'limit'] as $item) {
                    if ($this->$item) {
                        throw new JunxaInvalidQueryException(
                            $item
                            . ' specification invalid for '
                            . $this->type
                            . ' query'
                        );
                    }
                }
                break;
            case 'update':
            case 'delete':
                foreach (['join', 'having', 'group'] as $item) {
                    if ($this->$item) {
                        throw new JunxaInvalidQueryException(
                            $item
                            . ' specification invalid for '
                            . $this->type
                            . ' query'
                        );
                    }
                }
                break;
            default:
                throw new JunxaInvalidQueryException(
                    'unknown query type '
                    . $this->type
                );
        }
        $this->validated = true;
        return $this;
    }

    /**
     * Performs basic validation of the query's configuration even if has
     * already been validated.
     *
     * @return $this
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if there
     * is something wrong with the query
     */
    public function revalidate()
    {
        $this->validated = false;
        return $this->validate();
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
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if more
     * than 1 argument is given
     */
    public function getMode()
    {
        return $this->mode;
    }

    public function select()
    {
        $args = func_get_args();
        switch (count($args)) {
            case 0:
                return new Part($this, 'select');
            case 1:
                $what = $args[0];
                if (is_string($what) && $this->table) {
                    $what = $this->table->$what;
                }
                $this->select[] = $what;
                $this->changed();
                break;
            default:
                throw new JunxaInvalidQueryException(
                    'too many arguments ('
                    . count($args)
                    . ')'
                );
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
        $this->changed();
        return $this;
    }

    /**
     * Alias the most recently added select item.
     *
     * @param string the alias name to use
     * @return $this
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if there
     * are no select items
     */
    public function alias($name)
    {
        $ix = count($this->select) - 1;
        if ($ix < 0) {
            throw new JunxaInvalidQueryException('no select items');
        }
        $this->select[$ix] = Q::alias($this->select[$ix], $name);
        $this->changed();
        return $this;
    }

    public function update()
    {
        $args = func_get_args();
        switch (count($args)) {
            case 0:
                return new Part($this, 'update');
            case 1:
                $what = $args[0];
                if (!($what instanceof Assignment)) {
                    throw new JunxaInvalidQueryException(
                        'single argument to update() must be a column '
                        . 'assignment'
                    );
                }
                $this->update[] = $what;
                $this->changed();
                break;
            case 2:
                $a = $args[0];
                $b = $args[1];
                if (is_string($a) && $this->table) {
                    $a = $this->table->$a;
                }
                $this->update[] = new Assignment($a, $b);
                $this->changed();
                break;
            default:
                throw new JunxaInvalidQueryException(
                    'too many arguments ('
                    . count($args)
                    . ')'
                );
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
        $this->changed();
        return $this;
    }

    public function insert()
    {
        $args = func_get_args();
        switch (count($args)) {
            case 0:
                return new Part($this, 'insert');
            case 1:
                $what = $args[0];
                if (!($what instanceof Assignment)) {
                    throw new JunxaInvalidQueryException(
                        'single argument to insert() must be a column '
                        . 'assignment'
                    );
                }
                $this->insert[] = $what;
                $this->changed();
                break;
            case 2:
                $a = $args[0];
                $b = $args[1];
                if (is_string($a) && $this->table) {
                    $a = $this->table->$a;
                }
                $this->insert[] = new Assignment($a, $b);
                $this->changed();
                break;
            default:
                throw new JunxaInvalidQueryException(
                    'too many arguments ('
                    . count($args)
                    . ')'
                );
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
        $this->changed();
        return $this;
    }

    public function replace()
    {
        $args = func_get_args();
        switch (count($args)) {
            case 0:
                return new Part($this, 'replace');
            case 1:
                $what = $args[0];
                if (!($what instanceof Assignment)) {
                    throw new JunxaInvalidQueryException(
                        'single argument to replace() must be a column '
                        . 'assignment'
                    );
                }
                $this->replace[] = $what;
                $this->changed();
                break;
            case 2:
                $a = $args[0];
                $b = $args[1];
                if (is_string($a) && $this->table) {
                    $a = $this->table->$a;
                }
                $this->replace[] = new Assignment($a, $b);
                $this->changed();
                break;
            default:
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
        $this->changed();
        return $this;
    }

    public function delete($what = null)
    {
        if ($what === null && $this->table) {
            $what = $this->table;
        }
        $this->delete[] = $what;
        $this->changed();
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
        $this->changed();
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
        if (is_array($what)) {
            foreach ($what as $value) {
                $this->join($value);
            }
        } else {
            if (!$this->join && $this->table) {
                $this->join[] = $this->table;
            }
            if (is_string($what) && $this->database) {
                $what = Q::innerJoin($this->database->$what);
            }
            $this->join[] = $what;
            $this->changed();
        }
        return $this;
    }

    public function from($what)
    {
        if (is_array($what)) {
            foreach ($what as $value) {
                $this->join($value);
            }
        } else {
            if (is_string($what) && $this->database) {
                $what = Q::innerJoin($this->database->$what);
            }
            $this->join[] = $what;
            $this->changed();
        }
        return $this;
    }

    public function on()
    {
        $args = func_get_args();
        switch (count($args)) {
            case 0:
                return new Part($this, 'on');
            case 1:
                $what = $args[0];
                $this->join[] = Q::joinOn($what);
                $this->changed();
                break;
            case 2:
                $a = $args[0];
                $b = $args[1];
                if (is_string($a) && $this->table) {
                    $a = $this->table->$a;
                }
                $this->join[] = Q::joinOn(Q::eq($a, $b));
                $this->changed();
                break;
            default:
                throw new JunxaInvalidQueryException('too many arguments (' . count($args) . ')');
        }
        return $this;
    }

    public function using($what)
    {
        if (is_array($what) && $this->table) {
            $conv = [];
            foreach ($what as $item) {
                $conv[] = is_string($item) ? $this->table->$item : $item;
            }
            $what = $conv;
        } elseif (is_string($what) && $this->table) {
            $what = $this->table->$what;
        }
        $this->join[] = Q::joinUsing($what);
        $this->changed();
        return $this;
    }

    public function where()
    {
        $args = func_get_args();
        switch (count($args)) {
            case 0:
                return new Part($this, 'where');
            case 1:
                $arg = $args[0];
                if (is_array($arg)) {
                    foreach ($arg as $key => $value) {
                        $this->where($key, $value);
                    }
                } else {
                    if (is_string($arg) && $this->table) {
                        $arg = $this->table->$arg;
                    }
                    $this->where[] = $arg;
                    $this->changed();
                }
                break;
            case 2:
                $a = $args[0];
                $b = $args[1];
                if (is_array($a)) {
                    throw new JunxaInvalidQueryException('cannot use array with second argument');
                }
                if (is_string($a) && $this->table) {
                    $a = $this->table->$a;
                }
                $this->where[] = Q::eq($a, $b);
                $this->changed();
                break;
            default:
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
        $this->changed();
        return $this;
    }

    public function having()
    {
        $args = func_get_args();
        switch (count($args)) {
            case 0:
                return new Part($this, 'having');
            case 1:
                $arg = $args[0];
                if (is_array($arg)) {
                    foreach ($arg as $key => $value) {
                        $this->having($key, $value);
                    }
                } else {
                    if (is_string($arg) && $this->table) {
                        $arg = $this->table->$arg;
                    }
                    $this->having[] = $arg;
                    $this->changed();
                }
                break;
            case 2:
                $a = $args[0];
                $b = $args[1];
                if (is_array($a)) {
                    throw new JunxaInvalidQueryException('cannot use array with second argument');
                }
                if (is_string($a) && $this->table) {
                    $a = $this->table->$a;
                }
                $this->having[] = Q::eq($a, $b);
                $this->changed();
                break;
            default:
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
        $this->changed();
        return $this;
    }

    public function shiftHavingToWhere()
    {
        if ($this->having) {
            $this->where($this->having);
            $this->having = [];
            $this->changed();
        }
        return $this;
    }

    public function order()
    {
        $args = func_get_args();
        switch (count($args)) {
            case 0:
                return new Part($this, 'order');
            case 1:
                $what = $args[0];
                if (is_array($what)) {
                    foreach ($what as $item) {
                        $this->order($item);
                    }
                } else {
                    if (is_string($what) && $this->table) {
                        $what = $this->table->$what;
                    }
                    $this->order[] = $what;
                    $this->changed();
                }
                break;
            default:
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
        $this->changed();
        return $this;
    }

    /**
     * Converts the most recently added order item to descending sort.
     *
     * @return $this
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if there
     * are no order items
     */
    public function desc()
    {
        $ix = count($this->order) - 1;
        if ($ix < 0) {
            throw new JunxaInvalidQueryException('no order items');
        }
        $this->order[$ix] = Q::desc($this->order[$ix]);
        $this->changed();
        return $this;
    }

    public function group()
    {
        $args = func_get_args();
        switch (count($args)) {
            case 0:
                return new Part($this, 'group');
            case 1:
                $what = $args[0];
                if (is_array($what)) {
                    foreach ($what as $item) {
                        $this->group($item);
                    }
                } else {
                    if (is_string($what) && $this->table) {
                        $what = $this->table->$what;
                    }
                    $this->group[] = $what;
                    $this->changed();
                }
                break;
            default:
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
        $this->changed();
        return $this;
    }

    public function limit($a, $b = null)
    {
        if ($b === null) {
            $this->limit = intval($a);
        } else {
            $this->limit = intval($a) . ', ' . intval($b);
        }
        $this->changed();
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
        $this->changed();
        return $this;
    }

    public function performTableScan()
    {
        $tables = [];
        $null = [];
        $type = $this->type;
        if ($type === null) {
            throw new JunxaInvalidQueryException('query has not been validated');
        }
        $main = $this->$type;
        if (is_array($main)) {
            for ($i = 0; $i < count($main); $i++) {
                if (is_object($main[$i]) && method_exists($main[$i], 'tableScan')) {
                    $main[$i]->tableScan($tables, $null);
                }
            }
        } else {
            $main->tableScan($tables, $null);
        }
        foreach (['join', 'where', 'group', 'having', 'order'] as $item) {
            $value = $this->$item;
            if (!empty($value)) {
                if (is_array($value)) {
                    for ($i = 0; $i < count($value); $i++) {
                        if (is_object($value[$i]) && method_exists($value[$i], 'tableScan')) {
                            $value[$i]->tableScan($tables, $null);
                        }
                    }
                } else {
                    $value->tableScan($tables, $null);
                }
            }
        }
        $this->tables = array_keys($tables);
        $this->nullTables = $null;
        if (count($this->tables) > 1) {
            $this->isMultitable = true;
        }
    }

    public function express()
    {
        if ($this->outputCache !== null) {
            return $this->outputCache;
        }
        $this->validate();
        $type = $this->type;
        $main = $this->$type;
        $this->performTableScan();
        $out = strtoupper($type) . ' ';
        switch ($type) {
            case 'select':
                if ($this->getOption(self::OPTION_DISTINCT)) {
                    $out .= 'DISTINCT ';
                }
                $out .= $this->database->resolve($main, $this, $type, null, $this);
                if ($join = $this->join) {
                    $out .= "\n\tFROM " . $this->database->resolve($join, $this, 'join', null, $this);
                } elseif (count($this->tables)) {
                    $out .= "\n\tFROM `" . join('`, `', $this->tables) . '`';
                }
                break;
            case 'replace':
            case 'insert':
                if (count($this->tables) !== 1) {
                    throw new JunxaInvalidQueryException(
                        $type
                        . ' query requires exactly one table'
                    );
                }
                if ($this->options) {
                    if ($this->getOption(self::OPTION_HIGH_PRIORITY)) {
                        $out .= 'HIGH_PRIORITY ';
                    } elseif ($this->getOption(self::OPTION_LOW_PRIORITY)) {
                        $out .= 'LOW_PRIORITY ';
                    } elseif ($this->getOption(self::OPTION_DELAYED)) {
                        $out .= 'DELAYED ';
                    }
                    if ($this->getoption(self::OPTION_IGNORE)) {
                        $out .= 'IGNORE ';
                    }
                }
                $fields = [];
                $values = [];
                $out .= "\n\t" . $this->tables[0] . ' ';
                foreach ($main as $item) {
                    if (!($item instanceof Assignment)) {
                        throw new JunxaInvalidQueryException(
                            $type
                            . ' list elements must be column assignments'
                        );
                    }
                    $fields[] = $this->database->resolve($item->getColumn(), $this, $type, null, $this);
                    $values[] = $this->database->resolve($item->getValue(), $this, $type, $item->getColumn(), $this);
                }
                $out .= ' (' . join(', ', $fields) . ")\n\tVALUES\n\t(" . join(', ', $values) . ')';
                if ($this->update) {
                    $out .= "\n\tON DUPLICATE KEY UPDATE ";
                    $elem = [];
                    foreach ($this->update as $item) {
                        if (!($item instanceof Assignment)) {
                            throw new JunxaInvalidQueryException(
                                'ON DUPLICATE KEY UPDATE list elements must '
                                . ' be column assignments'
                            );
                        }
                        $elem[] =
                            $this->database->resolve($item->getColumn(), $this, $type, null, $this)
                            . ' = '
                            . $this->database->resolve($item->getValue(), $this, $type, $item->getColumn(), $this);
                    }
                    $out .= join(', ', $elem);
                }
                break;
            case 'update':
                if (count($this->tables) !== 1) {
                    throw new JunxaInvalidQueryException(
                        $type
                        . ' query requires exactly one table'
                    );
                }
                $out .= $this->tables[0] . "\n\tSET ";
                $elem = [];
                foreach ($main as $item) {
                    if (!($item instanceof Assignment)) {
                        throw new JunxaInvalidQueryException(
                            $type
                            . ' list elements must be column assignments'
                        );
                    }
                    $column = $item->getColumn();
                    $value = $item->getValue();
                    $elem[] =
                        $this->database->resolve($column, $this, $type, null, $this)
                        . ' = '
                        . $this->database->resolve($value, $this, $type, $column, $this);
                }
                $out .= join(', ', $elem);
                break;
            case 'delete':
                if (count($this->tables) !== 1) {
                    throw new JunxaInvalidQueryException(
                        $type
                        . ' query requires exactly one table'
                    );
                }
                $out .= 'FROM ' . $this->tables[0];
                break;
        }
        if ($this->where) {
            $out .=
                "\n\tWHERE "
                . $this->database->resolve(
                    is_array($this->where)
                    ? (
                        count($this->where) > 1
                        ? Q::andClause($this->where)
                        : $this->where[0]
                    )
                    : $this->where,
                    $this,
                    'where',
                    null,
                    $this
                );
        }
        if ($this->group) {
            $out .=
                "\n\tGROUP BY "
                . $this->database->resolve($this->group, $this, 'group', null, $this);
        }
        if ($this->having) {
            $out .=
                "\n\tHAVING "
                . $this->database->resolve(
                    is_array($this->having)
                    ? (
                        count($this->having) > 1
                        ? Q::andClause($this->having)
                        : $this->having[0]
                    )
                    : $this->having,
                    $this,
                    'having',
                    null,
                    $this
                );
        }
        if ($this->order) {
            $out .=
                "\n\tORDER BY "
                . $this->database->resolve($this->order, $this, 'order', null, $this);
        }
        if (isset($this->limit)) {
            $out .= "\n\tLIMIT " . $this->limit;
        }
        $this->outputCache = $out;
        return $out;
    }

    public function execute()
    {
        if (!$this->database) {
            throw new JunxaInvalidQueryException(
                'cannot call execute() on a query that was not generated '
                . 'from a database'
            );
        }
        $this->validate();
        return $this->database->query($this);
    }

    public function rows()
    {
        if (!$this->table) {
            throw new JunxaInvalidQueryException(
                'cannot call rows() on a query that was not generated from '
                . 'a table'
            );
        }
        return $this->table->rows($this);
    }

    public function row()
    {
        if (!$this->table) {
            throw new JunxaInvalidQueryException(
                'cannot call row() on a query that was not generated from a '
                . 'table'
            );
        }
        return $this->table->row($this);
    }

    public function count()
    {
        if (!$this->table) {
            throw new JunxaInvalidQueryException(
                'cannot call count() on a query that was not generated from '
                . 'a table'
            );
        }
        return $this->table->rowCount($this);
    }

    /**
     * Retrieves whether this query spans multiple tables.
     *
     * @return bool
     */
    public function isMultitable()
    {
        return $this->isMultitable;
    }

    /**
     * Retrieves whether a table's contents are potentially null in a query
     * because of its join position.
     *
     * @param string table name
     * @return bool
     */
    public function isNullTable($name)
    {
        return array_key_exists($name, $this->nullTables);
    }

    /**
     * Retrieves the first clause out of the provided list of clauses that
     * has a definition on this query, if any.
     *
     * @param array<string> list of clauses
     * @return string|null
     */
    public function checkClauses($clauses)
    {
        foreach ($clauses as $clause) {
            if (isset($this->$clause) && $this->$clause) {
                return $clause;
            }
        }
        return null;
    }

    /**
     * @param int Thaumatic\Junxa\Query\Builder::OPTION_* bitmask for the column
     * @return $this
     */
    public function setOptions($val)
    {
        $this->options = $val;
        return $this;
    }

    /**
     * @return int Thaumatic\Junxa\Query\Builder::OPTION_* bitmask for the column
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Enables or disables a query option.
     *
     * @param int Thaumatic\Junxa\Query\Builder::OPTION_*
     * @param bool whether we want the option on or off
     * @return $this
     */
    public function setOption($option, $flag)
    {
        if ($flag) {
            $this->options |= $option;
        } else {
            $this->options &= ~$option;
        }
        return $this;
    }

    /**
     * Retrieves whether a given query option is enabled.  If a bitmask of
     * multiple options is given, returns whether any of them are enabled.
     *
     * @param int Thaumatic\Junxa\Query\Builder::OPTION_*
     * @return bool
     */
    public function getOption($option)
    {
        return (bool) ($this->options & $option);
    }

    /**
     * Retrieves whether every option in a given bitmask of options is enabled.
     *
     * @param int Thaumatic\Junxa\Query\Builder::OPTION_*
     * @return bool
     */
    public function getEachOption($options)
    {
        return ($this->options & $options) === $options;
    }

    /**
     * @param callable what to call with the final SQL text as argument
     * @return $this
     */
    public function setCallOnSql(callable $call)
    {
        $this->callOnSql = $call;
        return $this;
    }

    /**
     * @return callable what to call with the final SQL text as argument
     */
    public function getCallOnSql()
    {
        return $this->callOnSql;
    }

    /**
     * Performs any processing needed on the final SQL text for the query.
     *
     * @return $this
     */
    public function processSql($sql)
    {
        if ($this->callOnSql) {
            call_user_func($this->callOnSql, $sql);
        }
        return $this;
    }

}
