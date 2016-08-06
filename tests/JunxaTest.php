<?php

namespace Thaumatic\Junxa\Tests;

use Thaumatic\Junxa;
use Thaumatic\Junxa\Events\JunxaQueryEvent;
use Thaumatic\Junxa\Exceptions\JunxaNoSuchTableException;
use Thaumatic\Junxa\Query as Q;
use Thaumatic\Junxa\Tests\DatabaseTestAbstract;

class JunxaTest extends DatabaseTestAbstract
{

    public function testBasicInteraction()
    {
        $this->assertInstanceOf('Thaumatic\Junxa', $this->db());
        $categoryTable = $this->db()->category;
        $this->assertInstanceOf('Thaumatic\Junxa\Table', $categoryTable);
        $this->assertSame('category', $categoryTable->getName());
        $itemTable = $this->db()->item;
        $this->assertInstanceOf('Thaumatic\Junxa\Table', $itemTable);
        $this->assertSame('item', $itemTable->getName());
    }

    public function testConfigurationModes()
    {
        $db1 = new Junxa([
            'hostname'                  => 'localhost',
            'databaseName'              => DatabaseTestAbstract::TEST_DATABASE_NAME,
            'username'                  => 'testUsername',
            'password'                  => '',
            'options'                   => Junxa::DB_PERSISTENT_CONNECTION,
            'defaultTableClass'         => 'FakeTableClass',
            'defaultColumnClass'        => 'FakeColumnClass',
            'defaultRowClass'           => 'FakeRowClass',
            'tableClasses'              => [
                'fake_table'            => 'FakeTableSpecificClass',
            ],
            'columnClasses'             => [
                'fake_column'           => 'FakeColumnSpecificClass',
            ],
            'rowClasses'                => [
                'fake_row'              => 'FakeRowSpecificClass',
            ],
            'regexpTableClasses'        => [
                '/fake_table_pattern/'  => 'FakeTableSetClass',
            ],
            'regexpColumnClasses'       => [
                '/fake_column_pattern/' => 'FakeColumnSetClass',
            ],
            'regexpRowClasses'          => [
                '/fake_row_pattern/'    => 'FakeRowSetClass',
            ],
            'autoTableClassNamespace'   => 'FakeTableNamespace',
            'autoColumnClassNamespace'  => 'FakeColumnNamespace',
            'autoRowClassNamespace'     => 'FakeRowNamespace',
            'changeHandler'             => [
                'hostname'              => 'localhost',
                'databaseName'          => DatabaseTestAbstract::TEST_DATABASE_NAME . '_alt',
                'username'              => 'unusableUsername',
                'password'              => 'unusablePassword',
            ],
        ]);
        $db2 = Junxa::make()
            ->setHostname('localhost')
            ->setDatabase(DatabaseTestAbstract::TEST_DATABASE_NAME)
            ->setUsername('testUsername')
            ->setPassword('')
            ->setOption(Junxa::DB_PERSISTENT_CONNECTION, true)
            ->setDefaultTableClass('FakeTableClass')
            ->setDefaultColumnClass('FakeColumnClass')
            ->setDefaultRowClass('FakeRowClass')
            ->setTableClasses([
                'fake_table'            => 'FakeTableSpecificClass',
            ])
            ->setColumnClasses([
                'fake_column'           => 'FakeColumnSpecificClass',
            ])
            ->setRowClasses([
                'fake_row'              => 'FakeRowSpecificClass',
            ])
            ->setRegexpTableClasses([
                '/fake_table_pattern/'  => 'FakeTableSetClass',
            ])
            ->setRegexpColumnClasses([
                '/fake_column_pattern/' => 'FakeColumnSetClass',
            ])
            ->setRegexpRowClasses([
                '/fake_row_pattern/'    => 'FakeRowSetClass',
            ])
            ->setAutoTableClassNamespace('FakeTableNamespace')
            ->setAutoColumnClassNamespace('FakeColumnNamespace')
            ->setAutoRowClassNamespace('FakeRowNamespace')
            ->setChangeHandler([
                'hostname'              => 'localhost',
                'databaseName'          => DatabaseTestAbstract::TEST_DATABASE_NAME . '_alt',
                'username'              => 'unusableUsername',
                'password'              => 'unusablePassword',
            ])
            ->ready();
        $this->assertEquals($db1, $db2);
    }

    public function runEventSystemTests()
    {
        $listenedDatabase = null;
        $listenedSql = null;
        $listenedQueryBuilder = null;
        $this->db()->getEventDispatcher()->addListener(
            JunxaQueryEvent::NAME,
            function (JunxaQueryEvent $event) use (&$listenedDatabase, &$listenedSql, &$listenedQueryBuilder) {
                $listenedDatabase = $event->getDatabase();
                $listenedSql = $event->getSql();
                $listenedQueryBuilder = $event->getQueryBuilder();
            }
        );
        $showTablesQuery = 'SHOW TABLES';
        $this->db()->query($showTablesQuery);
        $this->assertSame($this->db(), $listenedDatabase);
        $this->assertSame($showTablesQuery, $listenedSql);
        $this->assertNull($listenedQueryBuilder);
        $category = $this->db()->category->row(1);
        $this->assertNull($category);
        $this->assertSame($this->db(), $listenedDatabase);
        $this->assertSame("SELECT *\n\tFROM `category`\n\tWHERE (`id` = 1)\n\tLIMIT 1", $listenedSql);
        $this->assertInstanceOf('Thaumatic\Junxa\Query\Builder', $listenedQueryBuilder);
        $this->assertSame([$this->db()->category], $listenedQueryBuilder->getSelect());
        $this->assertCount(1, $listenedQueryBuilder->getWhere());
        $this->assertSame(1, $listenedQueryBuilder->getLimit());
    }

    public function runStringIntegrityTests()
    {
        $category = $this->db()->category->newRow();
        $this->addGeneratedRow($category);
        $category->name = 'Start';
        $category->created_at = Q::func('NOW');
        $category->insert();
        srand(1);
        for ($i = $this->db()->category->name->getLength() / 10; $i >= 0; $i--) {
            $name = '';
            for ($j = $this->db()->category->name->getLength() - $i - 1; $j >= 0; $j--) {
                $name .= self::unichr(rand(1, 10000));
            }
            $category->name = $name;
            $category->save();
            $this->assertEquals($name, $category->name);
            $categoryAlt = $this->db()->category->row($category->id);
            $this->assertEquals($name, $categoryAlt->name);
        }
    }

    private static function unichr($code)
    {
        return mb_convert_encoding('&#' . intval($code) . ';', 'UTF-8', 'HTML-ENTITIES');
    }

}
