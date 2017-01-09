<?php

namespace App;

use Core\Service;
use Core\Http\HttpServer as UpswarmHttpServer;
use React\EventLoop\LoopInterface;
use React\Stream\Stream;

class HttpServer extends Service
{
    public function serve(LoopInterface $loop, Stream $commandBus)
    {
        $server = new UpswarmHttpServer($this);

        $server->routes(function ($r) {
            $r->addRoute('GET', '/hello', 'App\\Controller');
        });

        $server->listen(1337);
    }
}
