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
            Column::MYSQL_FLAG_NOT_NULL
            | Column::MYSQL_FLAG_UNIQUE_KEY
            | Column::MYSQL_FLAG_NO_DEFAULT_VALUE
            | Column::MYSQL_FLAG_PART_KEY,
            $categoryNameColumn->getFlags()
        );
        $this->assertFalse($categoryNameColumn->isDynamic());
        $this->assertNull($categoryNameColumn->getDynamicAlias());
        $this->assertNull($categoryNameColumn->getDefault());
        $this->assertFalse($categoryNameColumn->hasDefault());
        $this->assertNull($categoryNameColumn->getDefaultValue());
    }

    public function testColumnFlagAccess()
    {
        $column = $this->db()->category->id;
        $this->assertTrue($column->getFlag(Column::MYSQL_FLAG_NUM));
        $this->assertTrue($column->getFlag(Column::MYSQL_FLAG_UNSIGNED));
        $this->assertTrue($column->getFlag(Column::MYSQL_FLAG_NOT_NULL));
        $this->assertTrue($column->getFlag(Column::MYSQL_FLAG_PRI_KEY));
        $this->assertTrue($column->getFlag(Column::MYSQL_FLAG_AUTO_INCREMENT));
        $this->assertFalse($column->getFlag(Column::MYSQL_FLAG_BLOB));
        $this->assertFalse($column->getFlag(Column::MYSQL_FLAG_UNIQUE_KEY));
        $this->assertFalse($column->getFlag(Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
        $this->assertTrue($column->getEachFlag(
            Column::MYSQL_FLAG_NUM
            | Column::MYSQL_FLAG_UNSIGNED
            | Column::MYSQL_FLAG_NOT_NULL
            | Column::MYSQL_FLAG_PRI_KEY
            | Column::MYSQL_FLAG_AUTO_INCREMENT
        ));
        $this->assertFalse($column->getEachFlag(
            Column::MYSQL_FLAG_NUM
            | Column::MYSQL_FLAG_UNSIGNED
            | Column::MYSQL_FLAG_NOT_NULL
            | Column::MYSQL_FLAG_PRI_KEY
            | Column::MYSQL_FLAG_AUTO_INCREMENT
            | Column::MYSQL_FLAG_BLOB
        ));
    }

    public function testColumnOptionsManipulation()
    {
        $column = $this->db()->category->id;
        $this->assertSame(0, $column->getOptions());
        $column->setOptions(Column::OPTION_MERGE_NO_UPDATE);
        $this->assertSame(Column::OPTION_MERGE_NO_UPDATE, $column->getOptions());
        $this->assertTrue($column->getOption(Column::OPTION_MERGE_NO_UPDATE));
        $this->assertFalse($column->getOption(Column::OPTION_NO_AUTO_FOREIGN_KEY));
        $this->assertTrue($column->getEachOption(Column::OPTION_MERGE_NO_UPDATE));
        $this->assertFalse($column->getEachOption(
            Column::OPTION_MERGE_NO_UPDATE
            | Column::OPTION_NO_AUTO_FOREIGN_KEY
        ));
        $column->setOptions(
            Column::OPTION_MERGE_NO_UPDATE
            | Column::OPTION_NO_AUTO_FOREIGN_KEY
        );
        $this->assertSame(
            Column::OPTION_MERGE_NO_UPDATE
            | Column::OPTION_NO_AUTO_FOREIGN_KEY,
            $column->getOptions()
        );
        $this->assertTrue($column->getOption(Column::OPTION_MERGE_NO_UPDATE));
        $this->assertTrue($column->getOption(Column::OPTION_NO_AUTO_FOREIGN_KEY));
        $this->assertTrue($column->getEachOption(Column::OPTION_MERGE_NO_UPDATE));
        $this->assertTrue($column->getEachOption(Column::OPTION_NO_AUTO_FOREIGN_KEY));
        $this->assertTrue($column->getEachOption(
            Column::OPTION_MERGE_NO_UPDATE
            | Column::OPTION_NO_AUTO_FOREIGN_KEY
        ));
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

    public function testDynamicDefaults()
    {
        $table = $this->db()->category;
        $table->created_at->setDynamicDefault(Q::func('NOW'));
        $row1 = $table->newRow();
        $row1->name = 'Arbitrary';
        $row1->save();
        $this->assertNotNull($row1->created_at);
        $this->assertNotSame('0000-00-00 00:00:00', $row1->created_at);
        $this->assertGreaterThan(time() - 1, strtotime($row1->created_at));
        $table->name->setDynamicDefault(Q::func('CONCAT', Q::func('DATABASE'), 'X'));
        $row2 = $table->newRow();
        $row2->save();
        $this->assertNotEquals($row1->id, $row2->id);
        $this->assertEquals(DatabaseTestAbstract::TEST_DATABASE_NAME . 'X', $row2->name);
        $this->assertNotNull($row2->created_at);
        $this->assertNotSame('0000-00-00 00:00:00', $row2->created_at);
        $this->assertGreaterThan(time() - 1, strtotime($row2->created_at));
        $row3 = $table->newRow();
        $row3->name = 'Undefaulted';
        $row3->created_at = '2001-01-01 12:00:00';
        $row3->save();
        $this->assertSame('Undefaulted', $row3->name);
        $this->assertSame('2001-01-01 12:00:00', $row3->created_at);
    }

}
