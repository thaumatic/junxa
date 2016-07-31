<?php

namespace Thaumatic\Junxa\Tests;

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
        $this->assertSame('tinyint', $category->getColumnType('active'));
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
        $this->assertSame('tinyint(1)', $category->getColumnFullType('active'));
        $this->assertSame('datetime', $item->getColumnFullType('created_at'));
        $this->assertSame('timestamp', $item->getColumnFullType('changed_at'));
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
