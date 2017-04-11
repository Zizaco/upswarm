<?php

namespace Upswarm\Util\Http;

use Psr\Http\Message\RequestInterface;

/**
 * Wraps an dispatched Http Request
 */
class HttpRequest
{
    /**
     * ReactPHP Http Request object.
     * @var RequestInterface
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
     * @param RequestInterface $request ReactPHP Http Request object.
     * @param string           $action  Request action.
     * @param array            $params  Parameters of the path of the request.
     */
    public function __construct(RequestInterface $request, string $action, array $params)
    {
        $this->request = $request;
        $this->action  = $action;
        $this->params  = $params;
    }
}
