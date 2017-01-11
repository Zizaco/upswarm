<?php

namespace Core\Exceptions;

use Core\Exceptions\ServiceException;

class UnserializableMessageDataException extends ServiceException
{
    public function __construct($data = null, $code = 0, $previous = null)
    {
        $dataAsString = is_object($data) ? sprintf("%s object", get_class($data)) : '$data';

        $message = "'$dataAsString' given is not serializable. Probably there is a Closure within the object. Message \$data MUST be serializable.";

        parent::__construct($message, $code);
    }
}
