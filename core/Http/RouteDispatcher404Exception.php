<?php

namespace Core\Http;

use Exception;

/**
 * For when the HttpServer don't have an Dispatcher registered.
 */
class RouteDispatcher404Exception extends Exception
{
    public function __construct($method = "GET", $route = "/", $code = 404)
    {
        $message = "Route [$method] $route not found.";
        parent::__construct($message, $code);
    }
}
