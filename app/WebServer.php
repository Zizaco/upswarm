<?php

namespace App;

use Core\Service;
use Core\Util\Http\HttpServer;
use React\EventLoop\LoopInterface;
use React\Stream\Stream;

class WebServer extends Service
{
    public function serve(LoopInterface $loop)
    {
        $server = new HttpServer($this);

        $server->routes(function ($r) {
            $r->addRoute('GET', '/hello', 'App\\Controller@hello');
            $r->addRoute('GET', '/users/index', 'App\\Controller@users');
        });

        $server->listen(1337);
    }
}
