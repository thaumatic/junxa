<?php

namespace Thaumatic\Junxa\Exceptions;

/**
 * Exception class for requests for a nonexistent table.
 */
class JunxaNoSuchTableException extends JunxaException
{

    private $tableName;

    final public function __construct($tableName)
    {
        $this->tableName = $tableName;
        parent::__construct('no such table: ' . $tableName);
    }

    final public function getTableName()
    {
        return $this->tableName;
    }

}
