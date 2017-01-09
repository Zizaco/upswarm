<?php

namespace App;

use Core\Command;
use Core\Service;
use React\EventLoop\LoopInterface;
use React\Stream\Stream;

class Main extends Service
{
    public function serve(LoopInterface $loop, Stream $commandBus)
    {
        $loop->addTimer(0.1, function () {
            $this->sendCommand(new Command('spawn', HttpServer::class));
        });

        $loop->addTimer(0.2, function () {
            $this->sendCommand(new Command('spawn', Controller::class));
        });

        $loop->addTimer(0.2, function () {
            $this->sendCommand(new Command('spawn', Controller::class));
        });

        $loop->addTimer(0.3, function () {
            $this->sendCommand(new Command('spawn', Controller::class));
        });

        // $loop->addPeriodicTimer(5, function () use ($commandBus) {
        //     $command = new Command('echo', [".\n"]);
        //     $commandBus->write(serialize($command));
        // });

        // $loop->addPeriodicTimer(10, function () use ($commandBus) {
        //     $command = new Command('spawn', [static::class]);
        //     $commandBus->write(serialize($command));
        // });
    }
}
