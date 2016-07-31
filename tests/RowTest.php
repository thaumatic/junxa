<?php

namespace Thaumatic\Junxa\Tests;

use Thaumatic\Junxa\Column;
use Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException;
use Thaumatic\Junxa\Query as Q;
use Thaumatic\Junxa\Tests\DatabaseTestAbstract;

class RowTest extends DatabaseTestAbstract
{

    public function testFieldInteractionWithIsset()
    {
        try {
            $categoryRow = $this->db()->category->newRow();
            $this->assertFalse(isset($categoryRow->id));
            $this->assertFalse(isset($categoryRow->name));
            $this->assertFalse(isset($categoryRow->created_at));
            $this->assertFalse(isset($categoryRow->changed_at));
            $this->assertFalse(isset($categoryRow->nonexistent_column));
            $categoryRow->name = 'Uncategorized';
            $categoryRow->created_at = Q::func('NOW');
            $this->assertFalse(isset($categoryRow->id));
            $this->assertTrue(isset($categoryRow->name));
            $this->assertTrue(isset($categoryRow->created_at));
            $this->assertFalse(isset($categoryRow->changed_at));
            $this->assertFalse(isset($categoryRow->nonexistent_column));
            $categoryRow->save();
            $this->assertTrue(isset($categoryRow->id));
            $this->assertTrue(isset($categoryRow->name));
            $this->assertTrue(isset($categoryRow->created_at));
            $this->assertTrue(isset($categoryRow->changed_at));
            $this->assertFalse(isset($categoryRow->nonexistent_column));
        } finally {
            if (isset($categoryRow) && $categoryRow->id !== null) {
                $categoryRow->delete();
            }
        }
    }

    public function testFieldInteractionWithEmpty()
    {
        try {
            $categoryRow = $this->db()->category->newRow();
            $this->assertTrue(empty($categoryRow->id));
            $this->assertTrue(empty($categoryRow->name));
            $this->assertTrue(empty($categoryRow->created_at));
            $this->assertTrue(empty($categoryRow->changed_at));
            $this->assertTrue(empty($categoryRow->nonexistent_column));
            $categoryRow->name = 'Uncategorized';
            $categoryRow->created_at = Q::func('NOW');
            $this->assertTrue(empty($categoryRow->id));
            $this->assertFalse(empty($categoryRow->name));
            $this->assertFalse(empty($categoryRow->created_at));
            $this->assertTrue(empty($categoryRow->changed_at));
            $this->assertTrue(empty($categoryRow->nonexistent_column));
            $categoryRow->save();
            $this->assertFalse(empty($categoryRow->id));
            $this->assertFalse(empty($categoryRow->name));
            $this->assertFalse(empty($categoryRow->created_at));
            $this->assertFalse(empty($categoryRow->changed_at));
            $this->assertTrue(empty($categoryRow->nonexistent_column));
        } finally {
            if (isset($categoryRow) && $categoryRow->id !== null) {
                $categoryRow->delete();
            }
        }
    }

    public function testExceptionOnNonexistentColumn()
    {
        try {
            $categoryRow = $this->db()->category->newRow();
            try {
                $value = $categoryRow->nonexistent_column;
                $this->fail('was able to access nonexistent_column');
            } catch (JunxaNoSuchColumnException $e) {
                $this->assertSame('nonexistent_column', $e->getColumnName());
            }
            try {
                $categoryRow->nonexistent_column = 'value';
                $this->fail('was able to mutate nonexistent_column');
            } catch (JunxaNoSuchColumnException $e) {
                $this->assertSame('nonexistent_column', $e->getColumnName());
            }
            $categoryRow->name = 'Uncategorized';
            $categoryRow->created_at = Q::func('NOW');
            $categoryRow->save();
            try {
                $value = $categoryRow->nonexistent_column;
                $this->fail('was able to access nonexistent_column');
            } catch (JunxaNoSuchColumnException $e) {
                $this->assertSame('nonexistent_column', $e->getColumnName());
            }
            try {
                $categoryRow->nonexistent_column = 'value';
                $this->fail('was able to mutate nonexistent_column');
            } catch (JunxaNoSuchColumnException $e) {
                $this->assertSame('nonexistent_column', $e->getColumnName());
            }
        } finally {
            if (isset($categoryRow) && $categoryRow->id !== null) {
                $categoryRow->delete();
            }
        }
    }

    public function testGetColumns()
    {
        $categoryRow = $this->db()->category->newRow();
        $this->assertSame([
            'id',
            'name',
            'type',
            'active',
            'created_at',
            'changed_at',
        ], $categoryRow->getColumns());
        $itemRow = $this->db()->item->newRow();
        $this->assertSame([
            'id',
            'category_id',
            'name',
            'active',
            'created_at',
            'changed_at',
        ], $itemRow->getColumns());
    }

    public function testGetColumnType()
    {
        $category = $this->db()->category->newRow();
        $this->assertSame('mediumint', $category->getColumnType('id'));
        $this->assertSame('varchar', $category->getColumnType('name'));
        $this->assertSame('enum', $category->getColumnType('type'));
        $this->assertSame('tinyint', $category->getColumnType('active'));
        $this->assertSame('datetime', $category->getColumnType('created_at'));
        $this->assertSame('timestamp', $category->getColumnType('changed_at'));
        $item = $this->db()->item->newRow();
        $this->assertSame('mediumint', $item->getColumnType('id'));
        $this->assertSame('mediumint', $item->getColumnType('category_id'));
        $this->assertSame('varchar', $item->getColumnType('name'));
        $this->assertSame('tinyint', $item->getColumnType('active'));
        $this->assertSame('datetime', $item->getColumnType('created_at'));
        $this->assertSame('timestamp', $item->getColumnType('changed_at'));
    }

    public function testGetColumnFullType()
    {
        $category = $this->db()->category->newRow();
        $this->assertSame('mediumint(8) unsigned', $category->getColumnFullType('id'));
        $this->assertSame('varchar(250)', $category->getColumnFullType('name'));
        $this->assertSame('enum(\'A\'\'s\',\'B\'\'s\',\'C\'\'s\')', $category->getColumnFullType('type'));
        $this->assertSame('tinyint(1)', $category->getColumnFullType('active'));
        $this->assertSame('datetime', $category->getColumnFullType('created_at'));
        $this->assertSame('timestamp', $category->getColumnFullType('changed_at'));
        $item = $this->db()->item->newRow();
        $this->assertSame('mediumint(8) unsigned', $item->getColumnFullType('id'));
        $this->assertSame('mediumint(8) unsigned', $item->getColumnFullType('category_id'));
        $this->assertSame('varchar(250)', $item->getColumnFullType('name'));
        $this->assertSame('tinyint(1)', $item->getColumnFullType('active'));
        $this->assertSame('datetime', $item->getColumnFullType('created_at'));
        $this->assertSame('timestamp', $item->getColumnFullType('changed_at'));
    }

    public function testGetColumnTypeClass()
    {
        $category = $this->db()->category->newRow();
        $this->assertSame('int', $category->getColumnTypeClass('id'));
        $this->assertSame('text', $category->getColumnTypeClass('name'));
        $this->assertSame('text', $category->getColumnTypeClass('type'));
        $this->assertSame('int', $category->getColumnTypeClass('active'));
        $this->assertSame('datetime', $category->getColumnTypeClass('created_at'));
        $this->assertSame('datetime', $category->getColumnTypeClass('changed_at'));
        $item = $this->db()->item->newRow();
        $this->assertSame('int', $item->getColumnTypeClass('id'));
        $this->assertSame('int', $item->getColumnTypeClass('category_id'));
        $this->assertSame('text', $item->getColumnTypeClass('name'));
        $this->assertSame('int', $item->getColumnTypeClass('active'));
        $this->assertSame('datetime', $item->getColumnTypeClass('created_at'));
        $this->assertSame('datetime', $item->getColumnTypeClass('changed_at'));
    }

    public function testGetColumnLength()
    {
        $category = $this->db()->category->newRow();
        $this->assertSame(8, $category->getColumnLength('id'));
        $this->assertSame(250, $category->getColumnLength('name'));
        $this->assertNull($category->getColumnLength('type'));
        $this->assertSame(1, $category->getColumnLength('active'));
        $this->assertNull($category->getColumnLength('created_at'));
        $this->assertNull($category->getColumnLength('changed_at'));
        $item = $this->db()->item->newRow();
        $this->assertSame(8, $item->getColumnLength('id'));
        $this->assertSame(8, $item->getColumnLength('category_id'));
        $this->assertSame(250, $item->getColumnLength('name'));
        $this->assertSame(1, $item->getColumnLength('active'));
        $this->assertNull($item->getColumnLength('created_at'));
        $this->assertNull($item->getColumnLength('changed_at'));
    }

    public function testGetColumnPrecision()
    {
        $category = $this->db()->category->newRow();
        $this->assertNull($category->getColumnPrecision('id'));
        $this->assertNull($category->getColumnPrecision('name'));
        $this->assertNull($category->getColumnPrecision('type'));
        $this->assertNull($category->getColumnPrecision('active'));
        $this->assertNull($category->getColumnPrecision('created_at'));
        $this->assertNull($category->getColumnPrecision('changed_at'));
        $item = $this->db()->item->newRow();
        $this->assertNull($item->getColumnPrecision('id'));
        $this->assertNull($item->getColumnPrecision('category_id'));
        $this->assertNull($item->getColumnPrecision('name'));
        $this->assertNull($item->getColumnPrecision('active'));
        $this->assertNull($item->getColumnPrecision('created_at'));
        $this->assertNull($item->getColumnPrecision('changed_at'));
    }

    public function testGetColumnFlags()
    {
        $category = $this->db()->category->newRow();
        $this->assertSame(
            Column::MYSQL_FLAG_NOT_NULL
            | Column::MYSQL_FLAG_PRI_KEY
            | Column::MYSQL_FLAG_UNSIGNED
            | Column::MYSQL_FLAG_AUTO_INCREMENT
            | Column::MYSQL_FLAG_PART_KEY
            | Column::MYSQL_FLAG_NUM,
            $category->getColumnFlags('id')
        );
        $this->assertSame(
            Column::MYSQL_FLAG_NOT_NULL
            | Column::MYSQL_FLAG_UNIQUE_KEY
            | Column::MYSQL_FLAG_NO_DEFAULT_VALUE
            | Column::MYSQL_FLAG_PART_KEY,
            $category->getColumnFlags('name')
        );
        $this->assertSame(
            Column::MYSQL_FLAG_ENUM,
            $category->getColumnFlags('type')
        );
        $this->assertSame(
            Column::MYSQL_FLAG_NOT_NULL
            | Column::MYSQL_FLAG_MULTIPLE_KEY
            | Column::MYSQL_FLAG_PART_KEY
            | Column::MYSQL_FLAG_NUM,
            $category->getColumnFlags('active')
        );
        $this->assertSame(
            Column::MYSQL_FLAG_NOT_NULL
            | Column::MYSQL_FLAG_BINARY
            | Column::MYSQL_FLAG_NO_DEFAULT_VALUE,
            $category->getColumnFlags('created_at')
        );
        $this->assertSame(
            Column::MYSQL_FLAG_NOT_NULL
            | Column::MYSQL_FLAG_UNSIGNED
            | Column::MYSQL_FLAG_ZEROFILL
            | Column::MYSQL_FLAG_BINARY
            | Column::MYSQL_FLAG_TIMESTAMP
            | Column::MYSQL_FLAG_ON_UPDATE_NOW,
            $category->getColumnFlags('changed_at')
        );
        $item = $this->db()->item->newRow();
        $this->assertSame(
            Column::MYSQL_FLAG_NOT_NULL
            | Column::MYSQL_FLAG_PRI_KEY
            | Column::MYSQL_FLAG_UNSIGNED
            | Column::MYSQL_FLAG_AUTO_INCREMENT
            | Column::MYSQL_FLAG_PART_KEY
            | Column::MYSQL_FLAG_NUM,
            $item->getColumnFlags('id')
        );
        $this->assertSame(
            Column::MYSQL_FLAG_NOT_NULL
            | Column::MYSQL_FLAG_MULTIPLE_KEY
            | Column::MYSQL_FLAG_UNSIGNED
            | Column::MYSQL_FLAG_NO_DEFAULT_VALUE
            | Column::MYSQL_FLAG_PART_KEY
            | Column::MYSQL_FLAG_NUM,
            $item->getColumnFlags('category_id')
        );
        $this->assertSame(
            Column::MYSQL_FLAG_NOT_NULL
            | Column::MYSQL_FLAG_UNIQUE_KEY
            | Column::MYSQL_FLAG_NO_DEFAULT_VALUE
            | Column::MYSQL_FLAG_PART_KEY,
            $item->getColumnFlags('name')
        );
        $this->assertSame(
            Column::MYSQL_FLAG_NOT_NULL
            | Column::MYSQL_FLAG_PART_KEY
            | Column::MYSQL_FLAG_NUM,
            $item->getColumnFlags('active')
        );
        $this->assertSame(
            Column::MYSQL_FLAG_NOT_NULL
            | Column::MYSQL_FLAG_BINARY
            | Column::MYSQL_FLAG_NO_DEFAULT_VALUE,
            $item->getColumnFlags('created_at')
        );
        $this->assertSame(
            Column::MYSQL_FLAG_NOT_NULL
            | Column::MYSQL_FLAG_UNSIGNED
            | Column::MYSQL_FLAG_ZEROFILL
            | Column::MYSQL_FLAG_BINARY
            | Column::MYSQL_FLAG_TIMESTAMP
            | Column::MYSQL_FLAG_ON_UPDATE_NOW,
            $item->getColumnFlags('changed_at')
        );
    }

    public function testGetColumnFlagNames()
    {
        $category = $this->db()->category->newRow();
        $this->assertSame(
            [
                'NOT_NULL',
                'PRI_KEY',
                'UNSIGNED',
                'AUTO_INCREMENT',
                'PART_KEY',
                'NUM',
            ],
            $category->getColumnFlagNames('id')
        );
        $this->assertSame(
            [
                'NOT_NULL',
                'UNIQUE_KEY',
                'NO_DEFAULT_VALUE',
                'PART_KEY',
            ],
            $category->getColumnFlagNames('name')
        );
        $this->assertSame(
            [
                'ENUM',
            ],
            $category->getColumnFlagNames('type')
        );
        $this->assertSame(
            [
                'NOT_NULL',
                'MULTIPLE_KEY',
                'PART_KEY',
                'NUM',
            ],
            $category->getColumnFlagNames('active')
        );
        $this->assertSame(
            [
                'NOT_NULL',
                'BINARY',
                'NO_DEFAULT_VALUE',
            ],
            $category->getColumnFlagNames('created_at')
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
            $category->getColumnFlagNames('changed_at')
        );
        $item = $this->db()->item->newRow();
        $this->assertSame(
            [
                'NOT_NULL',
                'PRI_KEY',
                'UNSIGNED',
                'AUTO_INCREMENT',
                'PART_KEY',
                'NUM',
            ],
            $item->getColumnFlagNames('id')
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
            $item->getColumnFlagNames('category_id')
        );
        $this->assertSame(
            [
                'NOT_NULL',
                'UNIQUE_KEY',
                'NO_DEFAULT_VALUE',
                'PART_KEY',
            ],
            $item->getColumnFlagNames('name')
        );
        $this->assertSame(
            [
                'NOT_NULL',
                'PART_KEY',
                'NUM',
            ],
            $item->getColumnFlagNames('active')
        );
        $this->assertSame(
            [
                'NOT_NULL',
                'BINARY',
                'NO_DEFAULT_VALUE',
            ],
            $item->getColumnFlagNames('created_at')
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
            $item->getColumnFlagNames('changed_at')
        );
    }

    public function testGetForeignRow()
    {
        try {
            $createCategoryRow = $this->db()->category->newRow();
            $createCategoryRow->name = 'Uncategorized';
            $createCategoryRow->created_at = Q::func('NOW');
            $createCategoryRow->save();
            $createItemRow = $this->db()->item->newRow();
            $createItemRow->category_id = $createCategoryRow->id;
            $createItemRow->name = 'Widget';
            $createItemRow->created_at = Q::func('NOW');
            $createItemRow->save();
            $itemRow = $this->db()->item->row($createItemRow->id);
            $this->assertEquals($createItemRow, $itemRow);
            $this->assertEquals('Widget', $itemRow->name);
            $categoryRow = $itemRow->getForeignRow('category_id');
            $this->assertEquals($createCategoryRow, $categoryRow);
            $this->assertEquals('Uncategorized', $categoryRow->name);
        } finally {
            if (isset($createCategoryRow) && $createCategoryRow->id !== null) {
                $createCategoryRow->delete();
            }
            if (isset($createItemRow) && $createItemRow->id !== null) {
                $createItemRow->delete();
            }
        }
    }

}
