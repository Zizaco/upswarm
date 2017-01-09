<?php

namespace Core\Http;

use Exception;

/**
 * For when the HttpServer don't have an Dispatcher registered.
 */
class RouteDispatcherMissingException extends Exception
{
    public function __construct($message = 'Route dispatcher missing. Call \'HttpServer::routes\' before calling \'HttpServer::listen\'', $code = 0)
    {
        parent::__construct($message, $code);
    }
}
