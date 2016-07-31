<?php

namespace Thaumatic\Junxa\Exceptions;

use Thaumatic\Junxa\Column;
use Thaumatic\Junxa\Table;

/**
 * Class for exceptions arising from referential integrity failures.
 */
class JunxaReferentialIntegrityException extends JunxaException
{

    private $localTable;
    private $localColumn;
    private $foreignTable;
    private $foreignColumn;
    private $missingValue;

    public function __construct(
        Table $localTable,
        Column $localColumn,
        Table $foreignTable,
        Column $foreignColumn,
        array $missingValues
    ) {
        $this->localTable = $localTable;
        $this->localColumn = $localColumn;
        $this->foreignTable = $foreignTable;
        $this->foreignColumn = $foreignColumn;
        $this->missingValues = $missingValues;
        parent::__construct('no such column: ' . $columnName);
    }

    public function getLocalTable()
    {
        return $this->localTable;
    }

    public function getLocalColumn()
    {
        return $this->localColumn;
    }

    public function getForeignTable()
    {
        return $this->foreignTable;
    }

    public function getForeignColumn()
    {
        return $this->foreignColumn;
    }

}
