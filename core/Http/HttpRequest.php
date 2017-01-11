<?php

namespace Core\Http;

use React\Http\Request;

/**
 * Wraps an dispatched Http Request
 */
class HttpRequest
{
    /**
     * ReactPHP Http Request object.
     * @var Request
     */
    public $request;

    /**
     * Request action.
     * @var string
     */
    public $action;

    /**
     * Parameters of the path of the request.
     * @var array
     */
    public $params;

    /**
     * Constructs an instance with properties
     *
     * @param Request $request ReactPHP Http Request object.
     * @param string  $action  Request action.
     * @param array   $params  Parameters of the path of the request.
     */
    public function __construct(Request $request, string $action, array $params)
    {
        $this->request = $request;
        $this->action  = $action;
        $this->params  = $params;
    }
}
