<?php

namespace Core\Util\Http;

use Core\Exceptions\ServiceException;

/**
 * For when a route was not found.
 */
class RouteDispatcher404Exception extends ServiceException
{
    public function __construct($method = "GET", $route = "/", $code = 404)
    {
        $message = "Route [$method] $route not found.";
        parent::__construct($message, $code);
    }
}
