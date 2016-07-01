<?php

namespace Thaumatic\Junxa\Query;

use Thaumatic\Junxa\Column;
use Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException;

/**
 * Abstracts the concept of "set this column to this value", as seen in insert, replace, and update queries.  Mostly used
 * internally; application developers should not normally have to work with it explicitly.
 */
class Assignment
{
    
    private $column;
    private $value;

    public function __construct(Column $column, $value)
    {
        $this->column = $column;
        $this->value = $value;
    }

    public function getColumn()
    {
        return $this->column;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function tableScan(&$tables, &$null)
    {
        $this->column->tableScan($tables, $null);
        if (is_object($this->value) && method_exists($this->value, 'tableScan')) {
            $this->value->tableScan($tables, $null);
        }
    }
}
