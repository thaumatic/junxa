<?php

namespace Thaumatic\Junxa\Tests;

use Thaumatic\Junxa\Query as Q;

class QueryTest extends \PHPUnit_Framework_TestCase
{

    public function testGenerateEq()
    {
        $elem = Q::eq(1, 2);
        $this->assertInstanceOf('Thaumatic\Junxa\Query\Element', $elem);
    }

}
