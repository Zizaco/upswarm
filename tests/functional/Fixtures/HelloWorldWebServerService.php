<?php
namespace Upswarm\FunctionalTest\Fixtures;

use React\EventLoop\LoopInterface;
use React\Http\Request;
use React\Socket\ConnectionException;
use Upswarm\Service;
use Upswarm\Util\Http\HttpServer;

/**
 * Application WebServer. The contact point with the world of internetz.
 */
class HelloWorldWebServerService extends Service
{
    /**
     * Provide the given service. This is the initialization point of the
     * service. In this case, creates a new HttpServer and register it's routes.
     *
     * @param LoopInterface $loop ReactPHP Loop.
     *
     * @return void
     */
    public function serve(LoopInterface $loop)
    {
        $server = new HttpServer($this);

        $server->routes(function ($router) {
            $router->addRoute(
                'GET',
                '/hello[/{name}]',
                'Upswarm\\FunctionalTest\\Fixtures\\HelloWorldController@hello'
            );
        });

        $server->listen(8081);
    }
}
