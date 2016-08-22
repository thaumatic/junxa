<?php

namespace Thaumatic\Junxa\Exceptions;

/**
 * Exception class for use of identifiers that cannot be
 * represented by Junxa.
 */
class JunxaInvalidIdentifierException extends JunxaException
{

    private $identifier;

    final public function __construct($identifier)
    {
        $this->identifier = $identifier;
        if (preg_match('/^junxaInternal/', $this->identifier)) {
            $msg =
                'cannot represent identifier beginning with junxaInternal: '
                . $identifier
            ;
        } else {
            $msg = 'cannot represent identifier: ' . $identifier;
        }
        parent::__construct($msg);
    }

    final public function getIdentifier()
    {
        return $this->identifier;
    }

}
