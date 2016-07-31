<?php

namespace Thaumatic\Junxa\Tests;

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

    public function testGetColumns()
    {
        $categoryRow = $this->db()->category->newRow();
        $this->assertSame([
            'id',
            'name',
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
