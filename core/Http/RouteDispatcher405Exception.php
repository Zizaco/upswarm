<?php

namespace Core\Http;

use Exception;

/**
 * For when a method is not allowed.
 */
class RouteDispatcher405Exception extends Exception
{
    public function __construct($method = "GET", $route = "/", $code = 405)
    {
        $message = "Method [$method] not allowed for $route";
        parent::__construct($message, $code);
    }
}
