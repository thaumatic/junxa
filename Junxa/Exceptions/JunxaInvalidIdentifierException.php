<?php

namespace Thaumatic\Junxa\Exceptions;

/**
 * Exception class for use of identifiers that cannot be
 * represented by Junxa.
 */
class JunxaInvalidIdentifierException extends JunxaException
{

    private $identifier;

    public function __construct($identifier)
    {
        $this->identifier = $identifier;
        if ($this->identifier[0] === '_') {
            $msg =
                'cannot represent identifier beginning with underscore: '
                . $identifier
            ;
        } else {
            $msg = 'cannot represent identifier: ' . $identifier;
        }
        parent::__construct($msg);
    }

    public function getIdentifier()
    {
        return $this->identifier;
    }

}
