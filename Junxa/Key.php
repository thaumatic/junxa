<?php

namespace Thaumatic\Junxa;

use Thaumatic\Junxa\Exceptions\JunxaDatabaseModelingException;
use Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException;
use Thaumatic\Junxa\KeyPart;

/**
 * Models a database key.
 */
class Key
{

    /**
     * @const int key collation: the key is stored in ascending order
     */
    const COLLATION_ASCENDING           = 1;

    /**
     * @const int key collation: the key is stored unordered
     */
    const COLLATION_NONE                = 2;

    /**
     * @const int index type: B-tree
     */
    const INDEX_TYPE_BTREE              = 1;

    /**
     * @const int index type: full text
     */
    const INDEX_TYPE_FULLTEXT           = 2;

    /**
     * @const int index type: hash
     */
    const INDEX_TYPE_HASH               = 3;

    /**
     * @const int index type: R-tree
     */
    const INDEX_TYPE_RTREE              = 4;

    /**
     * @var string the name of the key
     */
    private $name;

    /**
     * @var array<Thaumatic\Junxa\KeyPart> positional array of the constituent
     * parts of the key
     */
    private $parts = [];

    /**
     * @var array<string:int> map of column names in the key to their indices
     * in the parts array
     */
    private $columnIndices = [];

    /**
     * @var bool whether this is a unique key
     */
    private $unique;

    /**
     * @var int Thaumatic\Junxa\Key::COLLATION_* type of collation used in the
     * key
     */
    private $collation;

    /**
     * @var int Thaumatic\Junxa\Key::INDEX_TYPE_* index type used in the key
     */
    private $indexType;

    /**
     * @var string database-generated comment on the key
     */
    private $comment;

    /**
     * @var string user-specified comment on the key from table creation
     */
    private $indexComment;

    /**
     * @param string the name of the key
     * @param bool whether this is a unique key
     * @param int Thaumatic\Junxa\Key::COLLATION_* type of collation used in
     * this key
     * @param int Thaumatic\Junxa\Key::INDEX_TYPE_* index type used in this key
     * @param string database-generated comment on the key
     * @param string used-specified comment on the key from table creation
     */
    public function __construct(
        $name,
        $unique,
        $collation,
        $indexType,
        $comment,
        $indexComment
    ) {
        $this->name = $name;
        $this->unique = $unique;
        $this->collation = $collation;
        $this->indexType = $indexType;
        $this->comment = $comment;
        $this->indexComment = $indexComment;
    }

    /**
     * @param int the MySQL-reported position of the column in the key
     * @param Thaumatic\Junxa\KeyPart the key part model for the column
     * @return $this
     * @throws Thaumatic\Junxa\Exceptions\JunxaDatabaseModelingException if the
     * index in the parts array corresponding to $seq is already occupied
     * @throws Thaumatic\Junxa\Exceptions\JunxaDatabaseModelingException if
     * there is already an index tracked for the column name of the key part
     */
    public function addKeyPart($seq, KeyPart $keyPart)
    {
        $index = $seq - 1;
        if (isset($this->parts[$index])) {
            throw new JunxaDatabaseModelingException(
                'already have a key part at index ' . $index
            );
        }
        $columnName = $keyPart->getColumnName();
        if (isset($this->columnIndices[$columnName])) {
            throw new JunxaDatabaseModelingException(
                'already have an index for column ' . $columnName
            );
        }
        $this->parts[$index] = $keyPart;
        $this->columnIndices[$columnName] = $index;
        return $this;
    }

    /**
     * Retrieves the key part corresponding to the specified column name.
     *
     * @param string the column name
     * @return Thaumatic\Junxa\KeyPart
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException if the
     * specified column is not part of this key
     */
    public function getColumnKeyPart($columnName)
    {
        if (!isset($this->columnIndices[$columnName])) {
            throw new JunxaDatabaseModelingException(
                'column ' . $columnName . ' not part of key'
            );
        }
        return $this->parts[$this->columnIndices[$columnName]];
    }

    /**
     * @return string the name of the key
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return array<Thaumatic\Junxa\KeyPart> positional array of the
     * constituent parts of the key
     */
    public function getParts()
    {
        return $this->parts;
    }

    /**
     * @return array<string:int> map of column names in the key to their
     * indices in the parts array
     */
    public function getColumnIndices()
    {
        return $this->columnIndices;
    }

    /**
     * @return bool whether this is a unique key
     */
    public function getUnique()
    {
        return $this->unique;
    }

    /**
     * @return int Thaumatic\Junxa\Key::COLLATION_* type of collation used in
     * the key
     */
    public function getCollation()
    {
        return $this->collation;
    }

    /**
     * @return int Thaumatic\Junxa\Key::INDEX_TYPE_* index type used in the key
     */
    public function getIndexType()
    {
        return $this->indexType;
    }

    /**
     * @return string the database-generated comment on the key
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * @return string the user-specified comment on the key from table creation
     */
    public function getIndexComment()
    {
        return $this->indexComment;
    }

    /**
     * Retrieves whether a given column is part of this key.
     *
     * @param string column name
     * @return bool whether the specified column is part of this key
     */
    public function isColumnInKey($columnName)
    {
        return isset($this->columnIndices[$columnName]);
    }

}
