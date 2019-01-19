<?php

namespace Thaumatic\Junxa\Tests;

use PHPUnit\Framework\TestCase;
use Thaumatic\Junxa\Query as Q;

class QueryTest extends TestCase
{

    public function testGenerateEq()
    {
        $elem = Q::eq(1, 2);
        $this->assertInstanceOf('Thaumatic\Junxa\Query\Element', $elem);
    }

}
