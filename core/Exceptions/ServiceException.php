<?php

namespace Core\Exceptions;

use Exception;
use Serializable;

/**
 * An generic serializable Exception that may be exchanged between services.
 *
 * @see http://fabien.potencier.org/php-serialization-stack-traces-and-exceptions.html
 */
class ServiceException extends Exception implements Serializable
{
    /**
     * Pick properties in order to be serializable
     *
     * @return string
     */
    public function serialize()
    {
        return serialize(array($this->code, $this->message));
    }

    /**
     * @param string $serialized Serialized exception.
     *
     * @return void
     */
    public function unserialize($serialized)
    {
        list($this->code, $this->message) = unserialize($serialized);
    }
}
