<?php

namespace App;

use Core\Service;
use Core\Http\HttpServer;
use React\EventLoop\LoopInterface;
use React\Stream\Stream;

class WebServer extends Service
{
    public function serve(LoopInterface $loop)
    {
        $server = new HttpServer($this);

        $server->routes(function ($r) {
            $r->addRoute('GET', '/hello[/{name}]', 'App\\Controller@hello');
            $r->addRoute('GET', '/world', 'App\\Controller@world');
        });

        $server->listen(1337);
    }
}
