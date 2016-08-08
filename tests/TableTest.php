<?php

namespace Thaumatic\Junxa\Tests;

use Thaumatic\Junxa;
use Thaumatic\Junxa\Query as Q;
use Thaumatic\Junxa\Tests\DatabaseTestAbstract;

class TableTest extends DatabaseTestAbstract
{

    public function testGetColumns()
    {
        $this->assertSame([
            'id',
            'name',
            'type',
            'active',
            'createdAt',
            'changedAt',
        ], $this->db->category->getColumns());
        $this->assertSame([
            'id',
            'categoryId',
            'name',
            'price',
            'active',
            'createdAt',
            'changedAt',
        ], $this->db->item->getColumns());
    }

    public function testRow()
    {
        $createCategoryRow1 = $this->db->category->newRow();
        $this->addGeneratedRow($createCategoryRow1);
        $createCategoryRow1->name = 'Uncategorized';
        $createCategoryRow1->type = 'A\'s';
        $createCategoryRow1->createdAt = Q::func('NOW');
        $createCategoryRow1->save();
        $this->assertSame(Junxa::RESULT_SUCCESS, $this->db->getQueryStatus());
        //
        $categoryByPkRow = $this->db->category->row($createCategoryRow1->id);
        $this->assertSame(Junxa::RESULT_SUCCESS, $this->db->getQueryStatus());
        $this->assertEquals($createCategoryRow1, $categoryByPkRow);
        $this->assertNotSame($createCategoryRow1, $categoryByPkRow);
        $this->assertSame('Uncategorized', $categoryByPkRow->name);
        //
        $categoryByNameRow = $this->db->category->row(
            $this->db->category->query()
                ->where('name', $createCategoryRow1->name)
        );
        $this->assertSame(Junxa::RESULT_SUCCESS, $this->db->getQueryStatus());
        $this->assertEquals($createCategoryRow1, $categoryByNameRow);
        $this->assertNotSame($createCategoryRow1, $categoryByNameRow);
        $this->assertSame('Uncategorized', $categoryByNameRow->name);
        //
        $categoryByNameRow = $this->db->category->row(
            $this->db->category->query()
                ->where('name', $createCategoryRow1->name)
        );
        $this->assertSame(Junxa::RESULT_SUCCESS, $this->db->getQueryStatus());
        $this->assertEquals($createCategoryRow1, $categoryByNameRow);
        $this->assertNotSame($createCategoryRow1, $categoryByNameRow);
        $this->assertSame('Uncategorized', $categoryByNameRow->name);
        //
        $categoryByTypeRow = $this->db->category->row(
            $this->db->category->query()
                ->where('type', 'A\'s')
        );
        $this->assertSame(Junxa::RESULT_SUCCESS, $this->db->getQueryStatus());
        $this->assertEquals($createCategoryRow1, $categoryByTypeRow);
        $this->assertNotSame($createCategoryRow1, $categoryByTypeRow);
        $this->assertSame('Uncategorized', $categoryByTypeRow->name);
        //
        $createCategoryRow2 = $this->db->category->newRow();
        $this->addGeneratedRow($createCategoryRow2);
        $createCategoryRow2->name = 'Categorized';
        $createCategoryRow2->type = 'A\'s';
        $createCategoryRow2->createdAt = Q::func('NOW');
        $createCategoryRow2->save();
        $this->assertSame(Junxa::RESULT_SUCCESS, $this->db->getQueryStatus());
        //
        $categoryByPkRow = $this->db->category->row($createCategoryRow2->id);
        $this->assertSame(Junxa::RESULT_SUCCESS, $this->db->getQueryStatus());
        $this->assertEquals($createCategoryRow2, $categoryByPkRow);
        $this->assertNotSame($createCategoryRow2, $categoryByPkRow);
        $this->assertSame('Categorized', $categoryByPkRow->name);
        //
        $categoryByNameRow = $this->db->category->row(
            $this->db->category->query()
                ->where('name', $createCategoryRow2->name)
        );
        $this->assertSame(Junxa::RESULT_SUCCESS, $this->db->getQueryStatus());
        $this->assertEquals($createCategoryRow2, $categoryByNameRow);
        $this->assertNotSame($createCategoryRow2, $categoryByNameRow);
        $this->assertSame('Categorized', $categoryByNameRow->name);
        //
        $categoryByTypeRow = $this->db->category->row(
            $this->db->category->query()
                ->where('type', 'A\'s')
                ->order('id')
        );
        $this->assertSame(Junxa::RESULT_SUCCESS, $this->db->getQueryStatus());
        $this->assertEquals($createCategoryRow1, $categoryByTypeRow);
        $this->assertNotSame($createCategoryRow1, $categoryByTypeRow);
        $this->assertSame('Uncategorized', $categoryByTypeRow->name);
        //
        $categoryByTypeRow = $this->db->category->row(
            $this->db->category->query()
                ->where('type', 'A\'s')
                ->order('id')->desc()
        );
        $this->assertSame(Junxa::RESULT_SUCCESS, $this->db->getQueryStatus());
        $this->assertEquals($createCategoryRow2, $categoryByTypeRow);
        $this->assertNotSame($createCategoryRow2, $categoryByTypeRow);
        $this->assertSame('Categorized', $categoryByTypeRow->name);
        //
        $createItemRow = $this->db->item->newRow();
        $this->addGeneratedRow($createItemRow);
        $createItemRow->categoryId = $createCategoryRow1->id;
        $createItemRow->name = 'Widget';
        $createItemRow->createdAt = Q::func('NOW');
        $createItemRow->save();
        $this->assertSame(Junxa::RESULT_SUCCESS, $this->db->getQueryStatus());
        //
        $itemRow = $this->db->item->row($createItemRow->id);
        $this->assertSame(Junxa::RESULT_SUCCESS, $this->db->getQueryStatus());
        $this->assertEquals($createItemRow, $itemRow);
        $this->assertNotSame($createItemRow, $itemRow);
        $this->assertEquals('Widget', $itemRow->name);
    }

}
