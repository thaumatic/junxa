<?php

namespace Thaumatic\Junxa\Exceptions;

/**
 * Exception class for requests for a nonexistent key.
 */
class JunxaNoSuchKeyException extends JunxaException
{

    private $keyName;

    public function __construct($keyName)
    {
        $this->keyName = $keyName;
        parent::__construct('no such key: ' . $keyName);
    }

    public function getKeyName()
    {
        return $this->keyName;
    }

}
