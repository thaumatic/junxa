<?php

namespace Thaumatic\Junxa\Exceptions;

/**
 * Exception class for requests for a nonexistent column.
 */
class JunxaNoSuchColumnException extends JunxaException
{

    private $columnName;

    public function __construct($columnName)
    {
        $this->columnName = $columnName;
        parent::__construct('no such column: ' . $columnName);
    }

    public function getColumnName()
    {
        return $this->columnName;
    }
}
