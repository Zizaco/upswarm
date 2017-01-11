<?php

namespace App;

use Core\Instruction\SpawnService;
use Core\Message;
use Core\Service;
use React\EventLoop\LoopInterface;
use React\Stream\Stream;

class Main extends Service
{
    public function serve(LoopInterface $loop)
    {
        $loop->addTimer(0.1, function () {
            $this->sendMessage(new Message(new SpawnService(WebServer::class)));
        });

        $loop->addTimer(0.2, function () {
            $this->sendMessage(new Message(new SpawnService(Controller::class)));
        });

        $loop->addTimer(2, function () {
            $this->sendMessage(new Message(new SpawnService(Controller::class)));
        });

        $loop->addTimer(3, function () {
            $this->sendMessage(new Message(new SpawnService(Controller::class)));
        });

        $loop->addTimer(4, function () {
            $this->sendMessage(new Message(new SpawnService(Controller::class)));
        });

        $loop->addTimer(5, function () {
            $this->sendMessage(new Message(new SpawnService(Controller::class)));
        });

        $loop->addTimer(6, function () {
            $this->sendMessage(new Message(new SpawnService(Controller::class)));
        });

        $loop->addTimer(7, function () {
            $this->sendMessage(new Message(new SpawnService(Controller::class)));
        });

        $loop->addTimer(8, function () {
            $this->sendMessage(new Message(new SpawnService(Controller::class)));
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
