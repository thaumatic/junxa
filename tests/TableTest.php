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
            'active',
            'created_at',
            'changed_at',
        ], $this->db()->category->getColumns());
        $this->assertSame([
            'id',
            'category_id',
            'name',
            'active',
            'created_at',
            'changed_at',
        ], $this->db()->item->getColumns());
    }

}
