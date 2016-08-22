<?php

namespace Thaumatic\Junxa\Exceptions;

/**
 * Exception class for requests for a nonexistent column.
 */
class JunxaNoSuchColumnException extends JunxaException
{

    private $columnName;

    final public function __construct($columnName)
    {
        $this->columnName = $columnName;
        parent::__construct('no such column: ' . $columnName);
    }

    final public function getColumnName()
    {
        return $this->columnName;
    }

}
