<?php

namespace Thaumatic\Junxa\Exceptions;

/**
 * Exception class for requests for a nonexistent key.
 */
class JunxaNoSuchKeyException extends JunxaException
{

    private $keyName;

    final public function __construct($keyName)
    {
        $this->keyName = $keyName;
        parent::__construct('no such key: ' . $keyName);
    }

    final public function getKeyName()
    {
        return $this->keyName;
    }

}
