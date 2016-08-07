<?php

namespace Thaumatic\Junxa\Tests;

use Thaumatic\Junxa;
use Thaumatic\Junxa\Column;
use Thaumatic\Junxa\Query as Q;
use Thaumatic\Junxa\Tests\DatabaseTestAbstract;

class ColumnTest extends DatabaseTestAbstract
{

    public function testColumnConfiguration()
    {
        $categoryIdColumn = $this->db->category->id;
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $categoryIdColumn);
        $this->assertSame($this->db, $categoryIdColumn->getDatabase());
        $this->assertSame($this->db->category, $categoryIdColumn->getTable());
        $this->assertSame('id', $categoryIdColumn->getName());
        $this->assertSame('mediumint(8) unsigned', $categoryIdColumn->getFullType());
        $this->assertSame('mediumint', $categoryIdColumn->getType());
        $this->assertSame('int', $categoryIdColumn->getTypeClass());
        $this->assertFalse($categoryIdColumn->isDynamic());
        $this->assertNull($categoryIdColumn->getDynamicAlias());
        $this->assertNull($categoryIdColumn->getDefault());
        $this->assertFalse($categoryIdColumn->hasDefault());
        $this->assertNull($categoryIdColumn->getDefaultValue());
        $categoryNameColumn = $this->db->category->name;
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $categoryNameColumn);
        $this->assertSame($this->db, $categoryNameColumn->getDatabase());
        $this->assertSame($this->db->category, $categoryNameColumn->getTable());
        $this->assertSame('name', $categoryNameColumn->getName());
        $this->assertSame('varchar(250)', $categoryNameColumn->getFullType());
        $this->assertSame('varchar', $categoryNameColumn->getType());
        $this->assertSame('text', $categoryNameColumn->getTypeClass());
        $this->assertFalse($categoryNameColumn->isDynamic());
        $this->assertNull($categoryNameColumn->getDynamicAlias());
        $this->assertNull($categoryNameColumn->getDefault());
        $this->assertFalse($categoryNameColumn->hasDefault());
        $this->assertNull($categoryNameColumn->getDefaultValue());
    }

    public function testSetOptionsAndGetOptions()
    {
        try {
            $column = $this->db->category->id;
            $this->assertSame(0, $column->getOptions());
            $column->setOptions(Column::OPTION_MERGE_NO_UPDATE);
            $this->assertSame(Column::OPTION_MERGE_NO_UPDATE, $column->getOptions());
            $column->setOptions(
                Column::OPTION_MERGE_NO_UPDATE
                | Column::OPTION_NO_AUTO_FOREIGN_KEY
            );
            $this->assertSame(
                Column::OPTION_MERGE_NO_UPDATE
                | Column::OPTION_NO_AUTO_FOREIGN_KEY,
                $column->getOptions()
            );
        } finally {
            if (isset($column)) {
                $column->setOptions(0);
            }
        }
    }

    public function testSetOptionAndGetOption()
    {
        try {
            $column = $this->db->category->id;
            $this->assertFalse($column->getOption(Column::OPTION_MERGE_NO_UPDATE));
            $this->assertFalse($column->getOption(Column::OPTION_NO_AUTO_FOREIGN_KEY));
            $column->setOption(Column::OPTION_MERGE_NO_UPDATE, true);
            $this->assertTrue($column->getOption(Column::OPTION_MERGE_NO_UPDATE));
            $this->assertFalse($column->getOption(Column::OPTION_NO_AUTO_FOREIGN_KEY));
            $column->setOption(Column::OPTION_NO_AUTO_FOREIGN_KEY, true);
            $this->assertTrue($column->getOption(Column::OPTION_MERGE_NO_UPDATE));
            $this->assertTrue($column->getOption(Column::OPTION_NO_AUTO_FOREIGN_KEY));
        } finally {
            if (isset($column)) {
                $column->setOptions(0);
            }
        }
    }

    public function testGetFlagNames()
    {
        $categoryTable = $this->db->category;
        $this->assertSame(
            [
                'NOT_NULL',
                'PRI_KEY',
                'UNSIGNED',
                'AUTO_INCREMENT',
                'PART_KEY',
                'NUM',
            ],
            $categoryTable->id->getFlagNames()
        );
        $this->assertSame(
            [
                'NOT_NULL',
                'UNIQUE_KEY',
                'NO_DEFAULT_VALUE',
                'PART_KEY',
            ],
            $categoryTable->name->getFlagNames()
        );
        $this->assertSame(
            [
                'ENUM',
            ],
            $categoryTable->type->getFlagNames()
        );
        $this->assertSame(
            [
                'NOT_NULL',
                'MULTIPLE_KEY',
                'PART_KEY',
                'NUM',
            ],
            $categoryTable->active->getFlagNames()
        );
        $this->assertSame(
            [
                'NOT_NULL',
                'BINARY',
                'NO_DEFAULT_VALUE',
            ],
            $categoryTable->createdAt->getFlagNames()
        );
        $this->assertSame(
            [
                'NOT_NULL',
                'UNSIGNED',
                'ZEROFILL',
                'BINARY',
                'TIMESTAMP',
                'ON_UPDATE_NOW',
            ],
            $categoryTable->changedAt->getFlagNames()
        );
        $itemTable = $this->db->item;
        $this->assertSame(
            [
                'NOT_NULL',
                'PRI_KEY',
                'UNSIGNED',
                'AUTO_INCREMENT',
                'PART_KEY',
                'NUM',
            ],
            $itemTable->id->getFlagNames()
        );
        $this->assertSame(
            [
                'NOT_NULL',
                'MULTIPLE_KEY',
                'UNSIGNED',
                'NO_DEFAULT_VALUE',
                'PART_KEY',
                'NUM',
            ],
            $itemTable->categoryId->getFlagNames()
        );
        $this->assertSame(
            [],
            $itemTable->price->getFlagNames()
        );
        $this->assertSame(
            [
                'NOT_NULL',
                'UNIQUE_KEY',
                'NO_DEFAULT_VALUE',
                'PART_KEY',
            ],
            $itemTable->name->getFlagNames()
        );
        $this->assertSame(
            [
                'NOT_NULL',
                'PART_KEY',
                'NUM',
            ],
            $itemTable->active->getFlagNames()
        );
        $this->assertSame(
            [
                'NOT_NULL',
                'BINARY',
                'NO_DEFAULT_VALUE',
            ],
            $itemTable->createdAt->getFlagNames()
        );
        $this->assertSame(
            [
                'NOT_NULL',
                'UNSIGNED',
                'ZEROFILL',
                'BINARY',
                'TIMESTAMP',
                'ON_UPDATE_NOW',
            ],
            $itemTable->changedAt->getFlagNames()
        );
    }

    public function testGetFlagNamesWithManipulation()
    {
        try {
            $column = $this->db->category->changedAt;
            $refClass = new \ReflectionClass(get_class($column));
            $flagsProp = $refClass->getProperty('flags');
            $flagsProp->setAccessible(true);
            $origValue = $flagsProp->getValue($column);
            $haveOrigValue = true;
            $flagsProp->setValue($column, 0);
            $this->assertSame([], $column->getFlagNames());
            foreach (Column::MYSQL_FLAG_NAMES as $bit => $name) {
                $flagsProp->setValue($column, $bit);
                $this->assertSame([$name], $column->getFlagNames());
            }
            $flags = 0;
            $names = [];
            foreach (Column::MYSQL_FLAG_NAMES as $bit => $name) {
                $flags |= $bit;
                $names[] = $name;
                $flagsProp->setValue($column, $flags);
                $this->assertSame($names, $column->getFlagNames());
            }
            $flagsProp->setValue(
                $column,
                Column::MYSQL_FLAG_NOT_NULL |
                Column::MYSQL_FLAG_MULTIPLE_KEY |
                Column::MYSQL_FLAG_PART_KEY
            );
            $this->assertSame(
                [
                    'NOT_NULL',
                    'MULTIPLE_KEY',
                    'PART_KEY',
                ],
                $column->getFlagNames()
            );
        } finally {
            if (isset($flagsProp) && isset($haveOrigValue)) {
                $flagsProp->setValue($column, $origValue);
            }
        }
    }

    public function testGetFlag()
    {
        $categoryTable = $this->db->category;
        $this->assertTrue($categoryTable->id->getFlag(Column::MYSQL_FLAG_NOT_NULL));
        $this->assertTrue($categoryTable->id->getFlag(Column::MYSQL_FLAG_PRI_KEY));
        $this->assertTrue($categoryTable->id->getFlag(Column::MYSQL_FLAG_UNSIGNED));
        $this->assertTrue($categoryTable->id->getFlag(Column::MYSQL_FLAG_AUTO_INCREMENT));
        $this->assertTrue($categoryTable->id->getFlag(Column::MYSQL_FLAG_PART_KEY));
        $this->assertTrue($categoryTable->id->getFlag(Column::MYSQL_FLAG_NUM));
        $this->assertTrue(
            $categoryTable->id->getFlag(
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_PRI_KEY
            )
        );
        $this->assertTrue(
            $categoryTable->id->getFlag(
                Column::MYSQL_FLAG_PART_KEY
                | Column::MYSQL_FLAG_NUM
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($categoryTable->id->getFlag(Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
        $this->assertTrue($categoryTable->name->getFlag(Column::MYSQL_FLAG_NOT_NULL));
        $this->assertTrue($categoryTable->name->getFlag(Column::MYSQL_FLAG_UNIQUE_KEY));
        $this->assertTrue($categoryTable->name->getFlag(Column::MYSQL_FLAG_NO_DEFAULT_VALUE));
        $this->assertTrue($categoryTable->name->getFlag(Column::MYSQL_FLAG_PART_KEY));
        $this->assertTrue(
            $categoryTable->name->getFlag(
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_UNIQUE_KEY
            )
        );
        $this->assertTrue(
            $categoryTable->name->getFlag(
                Column::MYSQL_FLAG_NO_DEFAULT_VALUE
                | Column::MYSQL_FLAG_PART_KEY
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($categoryTable->name->getFlag(Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
        $this->assertTrue($categoryTable->type->getFlag(Column::MYSQL_FLAG_ENUM));
        $this->assertTrue(
            $categoryTable->type->getFlag(
                Column::MYSQL_FLAG_ENUM
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($categoryTable->type->getFlag(Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
        $this->assertTrue($categoryTable->active->getFlag(Column::MYSQL_FLAG_NOT_NULL));
        $this->assertTrue($categoryTable->active->getFlag(Column::MYSQL_FLAG_MULTIPLE_KEY));
        $this->assertTrue($categoryTable->active->getFlag(Column::MYSQL_FLAG_PART_KEY));
        $this->assertTrue($categoryTable->active->getFlag(Column::MYSQL_FLAG_NUM));
        $this->assertTrue(
            $categoryTable->active->getFlag(
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_MULTIPLE_KEY
            )
        );
        $this->assertTrue(
            $categoryTable->active->getFlag(
                Column::MYSQL_FLAG_PART_KEY
                | Column::MYSQL_FLAG_NUM
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($categoryTable->active->getFlag(Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
        $this->assertTrue($categoryTable->createdAt->getFlag(Column::MYSQL_FLAG_NOT_NULL));
        $this->assertTrue($categoryTable->createdAt->getFlag(Column::MYSQL_FLAG_BINARY));
        $this->assertTrue($categoryTable->createdAt->getFlag(Column::MYSQL_FLAG_NO_DEFAULT_VALUE));
        $this->assertTrue(
            $categoryTable->createdAt->getFlag(
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_BINARY
            )
        );
        $this->assertTrue(
            $categoryTable->createdAt->getFlag(
                Column::MYSQL_FLAG_BINARY
                | Column::MYSQL_FLAG_NO_DEFAULT_VALUE
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($categoryTable->createdAt->getFlag(Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
        $this->assertTrue($categoryTable->changedAt->getFlag(Column::MYSQL_FLAG_NOT_NULL));
        $this->assertTrue($categoryTable->changedAt->getFlag(Column::MYSQL_FLAG_UNSIGNED));
        $this->assertTrue($categoryTable->changedAt->getFlag(Column::MYSQL_FLAG_ZEROFILL));
        $this->assertTrue($categoryTable->changedAt->getFlag(Column::MYSQL_FLAG_BINARY));
        $this->assertTrue($categoryTable->changedAt->getFlag(Column::MYSQL_FLAG_TIMESTAMP));
        $this->assertTrue($categoryTable->changedAt->getFlag(Column::MYSQL_FLAG_ON_UPDATE_NOW));
        $this->assertTrue(
            $categoryTable->changedAt->getFlag(
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_UNSIGNED
            )
        );
        $this->assertTrue(
            $categoryTable->changedAt->getFlag(
                Column::MYSQL_FLAG_TIMESTAMP
                | Column::MYSQL_FLAG_ON_UPDATE_NOW
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($categoryTable->changedAt->getFlag(Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
        $itemTable = $this->db->item;
        $this->assertTrue($itemTable->id->getFlag(Column::MYSQL_FLAG_NOT_NULL));
        $this->assertTrue($itemTable->id->getFlag(Column::MYSQL_FLAG_PRI_KEY));
        $this->assertTrue($itemTable->id->getFlag(Column::MYSQL_FLAG_UNSIGNED));
        $this->assertTrue($itemTable->id->getFlag(Column::MYSQL_FLAG_AUTO_INCREMENT));
        $this->assertTrue($itemTable->id->getFlag(Column::MYSQL_FLAG_PART_KEY));
        $this->assertTrue($itemTable->id->getFlag(Column::MYSQL_FLAG_NUM));
        $this->assertTrue(
            $itemTable->id->getFlag(
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_PRI_KEY
            )
        );
        $this->assertTrue(
            $itemTable->id->getFlag(
                Column::MYSQL_FLAG_PART_KEY
                | Column::MYSQL_FLAG_NUM
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($itemTable->id->getFlag(Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
        $this->assertTrue($itemTable->categoryId->getFlag(Column::MYSQL_FLAG_NOT_NULL));
        $this->assertTrue($itemTable->categoryId->getFlag(Column::MYSQL_FLAG_MULTIPLE_KEY));
        $this->assertTrue($itemTable->categoryId->getFlag(Column::MYSQL_FLAG_UNSIGNED));
        $this->assertTrue($itemTable->categoryId->getFlag(Column::MYSQL_FLAG_NO_DEFAULT_VALUE));
        $this->assertTrue($itemTable->categoryId->getFlag(Column::MYSQL_FLAG_PART_KEY));
        $this->assertTrue($itemTable->categoryId->getFlag(Column::MYSQL_FLAG_NUM));
        $this->assertTrue(
            $itemTable->categoryId->getFlag(
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_MULTIPLE_KEY
            )
        );
        $this->assertTrue(
            $itemTable->categoryId->getFlag(
                Column::MYSQL_FLAG_PART_KEY
                | Column::MYSQL_FLAG_NUM
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($itemTable->categoryId->getFlag(Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
        $this->assertTrue($itemTable->name->getFlag(Column::MYSQL_FLAG_NOT_NULL));
        $this->assertTrue($itemTable->name->getFlag(Column::MYSQL_FLAG_UNIQUE_KEY));
        $this->assertTrue($itemTable->name->getFlag(Column::MYSQL_FLAG_NO_DEFAULT_VALUE));
        $this->assertTrue($itemTable->name->getFlag(Column::MYSQL_FLAG_PART_KEY));
        $this->assertTrue(
            $itemTable->name->getFlag(
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_UNIQUE_KEY
            )
        );
        $this->assertTrue(
            $itemTable->name->getFlag(
                Column::MYSQL_FLAG_NO_DEFAULT_VALUE
                | Column::MYSQL_FLAG_PART_KEY
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($itemTable->name->getFlag(Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
        $this->assertFalse($itemTable->price->getFlag(Column::MYSQL_FLAG_NUM));
        $this->assertFalse($itemTable->price->getFlag(Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
        $this->assertTrue($itemTable->active->getFlag(Column::MYSQL_FLAG_NOT_NULL));
        $this->assertTrue($itemTable->active->getFlag(Column::MYSQL_FLAG_PART_KEY));
        $this->assertTrue($itemTable->active->getFlag(Column::MYSQL_FLAG_NUM));
        $this->assertTrue(
            $itemTable->active->getFlag(
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_PART_KEY
            )
        );
        $this->assertTrue(
            $itemTable->active->getFlag(
                Column::MYSQL_FLAG_PART_KEY
                | Column::MYSQL_FLAG_NUM
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($itemTable->active->getFlag(Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
        $this->assertTrue($itemTable->createdAt->getFlag(Column::MYSQL_FLAG_NOT_NULL));
        $this->assertTrue($itemTable->createdAt->getFlag(Column::MYSQL_FLAG_BINARY));
        $this->assertTrue($itemTable->createdAt->getFlag(Column::MYSQL_FLAG_NO_DEFAULT_VALUE));
        $this->assertTrue(
            $itemTable->createdAt->getFlag(
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_BINARY
            )
        );
        $this->assertTrue(
            $itemTable->createdAt->getFlag(
                Column::MYSQL_FLAG_BINARY
                | Column::MYSQL_FLAG_NO_DEFAULT_VALUE
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($itemTable->createdAt->getFlag(Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
        $this->assertTrue($itemTable->changedAt->getFlag(Column::MYSQL_FLAG_NOT_NULL));
        $this->assertTrue($itemTable->changedAt->getFlag(Column::MYSQL_FLAG_UNSIGNED));
        $this->assertTrue($itemTable->changedAt->getFlag(Column::MYSQL_FLAG_ZEROFILL));
        $this->assertTrue($itemTable->changedAt->getFlag(Column::MYSQL_FLAG_BINARY));
        $this->assertTrue($itemTable->changedAt->getFlag(Column::MYSQL_FLAG_TIMESTAMP));
        $this->assertTrue($itemTable->changedAt->getFlag(Column::MYSQL_FLAG_ON_UPDATE_NOW));
        $this->assertTrue(
            $itemTable->changedAt->getFlag(
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_UNSIGNED
            )
        );
        $this->assertTrue(
            $itemTable->changedAt->getFlag(
                Column::MYSQL_FLAG_TIMESTAMP
                | Column::MYSQL_FLAG_ON_UPDATE_NOW
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($itemTable->changedAt->getFlag(Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
    }

    public function testGetEachFlag()
    {
        $categoryTable = $this->db->category;
        $this->assertTrue(
            $categoryTable->id->getEachFlag(
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_PRI_KEY
                | Column::MYSQL_FLAG_UNSIGNED
                | Column::MYSQL_FLAG_AUTO_INCREMENT
                | Column::MYSQL_FLAG_PART_KEY
                | Column::MYSQL_FLAG_NUM
            )
        );
        $this->assertFalse(
            $categoryTable->id->getEachFlag(
                Column::MYSQL_FLAG_PRI_KEY
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertTrue(
            $categoryTable->name->getEachFlag(
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_UNIQUE_KEY
                | Column::MYSQL_FLAG_NO_DEFAULT_VALUE
                | Column::MYSQL_FLAG_PART_KEY
            )
        );
        $this->assertFalse(
            $categoryTable->name->getEachFlag(
                Column::MYSQL_FLAG_UNIQUE_KEY
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertTrue(
            $categoryTable->type->getEachFlag(
                Column::MYSQL_FLAG_ENUM
            )
        );
        $this->assertFalse(
            $categoryTable->type->getEachFlag(
                Column::MYSQL_FLAG_ENUM
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertTrue(
            $categoryTable->active->getEachFlag(
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_MULTIPLE_KEY
                | Column::MYSQL_FLAG_PART_KEY
                | Column::MYSQL_FLAG_NUM
            )
        );
        $this->assertFalse(
            $categoryTable->active->getEachFlag(
                Column::MYSQL_FLAG_MULTIPLE_KEY
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertTrue(
            $categoryTable->createdAt->getEachFlag(
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_BINARY
                | Column::MYSQL_FLAG_NO_DEFAULT_VALUE
            )
        );
        $this->assertFalse(
            $categoryTable->createdAt->getEachFlag(
                Column::MYSQL_FLAG_BINARY
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertTrue(
            $categoryTable->changedAt->getEachFlag(
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_UNSIGNED
                | Column::MYSQL_FLAG_ZEROFILL
                | Column::MYSQL_FLAG_BINARY
                | Column::MYSQL_FLAG_TIMESTAMP
                | Column::MYSQL_FLAG_ON_UPDATE_NOW
            )
        );
        $this->assertFalse(
            $categoryTable->changedAt->getEachFlag(
                Column::MYSQL_FLAG_UNSIGNED
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $itemTable = $this->db->item;
        $this->assertTrue(
            $itemTable->id->getEachFlag(
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_PRI_KEY
                | Column::MYSQL_FLAG_UNSIGNED
                | Column::MYSQL_FLAG_AUTO_INCREMENT
                | Column::MYSQL_FLAG_PART_KEY
                | Column::MYSQL_FLAG_NUM
            )
        );
        $this->assertFalse(
            $itemTable->id->getEachFlag(
                Column::MYSQL_FLAG_PRI_KEY
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertTrue(
            $itemTable->categoryId->getEachFlag(
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_MULTIPLE_KEY
                | Column::MYSQL_FLAG_UNSIGNED
                | Column::MYSQL_FLAG_NO_DEFAULT_VALUE
                | Column::MYSQL_FLAG_PART_KEY
                | Column::MYSQL_FLAG_NUM
            )
        );
        $this->assertFalse(
            $itemTable->categoryId->getEachFlag(
                Column::MYSQL_FLAG_MULTIPLE_KEY
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertTrue(
            $itemTable->name->getEachFlag(
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_UNIQUE_KEY
                | Column::MYSQL_FLAG_NO_DEFAULT_VALUE
                | Column::MYSQL_FLAG_PART_KEY
            )
        );
        $this->assertFalse(
            $itemTable->name->getEachFlag(
                Column::MYSQL_FLAG_UNIQUE_KEY
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertTrue($itemTable->price->getEachFlag(0));
        $this->assertFalse(
            $itemTable->price->getEachFlag(
                Column::MYSQL_FLAG_UNIQUE_KEY
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertTrue(
            $itemTable->active->getEachFlag(
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_PART_KEY
                | Column::MYSQL_FLAG_NUM
            )
        );
        $this->assertFalse(
            $itemTable->active->getEachFlag(
                Column::MYSQL_FLAG_PART_KEY
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertTrue(
            $itemTable->createdAt->getEachFlag(
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_BINARY
                | Column::MYSQL_FLAG_NO_DEFAULT_VALUE
            )
        );
        $this->assertFalse(
            $itemTable->createdAt->getEachFlag(
                Column::MYSQL_FLAG_BINARY
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertTrue(
            $itemTable->changedAt->getEachFlag(
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_UNSIGNED
                | Column::MYSQL_FLAG_ZEROFILL
                | Column::MYSQL_FLAG_BINARY
                | Column::MYSQL_FLAG_TIMESTAMP
                | Column::MYSQL_FLAG_ON_UPDATE_NOW
            )
        );
        $this->assertFalse(
            $itemTable->changedAt->getEachFlag(
                Column::MYSQL_FLAG_UNSIGNED
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
    }

    public function testGetValues()
    {
        $categoryTable = $this->db->category;
        $this->assertNull($categoryTable->id->getValues());
        $this->assertNull($categoryTable->name->getValues());
        $this->assertSame([null, 'A\'s', 'B\'s', 'C\'s'], $categoryTable->type->getValues());
        $this->assertNull($categoryTable->active->getValues());
        $this->assertNull($categoryTable->createdAt->getValues());
        $this->assertNull($categoryTable->changedAt->getValues());
        $itemTable = $this->db->item;
        $this->assertNull($itemTable->id->getValues());
        $this->assertNull($itemTable->categoryId->getValues());
        $this->assertNull($itemTable->name->getValues());
        $this->assertNull($itemTable->price->getValues());
        $this->assertNull($itemTable->active->getValues());
        $this->assertNull($itemTable->createdAt->getValues());
        $this->assertNull($itemTable->changedAt->getValues());
    }

    public function testGetEachOption()
    {
        try {
            $column = $this->db->category->id;
            $this->assertFalse($column->getEachOption(Column::OPTION_MERGE_NO_UPDATE));
            $this->assertFalse($column->getEachOption(Column::OPTION_NO_AUTO_FOREIGN_KEY));
            $this->assertFalse(
                $column->getEachOption(
                    Column::OPTION_MERGE_NO_UPDATE
                    | Column::OPTION_NO_AUTO_FOREIGN_KEY
                )
            );
            $column->setOption(Column::OPTION_MERGE_NO_UPDATE, true);
            $this->assertTrue($column->getEachOption(Column::OPTION_MERGE_NO_UPDATE));
            $this->assertFalse($column->getEachOption(Column::OPTION_NO_AUTO_FOREIGN_KEY));
            $this->assertFalse(
                $column->getEachOption(
                    Column::OPTION_MERGE_NO_UPDATE
                    | Column::OPTION_NO_AUTO_FOREIGN_KEY
                )
            );
            $column->setOption(Column::OPTION_NO_AUTO_FOREIGN_KEY, true);
            $this->assertTrue($column->getEachOption(Column::OPTION_MERGE_NO_UPDATE));
            $this->assertTrue($column->getEachOption(Column::OPTION_NO_AUTO_FOREIGN_KEY));
            $this->assertTrue(
                $column->getEachOption(
                    Column::OPTION_MERGE_NO_UPDATE
                    | Column::OPTION_NO_AUTO_FOREIGN_KEY
                )
            );
        } finally {
            if (isset($column)) {
                $column->setOptions(0);
            }
        }
    }

    public function testDynamicDefaults()
    {
        $table = $this->db->category;
        $table->createdAt->setDynamicDefault(Q::func('NOW'));
        $row1 = $table->newRow();
        $row1->name = 'Arbitrary';
        $row1->save();
        $this->assertNotNull($row1->createdAt);
        $this->assertNotSame('0000-00-00 00:00:00', $row1->createdAt);
        $this->assertGreaterThan(time() - 1, strtotime($row1->createdAt));
        $table->name->setDynamicDefault(Q::func('CONCAT', Q::func('DATABASE'), 'X'));
        $row2 = $table->newRow();
        $row2->save();
        $this->assertNotEquals($row1->id, $row2->id);
        $this->assertEquals(DatabaseTestAbstract::TEST_DATABASE_NAME . 'X', $row2->name);
        $this->assertNotNull($row2->createdAt);
        $this->assertNotSame('0000-00-00 00:00:00', $row2->createdAt);
        $this->assertGreaterThan(time() - 1, strtotime($row2->createdAt));
        $row3 = $table->newRow();
        $row3->name = 'Undefaulted';
        $row3->createdAt = '2001-01-01 12:00:00';
        $row3->save();
        $this->assertSame('Undefaulted', $row3->name);
        $this->assertSame('2001-01-01 12:00:00', $row3->createdAt);
    }

}
