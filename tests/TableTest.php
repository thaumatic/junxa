<?php

namespace Thaumatic\Junxa\Tests;

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

}
