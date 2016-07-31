<?php

namespace Thaumatic\Junxa\Tests;

use Thaumatic\Junxa;
use Thaumatic\Junxa\Events\JunxaQueryEvent;
use Thaumatic\Junxa\Exceptions\JunxaNoSuchTableException;
use Thaumatic\Junxa\Query as Q;
use Thaumatic\Junxa\Tests\DatabaseTestAbstract;

class JunxaTest extends DatabaseTestAbstract
{

    const PATTERN_DATETIME_ANCHORED = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';

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
            'database'                  => DatabaseTestAbstract::TEST_DATABASE_NAME,
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
                'database'              => DatabaseTestAbstract::TEST_DATABASE_NAME . '_alt',
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
                'database'              => DatabaseTestAbstract::TEST_DATABASE_NAME . '_alt',
                'username'              => 'unusableUsername',
                'password'              => 'unusablePassword',
            ])
            ->ready();
        $this->assertEquals($db1, $db2);
    }

    public function testInsertUpdateAndDelete()
    {
        try {
            $category = $this->db()->category->newRow();
            $category->name = 'Uncategorized';
            $category->created_at = Q::func('NOW');
            $category->insert();
            //
            $this->assertInternalType('int', $category->id);
            $this->assertSame('Uncategorized', $category->name);
            $this->assertRegExp(self::PATTERN_DATETIME_ANCHORED, $category->created_at);
            $this->assertLessThanOrEqual(1, time() - strtotime($category->created_at));
            $this->assertRegExp(self::PATTERN_DATETIME_ANCHORED, $category->changed_at);
            $this->assertLessThanOrEqual(1, time() - strtotime($category->changed_at));
            $this->assertTrue($category->active);
            //
            $originalCategoryId = $category->id;
            $category->name = 'Recategorized';
            $result = $category->save();
            $this->assertSame(Junxa::RESULT_SUCCESS, $result);
            $this->assertSame('Recategorized', $category->name);
            $this->assertSame($originalCategoryId, $category->id);
            $categoryAlt = $this->db()->category->row($category->id);
            $this->assertNotSame($category, $categoryAlt);
            $this->assertSame($category->name, $categoryAlt->name);
            $result = $category->save();
            $this->assertSame(Junxa::RESULT_UPDATE_NOOP, $result);
            $this->assertTrue(Junxa::OK($result));
            //
            $item = $this->db()->item->newRow();
            $item->category_id = $category->id;
            $item->name = 'Widget';
            $item->created_at = Q::func('NOW');
            $item->insert();
            //
            $this->assertInternalType('int', $item->id);
            $this->assertSame($category->id, $item->category_id);
            $this->assertSame('Widget', $item->name);
            $this->assertRegExp(self::PATTERN_DATETIME_ANCHORED, $item->created_at);
            $this->assertLessThanOrEqual(1, time() - strtotime($item->created_at));
            $this->assertRegExp(self::PATTERN_DATETIME_ANCHORED, $item->changed_at);
            $this->assertLessThanOrEqual(1, time() - strtotime($item->changed_at));
            $this->assertTrue($item->active);
            //
            $originalItemId = $item->id;
            $item->name = 'Whatsit';
            $result = $item->save();
            $this->assertSame(Junxa::RESULT_SUCCESS, $result);
            $this->assertSame('Whatsit', $item->name);
            $this->assertSame($originalItemId, $item->id);
            $itemAlt = $this->db()->item->row($item->id);
            $this->assertNotSame($item, $itemAlt);
            $this->assertSame($item->name, $itemAlt->name);
            $result = $item->save();
            $this->assertSame(Junxa::RESULT_UPDATE_NOOP, $result);
            $this->assertTrue(Junxa::OK($result));
        } finally {
            if (isset($category) && $category->id !== null) {
                $category->delete();
            }
            if (isset($item) && $item->id !== null) {
                $item->delete();
            }
        }
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
        try {
            $category = $this->db()->category->newRow();
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
        } finally {
            if (isset($category) && $category->id !== null) {
                $category->delete();
            }
        }
    }

    private static function unichr($code)
    {
        return mb_convert_encoding('&#' . intval($code) . ';', 'UTF-8', 'HTML-ENTITIES');
    }

}
