<?php

namespace Thaumatic\Junxa\Tests\Column;

use Thaumatic\Junxa\Query as Q;

class CreatedAt extends \Thaumatic\Junxa\Column
{

    public function init()
    {
        parent::init();
        $this->setDynamicDefault(Q::func('NOW'));
    }

    public function isTestCreatedAtColumn()
    {
        return true;
    }

}
