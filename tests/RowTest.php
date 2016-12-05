<?php

namespace Thaumatic\Junxa\Tests;

use Thaumatic\Junxa;
use Thaumatic\Junxa\Column;
use Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException;
use Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException;
use Thaumatic\Junxa\Exceptions\JunxaNoSuchTableException;
use Thaumatic\Junxa\Query as Q;
use Thaumatic\Junxa\Query\Builder as QueryBuilder;
use Thaumatic\Junxa\Tests\DatabaseTestAbstract;

class RowTest extends DatabaseTestAbstract
{

    const PATTERN_DATETIME_ANCHORED = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';

    public function testFieldInteractionWithIsset()
    {
        $categoryRow = $this->db->category->newRow();
        $this->addGeneratedRow($categoryRow);
        $this->assertFalse(isset($categoryRow->id));
        $this->assertFalse(isset($categoryRow->name));
        $this->assertFalse(isset($categoryRow->type));
        $this->assertFalse(isset($categoryRow->createdAt));
        $this->assertFalse(isset($categoryRow->changedAt));
        $this->assertFalse(isset($categoryRow->nonexistent_column));
        $categoryRow->name = 'Uncategorized';
        $categoryRow->createdAt = Q::func('NOW');
        $this->assertFalse(isset($categoryRow->id));
        $this->assertTrue(isset($categoryRow->name));
        $this->assertFalse(isset($categoryRow->type));
        $this->assertTrue(isset($categoryRow->createdAt));
        $this->assertFalse(isset($categoryRow->changedAt));
        $this->assertFalse(isset($categoryRow->nonexistent_column));
        $categoryRow->save();
        $this->assertTrue(isset($categoryRow->id));
        $this->assertTrue(isset($categoryRow->name));
        $this->assertFalse(isset($categoryRow->type));
        $this->assertTrue(isset($categoryRow->createdAt));
        $this->assertTrue(isset($categoryRow->changedAt));
        $this->assertFalse(isset($categoryRow->nonexistent_column));
    }

    public function testFieldInteractionWithEmpty()
    {
        $categoryRow = $this->db->category->newRow();
        $this->addGeneratedRow($categoryRow);
        $this->assertTrue(empty($categoryRow->id));
        $this->assertTrue(empty($categoryRow->name));
        $this->assertTrue(empty($categoryRow->type));
        $this->assertTrue(empty($categoryRow->createdAt));
        $this->assertTrue(empty($categoryRow->changedAt));
        $this->assertTrue(empty($categoryRow->nonexistent_column));
        $categoryRow->name = 'Uncategorized';
        $categoryRow->createdAt = Q::func('NOW');
        $this->assertTrue(empty($categoryRow->id));
        $this->assertFalse(empty($categoryRow->name));
        $this->assertTrue(empty($categoryRow->type));
        $this->assertFalse(empty($categoryRow->createdAt));
        $this->assertTrue(empty($categoryRow->changedAt));
        $this->assertTrue(empty($categoryRow->nonexistent_column));
        $categoryRow->save();
        $this->assertFalse(empty($categoryRow->id));
        $this->assertFalse(empty($categoryRow->name));
        $this->assertTrue(empty($categoryRow->type));
        $this->assertFalse(empty($categoryRow->createdAt));
        $this->assertFalse(empty($categoryRow->changedAt));
        $this->assertTrue(empty($categoryRow->nonexistent_column));
    }

    public function testExceptionOnNonexistentColumn()
    {
        $categoryRow = $this->db->category->newRow();
        $this->addGeneratedRow($categoryRow);
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
        $categoryRow->createdAt = Q::func('NOW');
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
    }

    public function testGetColumns()
    {
        $categoryRow = $this->db->category->newRow();
        $this->assertSame([
            'id',
            'name',
            'type',
            'active',
            'createdAt',
            'changedAt',
        ], $categoryRow->getColumns());
        $itemRow = $this->db->item->newRow();
        $this->assertSame([
            'id',
            'categoryId',
            'name',
            'price',
            'active',
            'createdAt',
            'changedAt',
        ], $itemRow->getColumns());
    }

    public function testGetColumnType()
    {
        $category = $this->db->category->newRow();
        $this->assertSame('mediumint', $category->getColumnType('id'));
        $this->assertSame('varchar', $category->getColumnType('name'));
        $this->assertSame('enum', $category->getColumnType('type'));
        $this->assertSame('tinyint', $category->getColumnType('active'));
        $this->assertSame('datetime', $category->getColumnType('createdAt'));
        $this->assertSame('timestamp', $category->getColumnType('changedAt'));
        $item = $this->db->item->newRow();
        $this->assertSame('mediumint', $item->getColumnType('id'));
        $this->assertSame('mediumint', $item->getColumnType('categoryId'));
        $this->assertSame('varchar', $item->getColumnType('name'));
        $this->assertSame('decimal', $item->getColumnType('price'));
        $this->assertSame('tinyint', $item->getColumnType('active'));
        $this->assertSame('datetime', $item->getColumnType('createdAt'));
        $this->assertSame('timestamp', $item->getColumnType('changedAt'));
    }

    public function testGetColumnFullType()
    {
        $category = $this->db->category->newRow();
        $this->assertSame('mediumint(8) unsigned', $category->getColumnFullType('id'));
        $this->assertSame('varchar(250)', $category->getColumnFullType('name'));
        $this->assertSame('enum(\'A\'\'s\',\'B\'\'s\',\'C\'\'s\')', $category->getColumnFullType('type'));
        $this->assertSame('tinyint(1)', $category->getColumnFullType('active'));
        $this->assertSame('datetime', $category->getColumnFullType('createdAt'));
        $this->assertSame('timestamp', $category->getColumnFullType('changedAt'));
        $item = $this->db->item->newRow();
        $this->assertSame('mediumint(8) unsigned', $item->getColumnFullType('id'));
        $this->assertSame('mediumint(8) unsigned', $item->getColumnFullType('categoryId'));
        $this->assertSame('varchar(250)', $item->getColumnFullType('name'));
        $this->assertSame('decimal(10,2)', $item->getColumnFullType('price'));
        $this->assertSame('tinyint(1)', $item->getColumnFullType('active'));
        $this->assertSame('datetime', $item->getColumnFullType('createdAt'));
        $this->assertSame('timestamp', $item->getColumnFullType('changedAt'));
    }

    public function testGetColumnTypeClass()
    {
        $category = $this->db->category->newRow();
        $this->assertSame('int', $category->getColumnTypeClass('id'));
        $this->assertSame('text', $category->getColumnTypeClass('name'));
        $this->assertSame('text', $category->getColumnTypeClass('type'));
        $this->assertSame('int', $category->getColumnTypeClass('active'));
        $this->assertSame('datetime', $category->getColumnTypeClass('createdAt'));
        $this->assertSame('datetime', $category->getColumnTypeClass('changedAt'));
        $item = $this->db->item->newRow();
        $this->assertSame('int', $item->getColumnTypeClass('id'));
        $this->assertSame('int', $item->getColumnTypeClass('categoryId'));
        $this->assertSame('text', $item->getColumnTypeClass('name'));
        $this->assertSame('float', $item->getColumnTypeClass('price'));
        $this->assertSame('int', $item->getColumnTypeClass('active'));
        $this->assertSame('datetime', $item->getColumnTypeClass('createdAt'));
        $this->assertSame('datetime', $item->getColumnTypeClass('changedAt'));
    }

    public function testGetColumnLength()
    {
        $category = $this->db->category->newRow();
        $this->assertSame(8, $category->getColumnLength('id'));
        $this->assertSame(250, $category->getColumnLength('name'));
        $this->assertNull($category->getColumnLength('type'));
        $this->assertSame(1, $category->getColumnLength('active'));
        $this->assertNull($category->getColumnLength('createdAt'));
        $this->assertNull($category->getColumnLength('changedAt'));
        $item = $this->db->item->newRow();
        $this->assertSame(8, $item->getColumnLength('id'));
        $this->assertSame(8, $item->getColumnLength('categoryId'));
        $this->assertSame(250, $item->getColumnLength('name'));
        $this->assertSame(10, $item->getColumnLength('price'));
        $this->assertSame(1, $item->getColumnLength('active'));
        $this->assertNull($item->getColumnLength('createdAt'));
        $this->assertNull($item->getColumnLength('changedAt'));
    }

    public function testGetColumnPrecision()
    {
        $category = $this->db->category->newRow();
        $this->assertNull($category->getColumnPrecision('id'));
        $this->assertNull($category->getColumnPrecision('name'));
        $this->assertNull($category->getColumnPrecision('type'));
        $this->assertNull($category->getColumnPrecision('active'));
        $this->assertNull($category->getColumnPrecision('createdAt'));
        $this->assertNull($category->getColumnPrecision('changedAt'));
        $item = $this->db->item->newRow();
        $this->assertNull($item->getColumnPrecision('id'));
        $this->assertNull($item->getColumnPrecision('categoryId'));
        $this->assertNull($item->getColumnPrecision('name'));
        $this->assertSame(2, $item->getColumnPrecision('price'));
        $this->assertNull($item->getColumnPrecision('active'));
        $this->assertNull($item->getColumnPrecision('createdAt'));
        $this->assertNull($item->getColumnPrecision('changedAt'));
    }

    public function testGetColumnFlags()
    {
        $category = $this->db->category->newRow();
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
            $category->getColumnFlags('createdAt')
        );
        $item = $this->db->item->newRow();
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
            $item->getColumnFlags('categoryId')
        );
        $this->assertSame(
            Column::MYSQL_FLAG_NOT_NULL
            | Column::MYSQL_FLAG_UNIQUE_KEY
            | Column::MYSQL_FLAG_NO_DEFAULT_VALUE
            | Column::MYSQL_FLAG_PART_KEY,
            $item->getColumnFlags('name')
        );
        $this->assertSame(
            0,
            $item->getColumnFlags('price')
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
            $item->getColumnFlags('createdAt')
        );
    }

    public function testGetColumnFlagNames()
    {
        $category = $this->db->category->newRow();
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
            $category->getColumnFlagNames('createdAt')
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
            $category->getColumnFlagNames('changedAt')
        );
        $item = $this->db->item->newRow();
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
            $item->getColumnFlagNames('categoryId')
        );
        $this->assertSame(
            [],
            $item->getColumnFlagNames('price')
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
            $item->getColumnFlagNames('createdAt')
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
            $item->getColumnFlagNames('changedAt')
        );
    }

    public function testGetColumnFlag()
    {
        $category = $this->db->category->newRow();
        $this->assertTrue($category->getColumnFlag('id', Column::MYSQL_FLAG_NOT_NULL));
        $this->assertTrue($category->getColumnFlag('id', Column::MYSQL_FLAG_PRI_KEY));
        $this->assertTrue($category->getColumnFlag('id', Column::MYSQL_FLAG_UNSIGNED));
        $this->assertTrue($category->getColumnFlag('id', Column::MYSQL_FLAG_AUTO_INCREMENT));
        $this->assertTrue($category->getColumnFlag('id', Column::MYSQL_FLAG_PART_KEY));
        $this->assertTrue($category->getColumnFlag('id', Column::MYSQL_FLAG_NUM));
        $this->assertTrue(
            $category->getColumnFlag(
                'id',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_PRI_KEY
            )
        );
        $this->assertTrue(
            $category->getColumnFlag(
                'id',
                Column::MYSQL_FLAG_PART_KEY
                | Column::MYSQL_FLAG_NUM
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($category->getColumnFlag('id', Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
        $this->assertTrue($category->getColumnFlag('name', Column::MYSQL_FLAG_NOT_NULL));
        $this->assertTrue($category->getColumnFlag('name', Column::MYSQL_FLAG_UNIQUE_KEY));
        $this->assertTrue($category->getColumnFlag('name', Column::MYSQL_FLAG_NO_DEFAULT_VALUE));
        $this->assertTrue($category->getColumnFlag('name', Column::MYSQL_FLAG_PART_KEY));
        $this->assertTrue(
            $category->getColumnFlag(
                'name',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_UNIQUE_KEY
            )
        );
        $this->assertTrue(
            $category->getColumnFlag(
                'name',
                Column::MYSQL_FLAG_NO_DEFAULT_VALUE
                | Column::MYSQL_FLAG_PART_KEY
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($category->getColumnFlag('name', Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
        $this->assertTrue($category->getColumnFlag('type', Column::MYSQL_FLAG_ENUM));
        $this->assertTrue(
            $category->getColumnFlag(
                'type',
                Column::MYSQL_FLAG_ENUM
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($category->getColumnFlag('type', Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
        $this->assertTrue($category->getColumnFlag('active', Column::MYSQL_FLAG_NOT_NULL));
        $this->assertTrue($category->getColumnFlag('active', Column::MYSQL_FLAG_MULTIPLE_KEY));
        $this->assertTrue($category->getColumnFlag('active', Column::MYSQL_FLAG_PART_KEY));
        $this->assertTrue($category->getColumnFlag('active', Column::MYSQL_FLAG_NUM));
        $this->assertTrue(
            $category->getColumnFlag(
                'active',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_MULTIPLE_KEY
            )
        );
        $this->assertTrue(
            $category->getColumnFlag(
                'active',
                Column::MYSQL_FLAG_PART_KEY
                | Column::MYSQL_FLAG_NUM
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($category->getColumnFlag('active', Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
        $this->assertTrue($category->getColumnFlag('createdAt', Column::MYSQL_FLAG_NOT_NULL));
        $this->assertTrue($category->getColumnFlag('createdAt', Column::MYSQL_FLAG_BINARY));
        $this->assertTrue($category->getColumnFlag('createdAt', Column::MYSQL_FLAG_NO_DEFAULT_VALUE));
        $this->assertTrue(
            $category->getColumnFlag(
                'createdAt',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_BINARY
            )
        );
        $this->assertTrue(
            $category->getColumnFlag(
                'createdAt',
                Column::MYSQL_FLAG_BINARY
                | Column::MYSQL_FLAG_NO_DEFAULT_VALUE
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($category->getColumnFlag('createdAt', Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
        $this->assertTrue($category->getColumnFlag('changedAt', Column::MYSQL_FLAG_NOT_NULL));
        $this->assertTrue($category->getColumnFlag('changedAt', Column::MYSQL_FLAG_UNSIGNED));
        $this->assertTrue($category->getColumnFlag('changedAt', Column::MYSQL_FLAG_ZEROFILL));
        $this->assertTrue($category->getColumnFlag('changedAt', Column::MYSQL_FLAG_BINARY));
        $this->assertTrue($category->getColumnFlag('changedAt', Column::MYSQL_FLAG_TIMESTAMP));
        $this->assertTrue($category->getColumnFlag('changedAt', Column::MYSQL_FLAG_ON_UPDATE_NOW));
        $this->assertTrue(
            $category->getColumnFlag(
                'changedAt',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_UNSIGNED
            )
        );
        $this->assertTrue(
            $category->getColumnFlag(
                'changedAt',
                Column::MYSQL_FLAG_TIMESTAMP
                | Column::MYSQL_FLAG_ON_UPDATE_NOW
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($category->getColumnFlag('changedAt', Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
        $item = $this->db->item->newRow();
        $this->assertTrue($item->getColumnFlag('id', Column::MYSQL_FLAG_NOT_NULL));
        $this->assertTrue($item->getColumnFlag('id', Column::MYSQL_FLAG_PRI_KEY));
        $this->assertTrue($item->getColumnFlag('id', Column::MYSQL_FLAG_UNSIGNED));
        $this->assertTrue($item->getColumnFlag('id', Column::MYSQL_FLAG_AUTO_INCREMENT));
        $this->assertTrue($item->getColumnFlag('id', Column::MYSQL_FLAG_PART_KEY));
        $this->assertTrue($item->getColumnFlag('id', Column::MYSQL_FLAG_NUM));
        $this->assertTrue(
            $item->getColumnFlag(
                'id',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_PRI_KEY
            )
        );
        $this->assertTrue(
            $item->getColumnFlag(
                'id',
                Column::MYSQL_FLAG_PART_KEY
                | Column::MYSQL_FLAG_NUM
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($item->getColumnFlag('id', Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
        $this->assertTrue($item->getColumnFlag('categoryId', Column::MYSQL_FLAG_NOT_NULL));
        $this->assertTrue($item->getColumnFlag('categoryId', Column::MYSQL_FLAG_MULTIPLE_KEY));
        $this->assertTrue($item->getColumnFlag('categoryId', Column::MYSQL_FLAG_UNSIGNED));
        $this->assertTrue($item->getColumnFlag('categoryId', Column::MYSQL_FLAG_NO_DEFAULT_VALUE));
        $this->assertTrue($item->getColumnFlag('categoryId', Column::MYSQL_FLAG_PART_KEY));
        $this->assertTrue($item->getColumnFlag('categoryId', Column::MYSQL_FLAG_NUM));
        $this->assertTrue(
            $item->getColumnFlag(
                'categoryId',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_MULTIPLE_KEY
            )
        );
        $this->assertTrue(
            $item->getColumnFlag(
                'categoryId',
                Column::MYSQL_FLAG_PART_KEY
                | Column::MYSQL_FLAG_NUM
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($item->getColumnFlag('categoryId', Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
        $this->assertTrue($item->getColumnFlag('name', Column::MYSQL_FLAG_NOT_NULL));
        $this->assertTrue($item->getColumnFlag('name', Column::MYSQL_FLAG_UNIQUE_KEY));
        $this->assertTrue($item->getColumnFlag('name', Column::MYSQL_FLAG_NO_DEFAULT_VALUE));
        $this->assertTrue($item->getColumnFlag('name', Column::MYSQL_FLAG_PART_KEY));
        $this->assertTrue(
            $item->getColumnFlag(
                'name',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_UNIQUE_KEY
            )
        );
        $this->assertTrue(
            $item->getColumnFlag(
                'name',
                Column::MYSQL_FLAG_NO_DEFAULT_VALUE
                | Column::MYSQL_FLAG_PART_KEY
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($item->getColumnFlag('name', Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
        $this->assertFalse($item->getColumnFlag('price', Column::MYSQL_FLAG_NUM));
        $this->assertFalse($item->getColumnFlag('price', Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
        $this->assertTrue($item->getColumnFlag('active', Column::MYSQL_FLAG_NOT_NULL));
        $this->assertTrue($item->getColumnFlag('active', Column::MYSQL_FLAG_PART_KEY));
        $this->assertTrue($item->getColumnFlag('active', Column::MYSQL_FLAG_NUM));
        $this->assertTrue(
            $item->getColumnFlag(
                'active',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_PART_KEY
            )
        );
        $this->assertTrue(
            $item->getColumnFlag(
                'active',
                Column::MYSQL_FLAG_PART_KEY
                | Column::MYSQL_FLAG_NUM
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($item->getColumnFlag('active', Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
        $this->assertTrue($item->getColumnFlag('createdAt', Column::MYSQL_FLAG_NOT_NULL));
        $this->assertTrue($item->getColumnFlag('createdAt', Column::MYSQL_FLAG_BINARY));
        $this->assertTrue($item->getColumnFlag('createdAt', Column::MYSQL_FLAG_NO_DEFAULT_VALUE));
        $this->assertTrue(
            $item->getColumnFlag(
                'createdAt',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_BINARY
            )
        );
        $this->assertTrue(
            $item->getColumnFlag(
                'createdAt',
                Column::MYSQL_FLAG_BINARY
                | Column::MYSQL_FLAG_NO_DEFAULT_VALUE
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($item->getColumnFlag('createdAt', Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
        $this->assertTrue($item->getColumnFlag('changedAt', Column::MYSQL_FLAG_NOT_NULL));
        $this->assertTrue($item->getColumnFlag('changedAt', Column::MYSQL_FLAG_UNSIGNED));
        $this->assertTrue($item->getColumnFlag('changedAt', Column::MYSQL_FLAG_ZEROFILL));
        $this->assertTrue($item->getColumnFlag('changedAt', Column::MYSQL_FLAG_BINARY));
        $this->assertTrue($item->getColumnFlag('changedAt', Column::MYSQL_FLAG_TIMESTAMP));
        $this->assertTrue($item->getColumnFlag('changedAt', Column::MYSQL_FLAG_ON_UPDATE_NOW));
        $this->assertTrue(
            $item->getColumnFlag(
                'changedAt',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_UNSIGNED
            )
        );
        $this->assertTrue(
            $item->getColumnFlag(
                'changedAt',
                Column::MYSQL_FLAG_TIMESTAMP
                | Column::MYSQL_FLAG_ON_UPDATE_NOW
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($item->getColumnFlag('changedAt', Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
    }

    public function testGetColumnEachFlag()
    {
        $category = $this->db->category->newRow();
        $this->assertTrue(
            $category->getColumnEachFlag(
                'id',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_PRI_KEY
                | Column::MYSQL_FLAG_UNSIGNED
                | Column::MYSQL_FLAG_AUTO_INCREMENT
                | Column::MYSQL_FLAG_PART_KEY
                | Column::MYSQL_FLAG_NUM
            )
        );
        $this->assertFalse(
            $category->getColumnEachFlag(
                'id',
                Column::MYSQL_FLAG_PRI_KEY
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertTrue(
            $category->getColumnEachFlag(
                'name',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_UNIQUE_KEY
                | Column::MYSQL_FLAG_NO_DEFAULT_VALUE
                | Column::MYSQL_FLAG_PART_KEY
            )
        );
        $this->assertFalse(
            $category->getColumnEachFlag(
                'name',
                Column::MYSQL_FLAG_UNIQUE_KEY
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertTrue(
            $category->getColumnEachFlag(
                'type',
                Column::MYSQL_FLAG_ENUM
            )
        );
        $this->assertFalse(
            $category->getColumnEachFlag(
                'type',
                Column::MYSQL_FLAG_ENUM
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertTrue(
            $category->getColumnEachFlag(
                'active',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_MULTIPLE_KEY
                | Column::MYSQL_FLAG_PART_KEY
                | Column::MYSQL_FLAG_NUM
            )
        );
        $this->assertFalse(
            $category->getColumnEachFlag(
                'active',
                Column::MYSQL_FLAG_MULTIPLE_KEY
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertTrue(
            $category->getColumnEachFlag(
                'createdAt',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_BINARY
                | Column::MYSQL_FLAG_NO_DEFAULT_VALUE
            )
        );
        $this->assertFalse(
            $category->getColumnEachFlag(
                'createdAt',
                Column::MYSQL_FLAG_BINARY
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertTrue(
            $category->getColumnEachFlag(
                'changedAt',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_UNSIGNED
                | Column::MYSQL_FLAG_ZEROFILL
                | Column::MYSQL_FLAG_BINARY
                | Column::MYSQL_FLAG_TIMESTAMP
                | Column::MYSQL_FLAG_ON_UPDATE_NOW
            )
        );
        $this->assertFalse(
            $category->getColumnEachFlag(
                'changedAt',
                Column::MYSQL_FLAG_UNSIGNED
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $item = $this->db->item->newRow();
        $this->assertTrue(
            $item->getColumnEachFlag(
                'id',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_PRI_KEY
                | Column::MYSQL_FLAG_UNSIGNED
                | Column::MYSQL_FLAG_AUTO_INCREMENT
                | Column::MYSQL_FLAG_PART_KEY
                | Column::MYSQL_FLAG_NUM
            )
        );
        $this->assertFalse(
            $item->getColumnEachFlag(
                'id',
                Column::MYSQL_FLAG_PRI_KEY
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertTrue(
            $item->getColumnEachFlag(
                'categoryId',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_MULTIPLE_KEY
                | Column::MYSQL_FLAG_UNSIGNED
                | Column::MYSQL_FLAG_NO_DEFAULT_VALUE
                | Column::MYSQL_FLAG_PART_KEY
                | Column::MYSQL_FLAG_NUM
            )
        );
        $this->assertFalse(
            $item->getColumnEachFlag(
                'categoryId',
                Column::MYSQL_FLAG_MULTIPLE_KEY
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertTrue(
            $item->getColumnEachFlag(
                'name',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_UNIQUE_KEY
                | Column::MYSQL_FLAG_NO_DEFAULT_VALUE
                | Column::MYSQL_FLAG_PART_KEY
            )
        );
        $this->assertFalse(
            $item->getColumnEachFlag(
                'name',
                Column::MYSQL_FLAG_UNIQUE_KEY
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertTrue(
            $item->getColumnEachFlag(
                'price',
                0
            )
        );
        $this->assertFalse(
            $item->getColumnEachFlag(
                'price',
                Column::MYSQL_FLAG_UNIQUE_KEY
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertTrue(
            $item->getColumnEachFlag(
                'active',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_PART_KEY
                | Column::MYSQL_FLAG_NUM
            )
        );
        $this->assertFalse(
            $item->getColumnEachFlag(
                'active',
                Column::MYSQL_FLAG_PART_KEY
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertTrue(
            $item->getColumnEachFlag(
                'createdAt',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_BINARY
                | Column::MYSQL_FLAG_NO_DEFAULT_VALUE
            )
        );
        $this->assertFalse(
            $item->getColumnEachFlag(
                'createdAt',
                Column::MYSQL_FLAG_BINARY
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertTrue(
            $item->getColumnEachFlag(
                'changedAt',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_UNSIGNED
                | Column::MYSQL_FLAG_ZEROFILL
                | Column::MYSQL_FLAG_BINARY
                | Column::MYSQL_FLAG_TIMESTAMP
                | Column::MYSQL_FLAG_ON_UPDATE_NOW
            )
        );
        $this->assertFalse(
            $item->getColumnEachFlag(
                'changedAt',
                Column::MYSQL_FLAG_UNSIGNED
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
    }

    public function testGetColumnValues()
    {
        $category = $this->db->category->newRow();
        $this->assertNull($category->getColumnValues('id'));
        $this->assertNull($category->getColumnValues('name'));
        $this->assertSame([null, 'A\'s', 'B\'s', 'C\'s'], $category->getColumnValues('type'));
        $this->assertNull($category->getColumnValues('active'));
        $this->assertNull($category->getColumnValues('createdAt'));
        $this->assertNull($category->getColumnValues('changedAt'));
        $item = $this->db->item->newRow();
        $this->assertNull($item->getColumnValues('id'));
        $this->assertNull($item->getColumnValues('categoryId'));
        $this->assertNull($item->getColumnValues('name'));
        $this->assertNull($item->getColumnValues('price'));
        $this->assertNull($item->getColumnValues('active'));
        $this->assertNull($item->getColumnValues('createdAt'));
        $this->assertNull($item->getColumnValues('changedAt'));
    }

    public function testGetColumnOptions()
    {
        try {
            $category = $this->db->category->newRow();
            $column = $this->db->category->id;
            $this->assertSame(0, $category->getColumnOptions('id'));
            $column->setOptions(Column::OPTION_MERGE_NO_UPDATE);
            $this->assertSame(Column::OPTION_MERGE_NO_UPDATE, $category->getColumnOptions('id'));
            $column->setOptions(
                Column::OPTION_MERGE_NO_UPDATE
                | Column::OPTION_NO_AUTO_FOREIGN_KEY
            );
            $this->assertSame(
                Column::OPTION_MERGE_NO_UPDATE
                | Column::OPTION_NO_AUTO_FOREIGN_KEY,
                $category->getColumnOptions('id')
            );
        } finally {
            if (isset($column)) {
                $column->setOptions(0);
            }
        }
    }

    public function testGetColumnOption()
    {
        try {
            $category = $this->db->category->newRow();
            $column = $this->db->category->id;
            $this->assertFalse($category->getColumnOption('id', Column::OPTION_MERGE_NO_UPDATE));
            $this->assertFalse($category->getColumnOption('id', Column::OPTION_NO_AUTO_FOREIGN_KEY));
            $this->assertFalse(
                $category->getColumnOption(
                    'id',
                    Column::OPTION_MERGE_NO_UPDATE
                    | Column::OPTION_NO_AUTO_FOREIGN_KEY
                )
            );
            $column->setOptions(Column::OPTION_MERGE_NO_UPDATE);
            $this->assertTrue($category->getColumnOption('id', Column::OPTION_MERGE_NO_UPDATE));
            $this->assertFalse($category->getColumnOption('id', Column::OPTION_NO_AUTO_FOREIGN_KEY));
            $this->assertTrue(
                $category->getColumnOption(
                    'id',
                    Column::OPTION_MERGE_NO_UPDATE
                    | Column::OPTION_NO_AUTO_FOREIGN_KEY
                )
            );
            $column->setOptions(Column::OPTION_MERGE_NO_UPDATE | Column::OPTION_NO_AUTO_FOREIGN_KEY);
            $this->assertTrue($category->getColumnOption('id', Column::OPTION_MERGE_NO_UPDATE));
            $this->assertTrue($category->getColumnOption('id', Column::OPTION_NO_AUTO_FOREIGN_KEY));
            $this->assertTrue(
                $category->getColumnOption(
                    'id',
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

    public function testGetColumnEachOption()
    {
        try {
            $category = $this->db->category->newRow();
            $column = $this->db->category->id;
            $this->assertFalse($category->getColumnEachOption('id', Column::OPTION_MERGE_NO_UPDATE));
            $this->assertFalse($category->getColumnEachOption('id', Column::OPTION_NO_AUTO_FOREIGN_KEY));
            $this->assertFalse(
                $category->getColumnEachOption(
                    'id',
                    Column::OPTION_MERGE_NO_UPDATE
                    | Column::OPTION_NO_AUTO_FOREIGN_KEY
                )
            );
            $column->setOptions(Column::OPTION_MERGE_NO_UPDATE);
            $this->assertTrue($category->getColumnEachOption('id', Column::OPTION_MERGE_NO_UPDATE));
            $this->assertFalse($category->getColumnEachOption('id', Column::OPTION_NO_AUTO_FOREIGN_KEY));
            $this->assertFalse(
                $category->getColumnEachOption(
                    'id',
                    Column::OPTION_MERGE_NO_UPDATE
                    | Column::OPTION_NO_AUTO_FOREIGN_KEY
                )
            );
            $column->setOptions(Column::OPTION_MERGE_NO_UPDATE | Column::OPTION_NO_AUTO_FOREIGN_KEY);
            $this->assertTrue($category->getColumnEachOption('id', Column::OPTION_MERGE_NO_UPDATE));
            $this->assertTrue($category->getColumnEachOption('id', Column::OPTION_NO_AUTO_FOREIGN_KEY));
            $this->assertTrue(
                $category->getColumnOption(
                    'id',
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

    public function testFind()
    {
        $createCategoryRow1 = $this->db->category->newRow()
            ->setField('name', 'Uncategorized')
            ->setField('type', 'A\'s')
            ->setField('createdAt', Q::func('NOW'))
            ->performSave();
        $this->addGeneratedRow($createCategoryRow1);
        //
        $findCategoryRow = $this->db->category->newRow()
            ->setField('name', 'Uncategorized');
        $result = $findCategoryRow->find();
        $this->assertSame(Junxa::RESULT_SUCCESS, $result);
        $this->assertSame('A\'s', $findCategoryRow->type);
        //
        $findCategoryRow = $this->db->category->newRow()
            ->setField('type', 'A\'s');
        $result = $findCategoryRow->find();
        $this->assertSame(Junxa::RESULT_SUCCESS, $result);
        $this->assertSame('A\'s', $findCategoryRow->type);
        //
        $findCategoryRow = $this->db->category->newRow()
            ->setField('name', 'Unknown');
        $result = $findCategoryRow->find();
        $this->assertSame(Junxa::RESULT_FIND_FAIL, $result);
        $this->assertNull($findCategoryRow->type);
        //
        $findCategoryRow = $this->db->category->newRow()
            ->setField('type', 'B\'s');
        $result = $findCategoryRow->find();
        $this->assertSame(Junxa::RESULT_FIND_FAIL, $result);
        $this->assertNull($findCategoryRow->name);
        //
        $createCategoryRow2 = $this->db->category->newRow()
            ->setField('name', 'Categorized')
            ->setField('type', 'A\'s')
            ->setField('createdAt', Q::func('NOW'))
            ->performSave();
        $this->addGeneratedRow($createCategoryRow2);
        //
        $findCategoryRow = $this->db->category->newRow()
            ->setField('name', 'Uncategorized');
        $result = $findCategoryRow->find();
        $this->assertSame(Junxa::RESULT_SUCCESS, $result);
        $this->assertSame('A\'s', $findCategoryRow->type);
        //
        $findCategoryRow = $this->db->category->newRow()
            ->setField('name', 'Categorized');
        $result = $findCategoryRow->find();
        $this->assertSame(Junxa::RESULT_SUCCESS, $result);
        $this->assertSame('A\'s', $findCategoryRow->type);
        //
        $findCategoryRow = $this->db->category->newRow()
            ->setField('name', 'Unknown');
        $result = $findCategoryRow->find();
        $this->assertSame(Junxa::RESULT_FIND_FAIL, $result);
        $this->assertNull($findCategoryRow->type);
        //
        $findCategoryRow = $this->db->category->newRow()
            ->setField('type', 'A\'s');
        $result = $findCategoryRow->find();
        $this->assertSame(Junxa::RESULT_FIND_EXCESS, $result);
        $this->assertSame('A\'s', $findCategoryRow->type);
        $this->assertSame('Uncategorized', $findCategoryRow->name);
        //
        $findCategoryRow = $this->db->category->newRow()
            ->setField('type', 'A\'s');
        $result = $findCategoryRow->find(
            $findCategoryRow->getTable()->query()
                ->order('id')
                ->desc()
        );
        $this->assertSame(Junxa::RESULT_FIND_EXCESS, $result);
        $this->assertSame('A\'s', $findCategoryRow->type);
        $this->assertSame('Categorized', $findCategoryRow->name);
    }

    public function testRefresh()
    {
        $createCategoryRow = $this->db->category->newRow()
            ->setField('name', 'Uncategorized')
            ->setField('type', 'A\'s')
            ->setField('createdAt', Q::func('NOW'))
            ->performSave();
        $this->addGeneratedRow($createCategoryRow);
        //
        $refreshCategoryRow = $this->db->category->newRow()
            ->setField('id', $createCategoryRow->id);
        $result = $refreshCategoryRow->refresh();
        $this->assertSame(Junxa::RESULT_SUCCESS, $result);
        $this->assertSame('Uncategorized', $refreshCategoryRow->name);
        $this->assertSame('A\'s', $refreshCategoryRow->type);
        //
        $refreshCategoryRow = $this->db->category->newRow();
        $result = $refreshCategoryRow->refresh();
        $this->assertSame(Junxa::RESULT_REFRESH_FAIL, $result);
        $this->assertNull($refreshCategoryRow->name);
        $this->assertNull($refreshCategoryRow->type);
        //
        $refreshCategoryRow = $this->db->category->newRow()
            ->setField('id', $createCategoryRow->id + 1);
        try {
            $result = $refreshCategoryRow->refresh();
            $this->fail('refresh with invalid ID did not throw exception');
        } catch (JunxaInvalidQueryException $e) {
            // expected
        }
        $this->assertNull($refreshCategoryRow->name);
        $this->assertNull($refreshCategoryRow->type);
    }

    public function testInsert()
    {
        $category = $this->db->category->newRow();
        $this->addGeneratedRow($category);
        $category->name = 'Uncategorized';
        $category->createdAt = Q::func('NOW');
        $category->insert();
        //
        $this->assertInternalType('int', $category->id);
        $this->assertSame('Uncategorized', $category->name);
        $this->assertRegExp(self::PATTERN_DATETIME_ANCHORED, $category->createdAt);
        $this->assertLessThanOrEqual(1, time() - strtotime($category->createdAt));
        $this->assertRegExp(self::PATTERN_DATETIME_ANCHORED, $category->changedAt);
        $this->assertLessThanOrEqual(1, time() - strtotime($category->changedAt));
        $this->assertTrue($category->active);
        //
        $item = $this->db->item->newRow();
        $this->addGeneratedRow($item);
        $item->categoryId = $category->id;
        $item->name = 'Widget';
        $item->createdAt = Q::func('NOW');
        $item->insert();
        //
        $this->assertInternalType('int', $item->id);
        $this->assertSame($category->id, $item->categoryId);
        $this->assertSame('Widget', $item->name);
        $this->assertRegExp(self::PATTERN_DATETIME_ANCHORED, $item->createdAt);
        $this->assertLessThanOrEqual(1, time() - strtotime($item->createdAt));
        $this->assertRegExp(self::PATTERN_DATETIME_ANCHORED, $item->changedAt);
        $this->assertLessThanOrEqual(1, time() - strtotime($item->changedAt));
        $this->assertTrue($item->active);
    }

    public function testUpdate()
    {
        $category = $this->db->category->newRow();
        $this->addGeneratedRow($category);
        $category->name = 'Uncategorized';
        $category->createdAt = Q::func('NOW');
        $category->save();
        //
        $originalCategoryId = $category->id;
        $category->name = 'Recategorized';
        $result = $category->update();
        $this->assertSame(Junxa::RESULT_SUCCESS, $result);
        $this->assertSame('Recategorized', $category->name);
        $this->assertSame($originalCategoryId, $category->id);
        $categoryAlt = $this->db->category->row($category->id);
        $this->assertNotSame($category, $categoryAlt);
        $this->assertSame($category->name, $categoryAlt->name);
        $result = $category->update();
        $this->assertSame(Junxa::RESULT_UPDATE_NOOP, $result);
        $this->assertTrue(Junxa::OK($result));
        //
        $item = $this->db->item->newRow();
        $this->addGeneratedRow($item);
        $item->categoryId = $category->id;
        $item->name = 'Widget';
        $item->createdAt = Q::func('NOW');
        $item->insert();
        $this->assertTrue($item->active);
        //
        $originalItemId = $item->id;
        $item->name = 'Whatsit';
        $result = $item->update();
        $this->assertSame(Junxa::RESULT_SUCCESS, $result);
        $this->assertSame('Whatsit', $item->name);
        $this->assertSame($originalItemId, $item->id);
        $itemAlt = $this->db->item->row($item->id);
        $this->assertNotSame($item, $itemAlt);
        $this->assertSame($item->name, $itemAlt->name);
        $result = $item->update();
        $this->assertSame(Junxa::RESULT_UPDATE_NOOP, $result);
        $this->assertTrue(Junxa::OK($result));
    }

    public function testDelete()
    {
        $category = $this->db->category->newRow();
        $this->addGeneratedRow($category);
        $category->name = 'Uncategorized';
        $category->createdAt = Q::func('NOW');
        $category->save();
        //
        $item = $this->db->item->newRow();
        $this->addGeneratedRow($item);
        $item->categoryId = $category->id;
        $item->name = 'Widget';
        $item->createdAt = Q::func('NOW');
        //
        $result = $item->delete();
        $this->assertSame(Junxa::RESULT_DELETE_NOKEY, $result);
        $this->assertFalse(Junxa::OK($result));
        //
        $item->insert();
        $this->assertTrue($item->active);
        //
        $this->assertFalse($item->getDeleted());
        $result = $item->delete();
        $this->assertSame(Junxa::RESULT_SUCCESS, $result);
        $this->assertTrue(Junxa::OK($result));
        $this->assertTrue($item->getDeleted());
        //
        $this->assertFalse($category->getDeleted());
        $result = $category->delete();
        $this->assertSame(Junxa::RESULT_SUCCESS, $result);
        $this->assertTrue(Junxa::OK($result));
        $this->assertTrue($category->getDeleted());
        //
        try {
            $category->delete();
            $this->fail('was able to redelete a deleted row');
        } catch (JunxaInvalidQueryException $e) {
            $this->assertSame('row has already been deleted', $e->getMessage());
        }
        //
        $result = $category->delete(
            $category->getTable()->query()
                ->setOption(QueryBuilder::OPTION_REDELETE_OKAY, true)
        );
        $this->assertSame(Junxa::RESULT_DELETE_FAIL, $result);
        $this->assertFalse(Junxa::OK($result));
        //
        $result = $category->delete(
            $category->getTable()->query()
                ->setOption(QueryBuilder::OPTION_EMPTY_OKAY, true)
                ->setOption(QueryBuilder::OPTION_REDELETE_OKAY, true)
        );
        $this->assertSame(Junxa::RESULT_SUCCESS, $result);
        $this->assertTrue(Junxa::OK($result));
    }

    public function testMerge()
    {
        $category = $this->db->category->newRow();
        $this->addGeneratedRow($category);
        $category->name = 'Uncategorized';
        $category->type = 'A\'s';
        $category->createdAt = Q::func('NOW');
        $category->save();
        $this->assertSame('A\'s', $category->type);
        //
        $altCategory = $this->db->category->newRow();
        $this->addGeneratedRow($altCategory);
        $altCategory->name = 'Uncategorized';
        $altCategory->type = 'B\'s';
        $altCategory->createdAt = Q::func('NOW');
        $result = $altCategory->merge();
        $this->assertSame(Junxa::RESULT_SUCCESS, $result);
        $this->assertSame($category->id, $altCategory->id);
        $this->assertNotSame($category, $altCategory);
        $this->assertSame('A\'s', $category->type);
        $this->assertSame('B\'s', $altCategory->type);
        $result = $category->refresh();
        $this->assertSame(Junxa::RESULT_SUCCESS, $result);
        $this->assertSame('B\'s', $category->type);
        $this->assertSame('B\'s', $altCategory->type);
        //
        $item = $this->db->item->newRow();
        $this->addGeneratedRow($item);
        $item->categoryId = $category->id;
        $item->name = 'Widget';
        $item->price = 1.00;
        $item->createdAt = Q::func('NOW');
        $item->save();
        $this->assertSame('1.00', $item->price);
        $this->assertTrue($item->active);
        //
        $altItem = $this->db->item->newRow();
        $this->addGeneratedRow($altItem);
        $altItem->categoryId = $category->id;
        $altItem->name = 'Widget';
        $altItem->price = 2.00;
        $altItem->createdAt = Q::func('NOW');
        $result = $altItem->merge(
            $this->db->item->query()
                ->update('price', Q::add($this->db->item->price, 2.00))
        );
        $this->assertSame(Junxa::RESULT_SUCCESS, $result);
        $this->assertSame($item->id, $altItem->id);
        $this->assertNotSame($item, $altItem);
        $this->assertSame('1.00', $item->price);
        $this->assertSame('3.00', $altItem->price);
    }

    public function testGetParentRow()
    {
        $createCategoryRow = $this->db->category->newRow();
        $this->addGeneratedRow($createCategoryRow);
        $createCategoryRow->name = 'Uncategorized';
        $createCategoryRow->createdAt = Q::func('NOW');
        $createCategoryRow->save();
        //
        $createItemRow = $this->db->item->newRow();
        $this->addGeneratedRow($createItemRow);
        $createItemRow->categoryId = $createCategoryRow->id;
        $createItemRow->name = 'Widget';
        $createItemRow->createdAt = Q::func('NOW');
        $createItemRow->save();
        //
        $itemRow = $this->db->item->row($createItemRow->id);
        $this->assertEquals($createItemRow, $itemRow);
        $this->assertEquals('Widget', $itemRow->name);
        //
        $categoryRow = $itemRow->getParentRow('categoryId');
        $this->assertEquals($createCategoryRow, $categoryRow);
        $this->assertNotSame($createCategoryRow, $categoryRow);
        $this->assertSame('Uncategorized', $categoryRow->name);
    }

    public function testPropertyModeParentRowRetrieval()
    {
        $createCategoryRow = $this->db->category->newRow();
        $this->addGeneratedRow($createCategoryRow);
        $createCategoryRow->name = 'Uncategorized';
        $createCategoryRow->createdAt = Q::func('NOW');
        $createCategoryRow->save();
        $createItemRow = $this->db->item->newRow();
        $this->addGeneratedRow($createItemRow);
        $createItemRow->categoryId = $createCategoryRow->id;
        $createItemRow->name = 'Widget';
        $createItemRow->createdAt = Q::func('NOW');
        $createItemRow->save();
        $itemRow = $this->db->item->row($createItemRow->id);
        $this->assertEquals($createItemRow, $itemRow);
        $this->assertEquals('Widget', $itemRow->name);
        $categoryRow = $itemRow->category;
        $this->assertEquals($categoryRow, $categoryRow);
        $this->assertNotSame($createCategoryRow, $categoryRow);
        $this->assertSame('Uncategorized', $categoryRow->name);
    }

    public function testGetChildRows()
    {
        $categoryRow1 = $this->db->category->newRow();
        $this->addGeneratedRow($categoryRow1);
        $categoryRow1->name = 'Uncategorized';
        $categoryRow1->createdAt = Q::func('NOW');
        $categoryRow1->save();
        //
        $itemRows = $categoryRow1->getChildRows($this->db->item);
        $this->assertCount(0, $itemRows);
        //
        $itemRow1 = $this->db->item->newRow();
        $this->addGeneratedRow($itemRow1);
        $itemRow1->categoryId = $categoryRow1->id;
        $itemRow1->name = 'Widget';
        $itemRow1->createdAt = Q::func('NOW');
        $itemRow1->save();
        //
        $itemRows = $categoryRow1->getChildRows($this->db->item);
        $this->assertCount(1, $itemRows);
        $this->assertEquals($itemRow1, $itemRows[0]);
        //
        $itemRow2 = $this->db->item->newRow();
        $this->addGeneratedRow($itemRow2);
        $itemRow2->categoryId = $categoryRow1->id;
        $itemRow2->name = 'Whosit';
        $itemRow2->createdAt = Q::func('NOW');
        $itemRow2->save();
        //
        $itemRows = $categoryRow1->getChildRows($this->db->item);
        $this->assertCount(2, $itemRows);
        $this->assertEquals($itemRow1, $itemRows[0]);
        $this->assertEquals($itemRow2, $itemRows[1]);
        //
        $categoryRow2 = $this->db->category->newRow();
        $this->addGeneratedRow($categoryRow2);
        $categoryRow2->name = 'Categorized';
        $categoryRow2->createdAt = Q::func('NOW');
        $categoryRow2->save();
        //
        $itemRow3 = $this->db->item->newRow();
        $this->addGeneratedRow($itemRow3);
        $itemRow3->categoryId = $categoryRow2->id;
        $itemRow3->name = 'Whatsit';
        $itemRow3->createdAt = Q::func('NOW');
        $itemRow3->save();
        //
        $itemRows1 = $categoryRow1->getChildRows($this->db->item);
        $this->assertCount(2, $itemRows1);
        $this->assertEquals($itemRow1, $itemRows1[0]);
        $this->assertEquals($itemRow2, $itemRows1[1]);
        $itemRows2 = $categoryRow2->getChildRows($this->db->item);
        $this->assertCount(1, $itemRows2);
        $this->assertEquals($itemRow3, $itemRows2[0]);
        //
        try {
            $categoryRows = $itemRow1->getChildRows($this->db->category);
            $this->fail('was able to retrieve category child rows from item');
        } catch (JunxaInvalidQueryException $e) {
            $this->assertSame(
                'no foreign keys found on category that imply a '
                . 'child table relationship with item',
                $e->getMessage()
            );
        }
    }

    public function testGetChildRowsByTableName()
    {
        $categoryRow1 = $this->db->category->newRow();
        $this->addGeneratedRow($categoryRow1);
        $categoryRow1->name = 'Uncategorized';
        $categoryRow1->createdAt = Q::func('NOW');
        $categoryRow1->save();
        //
        $itemRows = $categoryRow1->getChildRowsByTableName('item');
        $this->assertCount(0, $itemRows);
        //
        $itemRow1 = $this->db->item->newRow();
        $this->addGeneratedRow($itemRow1);
        $itemRow1->categoryId = $categoryRow1->id;
        $itemRow1->name = 'Widget';
        $itemRow1->createdAt = Q::func('NOW');
        $itemRow1->save();
        //
        $itemRows = $categoryRow1->getChildRowsByTableName('item');
        $this->assertCount(1, $itemRows);
        $this->assertEquals($itemRow1, $itemRows[0]);
        //
        $itemRow2 = $this->db->item->newRow();
        $this->addGeneratedRow($itemRow2);
        $itemRow2->categoryId = $categoryRow1->id;
        $itemRow2->name = 'Whosit';
        $itemRow2->createdAt = Q::func('NOW');
        $itemRow2->save();
        //
        $itemRows = $categoryRow1->getChildRowsByTableName('item');
        $this->assertCount(2, $itemRows);
        $this->assertEquals($itemRow1, $itemRows[0]);
        $this->assertEquals($itemRow2, $itemRows[1]);
        //
        $categoryRow2 = $this->db->category->newRow();
        $this->addGeneratedRow($categoryRow2);
        $categoryRow2->name = 'Categorized';
        $categoryRow2->createdAt = Q::func('NOW');
        $categoryRow2->save();
        //
        $itemRow3 = $this->db->item->newRow();
        $this->addGeneratedRow($itemRow3);
        $itemRow3->categoryId = $categoryRow2->id;
        $itemRow3->name = 'Whatsit';
        $itemRow3->createdAt = Q::func('NOW');
        $itemRow3->save();
        //
        $itemRows1 = $categoryRow1->getChildRowsByTableName('item');
        $this->assertCount(2, $itemRows1);
        $this->assertEquals($itemRow1, $itemRows1[0]);
        $this->assertEquals($itemRow2, $itemRows1[1]);
        $itemRows2 = $categoryRow2->getChildRowsByTableName('item');
        $this->assertCount(1, $itemRows2);
        $this->assertEquals($itemRow3, $itemRows2[0]);
        //
        try {
            $categoryRows = $itemRow1->getChildRowsByTableName('category');
            $this->fail('was able to retrieve category child rows from item');
        } catch (JunxaInvalidQueryException $e) {
            $this->assertSame(
                'no foreign keys found on category that imply a '
                . 'child table relationship with item',
                $e->getMessage()
            );
        }
        //
        try {
            $itemRows = $categoryRow1->getChildRowsByTableName('nonexistent');
            $this->fail('was able to retrieve nonexistent child rows from category');
        } catch (JunxaNoSuchTableException $e) {
            $this->assertSame('nonexistent', $e->getTableName());
        }
    }

    public function testPropertyModeChildRowsRetrieval()
    {
        $categoryRow1 = $this->db->category->newRow();
        $this->addGeneratedRow($categoryRow1);
        $categoryRow1->name = 'Uncategorized';
        $categoryRow1->createdAt = Q::func('NOW');
        $categoryRow1->save();
        //
        $itemRows = $categoryRow1->items;
        $this->assertCount(0, $itemRows);
        //
        $itemRow1 = $this->db->item->newRow();
        $this->addGeneratedRow($itemRow1);
        $itemRow1->categoryId = $categoryRow1->id;
        $itemRow1->name = 'Widget';
        $itemRow1->createdAt = Q::func('NOW');
        $itemRow1->save();
        //
        $itemRows = $categoryRow1->items;
        $this->assertCount(1, $itemRows);
        $this->assertEquals($itemRow1, $itemRows[0]);
        //
        $itemRow2 = $this->db->item->newRow();
        $this->addGeneratedRow($itemRow2);
        $itemRow2->categoryId = $categoryRow1->id;
        $itemRow2->name = 'Whosit';
        $itemRow2->createdAt = Q::func('NOW');
        $itemRow2->save();
        //
        $itemRows = $categoryRow1->items;
        $this->assertCount(2, $itemRows);
        $this->assertEquals($itemRow1, $itemRows[0]);
        $this->assertEquals($itemRow2, $itemRows[1]);
        //
        $categoryRow2 = $this->db->category->newRow();
        $this->addGeneratedRow($categoryRow2);
        $categoryRow2->name = 'Categorized';
        $categoryRow2->createdAt = Q::func('NOW');
        $categoryRow2->save();
        //
        $itemRow3 = $this->db->item->newRow();
        $this->addGeneratedRow($itemRow3);
        $itemRow3->categoryId = $categoryRow2->id;
        $itemRow3->name = 'Whatsit';
        $itemRow3->createdAt = Q::func('NOW');
        $itemRow3->save();
        //
        $itemRows1 = $categoryRow1->items;
        $this->assertCount(2, $itemRows1);
        $this->assertEquals($itemRow1, $itemRows1[0]);
        $this->assertEquals($itemRow2, $itemRows1[1]);
        $itemRows2 = $categoryRow2->getChildRows($this->db->item);
        $this->assertCount(1, $itemRows2);
        $this->assertEquals($itemRow3, $itemRows2[0]);
        //
        try {
            $categoryRows = $itemRow1->categories;
            $this->fail('was able to retrieve category child rows from item');
        } catch (JunxaInvalidQueryException $e) {
            $this->assertSame(
                'no foreign keys found on category that imply a '
                . 'child table relationship with item',
                $e->getMessage()
            );
        }
    }

}
