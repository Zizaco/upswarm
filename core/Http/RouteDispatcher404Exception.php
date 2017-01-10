<?php

namespace Core\Http;

use Exception;

/**
 * For when a route was not found.
 */
class RouteDispatcher404Exception extends Exception
{
    public function __construct($method = "GET", $route = "/", $code = 404)
    {
        $message = "Route [$method] $route not found.";
        parent::__construct($message, $code);
    }
}
