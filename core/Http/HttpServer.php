<?php

namespace Core\Http;

use Core\Command;
use Core\Service;
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
     * Register routes
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

        $server->on('request', function ($request, $response) {
            try {
                $command = $this->dispatch($request);
                $this->service->sendCommand($command);
                $promise = $command->getPromisse();
            } catch (\Exception $e) {
                $promise = new RejectedPromise($e->getMessage());
            }

            $promise->then(
                function ($value) use ($response) {
                    $response->writeHead(200);
                    $response->end($value->params->param0);
                },
                function ($message) use ($response) {
                    $response->writeHead(500);
                    $response->end($message ?: "Internal server error.");
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
     *
     * @return return Command
     */
    protected function dispatch(\React\Http\Request $request)
    {
        $uri = $request->getHeaders()['Host'].$request->getPath();
        $routeInfo = $this->dispatcher->dispatch($request->getMethod(), $request->getPath());

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                throw new RouteDispatcher404Exception($request->getMethod(), $request->getPath());

                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                $allowedMethods = $routeInfo[1];
                throw new RouteDispatcher404Exception($request->getMethod(), $request->getPath());

                break;
            case Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];

                return new Command('request', array_merge([$request], $vars), $handler);
        }
    }
}
