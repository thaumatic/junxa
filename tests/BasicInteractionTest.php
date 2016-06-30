<?php

require 'DatabaseTest.php';

use Thaumatic\Junxa;
use Thaumatic\Junxa\Exceptions\JunxaNoSuchTableException;
use Thaumatic\Junxa\Query as Q;

class BasicInteractionTest
    extends DatabaseTest
{

    public function testWithArraySetup()
    {
        $db = new Junxa([
            'hostname'  => 'localhost',
            'database'  => DatabaseTest::TEST_DATABASE_NAME,
            'options'   => Junxa::DB_DATABASE_ERRORS,
        ]);
        $this->runBasicInteractionTests($db);
        $this->runInsertTests($db);
    }

    public function testWithFluentSetup()
    {
        $db = Junxa::make()
            ->setHostname('localhost')
            ->setDatabase(DatabaseTest::TEST_DATABASE_NAME)
            ->setOption(Junxa::DB_DATABASE_ERRORS, true)
            ->ready()
        ;
        $this->runBasicInteractionTests($db);
        $this->runInsertTests($db);
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

    private function runInsertTests($db)
    {
        $category = $db->category->row();
        $category->name = 'Uncategorized';
        $category->created_at = Q::func('NOW');
        $category->insert();
        $this->assertInternalType('int', $category->id);
        $this->assertSame('Uncategorized', $category->name);
        $this->assertRegExp('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $category->created_at);
        $this->assertRegExp('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $category->changed_at);
        $category->delete();
    }

}
