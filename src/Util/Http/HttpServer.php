<?php

namespace Upswarm\Util\Http;

use Upswarm\Message;
use Upswarm\Service;
use FastRoute\Dispatcher;
use React\EventLoop\LoopInterface;
use React\Promise\RejectedPromise;

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

        $this->registerRequestHandlers();

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

        $this->getReactSocket()->listen($port, $host);
    }

    /**
     * Returns the "raw" ReactPHP Http server.
     *
     * @return \React\Http\Server
     */
    public function getReactHttpServer()
    {
        if (! $this->reactHttpServer) {
            $this->reactHttpServer = new \React\Http\Server($this->getReactSocket());
        }

        return $this->reactHttpServer;
    }

    /**
     * Returns the "raw" ReactPHP Socket.
     *
     * @return \React\Socket\Server
     */
    public function getReactSocket()
    {
        if (! $this->reactSocket) {
            $this->reactSocket = new \React\Socket\Server($this->loop);
        }

        return $this->reactSocket;
    }

    /**
     * Register event listeners in React HTTP server in order to use the
     * dispatcher to handle the requests.
     *
     * @return void
     */
    protected function registerRequestHandlers()
    {
        $server = $this->getReactHttpServer();

        $server->on('request', function ($request, $reactResponse) {
            try {
                $message = $this->dispatch($request);
                $this->service->sendMessage($message);
                $promise = $message->getPromisse();
            } catch (\Exception $e) {
                if ($this->errorHandler) {
                    $promise = $this->service->sendMessage($e, $this->errorHandler)->getPromisse();
                } else {
                    $promise = new RejectedPromise($e->getMessage());
                }
            }

            $promise->then(
                function ($message) use ($reactResponse, $request) {
                    $httpResponse = $message->getData();
                    $reactResponse->writeHead($httpResponse->code, $httpResponse->headers);
                    $request->close();
                    $reactResponse->end($httpResponse->data);
                    unset($httpResponse);
                },
                function ($errorString) use ($reactResponse, $request) {
                    $reactResponse->writeHead(500);
                    $reactResponse->end($errorString ?: "Internal server error.");
                    $request->close();
                    unset($httpResponse);
                }
            );
        });
    }

    /**
     * Dispatchs the given $request
     *
     * @param  \React\Http\Request $request Request to be dispatched.
     *
     * @throws RouteDispatcher404Exception If uri didn't match any route.
     * @throws RouteDispatcher405Exception If matched uri don't allow the method.
     *
     * @return return Message
     */
    protected function dispatch(\React\Http\Request $request)
    {
        $uri       = $request->getHeaders()['Host'].$request->getPath();
        $routeInfo = $this->dispatcher->dispatch($request->getMethod(), $request->getPath());

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                throw new RouteDispatcher404Exception($request->getMethod(), $request->getPath());

                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                $allowedMethods = $routeInfo[1];
                throw new RouteDispatcher405Exception($request->getMethod(), $request->getPath());

                break;
            case Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];

                if (strstr($handler, '@')) {
                    list($handler, $action) = explode('@', $handler);
                }

                return new Message(new HttpRequest($request, $action ?? null, $vars), $handler);
        }
    }
}
