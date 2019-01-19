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

    public function testRows()
    {
        $createCategoryRow1 = $this->db->category->newRow();
        $this->addGeneratedRow($createCategoryRow1);
        $createCategoryRow1->name = 'Uncategorized';
        $createCategoryRow1->type = 'A\'s';
        $createCategoryRow1->createdAt = Q::func('NOW');
        $createCategoryRow1->save();
        $this->assertSame(Junxa::RESULT_SUCCESS, $this->db->getQueryStatus());
        //
        $categoryRowsAll = $this->db->category->rows(
            $this->db->category->query()
                ->order('id')
        );
        $this->assertSame(Junxa::RESULT_SUCCESS, $this->db->getQueryStatus());
        $this->assertCount(1, $categoryRowsAll);
        $this->assertEquals($createCategoryRow1, $categoryRowsAll[0]);
        $this->assertNotSame($createCategoryRow1, $categoryRowsAll[0]);
        $this->assertSame('Uncategorized', $categoryRowsAll[0]->name);
        //
        $categoryRowsByName = $this->db->category->rows(
            $this->db->category->query()
                ->where('name', 'Uncategorized')
                ->order('id')
        );
        $this->assertSame(Junxa::RESULT_SUCCESS, $this->db->getQueryStatus());
        $this->assertCount(1, $categoryRowsByName);
        $this->assertEquals($createCategoryRow1, $categoryRowsByName[0]);
        $this->assertNotSame($createCategoryRow1, $categoryRowsByName[0]);
        $this->assertSame('Uncategorized', $categoryRowsByName[0]->name);
        //
        $categoryRowsByType = $this->db->category->rows(
            $this->db->category->query()
                ->where('type', 'A\'s')
                ->order('id')
        );
        $this->assertSame(Junxa::RESULT_SUCCESS, $this->db->getQueryStatus());
        $this->assertCount(1, $categoryRowsByType);
        $this->assertEquals($createCategoryRow1, $categoryRowsByType[0]);
        $this->assertNotSame($createCategoryRow1, $categoryRowsByType[0]);
        $this->assertSame('Uncategorized', $categoryRowsByType[0]->name);
        //
        $createCategoryRow2 = $this->db->category->newRow();
        $this->addGeneratedRow($createCategoryRow2);
        $createCategoryRow2->name = 'Categorized';
        $createCategoryRow2->type = 'A\'s';
        $createCategoryRow2->createdAt = Q::func('NOW');
        $createCategoryRow2->save();
        $this->assertSame(Junxa::RESULT_SUCCESS, $this->db->getQueryStatus());
        //
        $categoryRowsAll = $this->db->category->rows(
            $this->db->category->query()
                ->order('id')
        );
        $this->assertSame(Junxa::RESULT_SUCCESS, $this->db->getQueryStatus());
        $this->assertCount(2, $categoryRowsAll);
        $this->assertEquals($createCategoryRow1, $categoryRowsAll[0]);
        $this->assertNotSame($createCategoryRow1, $categoryRowsAll[0]);
        $this->assertSame('Uncategorized', $categoryRowsAll[0]->name);
        $this->assertEquals($createCategoryRow2, $categoryRowsAll[1]);
        $this->assertNotSame($createCategoryRow2, $categoryRowsAll[1]);
        $this->assertSame('Categorized', $categoryRowsAll[1]->name);
        //
        $categoryRowsByName = $this->db->category->rows(
            $this->db->category->query()
                ->where('name', 'Uncategorized')
                ->order('id')
        );
        $this->assertSame(Junxa::RESULT_SUCCESS, $this->db->getQueryStatus());
        $this->assertCount(1, $categoryRowsByName);
        $this->assertEquals($createCategoryRow1, $categoryRowsByName[0]);
        $this->assertNotSame($createCategoryRow1, $categoryRowsByName[0]);
        $this->assertSame('Uncategorized', $categoryRowsByName[0]->name);
        //
        $categoryRowsByName = $this->db->category->rows(
            $this->db->category->query()
                ->where('name', 'Categorized')
                ->order('id')
        );
        $this->assertSame(Junxa::RESULT_SUCCESS, $this->db->getQueryStatus());
        $this->assertCount(1, $categoryRowsByName);
        $this->assertEquals($createCategoryRow2, $categoryRowsByName[0]);
        $this->assertNotSame($createCategoryRow2, $categoryRowsByName[0]);
        $this->assertSame('Categorized', $categoryRowsByName[0]->name);
        //
        $categoryRowsByType = $this->db->category->rows(
            $this->db->category->query()
                ->where('type', 'A\'s')
                ->order('id')
        );
        $this->assertSame(Junxa::RESULT_SUCCESS, $this->db->getQueryStatus());
        $this->assertCount(2, $categoryRowsByType);
        $this->assertEquals($createCategoryRow1, $categoryRowsByType[0]);
        $this->assertNotSame($createCategoryRow1, $categoryRowsByType[0]);
        $this->assertSame('Uncategorized', $categoryRowsByType[0]->name);
        $this->assertEquals($createCategoryRow2, $categoryRowsByType[1]);
        $this->assertNotSame($createCategoryRow2, $categoryRowsByType[1]);
        $this->assertSame('Categorized', $categoryRowsByType[1]->name);
        //
        $createItemRow = $this->db->item->newRow();
        $this->addGeneratedRow($createItemRow);
        $createItemRow->categoryId = $createCategoryRow1->id;
        $createItemRow->name = 'Widget';
        $createItemRow->createdAt = Q::func('NOW');
        $createItemRow->save();
        $this->assertSame(Junxa::RESULT_SUCCESS, $this->db->getQueryStatus());
        //
        $itemRowsAll = $this->db->item->rows(
            $this->db->item->query()
                ->order('id')
        );
        $this->assertSame(Junxa::RESULT_SUCCESS, $this->db->getQueryStatus());
        $this->assertCount(1, $itemRowsAll);
        $this->assertEquals($createItemRow, $itemRowsAll[0]);
        $this->assertNotSame($createItemRow, $itemRowsAll[0]);
        $this->assertEquals('Widget', $itemRowsAll[0]->name);
        //
        $itemRowsByCategoryType = $this->db->item->rows(
            $this->db->item->query()
                ->join('category')->on('categoryId', $this->db->category->id)
                ->where($this->db->category->type, 'A\'s')
                ->order('id')
        );
        $this->assertSame(Junxa::RESULT_SUCCESS, $this->db->getQueryStatus());
        $this->assertCount(1, $itemRowsByCategoryType);
        $this->assertEquals($createItemRow, $itemRowsByCategoryType[0]);
        $this->assertNotSame($createItemRow, $itemRowsByCategoryType[0]);
        $this->assertEquals('Widget', $itemRowsByCategoryType[0]->name);
    }

    public function testTransientData()
    {
        $table = $this->db->category;
        $this->assertNull($table->getTransientData('arbitrary'));
        $this->assertSame($table, $table->setTransientData('info', 'test'));
        $this->assertSame('test', $table->getTransientData('info'));
        $this->assertNull($table->getTransientData('arbitrary'));
        $this->assertSame(posix_getpid(), $table->requireTransientData('pid', 'posix_getpid'));
        $this->assertSame(posix_getpid(), $table->getTransientData('pid'));
        $this->assertSame(2, $table->requireTransientData('number', function() { return 2; }));
    }

}
