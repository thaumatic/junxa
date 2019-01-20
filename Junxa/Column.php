<?php

namespace Thaumatic\Junxa;

use Thaumatic\Junxa\Column;
use Thaumatic\Junxa\Exceptions\JunxaConfigurationException;
use Thaumatic\Junxa\Exceptions\JunxaDatabaseModelingException;
use Thaumatic\Junxa\Query\Builder as QueryBuilder;
use Thaumatic\Junxa\Query\Element;

/**
 * Models a database column.
 */
class Column
{

    /**
     * @const int column option: when performing a merge (insert on duplicate
     * key update) query, exclude this column from being updated (but not from
     * being inserted)
     */
    const OPTION_MERGE_NO_UPDATE            = 0x00000001;

    /**
     * @const int column option: never attempt to automatically determine
     * foreign key relationships for this column
     */
    const OPTION_NO_AUTO_FOREIGN_KEY        = 0x00000002;

    /**
     * @const int MySQL flag: column is NOT NULL
     */
    const MYSQL_FLAG_NOT_NULL               = 0x00000001;

    /**
     * @const int MySQL flag: column is part of a primary key
     */
    const MYSQL_FLAG_PRI_KEY                = 0x00000002;

    /**
     * @const int MySQL flag: column forms the entirey of a non-primary
     * unique key
     */
    const MYSQL_FLAG_UNIQUE_KEY             = 0x00000004;

    /**
     * @const int MySQL flag: column is the first element of a non-primary
     * key and MYSQL_FLAG_UNIQUE_KEY does not apply
     */
    const MYSQL_FLAG_MULTIPLE_KEY           = 0x00000008;

    /**
     * @const int MySQL flag: column is blob-like (any kind of BLOB or TEXT)
     */
    const MYSQL_FLAG_BLOB                   = 0x00000010;

    /**
     * @const int MySQL flag: column is UNSIGNED
     */
    const MYSQL_FLAG_UNSIGNED               = 0x00000020;

    /**
     * @const int MySQL flag: column is ZEROFILL
     */
    const MYSQL_FLAG_ZEROFILL               = 0x00000040;

    /**
     * @const int MySQL flag: column is marked binary
     */
    const MYSQL_FLAG_BINARY                 = 0x00000080;

    /**
     * @const int MySQL flag: column is an ENUM
     */
    const MYSQL_FLAG_ENUM                   = 0x00000100;

    /**
     * @const int MySQL flag: column is AUTO_INCREMENT
     */
    const MYSQL_FLAG_AUTO_INCREMENT         = 0x00000200;

    /**
     * @const int MySQL flag: column is a TIMESTAMP
     */
    const MYSQL_FLAG_TIMESTAMP              = 0x00000400;

    /**
     * @const int MySQL flag: column is a SET
     */
    const MYSQL_FLAG_SET                    = 0x00000800;

    /**
     * @const int MySQL flag: column has no default value (including one
     * provided by AUTO_INCREMENT or TIMESTAMP behavior)
     */
    const MYSQL_FLAG_NO_DEFAULT_VALUE       = 0x00001000;

    /**
     * @const int MySQL flag: column is set to NOW() on update (i.e.
     * TIMESTAMP behavior)
     */
    const MYSQL_FLAG_ON_UPDATE_NOW          = 0x00002000;

    /**
     * @const int MySQL flag: column is part of any key
     */
    const MYSQL_FLAG_PART_KEY               = 0x00004000;

    /**
     * @const int MySQL flag: column is some variety of integer
     */
    const MYSQL_FLAG_NUM                    = 0x00008000;

    /**
     * @const int MySQL flag: sql_yacc flag of unclear significance
     */
    const MYSQL_FLAG_UNIQUE                 = 0x00010000;

    /**
     * @const int MySQL flag: sql_yacc flag of unclear significance
     */
    const MYSQL_FLAG_BINCMP                 = 0x00020000;

    /**
     * @const int MySQL flag: MySQL internal use flag of unclear significance
     */
    const MYSQL_FLAG_GET_FIXED_FIELDS       = 0x00040000;

    /**
     * @const int MySQL flag: column appears in the partitioning function of
     * its table
     */
    const MYSQL_FLAG_FIELD_IN_PART_FUNC     = 0x00080000;

    /**
     * @const array<numeric:string> lookup table of the names of the
     * self::MYSQL_FLAG_* values
     */
    const MYSQL_FLAG_NAMES                  = [
        self::MYSQL_FLAG_NOT_NULL           => 'NOT_NULL',
        self::MYSQL_FLAG_PRI_KEY            => 'PRI_KEY',
        self::MYSQL_FLAG_UNIQUE_KEY         => 'UNIQUE_KEY',
        self::MYSQL_FLAG_MULTIPLE_KEY       => 'MULTIPLE_KEY',
        self::MYSQL_FLAG_BLOB               => 'BLOB',
        self::MYSQL_FLAG_UNSIGNED           => 'UNSIGNED',
        self::MYSQL_FLAG_ZEROFILL           => 'ZEROFILL',
        self::MYSQL_FLAG_BINARY             => 'BINARY',
        self::MYSQL_FLAG_ENUM               => 'ENUM',
        self::MYSQL_FLAG_AUTO_INCREMENT     => 'AUTO_INCREMENT',
        self::MYSQL_FLAG_TIMESTAMP          => 'TIMESTAMP',
        self::MYSQL_FLAG_SET                => 'SET',
        self::MYSQL_FLAG_NO_DEFAULT_VALUE   => 'NO_DEFAULT_VALUE',
        self::MYSQL_FLAG_ON_UPDATE_NOW      => 'ON_UPDATE_NOW',
        self::MYSQL_FLAG_PART_KEY           => 'PART_KEY',
        self::MYSQL_FLAG_NUM                => 'NUM',
        self::MYSQL_FLAG_UNIQUE             => 'UNIQUE',
        self::MYSQL_FLAG_BINCMP             => 'BINCMP',
        self::MYSQL_FLAG_GET_FIXED_FIELDS   => 'GET_FIXED_FIELDS',
        self::MYSQL_FLAG_FIELD_IN_PART_FUNC => 'FIELD_IN_PART_FUNC',
    ];

    /**
     * @var mixed the raw version of the column's default, as provided by a
     * SHOW COLUMNS query.
     */
    private $default;

    /**
     * @var mixed the imported native version of the column's default
     */
    private $defaultValue;

    /**
     * @var Thaumatic\Junxa\Query\Element the SQL alias used to construct this
     * column if it's virtual
     */
    private $dynamicAlias;

    /**
     * @var Thaumatic\Junxa\Query\Element a default to impose on the column at
     * the application level
     */
    private $dynamicDefault;

    /**
     * @var int self::MYSQL_FLAG_* values, bitmasked
     */
    private $flags;

    /**
     * @var string the "full" type information for the column as it appears in
     * a SHOW COLUMNS query.
     */
    private $fullType;

    /**
     * @var int the length specification for this column, if any
     */
    private $length;

    /**
     * @var string the name of this column
     */
    private $name;

    /**
     * @var string the title of this column; a display-oriented version of
     * the name, usually automatically generated
     */
    private $title;

    /**
     * @var int self::OPTION_* values, bitmasked, defining this columns'
     * behavior
     */
    private $options = 0;

    /**
     * @var int the precision specification for this column, if any
     */
    private $precision;

    /**
     * @var Thaumatic\Junxa\Table the table this column is part of
     */
    private $table;

    /**
     * @var string the basic type information for the column, as derived from
     * {@see $fulltype}
     */
    private $type;

    /**
     * @var string the general class of types this columnn belongs to (int,
     * float, datetime, date, time, array, or text).
     */
    private $typeClass;

    /**
     * @var array<string> the possible values for this column, if a set or enum
     */
    private $values;

    /**
     * @var bool whether this column's status as a foreign key is known
     */
    private $foreignKeyKnown;

    /**
     * @var Thaumatic\Junxa\Column the column this column is a foreign key to,
     * if any
     */
    private $foreignKey;

    /**
     * @var string the name of the table the column links to as a foreign key
     */
    private $foreignKeyTableName;

    /**
     * @var string the name of the column the column links to as a foreign key
     */
    private $foreignKeyColumnName;

    final public function __construct($table, $name, $info, $colinfo, $dynamicAlias)
    {
        $this->table = $table;
        $this->name = $name;
        $this->dynamicAlias = $dynamicAlias;
        $this->flags = $info->flags;
        $this->default = $colinfo->Default;
        $this->fullType = $colinfo->Type;
        if ($colinfo->Null === 'YES') {
            if ($this->getFlag(self::MYSQL_FLAG_NOT_NULL)) {
                throw new JunxaDatabaseModelingException('nullability mismatch');
            }
        } else {
            if (!$this->getFlag(self::MYSQL_FLAG_NOT_NULL)) {
                throw new JunxaDatabaseModelingException('nullability mismatch');
            }
        }
        if (preg_match('/^([^\s\(]+)/', $this->fullType, $match)) {
            $this->type = $match[1];
        } else {
            $this->type = $this->fullType;
        }
        if ($this->getFlag(self::MYSQL_FLAG_ENUM) || $this->getFlag(self::MYSQL_FLAG_SET)) {
            if (!preg_match('/\(.*\)$/', $this->fullType, $match)) {
                throw new JunxaDatabaseModelingException('unparseable enum/set');
            }
            $list = substr($match[0], 2, strlen($match[0]) - 4);
            $this->values = preg_split("/','/", $list);
            foreach ($this->values as &$value) {
                $value = str_replace("''", "'", $value);
            }
            if (!$this->getFlag(self::MYSQL_FLAG_NOT_NULL) && $this->getFlag(self::MYSQL_FLAG_ENUM)) {
                array_unshift($this->values, null);
            }
        } else {
            if (preg_match('/\((\d+),(\d+)\)/', $this->fullType, $match)) {
                $this->length = intval($match[1]);
                $this->precision = intval($match[2]);
            } elseif (preg_match('/\((\d+)\)/', $this->fullType, $match)) {
                $this->length = intval($match[1]);
            }
        }
        switch ($this->type) {
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'bigint':
            case 'year':
                $this->typeClass = 'int';
                break;
            case 'float':
            case 'double':
            case 'numeric':
            case 'decimal':
                $this->typeClass = 'float';
                break;
            case 'datetime':
            case 'timestamp':
                $this->typeClass = 'datetime';
                break;
            case 'date':
                $this->typeClass = 'date';
                break;
            case 'time':
                $this->typeClass = 'time';
                break;
            case 'set':
                $this->typeClass = 'array';
                break;
            default:
                $this->typeClass = 'text';
                break;
        }
        if ($this->default !== null) {
            $this->defaultValue = $this->import($this->default);
        }
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
     * @return Thaumatic\Junxa the database model this column is part of
     */
    final public function getDatabase()
    {
        return $this->table->getDatabase();
    }

    /**
     * Retrieves the model of the table this column is part of.
     *
     * @return Thaumatic\Junxa\Table actual class will be as defined by
     * Junxa::getTableClass()
     */
    final public function getTable()
    {
        return $this->table;
    }

    /**
     * @return string the column's name
     */
    final public function getName()
    {
        return $this->name;
    }

    /**
     * @param string the title to use for the column
     * @return $this
     */
    final public function setTitle($val)
    {
        $this->title = $val;
        return $this;
    }

    /**
     * @return string the title to use for the column
     */
    final public function getTitle()
    {
        if (!$this->title) {
            $this->title = self::columnNameToTitle($this->name);
        }
        return $this->title;
    }

    /**
     * Retrieves whether this is a dynamic column (no corresponding database
     * column, constructed at row retrieval with SQL).
     *
     * @return bool
     */
    final public function isDynamic()
    {
        return $this->dynamicAlias ? true : false;
    }

    /**
     * Retrieves the dynamic alias model for this column (a query element
     * defining the column's name and how it's constructed), if any.
     *
     * @return Thaumatic\Junxa\Query\Element|null
     */
    final public function getDynamicAlias()
    {
        return $this->dynamicAlias;
    }

    /**
     * Sets a default to impose on the column at the application level.
     * Example:
     *
     * use Thaumatic\Junxa\Query as Q;
     *
     * $db = new Junxa...;
     * // set the createdAt column to default to the SQL function NOW()
     * $db->table_name->createdAt->setDynamicDefault(Q::func('NOW'));
     *
     * @param Thaumatic\Junxa\Query\Element expression for default
     * @return $this
     */
    final public function setDynamicDefault(Element $default)
    {
        $this->dynamicDefault = $default;
        $this->table->setDynamicDefaultsPresent(true);
        return $this;
    }

    /**
     * Retrieves the default imposed on the column at the application level,
     * if any.
     *
     * @return Thaumatic\Junxa\Query\Element expression for default
     */
    final public function getDynamicDefault()
    {
        return $this->dynamicDefault;
    }

    /**
     * @return int the column's Column::MYSQL_FLAG_* bitmask
     */
    final public function getFlags()
    {
        return $this->flags;
    }

    /**
     * return array<string> the names of the flags set on the column
     */
    final public function getFlagNames()
    {
        $out = [];
        foreach (self::MYSQL_FLAG_NAMES as $bit => $name) {
            if ($this->flags & $bit) {
                $out[] = $name;
            }
        }
        return $out;
    }

    /**
     * Retrieves whether a specified Column::MYSQL_FLAG_* is enabled on
     * the column.  If a bitmask of multiple flags is sent, returns whether
     * any of them are enabled.
     *
     * @param int Thaumatic\Junxa\Column::MYSQL_FLAG_*
     * @return bool
     */
    final public function getFlag($flag)
    {
        return (bool) ($this->flags & $flag);
    }

    /**
     * Retrieves whether every flag in a specified bitmask of
     * Column::MYSQL_FLAG_* values is enabled on the column.
     *
     * @param int Thaumatic\Junxa\Column::MYSQL_FLAG_*
     * @return bool
     */
    final public function getEachFlag($flags)
    {
        return ($this->flags & $flags) === $flags;
    }

    /**
     * @return int|null the column's length specification, if any
     */
    final public function getLength()
    {
        return $this->length;
    }

    /**
     * @return int|null the column's precision specification, if any
     */
    final public function getPrecision()
    {
        return $this->precision;
    }

    /**
     * @return string the column's type class
     */
    final public function getTypeClass()
    {
        return $this->typeClass;
    }

    /**
     * @return string the column's type
     */
    final public function getType()
    {
        return $this->type;
    }

    /**
     * @return string the column's full type specification
     */
    final public function getFullType()
    {
        return $this->fullType;
    }

    /**
     * @return string|null the specification of the column's default value
     */
    final public function getDefault()
    {
        return $this->default;
    }

    /**
     * @return bool whether the column has a default value
     */
    final public function hasDefault()
    {
        return $this->default !== null;
    }

    /**
     * @return mixed the PHP native version of the column's default value
     */
    final public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    /**
     * @return array<string>|null the values this column can have, if an enum
     * or set
     */
    final public function getValues()
    {
        return $this->values;
    }

    /**
     * Evaluates whether it is possible for the column to have a null
     * value in a particular query context.
     *
     * @param Thaumatic\Junxa\Query\Builder query builder
     * @param string the clause of the query that is being rendered
     * @return bool
     */
    final public function contextNull(QueryBuilder $query = null, $context = null)
    {
        if (!$this->getFlag(self::MYSQL_FLAG_NOT_NULL)) {
            return true;
        }
        if ($context !== 'join' && $query && $query->isNullTable($this->table->getName())) {
            return true;
        }
        return false;
    }

    /**
     * Generates the appropriate database representation to use for a
     * specified PHP value in context of this column.
     *
     * @param mixed the PHP value to represent
     * @param Thaumatic\Junxa\Query\Build a query builder representing a query
     * we are rendering, if any
     * @param string the clause of the query that is being rendered
     * @return mixed
     */
    final public function represent(
        $value,
        QueryBuilder $query = null,
        $context = 'select'
    ) {
        if ($value === null && $this->contextNull($query, $context)) {
            return 'NULL';
        }
        switch ($this->typeClass) {
            case 'text':
            case 'date':
            case 'datetime':
            case 'time':
                return "'" . $this->getDatabase()->escapeString($value) . "'";
            case 'array':
                return (
                    "'"
                    . join(
                        ',',
                        array_map(
                            $value,
                            [$this->getDatabase(), 'escapeString']
                        )
                    )
                    . "'"
                );
            case 'int':
                switch ($this->type) {
                    case 'bigint':
                        return preg_replace('/[^0-9-]+/', '', $value);
                    case 'int':
                        if ($this->getFlag(self::MYSQL_FLAG_ZEROFILL)) {
                            return preg_replace('/[^0-9-]+/', '', $value);
                        }
                        if ($this->getFlag(self::MYSQL_FLAG_UNSIGNED)) {
                            return preg_replace('/\D+/', '', $value);
                        }
                        return intval($value);
                    case 'tinyint':
                        if ($this->getFlag(self::MYSQL_FLAG_ZEROFILL)) {
                            return preg_replace('/[^0-9-]+/', '', $value);
                        }
                        return intval($value);
                    default:
                        if ($this->getFlag(self::MYSQL_FLAG_ZEROFILL)) {
                            return preg_replace('/[^0-9-]+/', '', $value);
                        }
                        return intval($value);
                }
                break;
            case 'float':
                switch ($this->type) {
                    case 'float':
                    case 'double':
                        return doubleval($value);
                    default:
                        return $value;
                }
                break;
            default:
                throw new JunxaDatabaseModelingException('unknown type class ' . $this->typeClass);
        }
    }

    /**
     * Generates the appropriate PHP representation to use for a specified
     * value obtained from the database in context of this column.
     *
     * @param mixed the database value to import
     * @return mixed
     */
    final public function import($value)
    {
        if (($value === null) && !$this->getFlag(self::MYSQL_FLAG_NOT_NULL)) {
            return null;
        }
        switch ($this->typeClass) {
            case 'int':
                switch ($this->type) {
                    case 'bigint':
                        if ($this->getFlag(self::MYSQL_FLAG_UNSIGNED)) {
                            // bizarre that this works but it does
                            if (PHP_INT_MAX < 18446744073709551615) {
                                break;
                            }
                        } else {
                            if (PHP_INT_MAX < 9223372036854775807) {
                                break;
                            }
                        }
                        return intval($value);
                    case 'int':
                        if ($this->getFlag(self::MYSQL_FLAG_ZEROFILL)) {
                            break;
                        }
                        if ($this->getFlag(self::MYSQL_FLAG_UNSIGNED)) {
                            break;
                        }
                        return intval($value);
                    case 'tinyint':
                        if ($this->getFlag(self::MYSQL_FLAG_ZEROFILL)) {
                            break;
                        }
                        if ($this->length === 1 && !$this->getFlag(self::MYSQL_FLAG_UNSIGNED)) {
                            // interpret as bool
                            if ($value === '1') {
                                return true;
                            } elseif ($value === '0') {
                            }
                        } false;
                        return intval($value);
                    default:
                        if ($this->getFlag(self::MYSQL_FLAG_ZEROFILL)) {
                            break;
                        }
                        return intval($value);
                }
                break;
            case 'array':
                return explode(',', $value);
        }
        return $value;
    }

    /**
     * @return string a summary of the column's type information
     */
    final public function getTypeSummary()
    {
        $out = $this->type;
        if (isset($this->length)) {
            $out .= ', length ' . $this->length;
        }
        if ($this->flags) {
            $out .= ', flags ' . $this->flags;
        }
        if (isset($this->values)) {
            $out .= ', values ';
            foreach ($this->values as $value) {
                $out .= ' ' . $this->represent($value);
            }
        }
        if (isset($this->default)) {
            $out .= ', default ' . $this->represent($this->default);
        }
        return $out;
    }

    /**
     * Implements its part of a table scan, tracking the column's
     * parent table as part of a query.
     *
     * @param array<string:bool> assoc of names of tables present in query
     * @param array<string:bool> assoc of names of tables that can be all
     * null (as with an inner/outer join)
     * @return $this
     */
    final public function tableScan(&$tables, &$null)
    {
        $tables[$this->table->getName()] = true;
        return $this;
    }

    /**
     * Retrieves the correct SQL expression to use to refer to this column
     * at the database level.
     *
     * @param Thaumatic\Junxa\Query\Builder the query being rendered
     * @param string the clause of the query that is being rendered
     * @param Thaumatic\Junxa\Column the column we are rendering in context of
     * @param mixed the parent model of our current query
     */
    final public function express(
        QueryBuilder $query,
        $context,
        Column $column = null,
        $parent = null
    ) {
        if ($this->dynamicAlias) {
            return $this->dynamicAlias->express($query, $context, $column, $parent);
        } elseif ($query->isMultitable()) {
            return '`' . $this->table->getName() . '`.`' . $this->getName() . '`';
        } else {
            return '`' . $this->getName() . '`';
        }
    }

    /**
     * Provides a serialized representation of the column, allowing it to be
     * restored (from within its full database and table model context) later.
     *
     * @return string
     */
    final public function serialize()
    {
        $table = $this->getTable();
        return 'column:' . $table->getName() . "\0" . $this->getName();
    }

    /**
     * Sets whether the treat this column as demand-only, i.e. not retrieved
     * by default when a row is retrieved, only when specifically requested.
     *
     * @param bool whether to treat the column as demand-only
     * @return $this
     */
    final public function setDemandOnly($flag)
    {
        $this->getTable()->setColumnDemandOnly($this->getName(), $flag);
        return $this;
    }

    /**
     * Retrieves whether this column should be considered demand-only, i.e.
     * not retrieved by default when a row is retrieved, only when
     * specifically requested.
     *
     * @return bool
     */
    final public function queryDemandOnly()
    {
        return $this->getTable()->queryColumnDemandOnly($this->getName());
    }

    /**
     * @param int Thaumatic\Junxa\Column::OPTION_* bitmask for the column
     * @return $this
     */
    final public function setOptions($val)
    {
        $this->options = $val;
        return $this;
    }

    /**
     * @return int Thaumatic\Junxa\Column::OPTION_* bitmask for the column
     */
    final public function getOptions()
    {
        return $this->options;
    }

    /**
     * Enables or disables a column option.
     *
     * @param int Thaumatic\Junxa\Column::OPTION_*
     * @param bool whether we want the option on or off
     * @return $this
     */
    final public function setOption($option, $flag)
    {
        if ($flag) {
            $this->options |= $option;
        } else {
            $this->options &= ~$option;
        }
        return $this;
    }

    /**
     * Retrieves whether a given column option is enabled.  If a bitmask of
     * multiple options is given, returns whether any of them are enabled.
     *
     * @param int Thaumatic\Junxa\Column::OPTION_*
     * @return bool
     */
    final public function getOption($option)
    {
        return (bool) ($this->options & $option);
    }

    /**
     * Retrieves whether every option in a given bitmask of options is enabled.
     *
     * @param int Thaumatic\Junxa\Column::OPTION_*
     * @return bool
     */
    final public function getEachOption($options)
    {
        return ($this->options & $options) === $options;
    }

    /**
     * If this column's foreign key configuration is not established, establish it.
     */
    private function determineForeignKey()
    {
        if (!$this->foreignKeyKnown) {
            if ((!$this->foreignKeyTableName || !$this->foreignKeyColumnName)
                && !$this->getOption(self::OPTION_NO_AUTO_FOREIGN_KEY)
            ) {
                $tableName = $this->getDatabase()->getForeignKeySuffixMatch($this->getName());
                if ($tableName !== null) {
                    if (!$this->foreignKeyTableName && $this->getDatabase()->tableExists($tableName)) {
                        $this->foreignKeyTableName = $tableName;
                    }
                    if (!$this->foreignKeyColumnName && $this->foreignKeyTableName) {
                        if ($this->getDatabase()->{$this->foreignKeyTableName}->hasColumn('id')) {
                            $this->foreignKeyColumnName = 'id';
                        }
                    }
                }
            }
            if ($this->foreignKeyTableName && $this->foreignKeyColumnName) {
                $this->foreignKey = $this->getDatabase()
                    ->{$this->foreignKeyTableName}
                    ->{$this->foreignKeyColumnName}
                ;
            }
            $this->foreignKeyKnown = true;
        }
    }

    /**
     * Sets the name of the table this column links to as a foreign key.
     *
     * @param string the table name
     * @return $this
     * @throws Thaumatic\Junxa\Exceptions\JunxaConfigurationException if the
     * parameter is invalid
     */
    final public function setForeignKeyTableName($val)
    {
        if (!is_string($val)) {
            throw new JunxaConfigurationException(
                'expected string table name, got ' . gettype($item)
            );
        }
        $this->foreignKeyTableName = $val;
        $this->foreignKeyKnown = false;
        return $this;
    }

    /**
     * Retrieves the name of the table this column links to as a foreign key,
     * if any.
     *
     * @return string|null
     */
    final public function getForeignKeyTableName()
    {
        $this->determineForeignKey();
        return $this->foreignKeyTableName;
    }

    /**
     * Sets the name of the column this column links to as a foreign key.
     *
     * @param string the column name
     * @return $this
     * @throws Thaumatic\Junxa\Exceptions\JunxaConfigurationException if the
     * parameter is invalid
     */
    final public function setForeignKeyColumnName($val)
    {
        if (!is_string($val)) {
            throw new JunxaConfigurationException(
                'expected string column name, got ' . gettype($item)
            );
        }
        $this->foreignKeyColumnName = $val;
        $this->foreignKeyKnown = false;
        return $this;
    }

    /**
     * Retrieves the name of the column this column links to as a foreign key,
     * if any.
     *
     * @return string|null
     */
    final public function getForeignKeyColumnName()
    {
        $this->determineForeignKey();
        return $this->foreignKeyColumnName;
    }

    /**
     * Retrieves the column this column is a foreign key to, if any.
     *
     * @return Thaumatic\Junxa\Column|null
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchTableException if a table
     * requested does not exist
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException if a
     * column requested does not exist
     */
    final public function getForeignColumn()
    {
        $this->determineForeignKey();
        return $this->foreignKey;
    }

    /**
     * Retrieves whether a given column model specifies the same column as
     * this model.
     *
     * @param Thaumatic\Junxa\Column column model to check
     * @return bool
     */
    final public function isSame(Column $column)
    {
        if ($column === $this) {
            return true;
        }
        if ($column->getName() !== $this->getName()) {
            return false;
        }
        return $this->getTable()->isSame($column->getTable());
    }

    /**
     * Coerce a word into the case pattern appropriate for its usage
     * in a title.
     *
     * @param string the word
     * @param bool whether the word is the first word in the title
     * @param bool whether the word is known to already be in lower case
     * @return string
     */
    private static function titleifyWord($word, $first = false, $alreadyLowerCase = false)
    {
        if (!$alreadyLowerCase) {
            $word = strtolower($word);
        }
        switch ($word) {
            case 'acl':
            case 'ansi':
            case 'atm':
            case 'bgp':
            case 'cli':
            case 'cpu':
            case 'dhcp':
            case 'dns':
            case 'ftp':
            case 'id':
            case 'ieee':
            case 'ietf':
            case 'ip':
            case 'iso':
            case 'lan':
            case 'mac':
            case 'nat':
            case 'osi':
            case 'sdlc':
            case 'ssh':
            case 'smtp':
            case 'tcp':
            case 'tftp':
            case 'udp':
            case 'www':
                return strtoupper($word);
        }
        if ($first) {
            return ucfirst($word);
        }
        switch ($word) {
            case 'a':
            case 'an':
            case 'the':
            case 'at':
            case 'by':
            case 'for':
            case 'in':
            case 'of':
            case 'on':
            case 'to':
            case 'up':
            case 'and':
            case 'as':
            case 'but':
            case 'or':
            case 'nor':
                return $word;
            default:
                return ucfirst($word);
        }
    }

    /**
     * Convert a list of words to a title.
     *
     * @param array<string> the words
     * @return string
     */
    private static function wordsToTitle(array $words)
    {
        $useWords = [self::titleifyWord(array_shift($words), true)];
        foreach ($words as $word) {
            $useWords[] = self::titleifyWord($word);
        }
        return implode(' ', $useWords);
    }

    /**
     * Derive a column title from a column name.
     *
     * @param string the column name
     * @return string
     */
    private static function columnNameToTitle($name)
    {
        if (strpos($name, '_') !== false) {
            $words = explode('_', $name);
            return self::wordsToTitle($words);
        } elseif (ctype_upper($name)) {
            return self::titleifyWord($name, true);
        } elseif (!ctype_lower($name)) {
            $words = preg_split('/(?=[A-Z])(?<![A-Z])(?<!^)/', $name);
            return self::wordsToTitle($words);
        } else {
            return self::titleifyWord($name, false, true);
        }
    }

}
