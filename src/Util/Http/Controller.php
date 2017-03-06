<?php

namespace Upswarm\Util\Http;

use FastRoute\Dispatcher;
use React\EventLoop\LoopInterface;
use React\Promise\RejectedPromise;
use Upswarm\Exceptions\ServiceException;
use Upswarm\Message;
use Upswarm\Service;
use Upswarm\Util\Http\HttpRequest;

/**
 * Upswarm base controller service.
 */
class Controller extends Service
{
    /**
     * Handles incoming messages by calling a corresponding action.
     *
     * @param  Message       $message Incoming request.
     * @param  LoopInterface $loop    ReactPHP Loop.
     *
     * @return void
     */
    public function handleMessage(Message $message, LoopInterface $loop)
    {
        if (HttpRequest::class !== $message->getDataType()) {
            return;
        }

        $httpRequest = $message->getData();
        $actionName  = $httpRequest->action;

        // Calls action
        try {
            $actionResponse = $this->$actionName($httpRequest->request, ...array_values($httpRequest->params));
        } catch (\Exception $e) {
            $actionResponse = $this->buildResponse((string) $e, $httpRequest, 500);
        }

        // Wrapps content into an HttpResponse if necessary.
        if (! $actionResponse instanceof HttpResponse) {
            $actionResponse = $this->buildResponse($actionResponse, $httpRequest);
        }

        $message->respond(new Message($actionResponse));
    }

    /**
     * Prepares the given input to become an HttpResponse
     *
     * @param  mixed       $data    Data that should be "wrapped" in an HttpResponse.
     * @param  HttpRequest $request Original request.
     * @param  integer     $code    Http code of the response.
     *
     * @return HttpResponse
     */
    protected function buildResponse($data, HttpRequest $request, int $code = 200): HttpResponse
    {
        if (is_array($data)) {
            $data = json_encode($data);
            return new HttpResponse(['Content-Type' => 'application/json; charset=utf-8', 'Content-Length' => strlen($data), 'Connection' => 'close'], $code, $data);
        }

        if (is_string($data)) {
            return new HttpResponse(['Content-Type' => 'text/html; charset=utf-8', 'Content-Length' => strlen($data), 'Connection' => 'close'], $code, $data);
        }

        return $data;
    }
}
