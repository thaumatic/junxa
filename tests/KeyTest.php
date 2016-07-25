<?php

namespace Thaumatic\Junxa\Tests;

use Thaumatic\Junxa\Key;
use Thaumatic\Junxa\Tests\DatabaseTestAbstract;

class KeyTest extends DatabaseTestAbstract
{

    public function testKeyConfiguration()
    {
        $keys = $this->db()->category->getKeys();
        $this->assertCount(3, $keys);
        $this->assertArrayHasKey('PRIMARY', $keys);
        $this->assertArrayHasKey('name', $keys);
        $this->assertArrayHasKey('active', $keys);
        //
        $key = $keys['PRIMARY'];
        $this->assertInstanceOf('Thaumatic\Junxa\Key', $key);
        $this->assertTrue($key->getUnique());
        $this->assertSame(Key::COLLATION_ASCENDING, $key->getCollation());
        $this->assertSame(Key::INDEX_TYPE_BTREE, $key->getIndexType());
        $this->assertEmpty($key->getComment());
        $this->assertEmpty($key->getIndexComment());
        $parts = $key->getParts();
        $this->assertCount(1, $parts);
        $this->assertArrayHasKey(0, $parts);
        $part = $parts[0];
        $this->assertSame('id', $part->getColumnName());
        $this->assertEquals(0, $part->getCardinality());
        $this->assertNull($part->getLength());
        $this->assertFalse($part->getNulls());
        $columnIndices = $key->getColumnIndices();
        $this->assertArrayHasKey($part->getColumnName(), $columnIndices);
        $this->assertEquals(0, $columnIndices[$part->getColumnName()]);
        //
        $key = $keys['name'];
        $this->assertInstanceOf('Thaumatic\Junxa\Key', $key);
        $this->assertTrue($key->getUnique());
        $this->assertSame(Key::COLLATION_ASCENDING, $key->getCollation());
        $this->assertSame(Key::INDEX_TYPE_BTREE, $key->getIndexType());
        $this->assertEmpty($key->getComment());
        $this->assertEmpty($key->getIndexComment());
        $parts = $key->getParts();
        $this->assertCount(1, $parts);
        $this->assertArrayHasKey(0, $parts);
        $part = $parts[0];
        $this->assertSame('name', $part->getColumnName());
        $this->assertEquals(0, $part->getCardinality());
        $this->assertNull($part->getLength());
        $this->assertFalse($part->getNulls());
        $columnIndices = $key->getColumnIndices();
        $this->assertArrayHasKey($part->getColumnName(), $columnIndices);
        $this->assertEquals(0, $columnIndices[$part->getColumnName()]);
        //
        $key = $keys['active'];
        $this->assertInstanceOf('Thaumatic\Junxa\Key', $key);
        $this->assertFalse($key->getUnique());
        $this->assertSame(Key::COLLATION_ASCENDING, $key->getCollation());
        $this->assertSame(Key::INDEX_TYPE_BTREE, $key->getIndexType());
        $this->assertEmpty($key->getComment());
        $this->assertEmpty($key->getIndexComment());
        $parts = $key->getParts();
        $this->assertCount(1, $parts);
        $this->assertArrayHasKey(0, $parts);
        $part = $parts[0];
        $this->assertSame('active', $part->getColumnName());
        $this->assertEquals(0, $part->getCardinality());
        $this->assertNull($part->getLength());
        $this->assertFalse($part->getNulls());
        $columnIndices = $key->getColumnIndices();
        $this->assertArrayHasKey($part->getColumnName(), $columnIndices);
        $this->assertEquals(0, $columnIndices[$part->getColumnName()]);
    }

}
