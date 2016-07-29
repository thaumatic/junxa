<?php

namespace Thaumatic\Junxa\Tests;

use Thaumatic\Junxa\Query as Q;
use Thaumatic\Junxa\Tests\DatabaseTestAbstract;

class RowTest extends DatabaseTestAbstract
{

    public function testGetForeignRow()
    {
        $createCategoryRow = $this->db()->category->newRow();
        $createCategoryRow->name = 'Uncategorized';
        $createCategoryRow->created_at = Q::func('NOW');
        $createCategoryRow->insert();
        $createItemRow = $this->db()->item->newRow();
        $createItemRow->category_id = $createCategoryRow->id;
        $createItemRow->name = 'Widget';
        $createItemRow->created_at = Q::func('NOW');
        $createItemRow->insert();
        $itemRow = $this->db()->item->row($createItemRow->id);
        $this->assertEquals($createItemRow, $itemRow);
        $this->assertEquals('Widget', $itemRow->name);
        $categoryRow = $itemRow->getForeignRow('category_id');
        $this->assertEquals($createCategoryRow, $categoryRow);
        $this->assertEquals('Uncategorized', $categoryRow->name);
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

}
