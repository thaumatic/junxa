<?php

namespace Thaumatic\Junxa\Tests;

use Thaumatic\Junxa;
use Thaumatic\Junxa\Column;
use Thaumatic\Junxa\Tests\DatabaseTestAbstract;

class ColumnTest extends DatabaseTestAbstract
{

    public function testColumnConfiguration()
    {
        $categoryIdColumn = $this->db()->category->id;
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $categoryIdColumn);
        $this->assertSame($this->db(), $categoryIdColumn->getDatabase());
        $this->assertSame($this->db()->category, $categoryIdColumn->getTable());
        $this->assertSame('id', $categoryIdColumn->getName());
        $this->assertSame('mediumint(8) unsigned', $categoryIdColumn->getFullType());
        $this->assertSame('mediumint', $categoryIdColumn->getType());
        $this->assertSame('int', $categoryIdColumn->getTypeClass());
        $this->assertSame(
            Column::MYSQL_FLAG_NUM
            | Column::MYSQL_FLAG_UNSIGNED
            | Column::MYSQL_FLAG_NOT_NULL
            | Column::MYSQL_FLAG_PRI_KEY
            | Column::MYSQL_FLAG_PART_KEY
            | Column::MYSQL_FLAG_AUTO_INCREMENT,
            $categoryIdColumn->getFlags()
        );
        $this->assertFalse($categoryIdColumn->isDynamic());
        $this->assertNull($categoryIdColumn->getDynamicAlias());
        $this->assertNull($categoryIdColumn->getDefault());
        $this->assertFalse($categoryIdColumn->hasDefault());
        $this->assertNull($categoryIdColumn->getDefaultValue());
        $categoryNameColumn = $this->db()->category->name;
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $categoryNameColumn);
        $this->assertSame($this->db(), $categoryNameColumn->getDatabase());
        $this->assertSame($this->db()->category, $categoryNameColumn->getTable());
        $this->assertSame('name', $categoryNameColumn->getName());
        $this->assertSame('varchar(250)', $categoryNameColumn->getFullType());
        $this->assertSame('varchar', $categoryNameColumn->getType());
        $this->assertSame('text', $categoryNameColumn->getTypeClass());
        $this->assertSame(
            Column::MYSQL_FLAG_NOT_NULL |
            Column::MYSQL_FLAG_UNIQUE_KEY |
            Column::MYSQL_FLAG_NO_DEFAULT_VALUE |
            Column::MYSQL_FLAG_PART_KEY,
            $categoryNameColumn->getFlags()
        );
        $this->assertFalse($categoryNameColumn->isDynamic());
        $this->assertNull($categoryNameColumn->getDynamicAlias());
        $this->assertNull($categoryNameColumn->getDefault());
        $this->assertFalse($categoryNameColumn->hasDefault());
        $this->assertNull($categoryNameColumn->getDefaultValue());
    }

    public function testGetFlagNames()
    {
        $column = $this->db()->category->changed_at;
        $refClass = new \ReflectionClass(get_class($column));
        $flagsProp = $refClass->getProperty('flags');
        $flagsProp->setAccessible(true);
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
    }

}
