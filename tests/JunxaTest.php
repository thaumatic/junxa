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

    public function testWithArraySetup()
    {
        $db = new Junxa([
            'hostname'  => 'localhost',
            'database'  => DatabaseTestAbstract::TEST_DATABASE_NAME,
        ]);
        $this->runBasicInteractionTests($db);
        $this->runInsertUpdateAndDeleteTests($db);
        $this->runEventSystemTests($db);
    }

    public function testWithFluentSetup()
    {
        $db = Junxa::make()
            ->setHostname('localhost')
            ->setDatabase(DatabaseTestAbstract::TEST_DATABASE_NAME)
            ->ready()
        ;
        $this->runBasicInteractionTests($db);
        $this->runInsertUpdateAndDeleteTests($db);
        $this->runEventSystemTests($db);
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
            $categoryAlt = $db->category->row($category->id);
            $this->assertNotSame($category, $categoryAlt);
            $this->assertSame($category->name, $categoryAlt->name);
            $result = $category->save();
            $this->assertSame(Junxa::RESULT_UPDATE_NOOP, $result);
            $this->assertTrue(Junxa::OK($result));
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
            $itemAlt = $db->item->row($item->id);
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

    private function runEventSystemTests($db)
    {
        $listenedDatabase = null;
        $listenedSql = null;
        $listenedQueryBuilder = null;
        $db->getEventDispatcher()->addListener(
            JunxaQueryEvent::NAME,
            function (JunxaQueryEvent $event) use (&$listenedDatabase, &$listenedSql, &$listenedQueryBuilder) {
                $listenedDatabase = $event->getDatabase();
                $listenedSql = $event->getSql();
                $listenedQueryBuilder = $event->getQueryBuilder();
            }
        );
        $showTablesQuery = 'SHOW TABLES';
        $db->query($showTablesQuery);
        $this->assertSame($db, $listenedDatabase);
        $this->assertSame($showTablesQuery, $listenedSql);
        $this->assertNull($listenedQueryBuilder);
        $category = $db->category->row(1);
        $this->assertNull($category);
        $this->assertSame($db, $listenedDatabase);
        $this->assertSame("SELECT *\n\tFROM `category`\n\tWHERE (`id` = 1)\n\tLIMIT 1", $listenedSql);
        $this->assertInstanceOf('Thaumatic\Junxa\Query\Builder', $listenedQueryBuilder);
        $this->assertSame([$db->category], $listenedQueryBuilder->getSelect());
        $this->assertCount(1, $listenedQueryBuilder->getWhere());
        $this->assertSame(1, $listenedQueryBuilder->getLimit());
    }

}
