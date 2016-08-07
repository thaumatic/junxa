<?php

namespace Thaumatic\Junxa\Tests;

use Thaumatic\Junxa;
use Thaumatic\Junxa\Column;
use Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException;
use Thaumatic\Junxa\Exceptions\JunxaNoSuchColumnException;
use Thaumatic\Junxa\Query as Q;
use Thaumatic\Junxa\Query\Builder as QueryBuilder;
use Thaumatic\Junxa\Tests\DatabaseTestAbstract;

class RowTest extends DatabaseTestAbstract
{

    const PATTERN_DATETIME_ANCHORED = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';

    public function testFieldInteractionWithIsset()
    {
        $categoryRow = $this->db()->category->newRow();
        $this->addGeneratedRow($categoryRow);
        $this->assertFalse(isset($categoryRow->id));
        $this->assertFalse(isset($categoryRow->name));
        $this->assertFalse(isset($categoryRow->type));
        $this->assertFalse(isset($categoryRow->created_at));
        $this->assertFalse(isset($categoryRow->changed_at));
        $this->assertFalse(isset($categoryRow->nonexistent_column));
        $categoryRow->name = 'Uncategorized';
        $categoryRow->created_at = Q::func('NOW');
        $this->assertFalse(isset($categoryRow->id));
        $this->assertTrue(isset($categoryRow->name));
        $this->assertFalse(isset($categoryRow->type));
        $this->assertTrue(isset($categoryRow->created_at));
        $this->assertFalse(isset($categoryRow->changed_at));
        $this->assertFalse(isset($categoryRow->nonexistent_column));
        $categoryRow->save();
        $this->assertTrue(isset($categoryRow->id));
        $this->assertTrue(isset($categoryRow->name));
        $this->assertFalse(isset($categoryRow->type));
        $this->assertTrue(isset($categoryRow->created_at));
        $this->assertTrue(isset($categoryRow->changed_at));
        $this->assertFalse(isset($categoryRow->nonexistent_column));
    }

    public function testFieldInteractionWithEmpty()
    {
        $categoryRow = $this->db()->category->newRow();
        $this->addGeneratedRow($categoryRow);
        $this->assertTrue(empty($categoryRow->id));
        $this->assertTrue(empty($categoryRow->name));
        $this->assertTrue(empty($categoryRow->type));
        $this->assertTrue(empty($categoryRow->created_at));
        $this->assertTrue(empty($categoryRow->changed_at));
        $this->assertTrue(empty($categoryRow->nonexistent_column));
        $categoryRow->name = 'Uncategorized';
        $categoryRow->created_at = Q::func('NOW');
        $this->assertTrue(empty($categoryRow->id));
        $this->assertFalse(empty($categoryRow->name));
        $this->assertTrue(empty($categoryRow->type));
        $this->assertFalse(empty($categoryRow->created_at));
        $this->assertTrue(empty($categoryRow->changed_at));
        $this->assertTrue(empty($categoryRow->nonexistent_column));
        $categoryRow->save();
        $this->assertFalse(empty($categoryRow->id));
        $this->assertFalse(empty($categoryRow->name));
        $this->assertTrue(empty($categoryRow->type));
        $this->assertFalse(empty($categoryRow->created_at));
        $this->assertFalse(empty($categoryRow->changed_at));
        $this->assertTrue(empty($categoryRow->nonexistent_column));
    }

    public function testExceptionOnNonexistentColumn()
    {
        $categoryRow = $this->db()->category->newRow();
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
            'price',
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
        $this->assertSame('decimal', $item->getColumnType('price'));
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
        $this->assertSame('decimal(10,2)', $item->getColumnFullType('price'));
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
        $this->assertSame('float', $item->getColumnTypeClass('price'));
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
        $this->assertSame(10, $item->getColumnLength('price'));
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
        $this->assertSame(2, $item->getColumnPrecision('price'));
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

    public function testGetColumnFlag()
    {
        $category = $this->db()->category->newRow();
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
        $this->assertTrue($category->getColumnFlag('created_at', Column::MYSQL_FLAG_NOT_NULL));
        $this->assertTrue($category->getColumnFlag('created_at', Column::MYSQL_FLAG_BINARY));
        $this->assertTrue($category->getColumnFlag('created_at', Column::MYSQL_FLAG_NO_DEFAULT_VALUE));
        $this->assertTrue(
            $category->getColumnFlag(
                'created_at',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_BINARY
            )
        );
        $this->assertTrue(
            $category->getColumnFlag(
                'created_at',
                Column::MYSQL_FLAG_BINARY
                | Column::MYSQL_FLAG_NO_DEFAULT_VALUE
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($category->getColumnFlag('created_at', Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
        $this->assertTrue($category->getColumnFlag('changed_at', Column::MYSQL_FLAG_NOT_NULL));
        $this->assertTrue($category->getColumnFlag('changed_at', Column::MYSQL_FLAG_UNSIGNED));
        $this->assertTrue($category->getColumnFlag('changed_at', Column::MYSQL_FLAG_ZEROFILL));
        $this->assertTrue($category->getColumnFlag('changed_at', Column::MYSQL_FLAG_BINARY));
        $this->assertTrue($category->getColumnFlag('changed_at', Column::MYSQL_FLAG_TIMESTAMP));
        $this->assertTrue($category->getColumnFlag('changed_at', Column::MYSQL_FLAG_ON_UPDATE_NOW));
        $this->assertTrue(
            $category->getColumnFlag(
                'changed_at',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_UNSIGNED
            )
        );
        $this->assertTrue(
            $category->getColumnFlag(
                'changed_at',
                Column::MYSQL_FLAG_TIMESTAMP
                | Column::MYSQL_FLAG_ON_UPDATE_NOW
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($category->getColumnFlag('changed_at', Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
        $item = $this->db()->item->newRow();
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
        $this->assertTrue($item->getColumnFlag('category_id', Column::MYSQL_FLAG_NOT_NULL));
        $this->assertTrue($item->getColumnFlag('category_id', Column::MYSQL_FLAG_MULTIPLE_KEY));
        $this->assertTrue($item->getColumnFlag('category_id', Column::MYSQL_FLAG_UNSIGNED));
        $this->assertTrue($item->getColumnFlag('category_id', Column::MYSQL_FLAG_NO_DEFAULT_VALUE));
        $this->assertTrue($item->getColumnFlag('category_id', Column::MYSQL_FLAG_PART_KEY));
        $this->assertTrue($item->getColumnFlag('category_id', Column::MYSQL_FLAG_NUM));
        $this->assertTrue(
            $item->getColumnFlag(
                'category_id',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_MULTIPLE_KEY
            )
        );
        $this->assertTrue(
            $item->getColumnFlag(
                'category_id',
                Column::MYSQL_FLAG_PART_KEY
                | Column::MYSQL_FLAG_NUM
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($item->getColumnFlag('category_id', Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
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
        $this->assertTrue($item->getColumnFlag('created_at', Column::MYSQL_FLAG_NOT_NULL));
        $this->assertTrue($item->getColumnFlag('created_at', Column::MYSQL_FLAG_BINARY));
        $this->assertTrue($item->getColumnFlag('created_at', Column::MYSQL_FLAG_NO_DEFAULT_VALUE));
        $this->assertTrue(
            $item->getColumnFlag(
                'created_at',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_BINARY
            )
        );
        $this->assertTrue(
            $item->getColumnFlag(
                'created_at',
                Column::MYSQL_FLAG_BINARY
                | Column::MYSQL_FLAG_NO_DEFAULT_VALUE
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($item->getColumnFlag('created_at', Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
        $this->assertTrue($item->getColumnFlag('changed_at', Column::MYSQL_FLAG_NOT_NULL));
        $this->assertTrue($item->getColumnFlag('changed_at', Column::MYSQL_FLAG_UNSIGNED));
        $this->assertTrue($item->getColumnFlag('changed_at', Column::MYSQL_FLAG_ZEROFILL));
        $this->assertTrue($item->getColumnFlag('changed_at', Column::MYSQL_FLAG_BINARY));
        $this->assertTrue($item->getColumnFlag('changed_at', Column::MYSQL_FLAG_TIMESTAMP));
        $this->assertTrue($item->getColumnFlag('changed_at', Column::MYSQL_FLAG_ON_UPDATE_NOW));
        $this->assertTrue(
            $item->getColumnFlag(
                'changed_at',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_UNSIGNED
            )
        );
        $this->assertTrue(
            $item->getColumnFlag(
                'changed_at',
                Column::MYSQL_FLAG_TIMESTAMP
                | Column::MYSQL_FLAG_ON_UPDATE_NOW
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertFalse($item->getColumnFlag('changed_at', Column::MYSQL_FLAG_FIELD_IN_PART_FUNC));
    }

    public function testGetColumnEachFlag()
    {
        $category = $this->db()->category->newRow();
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
                'created_at',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_BINARY
                | Column::MYSQL_FLAG_NO_DEFAULT_VALUE
            )
        );
        $this->assertFalse(
            $category->getColumnEachFlag(
                'created_at',
                Column::MYSQL_FLAG_BINARY
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertTrue(
            $category->getColumnEachFlag(
                'changed_at',
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
                'changed_at',
                Column::MYSQL_FLAG_UNSIGNED
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $item = $this->db()->item->newRow();
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
                'category_id',
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
                'category_id',
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
                'created_at',
                Column::MYSQL_FLAG_NOT_NULL
                | Column::MYSQL_FLAG_BINARY
                | Column::MYSQL_FLAG_NO_DEFAULT_VALUE
            )
        );
        $this->assertFalse(
            $item->getColumnEachFlag(
                'created_at',
                Column::MYSQL_FLAG_BINARY
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
        $this->assertTrue(
            $item->getColumnEachFlag(
                'changed_at',
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
                'changed_at',
                Column::MYSQL_FLAG_UNSIGNED
                | Column::MYSQL_FLAG_FIELD_IN_PART_FUNC
            )
        );
    }

    public function testGetColumnValues()
    {
        $category = $this->db()->category->newRow();
        $this->assertNull($category->getColumnValues('id'));
        $this->assertNull($category->getColumnValues('name'));
        $this->assertSame([null, 'A\'s', 'B\'s', 'C\'s'], $category->getColumnValues('type'));
        $this->assertNull($category->getColumnValues('active'));
        $this->assertNull($category->getColumnValues('created_at'));
        $this->assertNull($category->getColumnValues('changed_at'));
        $item = $this->db()->item->newRow();
        $this->assertNull($item->getColumnValues('id'));
        $this->assertNull($item->getColumnValues('category_id'));
        $this->assertNull($item->getColumnValues('name'));
        $this->assertNull($item->getColumnValues('price'));
        $this->assertNull($item->getColumnValues('active'));
        $this->assertNull($item->getColumnValues('created_at'));
        $this->assertNull($item->getColumnValues('changed_at'));
    }

    public function testGetColumnOptions()
    {
        try {
            $category = $this->db()->category->newRow();
            $column = $this->db()->category->id;
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
            $category = $this->db()->category->newRow();
            $column = $this->db()->category->id;
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
            $category = $this->db()->category->newRow();
            $column = $this->db()->category->id;
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
        $createCategoryRow1 = $this->db()->category->newRow()
            ->setField('name', 'Uncategorized')
            ->setField('type', 'A\'s')
            ->setField('created_at', Q::func('NOW'))
            ->performSave();
        $this->addGeneratedRow($createCategoryRow1);
        //
        $findCategoryRow = $this->db()->category->newRow()
            ->setField('name', 'Uncategorized');
        $result = $findCategoryRow->find();
        $this->assertSame(Junxa::RESULT_SUCCESS, $result);
        $this->assertSame('A\'s', $findCategoryRow->type);
        //
        $findCategoryRow = $this->db()->category->newRow()
            ->setField('type', 'A\'s');
        $result = $findCategoryRow->find();
        $this->assertSame(Junxa::RESULT_SUCCESS, $result);
        $this->assertSame('A\'s', $findCategoryRow->type);
        //
        $findCategoryRow = $this->db()->category->newRow()
            ->setField('name', 'Unknown');
        $result = $findCategoryRow->find();
        $this->assertSame(Junxa::RESULT_FIND_FAIL, $result);
        $this->assertNull($findCategoryRow->type);
        //
        $findCategoryRow = $this->db()->category->newRow()
            ->setField('type', 'B\'s');
        $result = $findCategoryRow->find();
        $this->assertSame(Junxa::RESULT_FIND_FAIL, $result);
        $this->assertNull($findCategoryRow->name);
        //
        $createCategoryRow2 = $this->db()->category->newRow()
            ->setField('name', 'Categorized')
            ->setField('type', 'A\'s')
            ->setField('created_at', Q::func('NOW'))
            ->performSave();
        $this->addGeneratedRow($createCategoryRow2);
        //
        $findCategoryRow = $this->db()->category->newRow()
            ->setField('name', 'Uncategorized');
        $result = $findCategoryRow->find();
        $this->assertSame(Junxa::RESULT_SUCCESS, $result);
        $this->assertSame('A\'s', $findCategoryRow->type);
        //
        $findCategoryRow = $this->db()->category->newRow()
            ->setField('name', 'Categorized');
        $result = $findCategoryRow->find();
        $this->assertSame(Junxa::RESULT_SUCCESS, $result);
        $this->assertSame('A\'s', $findCategoryRow->type);
        //
        $findCategoryRow = $this->db()->category->newRow()
            ->setField('name', 'Unknown');
        $result = $findCategoryRow->find();
        $this->assertSame(Junxa::RESULT_FIND_FAIL, $result);
        $this->assertNull($findCategoryRow->type);
        //
        $findCategoryRow = $this->db()->category->newRow()
            ->setField('type', 'A\'s');
        $result = $findCategoryRow->find();
        $this->assertSame(Junxa::RESULT_FIND_EXCESS, $result);
        $this->assertSame('A\'s', $findCategoryRow->type);
        $this->assertSame('Uncategorized', $findCategoryRow->name);
        //
        $findCategoryRow = $this->db()->category->newRow()
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
        $createCategoryRow = $this->db()->category->newRow()
            ->setField('name', 'Uncategorized')
            ->setField('type', 'A\'s')
            ->setField('created_at', Q::func('NOW'))
            ->performSave();
        $this->addGeneratedRow($createCategoryRow);
        //
        $refreshCategoryRow = $this->db()->category->newRow()
            ->setField('id', $createCategoryRow->id);
        $result = $refreshCategoryRow->refresh();
        $this->assertSame(Junxa::RESULT_SUCCESS, $result);
        $this->assertSame('Uncategorized', $refreshCategoryRow->name);
        $this->assertSame('A\'s', $refreshCategoryRow->type);
        //
        $refreshCategoryRow = $this->db()->category->newRow();
        $result = $refreshCategoryRow->refresh();
        $this->assertSame(Junxa::RESULT_REFRESH_FAIL, $result);
        $this->assertNull($refreshCategoryRow->name);
        $this->assertNull($refreshCategoryRow->type);
        //
        $refreshCategoryRow = $this->db()->category->newRow()
            ->setField('id', $createCategoryRow->id + 1);
        try {
            $result = $refreshCategoryRow->refresh();
            $this->fail('refresh with invalid ID did not throw exception');
        } catch(JunxaInvalidQueryException $e) {
            // expected
        }
        $this->assertNull($refreshCategoryRow->name);
        $this->assertNull($refreshCategoryRow->type);
    }

    public function testInsert()
    {
        $category = $this->db()->category->newRow();
        $this->addGeneratedRow($category);
        $category->name = 'Uncategorized';
        $category->created_at = Q::func('NOW');
        $category->insert();
        //
        $this->assertInternalType('int', $category->id);
        $this->assertSame('Uncategorized', $category->name);
        $this->assertRegExp(self::PATTERN_DATETIME_ANCHORED, $category->created_at);
        $this->assertLessThanOrEqual(1, time() - strtotime($category->created_at));
        $this->assertRegExp(self::PATTERN_DATETIME_ANCHORED, $category->changed_at);
        $this->assertLessThanOrEqual(1, time() - strtotime($category->changed_at));
        $this->assertTrue($category->active);
        //
        $item = $this->db()->item->newRow();
        $this->addGeneratedRow($item);
        $item->category_id = $category->id;
        $item->name = 'Widget';
        $item->created_at = Q::func('NOW');
        $item->insert();
        //
        $this->assertInternalType('int', $item->id);
        $this->assertSame($category->id, $item->category_id);
        $this->assertSame('Widget', $item->name);
        $this->assertRegExp(self::PATTERN_DATETIME_ANCHORED, $item->created_at);
        $this->assertLessThanOrEqual(1, time() - strtotime($item->created_at));
        $this->assertRegExp(self::PATTERN_DATETIME_ANCHORED, $item->changed_at);
        $this->assertLessThanOrEqual(1, time() - strtotime($item->changed_at));
        $this->assertTrue($item->active);
    }

    public function testUpdate()
    {
        $category = $this->db()->category->newRow();
        $this->addGeneratedRow($category);
        $category->name = 'Uncategorized';
        $category->created_at = Q::func('NOW');
        $category->save();
        //
        $originalCategoryId = $category->id;
        $category->name = 'Recategorized';
        $result = $category->update();
        $this->assertSame(Junxa::RESULT_SUCCESS, $result);
        $this->assertSame('Recategorized', $category->name);
        $this->assertSame($originalCategoryId, $category->id);
        $categoryAlt = $this->db()->category->row($category->id);
        $this->assertNotSame($category, $categoryAlt);
        $this->assertSame($category->name, $categoryAlt->name);
        $result = $category->update();
        $this->assertSame(Junxa::RESULT_UPDATE_NOOP, $result);
        $this->assertTrue(Junxa::OK($result));
        //
        $item = $this->db()->item->newRow();
        $this->addGeneratedRow($item);
        $item->category_id = $category->id;
        $item->name = 'Widget';
        $item->created_at = Q::func('NOW');
        $item->insert();
        $this->assertTrue($item->active);
        //
        $originalItemId = $item->id;
        $item->name = 'Whatsit';
        $result = $item->update();
        $this->assertSame(Junxa::RESULT_SUCCESS, $result);
        $this->assertSame('Whatsit', $item->name);
        $this->assertSame($originalItemId, $item->id);
        $itemAlt = $this->db()->item->row($item->id);
        $this->assertNotSame($item, $itemAlt);
        $this->assertSame($item->name, $itemAlt->name);
        $result = $item->update();
        $this->assertSame(Junxa::RESULT_UPDATE_NOOP, $result);
        $this->assertTrue(Junxa::OK($result));
    }

    public function testDelete()
    {
        $category = $this->db()->category->newRow();
        $this->addGeneratedRow($category);
        $category->name = 'Uncategorized';
        $category->created_at = Q::func('NOW');
        $category->save();
        //
        $item = $this->db()->item->newRow();
        $this->addGeneratedRow($item);
        $item->category_id = $category->id;
        $item->name = 'Widget';
        $item->created_at = Q::func('NOW');
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
        } catch(JunxaInvalidQueryException $e) {
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
        $category = $this->db()->category->newRow();
        $this->addGeneratedRow($category);
        $category->name = 'Uncategorized';
        $category->type = 'A\'s';
        $category->created_at = Q::func('NOW');
        $category->save();
        $this->assertSame('A\'s', $category->type);
        //
        $altCategory = $this->db()->category->newRow();
        $this->addGeneratedRow($altCategory);
        $altCategory->name = 'Uncategorized';
        $altCategory->type = 'B\'s';
        $altCategory->created_at = Q::func('NOW');
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
        $item = $this->db()->item->newRow();
        $this->addGeneratedRow($item);
        $item->category_id = $category->id;
        $item->name = 'Widget';
        $item->price = 1.00;
        $item->created_at = Q::func('NOW');
        $item->save();
        $this->assertSame(1.00, $item->price);
        $this->assertTrue($item->active);
    }

    public function testGetForeignRow()
    {
        $createCategoryRow = $this->db()->category->newRow();
        $this->addGeneratedRow($createCategoryRow);
        $createCategoryRow->name = 'Uncategorized';
        $createCategoryRow->created_at = Q::func('NOW');
        $createCategoryRow->save();
        $createItemRow = $this->db()->item->newRow();
        $this->addGeneratedRow($createItemRow);
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
    }

}
