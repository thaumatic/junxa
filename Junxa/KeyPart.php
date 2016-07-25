<?php

namespace Thaumatic\Junxa;

/**
 * Models a column-linked portion of a database key.
 */
class KeyPart
{

    /**
     * @var string the name of the column indexed by this key part
     */
    private $columnName;

    /**
     * @var int database-provided estimates of key cardinality
     */
    private $cardinality;

    /**
     * @var int prefix length of the column that is indexed, null for entire
     * column
     */
    private $length;

    /**
     * @var bool whether this portion of the key can contain nulls
     */
    private $nulls;

    /**
     * @param string column name
     * @param int cardinality estimate
     * @param int indexed prefix length, null for entire column
     * @param bool whether nulls may be present
     */
    public function __construct($columnName, $cardinality, $length, $nulls)
    {
        $this->columnName = $columnName;
        $this->cardinality = $cardinality;
        $this->length = $length;
        $this->nulls = $nulls;
    }

    /**
     * @return string the name of the column indexed by this key part
     */
    public function getColumnName()
    {
        return $this->columnName;
    }

    /**
     * @return int database-provided estimate of the cardinality of this key part
     */
    public function getCardinality()
    {
        return $this->cardinality;
    }

    /**
     * @return int prefix length of the column that is indexed, null for entire
     * column
     */
    public function getLength()
    {
        return $this->length;
    }

    /**
     * @return bool whether this key part can contain nulls
     */
    public function getNulls()
    {
        return $this->nulls;
    }

}
