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
        $this->assertInstanceOf('Thaumatic\Junxa', $this->db);
        $categoryTable = $this->db->category;
        $this->assertInstanceOf('Thaumatic\Junxa\Table', $categoryTable);
        $this->assertSame('category', $categoryTable->getName());
        $itemTable = $this->db->item;
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
            'foreignKeySuffix'          => '_id',
            'inflectionLocale'          => 'fr',
            'pluralToSingularMap'       => [
                'boxen'                 => 'box',
                'h4x0rz'                => 'h4x0r',
            ],
            'changeHandler'             => [
                'hostname'              => 'localhost',
                'databaseName'          => DatabaseTestAbstract::TEST_DATABASE_NAME . '_alt',
                'username'              => 'unusableUsername',
                'password'              => 'unusablePassword',
            ],
        ]);
        $db2 = Junxa::make()
            ->setHostname('localhost')
            ->setDatabaseName(DatabaseTestAbstract::TEST_DATABASE_NAME)
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
            ->setForeignKeySuffix('_id')
            ->setInflectionLocale('fr')
            ->setPluralToSingularMapping('boxen', 'box')
            ->setPluralToSingularMapping('h4x0rz', 'h4x0r')
            ->setChangeHandler([
                'hostname'              => 'localhost',
                'databaseName'          => DatabaseTestAbstract::TEST_DATABASE_NAME . '_alt',
                'username'              => 'unusableUsername',
                'password'              => 'unusablePassword',
            ])
            ->ready();
        $this->assertEquals($db1, $db2);
    }

    public function testDefaultTableClass()
    {
        $db = Junxa::make()
            ->setHostname('localhost')
            ->setDatabaseName(DatabaseTestAbstract::TEST_DATABASE_NAME)
            ->setUsername('testUsername')
            ->setPassword('')
            ->setDefaultTableClass('Thaumatic\Junxa\Tests\Table\Generic')
            ->ready();
        $table = $db->category;
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Table\Generic', $table);
        $this->assertInstanceOf('Thaumatic\Junxa\Table', $table);
        $this->assertTrue($table->isTestGenericTable());
    }

    public function testDefaultColumnClass()
    {
        $db = Junxa::make()
            ->setHostname('localhost')
            ->setDatabaseName(DatabaseTestAbstract::TEST_DATABASE_NAME)
            ->setUsername('testUsername')
            ->setPassword('')
            ->setDefaultColumnClass('Thaumatic\Junxa\Tests\Column\Generic')
            ->ready();
        $column = $db->category->name;
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Column\Generic', $column);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $column);
        $this->assertTrue($column->isTestGenericColumn());
    }

    public function testDefaultRowClass()
    {
        $db = Junxa::make()
            ->setHostname('localhost')
            ->setDatabaseName(DatabaseTestAbstract::TEST_DATABASE_NAME)
            ->setUsername('testUsername')
            ->setPassword('')
            ->setDefaultRowClass('Thaumatic\Junxa\Tests\Row\Generic')
            ->ready();
        $row = $db->category->newRow();
        $this->addGeneratedRow($row);
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Row\Generic', $row);
        $this->assertInstanceOf('Thaumatic\Junxa\Row', $row);
        $this->assertTrue($row->isTestGenericRow());
    }

    public function testSetTableClasses()
    {
        $db = Junxa::make()
            ->setHostname('localhost')
            ->setDatabaseName(DatabaseTestAbstract::TEST_DATABASE_NAME)
            ->setUsername('testUsername')
            ->setPassword('')
            ->setTableClasses([
                'category'  => 'Thaumatic\Junxa\Tests\Table\Category',
                'item'      => 'Thaumatic\Junxa\Tests\Table\Item',
            ])
            ->ready();
        $table = $db->category;
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Table\Category', $table);
        $this->assertInstanceOf('Thaumatic\Junxa\Table', $table);
        $this->assertTrue($table->isTestCategoryTable());
        $table = $db->item;
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Table\Item', $table);
        $this->assertInstanceOf('Thaumatic\Junxa\Table', $table);
        $this->assertTrue($table->isTestItemTable());
    }

    public function testSetTableClass()
    {
        $db = Junxa::make()
            ->setHostname('localhost')
            ->setDatabaseName(DatabaseTestAbstract::TEST_DATABASE_NAME)
            ->setUsername('testUsername')
            ->setPassword('')
            ->setTableClass('category', 'Thaumatic\Junxa\Tests\Table\Category')
            ->setTableClass('item', 'Thaumatic\Junxa\Tests\Table\Item')
            ->ready();
        $table = $db->category;
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Table\Category', $table);
        $this->assertInstanceOf('Thaumatic\Junxa\Table', $table);
        $this->assertTrue($table->isTestCategoryTable());
        $table = $db->item;
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Table\Item', $table);
        $this->assertInstanceOf('Thaumatic\Junxa\Table', $table);
        $this->assertTrue($table->isTestItemTable());
    }

    public function testSetTableClassesNoCrosstalk()
    {
        $db = Junxa::make()
            ->setHostname('localhost')
            ->setDatabaseName(DatabaseTestAbstract::TEST_DATABASE_NAME)
            ->setUsername('testUsername')
            ->setPassword('')
            ->setTableClasses([
                'category'  => 'Thaumatic\Junxa\Tests\Table\Category',
            ])
            ->ready();
        $table = $db->category;
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Table\Category', $table);
        $this->assertInstanceOf('Thaumatic\Junxa\Table', $table);
        $this->assertTrue($table->isTestCategoryTable());
        $table = $db->item;
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Table\Category', $table);
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Table\Item', $table);
        $this->assertInstanceOf('Thaumatic\Junxa\Table', $table);
        $this->assertFalse(method_exists($table, 'isTestItemTable'));
    }

    public function testSetTableClassNoCrosstalk()
    {
        $db = Junxa::make()
            ->setHostname('localhost')
            ->setDatabaseName(DatabaseTestAbstract::TEST_DATABASE_NAME)
            ->setUsername('testUsername')
            ->setPassword('')
            ->setTableClass('item', 'Thaumatic\Junxa\Tests\Table\Item')
            ->ready();
        $table = $db->category;
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Table\Category', $table);
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Table\Item', $table);
        $this->assertInstanceOf('Thaumatic\Junxa\Table', $table);
        $this->assertFalse(method_exists($table, 'isTestCategoryTable'));
        $table = $db->item;
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Table\Item', $table);
        $this->assertInstanceOf('Thaumatic\Junxa\Table', $table);
        $this->assertTrue($table->isTestItemTable());
    }

    public function testSetTableClassWithDefault()
    {
        $db = Junxa::make()
            ->setHostname('localhost')
            ->setDatabaseName(DatabaseTestAbstract::TEST_DATABASE_NAME)
            ->setUsername('testUsername')
            ->setPassword('')
            ->setTableClass('item', 'Thaumatic\Junxa\Tests\Table\Item')
            ->setDefaultTableClass('Thaumatic\Junxa\Tests\Table\Generic')
            ->ready();
        $table = $db->category;
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Table\Generic', $table);
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Table\Item', $table);
        $this->assertInstanceOf('Thaumatic\Junxa\Table', $table);
        $this->assertTrue($table->isTestGenericTable());
        $this->assertFalse(method_exists($table, 'isTestCategoryTable'));
        $table = $db->item;
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Table\Item', $table);
        $this->assertInstanceOf('Thaumatic\Junxa\Table', $table);
        $this->assertTrue($table->isTestItemTable());
    }

    public function testSetRowClasses()
    {
        $db = Junxa::make()
            ->setHostname('localhost')
            ->setDatabaseName(DatabaseTestAbstract::TEST_DATABASE_NAME)
            ->setUsername('testUsername')
            ->setPassword('')
            ->setRowClasses([
                'category'  => 'Thaumatic\Junxa\Tests\Row\Category',
                'item'      => 'Thaumatic\Junxa\Tests\Row\Item',
            ])
            ->ready();
        $row = $db->category->newRow();
        $this->addGeneratedRow($row);
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Row\Category', $row);
        $this->assertInstanceOf('Thaumatic\Junxa\Row', $row);
        $this->assertTrue($row->isTestCategoryRow());
        $row->name = 'Uncategorized';
        $row->createdAt = Q::func('NOW');
        $row->save();
        $altRow = $db->category->row($row->id);
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Row\Category', $altRow);
        $this->assertInstanceOf('Thaumatic\Junxa\Row', $altRow);
        $this->assertTrue($altRow->isTestCategoryRow());
        $categoryId = $altRow->id;
        $row = $db->item->newRow();
        $this->addGeneratedRow($row);
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Row\Item', $row);
        $this->assertInstanceOf('Thaumatic\Junxa\Row', $row);
        $this->assertTrue($row->isTestItemRow());
        $row->categoryId = $categoryId;
        $row->name = 'Widget';
        $row->createdAt = Q::func('NOW');
        $row->save();
        $altRow = $db->item->row($row->id);
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Row\Item', $altRow);
        $this->assertInstanceOf('Thaumatic\Junxa\Row', $altRow);
        $this->assertTrue($altRow->isTestItemRow());
    }

    public function testSetRowClass()
    {
        $db = Junxa::make()
            ->setHostname('localhost')
            ->setDatabaseName(DatabaseTestAbstract::TEST_DATABASE_NAME)
            ->setUsername('testUsername')
            ->setPassword('')
            ->setRowClass('category', 'Thaumatic\Junxa\Tests\Row\Category')
            ->setRowClass('item', 'Thaumatic\Junxa\Tests\Row\Item')
            ->ready();
        $row = $db->category->newRow();
        $this->addGeneratedRow($row);
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Row\Category', $row);
        $this->assertInstanceOf('Thaumatic\Junxa\Row', $row);
        $this->assertTrue($row->isTestCategoryRow());
        $row = $db->item->newRow();
        $this->addGeneratedRow($row);
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Row\Item', $row);
        $this->assertInstanceOf('Thaumatic\Junxa\Row', $row);
        $this->assertTrue($row->isTestItemRow());
    }

    public function testSetRowClassesNoCrosstalk()
    {
        $db = Junxa::make()
            ->setHostname('localhost')
            ->setDatabaseName(DatabaseTestAbstract::TEST_DATABASE_NAME)
            ->setUsername('testUsername')
            ->setPassword('')
            ->setRowClasses([
                'category'  => 'Thaumatic\Junxa\Tests\Row\Category',
            ])
            ->ready();
        $row = $db->category->newRow();
        $this->addGeneratedRow($row);
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Row\Category', $row);
        $this->assertInstanceOf('Thaumatic\Junxa\Row', $row);
        $this->assertTrue($row->isTestCategoryRow());
        $row = $db->item->newRow();
        $this->addGeneratedRow($row);
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Row\Category', $row);
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Row\Item', $row);
        $this->assertInstanceOf('Thaumatic\Junxa\Row', $row);
        $this->assertFalse(method_exists($row, 'isTestItemRow'));
    }

    public function testSetRowClassNoCrosstalk()
    {
        $db = Junxa::make()
            ->setHostname('localhost')
            ->setDatabaseName(DatabaseTestAbstract::TEST_DATABASE_NAME)
            ->setUsername('testUsername')
            ->setPassword('')
            ->setRowClass('item', 'Thaumatic\Junxa\Tests\Row\Item')
            ->ready();
        $row = $db->category->newRow();
        $this->addGeneratedRow($row);
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Row\Category', $row);
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Row\Item', $row);
        $this->assertInstanceOf('Thaumatic\Junxa\Row', $row);
        $this->assertFalse(method_exists($row, 'isTestCategoryRow'));
        $row = $db->item->newRow();
        $this->addGeneratedRow($row);
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Row\Item', $row);
        $this->assertInstanceOf('Thaumatic\Junxa\Row', $row);
        $this->assertTrue($row->isTestItemRow());
    }

    public function testSetRowClassWithDefault()
    {
        $db = Junxa::make()
            ->setHostname('localhost')
            ->setDatabaseName(DatabaseTestAbstract::TEST_DATABASE_NAME)
            ->setUsername('testUsername')
            ->setPassword('')
            ->setRowClass('item', 'Thaumatic\Junxa\Tests\Row\Item')
            ->setDefaultRowClass('Thaumatic\Junxa\Tests\Row\Generic')
            ->ready();
        $row = $db->category->newRow();
        $this->addGeneratedRow($row);
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Row\Generic', $row);
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Row\Item', $row);
        $this->assertInstanceOf('Thaumatic\Junxa\Row', $row);
        $this->assertTrue($row->isTestGenericRow());
        $this->assertFalse(method_exists($row, 'isTestCategoryRow'));
        $row = $db->item->newRow();
        $this->addGeneratedRow($row);
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Row\Item', $row);
        $this->assertInstanceOf('Thaumatic\Junxa\Row', $row);
        $this->assertTrue($row->isTestItemRow());
    }

    public function testSetColumnClasses()
    {
        $db = Junxa::make()
            ->setHostname('localhost')
            ->setDatabaseName(DatabaseTestAbstract::TEST_DATABASE_NAME)
            ->setUsername('testUsername')
            ->setPassword('')
            ->setColumnClasses([
                'name'      => 'Thaumatic\Junxa\Tests\Column\Name',
                'createdAt' => 'Thaumatic\Junxa\Tests\Column\CreatedAt',
            ])
            ->ready();
        $categoryName = $db->category->name;
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Column\Name', $categoryName);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $categoryName);
        $this->assertTrue($categoryName->isTestNameColumn());
        $categoryCreatedAt = $db->category->createdAt;
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Column\CreatedAt', $categoryCreatedAt);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $categoryCreatedAt);
        $this->assertTrue($categoryCreatedAt->isTestCreatedAtColumn());
        $categoryType = $db->category->type;
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\Name', $categoryType);
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\CreatedAt', $categoryType);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $categoryType);
        $this->assertFalse(method_exists($categoryType, 'isTestNameColumn'));
        $this->assertFalse(method_exists($categoryType, 'isTestCreatedAtColumn'));
        $itemName = $db->item->name;
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Column\Name', $itemName);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $itemName);
        $this->assertTrue($itemName->isTestNameColumn());
        $itemCreatedAt = $db->item->createdAt;
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Column\CreatedAt', $itemCreatedAt);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $itemCreatedAt);
        $this->assertTrue($itemCreatedAt->isTestCreatedAtColumn());
        $itemCategoryId = $db->item->categoryId;
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\Name', $itemCategoryId);
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\CreatedAt', $itemCategoryId);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $itemCategoryId);
        $this->assertFalse(method_exists($itemCategoryId, 'isTestNameColumn'));
        $this->assertFalse(method_exists($itemCategoryId, 'isTestCreatedAtColumn'));
        //
        $category = $db->category->newRow();
        $this->addGeneratedRow($category);
        $category->name = 'Uncategorized';
        $category->save();
        $this->assertGreaterThan(time() - 1, strtotime($category->createdAt));
        $item = $db->item->newRow();
        $this->addGeneratedRow($item);
        $item->categoryId = $category->id;
        $item->name = 'Widget';
        $item->price = 5.00;
        $item->save();
        $this->assertGreaterThan(time() - 1, strtotime($item->createdAt));
    }

    public function testSetColumnClass()
    {
        $db = Junxa::make()
            ->setHostname('localhost')
            ->setDatabaseName(DatabaseTestAbstract::TEST_DATABASE_NAME)
            ->setUsername('testUsername')
            ->setPassword('')
            ->setColumnClass('name', 'Thaumatic\Junxa\Tests\Column\Name')
            ->setColumnClass('createdAt', 'Thaumatic\Junxa\Tests\Column\CreatedAt')
            ->ready();
        $categoryName = $db->category->name;
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Column\Name', $categoryName);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $categoryName);
        $this->assertTrue($categoryName->isTestNameColumn());
        $categoryCreatedAt = $db->category->createdAt;
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Column\CreatedAt', $categoryCreatedAt);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $categoryCreatedAt);
        $this->assertTrue($categoryCreatedAt->isTestCreatedAtColumn());
        $categoryType = $db->category->type;
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\Name', $categoryType);
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\CreatedAt', $categoryType);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $categoryType);
        $this->assertFalse(method_exists($categoryType, 'isTestNameColumn'));
        $this->assertFalse(method_exists($categoryType, 'isTestCreatedAtColumn'));
        $itemName = $db->item->name;
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Column\Name', $itemName);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $itemName);
        $this->assertTrue($itemName->isTestNameColumn());
        $itemCreatedAt = $db->item->createdAt;
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Column\CreatedAt', $itemCreatedAt);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $itemCreatedAt);
        $this->assertTrue($itemCreatedAt->isTestCreatedAtColumn());
        $itemCategoryId = $db->item->categoryId;
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\Name', $itemCategoryId);
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\CreatedAt', $itemCategoryId);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $itemCategoryId);
        $this->assertFalse(method_exists($itemCategoryId, 'isTestNameColumn'));
        $this->assertFalse(method_exists($itemCategoryId, 'isTestCreatedAtColumn'));
        //
        $category = $db->category->newRow();
        $this->addGeneratedRow($category);
        $category->name = 'Uncategorized';
        $category->save();
        $this->assertGreaterThan(time() - 1, strtotime($category->createdAt));
        $item = $db->item->newRow();
        $this->addGeneratedRow($item);
        $item->categoryId = $category->id;
        $item->name = 'Widget';
        $item->price = 5.00;
        $item->save();
        $this->assertGreaterThan(time() - 1, strtotime($item->createdAt));
    }

    public function testSetColumnClassesNoCrosstalk()
    {
        $db = Junxa::make()
            ->setHostname('localhost')
            ->setDatabaseName(DatabaseTestAbstract::TEST_DATABASE_NAME)
            ->setUsername('testUsername')
            ->setPassword('')
            ->setColumnClasses([
                'name'      => 'Thaumatic\Junxa\Tests\Column\Name',
            ])
            ->ready();
        $categoryName = $db->category->name;
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Column\Name', $categoryName);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $categoryName);
        $this->assertTrue($categoryName->isTestNameColumn());
        $categoryCreatedAt = $db->category->createdAt;
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\Name', $categoryCreatedAt);
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\CreatedAt', $categoryCreatedAt);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $categoryCreatedAt);
        $this->assertFalse(method_exists($categoryCreatedAt, 'isTestNameColumn'));
        $this->assertFalse(method_exists($categoryCreatedAt, 'isTestCreatedAtColumn'));
        $categoryType = $db->category->type;
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\Name', $categoryType);
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\CreatedAt', $categoryType);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $categoryType);
        $this->assertFalse(method_exists($categoryType, 'isTestNameColumn'));
        $this->assertFalse(method_exists($categoryType, 'isTestCreatedAtColumn'));
        $itemName = $db->item->name;
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Column\Name', $itemName);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $itemName);
        $this->assertTrue($itemName->isTestNameColumn());
        $itemCreatedAt = $db->item->createdAt;
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\Name', $itemCreatedAt);
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\CreatedAt', $itemCreatedAt);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $itemCreatedAt);
        $this->assertFalse(method_exists($itemCreatedAt, 'isTestNameColumn'));
        $this->assertFalse(method_exists($itemCreatedAt, 'isTestCreatedAtColumn'));
        $itemCategoryId = $db->item->categoryId;
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\Name', $itemCategoryId);
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\CreatedAt', $itemCategoryId);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $itemCategoryId);
        $this->assertFalse(method_exists($itemCategoryId, 'isTestNameColumn'));
        $this->assertFalse(method_exists($itemCategoryId, 'isTestCreatedAtColumn'));
        //
        $category = $db->category->newRow();
        $this->addGeneratedRow($category);
        $category->name = 'Uncategorized';
        $category->save();
        $this->assertSame('0000-00-00 00:00:00', $category->createdAt);
        $item = $db->item->newRow();
        $this->addGeneratedRow($item);
        $item->categoryId = $category->id;
        $item->name = 'Widget';
        $item->price = 5.00;
        $item->save();
        $this->assertSame('0000-00-00 00:00:00', $item->createdAt);
    }

    public function testSetColumnClassNoCrosstalk()
    {
        $db = Junxa::make()
            ->setHostname('localhost')
            ->setDatabaseName(DatabaseTestAbstract::TEST_DATABASE_NAME)
            ->setUsername('testUsername')
            ->setPassword('')
            ->setColumnClass('createdAt', 'Thaumatic\Junxa\Tests\Column\CreatedAt')
            ->ready();
        $categoryName = $db->category->name;
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\Name', $categoryName);
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\CreatedAt', $categoryName);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $categoryName);
        $this->assertFalse(method_exists($categoryName, 'isTestNameColumn'));
        $this->assertFalse(method_exists($categoryName, 'isTestCreatedAtColumn'));
        $categoryCreatedAt = $db->category->createdAt;
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Column\CreatedAt', $categoryCreatedAt);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $categoryCreatedAt);
        $this->assertTrue($categoryCreatedAt->isTestCreatedAtColumn());
        $categoryType = $db->category->type;
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\Name', $categoryType);
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\CreatedAt', $categoryType);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $categoryType);
        $this->assertFalse(method_exists($categoryType, 'isTestNameColumn'));
        $this->assertFalse(method_exists($categoryType, 'isTestCreatedAtColumn'));
        $itemName = $db->item->name;
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\Name', $itemName);
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\CreatedAt', $itemName);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $itemName);
        $this->assertFalse(method_exists($itemName, 'isTestNameColumn'));
        $this->assertFalse(method_exists($itemName, 'isTestCreatedAtColumn'));
        $itemCreatedAt = $db->item->createdAt;
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Column\CreatedAt', $itemCreatedAt);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $itemCreatedAt);
        $this->assertTrue($itemCreatedAt->isTestCreatedAtColumn());
        $itemCategoryId = $db->item->categoryId;
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\Name', $itemCategoryId);
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\CreatedAt', $itemCategoryId);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $itemCategoryId);
        $this->assertFalse(method_exists($itemCategoryId, 'isTestNameColumn'));
        $this->assertFalse(method_exists($itemCategoryId, 'isTestCreatedAtColumn'));
        //
        $category = $db->category->newRow();
        $this->addGeneratedRow($category);
        $category->name = 'Uncategorized';
        $category->save();
        $this->assertGreaterThan(time() - 1, strtotime($category->createdAt));
        $item = $db->item->newRow();
        $this->addGeneratedRow($item);
        $item->categoryId = $category->id;
        $item->name = 'Widget';
        $item->price = 5.00;
        $item->save();
        $this->assertGreaterThan(time() - 1, strtotime($item->createdAt));
    }

    public function testSetColumnClassWithDefault()
    {
        $db = Junxa::make()
            ->setHostname('localhost')
            ->setDatabaseName(DatabaseTestAbstract::TEST_DATABASE_NAME)
            ->setUsername('testUsername')
            ->setPassword('')
            ->setColumnClass('createdAt', 'Thaumatic\Junxa\Tests\Column\CreatedAt')
            ->setDefaultColumnClass('Thaumatic\Junxa\Tests\Column\Generic')
            ->ready();
        $categoryName = $db->category->name;
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Column\Generic', $categoryName);
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\Name', $categoryName);
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\CreatedAt', $categoryName);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $categoryName);
        $this->assertTrue($categoryName->isTestGenericColumn());
        $this->assertFalse(method_exists($categoryName, 'isTestNameColumn'));
        $this->assertFalse(method_exists($categoryName, 'isTestCreatedAtColumn'));
        $categoryCreatedAt = $db->category->createdAt;
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Column\CreatedAt', $categoryCreatedAt);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $categoryCreatedAt);
        $this->assertTrue($categoryCreatedAt->isTestCreatedAtColumn());
        $categoryType = $db->category->type;
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\Name', $categoryType);
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\CreatedAt', $categoryType);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $categoryType);
        $this->assertFalse(method_exists($categoryType, 'isTestNameColumn'));
        $this->assertFalse(method_exists($categoryType, 'isTestCreatedAtColumn'));
        $itemName = $db->item->name;
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Column\Generic', $itemName);
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\Name', $itemName);
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\CreatedAt', $itemName);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $itemName);
        $this->assertTrue($itemName->isTestGenericColumn());
        $this->assertFalse(method_exists($itemName, 'isTestNameColumn'));
        $this->assertFalse(method_exists($itemName, 'isTestCreatedAtColumn'));
        $itemCreatedAt = $db->item->createdAt;
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Column\CreatedAt', $itemCreatedAt);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $itemCreatedAt);
        $this->assertTrue($itemCreatedAt->isTestCreatedAtColumn());
        $itemCategoryId = $db->item->categoryId;
        $this->assertInstanceOf('Thaumatic\Junxa\Tests\Column\Generic', $itemCategoryId);
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\Name', $itemCategoryId);
        $this->assertNotInstanceOf('Thaumatic\Junxa\Tests\Column\CreatedAt', $itemCategoryId);
        $this->assertInstanceOf('Thaumatic\Junxa\Column', $itemCategoryId);
        $this->assertTrue($itemCategoryId->isTestGenericColumn());
        $this->assertFalse(method_exists($itemCategoryId, 'isTestNameColumn'));
        $this->assertFalse(method_exists($itemCategoryId, 'isTestCreatedAtColumn'));
        //
        $category = $db->category->newRow();
        $this->addGeneratedRow($category);
        $category->name = 'Uncategorized';
        $category->save();
        $this->assertGreaterThan(time() - 1, strtotime($category->createdAt));
        $item = $db->item->newRow();
        $this->addGeneratedRow($item);
        $item->categoryId = $category->id;
        $item->name = 'Widget';
        $item->price = 5.00;
        $item->save();
        $this->assertGreaterThan(time() - 1, strtotime($item->createdAt));
    }

    public function testGetSingularFromPlural()
    {
        $originalMap = $this->db->getPluralToSingularMap();
        try {
            $this->assertSame('category', $this->db->getSingularFromPlural('categories'));
            $this->assertSame('item', $this->db->getSingularFromPlural('items'));
            $this->assertSame('child', $this->db->getSingularFromPlural('children'));
            $this->assertSame('index', $this->db->getSingularFromPlural('indices'));
            $this->assertSame('medium', $this->db->getSingularFromPlural('media'));
            $this->assertSame('matrix', $this->db->getSingularFromPlural('matrices'));
            $this->assertSame('foo', $this->db->getSingularFromPlural('foo'));
            $this->db->setPluralToSingularMapping('foo', 'bar');
            $this->assertSame('bar', $this->db->getSingularFromPlural('foo'));
            $this->db->setPluralToSingularMapping('categories', 'categoron');
            $this->assertSame('categoron', $this->db->getSingularFromPlural('categories'));
            $this->db->setPluralToSingularMap($originalMap);
            $this->assertSame('foo', $this->db->getSingularFromPlural('foo'));
            $this->assertSame('category', $this->db->getSingularFromPlural('categories'));
        } finally {
            $this->db->setPluralToSingularMap($originalMap);
        }
    }

    public function testEventSystem()
    {
        $listenedDatabase = null;
        $listenedSql = null;
        $listenedQueryBuilder = null;
        $this->db->getEventDispatcher()->addListener(
            JunxaQueryEvent::NAME,
            function (JunxaQueryEvent $event) use (&$listenedDatabase, &$listenedSql, &$listenedQueryBuilder) {
                $listenedDatabase = $event->getDatabase();
                $listenedSql = $event->getSql();
                $listenedQueryBuilder = $event->getQueryBuilder();
            }
        );
        $showTablesQuery = 'SHOW TABLES';
        $this->db->query($showTablesQuery);
        $this->assertSame($this->db, $listenedDatabase);
        $this->assertSame($showTablesQuery, $listenedSql);
        $this->assertNull($listenedQueryBuilder);
        $category = $this->db->category->row(1);
        $this->assertNull($category);
        $this->assertSame($this->db, $listenedDatabase);
        $this->assertSame("SELECT *\n\tFROM `category`\n\tWHERE (`id` = 1)\n\tLIMIT 1", $listenedSql);
        $this->assertInstanceOf('Thaumatic\Junxa\Query\Builder', $listenedQueryBuilder);
        $this->assertSame([$this->db->category], $listenedQueryBuilder->getSelect());
        $this->assertCount(1, $listenedQueryBuilder->getWhere());
        $this->assertSame(1, $listenedQueryBuilder->getLimit());
    }

    public function testStringIntegrity()
    {
        $category = $this->db->category->newRow();
        $this->addGeneratedRow($category);
        $category->name = 'Start';
        $category->createdAt = Q::func('NOW');
        $category->insert();
        srand(1);
        for ($i = $this->db->category->name->getLength() / 10; $i >= 0; $i--) {
            $name = '';
            for ($j = $this->db->category->name->getLength() - $i - 1; $j >= 0; $j--) {
                $name .= self::unichr(rand(1, 10000));
            }
            $category->name = $name;
            $category->save();
            $this->assertEquals($name, $category->name);
            $categoryAlt = $this->db->category->row($category->id);
            $this->assertEquals($name, $categoryAlt->name);
        }
    }

    private static function unichr($code)
    {
        return mb_convert_encoding('&#' . intval($code) . ';', 'UTF-8', 'HTML-ENTITIES');
    }

}
