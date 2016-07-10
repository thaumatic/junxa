<?php

namespace Thaumatic\Junxa\Tests;

use Thaumatic\Junxa;
use Thaumatic\Junxa\Column;
use Thaumatic\Junxa\Tests\DatabaseTestAbstract;

class ColumnTest extends DatabaseTestAbstract
{

    public function testWithArraySetup()
    {
        $db = new Junxa([
            'hostname'  => 'localhost',
            'database'  => DatabaseTestAbstract::TEST_DATABASE_NAME,
        ]);
        $this->runColumnConfigurationTests($db);
    }

    public function testWithFluentSetup()
    {
        $db = Junxa::make()
            ->setHostname('localhost')
            ->setDatabase(DatabaseTestAbstract::TEST_DATABASE_NAME)
            ->ready()
        ;
        $this->runColumnConfigurationTests($db);
    }

    private function runColumnConfigurationTests($db)
    {
        $categoryIdColumn = $db->category->id;
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $categoryIdColumn);
        $this->assertSame('id', $categoryIdColumn->getName());
        $this->assertSame('mediumint(8) unsigned', $categoryIdColumn->getFullType());
        $this->assertSame('mediumint', $categoryIdColumn->getType());
        $this->assertSame('int', $categoryIdColumn->getTypeClass());
        $this->assertSame(
            Column::MYSQL_FLAG_NUM
            | Column::MYSQL_FLAG_UNSIGNED
            | Column::MYSQL_FLAG_NOT_NULL
            | Column::MYSQL_FLAG_PRI_KEY
            | Column::MYSQL_FLAG_PART_KEY
            | Column::MYSQL_FLAG_AUTO_INCREMENT,
            $categoryIdColumn->getFlags()
        );
        $this->assertFalse($categoryIdColumn->isDynamic());
        $this->assertNull($categoryIdColumn->getDynamicAlias());
        $this->assertNull($categoryIdColumn->getDefault());
        $this->assertFalse($categoryIdColumn->hasDefault());
        $this->assertNull($categoryIdColumn->getDefaultValue());
        $categoryNameColumn = $db->category->name;
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $categoryNameColumn);
        $this->assertSame('name', $categoryNameColumn->getName());
        $this->assertSame('varchar(250)', $categoryNameColumn->getFullType());
        $this->assertSame('varchar', $categoryNameColumn->getType());
        $this->assertSame('text', $categoryNameColumn->getTypeClass());
        $this->assertSame(
            Column::MYSQL_FLAG_NOT_NULL |
            Column::MYSQL_FLAG_UNIQUE_KEY |
            Column::MYSQL_FLAG_NO_DEFAULT_VALUE |
            Column::MYSQL_FLAG_PART_KEY,
            $categoryNameColumn->getFlags()
        );
        $this->assertFalse($categoryNameColumn->isDynamic());
        $this->assertNull($categoryNameColumn->getDynamicAlias());
        $this->assertNull($categoryNameColumn->getDefault());
        $this->assertFalse($categoryNameColumn->hasDefault());
        $this->assertNull($categoryNameColumn->getDefaultValue());
    }

}
