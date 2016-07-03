<?php

namespace Thaumatic\Junxa;

use Thaumatic\Junxa\Exceptions\JunxaDatabaseModelingException;

/**
 * Models a database column.
 */
class Column
{

    const OPTION_MERGE_NO_UPDATE    = 0x00000001;

    const MYSQL_FLAG_NOT_NULL       = 0x00000001;
    const MYSQL_FLAG_PRI_KEY        = 0x00000002;
    const MYSQL_FLAG_UNIQUE_KEY     = 0x00000004;
    const MYSQL_FLAG_BLOB           = 0x00000010;
    const MYSQL_FLAG_UNSIGNED       = 0x00000020;
    const MYSQL_FLAG_ZEROFILL       = 0x00000040;
    const MYSQL_FLAG_BINARY         = 0x00000080;
    const MYSQL_FLAG_ENUM           = 0x00000100;
    const MYSQL_FLAG_AUTO_INCREMENT = 0x00000200;
    const MYSQL_FLAG_TIMESTAMP      = 0x00000400;
    const MYSQL_FLAG_SET            = 0x00000800;
    const MYSQL_FLAG_PART_KEY       = 0x00004000;
    const MYSQL_FLAG_NUM            = 0x00008000;
    const MYSQL_FLAG_UNIQUE         = 0x00010000;

    private $default;
    private $dynalias;
    private $flags;
    private $fullType;
    private $length;
    private $name;
    private $options = 0;
    private $precision;
    private $table;
    private $type;
    private $typeClass;
    private $values;

    public function __construct($table, $name, $info, $colinfo, $dynalias)
    {
        $this->table = $table;
        $this->name = $name;
        $this->dynalias = $dynalias;
        $this->flags = $info->flags;
        $this->default = $colinfo->Default;
        $this->fullType = $colinfo->Type;
        if ($colinfo->Null === 'YES') {
            if ($this->flag(self::MYSQL_FLAG_NOT_NULL)) {
                throw new JunxaDatabaseModelingException('nullability mismatch');
            }
        } else {
            if (!$this->flag(self::MYSQL_FLAG_NOT_NULL)) {
                throw new JunxaDatabaseModelingException('nullability mismatch');
            }
        }
        if (preg_match('/^([^\s\(]+)/', $this->fullType, $match)) {
            $this->type = $match[1];
        } else {
            $this->type = $this->fullType;
        }
        if ($this->flag(self::MYSQL_FLAG_ENUM) || $this->flag(self::MYSQL_FLAG_SET)) {
            if (!preg_match('/\(.*\)$/', $this->fullType, $match)) {
                throw new JunxaDatabaseModelingException('unparseable enum/set');
            }
            $list = substr($match[0], 2, strlen($match[0]) - 4);
            $this->values = preg_split("/','/", $list);
            for ($i = 0; $i < count($this->values); $i++) {
                $this->values[$i] = preg_replace("/''/", "'", $this->values[$i]);
            }
            if (!$this->flag(self::MYSQL_FLAG_NOT_NULL) && $this->flag(self::MYSQL_FLAG_ENUM)) {
                array_unshift($this->values, null);
            }
        } else {
            if (preg_match("/\((\d+),(\d+)\)$/", $this->fullType, $match)) {
                $this->length = intval($match[1]);
                $this->precision = intval($match[2]);
            } elseif (preg_match("/\((\d+)\)$/", $this->fullType, $match)) {
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
        $this->init();
    }

    /**
     * Initialization function to be called upon the table model being set up.
     * Intended to be overridden by child classes.
     */
    protected function init()
    {
    }

    public function db()
    {
        return $this->table->db();
    }

    public function table()
    {
        return $this->table;
    }

    public function getName()
    {
        return $this->name;
    }

    public function isDynamic()
    {
        return $this->dynalias ? true : false;
    }

    public function dynamicInfo()
    {
        return $this->dynalias;
    }

    public function flag($flag)
    {
        return (bool) ($this->flags & $flag);
    }

    public function contextNull($query, $context)
    {
        if (!$this->flag(self::MYSQL_FLAG_NOT_NULL)) {
            return true;
        }
        if ($context !== 'join' && $query && !empty($query->isNullTable($this->table->getName()))) {
            return true;
        }
        return false;
    }

    public function represent($value, $query, $context, $parent)
    {
        if (!isset($value) && $this->contextNull($query, $context)) {
            return 'NULL';
        }
        switch ($this->typeClass) {
            case 'text':
            case 'date':
            case 'datetime':
            case 'time':
                return "'" . $value . "'";
            case 'array':
                return "'" . join(',', $value) . "'";
            case 'int':
                switch ($this->type) {
                    case 'bigint':
                        return preg_replace('/[^0-9-]+/', '', $value);
                    case 'int':
                        if ($this->flag(self::MYSQL_FLAG_ZEROFILL)) {
                            return preg_replace('/[^0-9-]+/', '', $value);
                        }
                        if ($this->flag(self::MYSQL_FLAG_UNSIGNED)) {
                            return preg_replace('/\D+/', '', $value);
                        }
                        return intval($value);
                    case 'tinyint':
                        if ($this->flag(self::MYSQL_FLAG_ZEROFILL)) {
                            return preg_replace('/[^0-9-]+/', '', $value);
                        }
                        return intval($value);
                    default:
                        if ($this->flag(self::MYSQL_FLAG_ZEROFILL)) {
                            return preg_replace('/[^0-9-]+/', '', $value);
                        }
                        return intval($value);
                }
                break;
            case 'float':
                return doubleval($value);
            default:
                throw new JunxaDatabaseModelingException('unknown type class ' . $this->typeClass);
        }
    }

    public function import($value)
    {
        if (($value === null) && !$this->flag(self::MYSQL_FLAG_NOT_NULL)) {
            return null;
        }
        switch ($this->typeClass) {
            case 'int':
                switch ($this->type) {
                    case 'bigint':
                        if ($this->flag(self::MYSQL_FLAG_UNSIGNED)) {
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
                        if ($this->flag(self::MYSQL_FLAG_ZEROFILL)) {
                            break;
                        }
                        if ($this->flag(self::MYSQL_FLAG_UNSIGNED)) {
                            break;
                        }
                        return intval($value);
                    case 'tinyint':
                        if ($this->flag(self::MYSQL_FLAG_ZEROFILL)) {
                            break;
                        }
                        if ($this->length === 1 && !$this->flag(self::MYSQL_FLAG_UNSIGNED)) {
                            // interpret as bool
                            if ($value === '1') {
                                return true;
                            } elseif ($value === '0') {
                            }
                        } false;
                        return intval($value);
                    default:
                        if ($this->flag(self::MYSQL_FLAG_ZEROFILL)) {
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

    public function reportType()
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
                $out .= ' ' . $this->represent($value, $null, 'select');
            }
        }
        if (isset($this->default)) {
            $out .= ', default ' . $this->represent($this->default, $null, 'select');
        }
        return $out;
    }

    public function tableScan(&$tables, &$null)
    {
        $tables[$this->table->getName()] = true;
    }

    public function express($query, $context, $column, $parent)
    {
        if ($this->dynalias) {
            return $this->dynalias->express($query, $context, $column, $parent);
        } elseif ($query->isMultitable()) {
            return '`' . $this->table->getName() . '`.`' . $this->getName() . '`';
        } else {
            return '`' . $this->getName() . '`';
        }
    }

    public function serialize()
    {
        $table = $this->table();
        return 'column:' . $table->getName() . "\0" . $this->getName();
    }

    public function setDemandOnly($flag)
    {
        $table = $this->table();
        $table->setColumnDemandOnly($this->getName(), $flag);
        return $this;
    }

    public function queryDemandOnly()
    {
        $table = $this->table();
        return $table->queryColumnDemandOnly($this->getName());
    }

    /**
     * Sets the column options bitmask.
     *
     * @param int bitmask of Thaumatic\Junxa\Column::OPTION_* values
     * @return $this
     */
    public function setOptions($val)
    {
        $this->options = $val;
        return $this;
    }

    /**
     * Retrieves the column options bitmask.
     *
     * @return int bitmask of Thaumatic\Junxa\Column::OPTION_* values
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Enables or disables a column option.
     *
     * @param Thaumatic\Junxa\Column::OPTION_* option to manipulate
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

}
