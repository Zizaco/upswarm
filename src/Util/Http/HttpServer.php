<?php

namespace Upswarm\Util\Http;

use FastRoute\Dispatcher;
use Psr\Http\Message\RequestInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Promise;
use React\Promise\RejectedPromise;
use RingCentral\Psr7\BufferStream;
use RingCentral\Psr7\Response;
use Upswarm\Message;
use Upswarm\Service;
use Upswarm\Util\Http\HttpResponse;

/**
 * Upswarm HttpServer wraps the ReactHTTP server.
 */
class HttpServer
{
    /**
     * Upswarm service in which the Http server is running.
     * @var Service
     */
    protected $service;

    /**
     * ReactPHP loop
     * @var LoopInterface
     */
    protected $loop;

    /**
     * Fast route dispatcher
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * Name of service that will handle exceptions
     * @var string
     */
    protected $errorHandler;

    protected $reactHttpServer;
    protected $reactSocket;

    /**
     * Injects dependencies
     *
     * @param Service $service Service in which the Http Server will run into.
     */
    public function __construct(Service $service)
    {
        $this->loop    = $service->getLoop();
        $this->service = $service;
    }

    /**
     * Register routes. Due to the nature of Upswarm the routes handlers are
     * services and it's methods. Because of this, the HttpServer will be always
     * ready to receive new requests.
     *
     * @example $httpServer->routes(function ($r) {
     *              $r->addRoute('GET', '/do-something/{id}', 'App\\MyService@action');
     *              // ...
     *          });
     *
     * @param callable $registerRoutes A callable that will receive a \FastRoute\RouteCollector as the parameter.
     *
     * @return self
     */
    public function routes(callable $registerRoutes)
    {
        $this->dispatcher = \FastRoute\simpleDispatcher($registerRoutes);

        return $this;
    }

    /**
     * Registers an service to handle exceptions. Due to the nature of Upswarm
     * it's good to dispatch the error handling to a different service. By
     * doing this, the HttpServer will be always ready to receive new
     * requests.
     *
     * @example $httpServer->errorHandler('App\\MyService');
     *
     * @param  string $service Service name (class name).
     *
     * @return self
     */
    public function errorHandler(string $service)
    {
        $this->errorHandler = $service;

        return $this;
    }

    /**
     * Listen to incoming connections
     *
     * @param integer $port Port to listen to.
     * @param string  $host Host to listen to.
     *
     * @throws RouteDispatcherMissingException If routes were not registered.
     *
     * @return void
     */
    public function listen(int $port, string $host = '127.0.0.1')
    {
        if (! $this->dispatcher) {
            throw new RouteDispatcherMissingException;
        }


        $this->reactSocket = new \React\Socket\Server("$host:$port", $this->loop);
        $this->reactHttpServer = new \React\Http\Server($this->reactSocket, function (RequestInterface $request) {
            return new Promise(function ($resolve, $reject) use ($request) {
                $contentLength = 0;
                $request->getBody()->on('data', function ($data) use (&$contentLength) {
                    $contentLength += strlen($data);
                });

                $request->getBody()->on('end', function () use ($request, $resolve, &$contentLength) {
                    try {
                        $message = $this->dispatch($request);
                        if ('self' == $message->recipient) {
                            return $this->localAction($message->getData(), $request, $reactResponse);
                        }
                        $this->service->sendMessage($message);
                        $promise = $message->getPromise();
                    } catch (\Exception $e) {
                        if ($this->errorHandler) {
                            $promise = $this->service->sendMessage($e, $this->errorHandler)->getPromise();
                        } else {
                            $promise = new RejectedPromise($e->getMessage());
                        }
                    }

                    $promise->then(
                        function ($message) use ($resolve) {
                            $httpResponse = $message->getData();
                            $response = new Response(
                                $httpResponse->code,
                                $httpResponse->headers,
                                $httpResponse->data
                            );
                            $resolve($response);
                        },
                        function ($errorString) use ($resolve) {
                            $response = new Response(
                                501,
                                [],
                                $errorString ?: "Internal server error."
                            );
                            $resolve($response);
                        }
                    );
                });
            });
        });
    }

    /**
     * Dispatchs the given $request
     *
     * @param  RequestInterface $request Request to be dispatched.
     *
     * @throws RouteDispatcher404Exception If uri didn't match any route.
     * @throws RouteDispatcher405Exception If matched uri don't allow the method.
     *
     * @return return Message
     */
    protected function dispatch(RequestInterface $request)
    {
        $uri       = $request->getUri()->getPath();
        $method    = $request->getMethod();
        $routeInfo = $this->dispatcher->dispatch($method, $uri);

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                throw new RouteDispatcher404Exception($method, $uri);

                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                $allowedMethods = $routeInfo[1];
                throw new RouteDispatcher405Exception($method, $uri);

                break;
            case Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];

                if (strstr($handler, '@')) {
                    list($handler, $action) = explode('@', $handler);
                }

                $body = new BufferStream();
                $body->write((string) $request->getBody());
                $request = $request->withBody($body);

                return new Message(new HttpRequest($request, $action ?? null, $vars), $handler);
        }
    }

    /**
     * Handles requests that are meant to be handled by the same service.
     *
     * @param  HttpRequest          $httpRequest   Upswarm Http request.
     * @param  \React\Http\Request  $request       ReactPHP Http request.
     * @param  \React\Http\Response $reactResponse ReactPHP Http response object.
     *
     * @return void
     */
    protected function localAction(HttpRequest $httpRequest, \React\Http\Request $request, \React\Http\Response $reactResponse)
    {
        global $_service;

        $actionName  = $httpRequest->action;
        $actionResponse = $_service->$actionName($httpRequest->request, ...array_values($httpRequest->params));

        if (! $actionResponse instanceof HttpResponse) {
            $actionResponse = $this->buildResponse($actionResponse, $httpRequest);
        }

        $reactResponse->writeHead($actionResponse->code, $actionResponse->headers);
        $request->close();
        $reactResponse->end($actionResponse->data);
        unset($actionResponse);
    }

    /**
     * Prepares the given input to become an HttpResponse
     *
     * @param  mixed       $data    Data that should be "wrapped" in an HttpResponse.
     * @param  HttpRequest $request Original request.
     *
     * @return HttpResponse
     */
    protected function buildResponse($data, HttpRequest $request): HttpResponse
    {
        if (is_array($data)) {
            $data = json_encode($data);
            return new HttpResponse(['Content-Type' => 'application/json; charset=utf-8', 'Content-Length' => strlen($data), 'Connection' => 'close'], 200, $data);
        }

        if (is_string($data)) {
            return new HttpResponse(['Content-Type' => 'text/html; charset=utf-8', 'Content-Length' => strlen($data), 'Connection' => 'close'], 200, $data);
        }

        return $data;
    }
}
