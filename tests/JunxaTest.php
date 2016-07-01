<?php

require 'DatabaseTest.php';

use Thaumatic\Junxa;
use Thaumatic\Junxa\Exceptions\JunxaNoSuchTableException;
use Thaumatic\Junxa\Query as Q;

class JunxaTest
    extends DatabaseTest
{

    public function testWithArraySetup()
    {
        $db = new Junxa([
            'hostname'  => 'localhost',
            'database'  => DatabaseTest::TEST_DATABASE_NAME,
        ]);
        $this->runBasicInteractionTests($db);
        $this->runInsertUpdateAndDeleteTests($db);
    }

    public function testWithFluentSetup()
    {
        $db = Junxa::make()
            ->setHostname('localhost')
            ->setDatabase(DatabaseTest::TEST_DATABASE_NAME)
            ->ready()
        ;
        $this->runBasicInteractionTests($db);
        $this->runInsertUpdateAndDeleteTests($db);
    }

    private function runBasicInteractionTests($db)
    {
        $this->assertInstanceOf('Thaumatic\Junxa', $db);
        $categoryTable = $db->category;
        $this->assertInstanceOf('Thaumatic\Junxa\Table', $categoryTable);
        $this->assertSame('category', $categoryTable->getName());
        $itemTable = $db->item;
        $this->assertInstanceOf('Thaumatic\Junxa\Table', $itemTable);
        $this->assertSame('item', $itemTable->getName());
        $categoryTableIdColumn = $categoryTable->id;
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $categoryTableIdColumn);
        $this->assertSame('id', $categoryTableIdColumn->getName());
    }

    private function runInsertUpdateAndDeleteTests($db)
    {
        try {
            $category = $db->category->row();
            $category->name = 'Uncategorized';
            $category->created_at = Q::func('NOW');
            $category->insert();
            //
            $this->assertInternalType('int', $category->id);
            $this->assertSame('Uncategorized', $category->name);
            $this->assertRegExp('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $category->created_at);
            $this->assertLessThanOrEqual(1, time() - strtotime($category->created_at));
            $this->assertRegExp('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $category->changed_at);
            $this->assertLessThanOrEqual(1, time() - strtotime($category->changed_at));
            $this->assertTrue($category->active);
            //
            $category->name = 'Recategorized';
            $category->save();
            $this->assertSame('Recategorized', $category->name);
            $categoryAlt = $db->category->row($category->id);
            $this->assertNotSame($category, $categoryAlt);
            $this->assertSame($category->name, $categoryAlt->name);
            //
            $item = $db->item->row();
            $item->category_id = $category->id;
            $item->name = 'Widget';
            $item->created_at = Q::func('NOW');
            $item->insert();
            //
            $this->assertInternalType('int', $item->id);
            $this->assertSame($category->id, $item->category_id);
            $this->assertSame('Widget', $item->name);
            $this->assertRegExp('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $item->created_at);
            $this->assertLessThanOrEqual(1, time() - strtotime($item->created_at));
            $this->assertRegExp('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $item->changed_at);
            $this->assertLessThanOrEqual(1, time() - strtotime($item->changed_at));
            $this->assertTrue($item->active);
        } finally {
            if(isset($category) && $category->id !== null)
                $category->delete();
            if(isset($item) && $item->id !== null)
                $item->delete();
        }
    }

}
