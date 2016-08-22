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

    final public function __construct(
        Table $localTable,
        Column $localColumn,
        Table $foreignTable,
        Column $foreignColumn,
        $missingValue
    ) {
        $this->localTable = $localTable;
        $this->localColumn = $localColumn;
        $this->foreignTable = $foreignTable;
        $this->foreignColumn = $foreignColumn;
        $this->missingValue = $missingValue;
        parent::__construct(
            'foreign table '
            . $foreignTable->getName()
            . ' has no value in '
            . $foreignColumn->getName()
            . ' corresponding to value '
            . print_r($missingValue, true)
            . ' for '
            . $localColumn->getName()
            . ' in local table '
            . $localTable->getName()
        );
    }

    final public function getLocalTable()
    {
        return $this->localTable;
    }

    final public function getLocalColumn()
    {
        return $this->localColumn;
    }

    final public function getForeignTable()
    {
        return $this->foreignTable;
    }

    final public function getForeignColumn()
    {
        return $this->foreignColumn;
    }

    final public function getMissingValue()
    {
        return $this->missingValue;
    }

}
