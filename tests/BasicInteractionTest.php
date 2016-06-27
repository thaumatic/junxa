<?php

require 'DatabaseTest.php';

use Thaumatic\Junxa;
use Thaumatic\Junxa\Exceptions\JunxaNoSuchTableException;

class BasicInteractionTest
    extends DatabaseTest
{

    private function runBasicInteractionTests($db)
    {
        $this->assertInstanceOf('Thaumatic\Junxa', $db);
        $categoryTable = $db->category;
        $this->assertInstanceOf('Thaumatic\Junxa\Table', $categoryTable);
        $this->assertSame('category', $categoryTable->name());
        $itemTable = $db->item;
        $this->assertInstanceOf('Thaumatic\Junxa\Table', $itemTable);
        $this->assertSame('item', $itemTable->name());
        $categoryTableIdColumn = $categoryTable->id;
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $categoryTableIdColumn);
        $this->assertSame('id', $categoryTableIdColumn->name());
    }

    public function testWithArraySetup()
    {
        $db = new Junxa([
            'hostname'  => 'localhost',
            'database'  => DatabaseTest::TEST_DATABASE_NAME,
        ]);
        $this->runBasicInteractionTests($db);
    }

    public function testWithFluentSetup()
    {
        $db = Junxa::make()
            ->setHostname('localhost')
            ->setDatabase(DatabaseTest::TEST_DATABASE_NAME)
            ->ready()
        ;
        $this->runBasicInteractionTests($db);
    }

}
