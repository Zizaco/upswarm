<?php

namespace App;

use Core\Command;
use Core\Service;
use React\EventLoop\LoopInterface;
use React\Stream\Stream;

class HttpServer extends Service
{
    public function serve(LoopInterface $loop, Stream $commandBus)
    {
        $socket = new \React\Socket\Server($loop);
        $http = new \React\Http\Server($socket);

        $http->on('request', function ($request, $response) use ($commandBus) {

            $command = new Command('request', "LOL", 'App\\Controller');
            $this->sendCommand($command);
            $promise = $command->getPromisse();

            $promise->then(
                function ($value) use ($response) {
                    $response->writeHead(200);
                    $response->end($value->params->param0);
                },
                function () use ($response) {
                    $response->writeHead(500);
                    $response->end("Internal server error.");
                });

            // $response->writeHead(500);
            // $response->end("Internal server error.");
        });

        $socket->listen(1337);
    }
}
