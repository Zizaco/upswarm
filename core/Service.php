<?php

namespace Core;

use Core\Command;
use Evenement\EventEmitter;
use React\Dns\Resolver\Factory as DnsResolver;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\SocketClient\Connector;
use React\Stream\Stream;

abstract class Service {
    protected $id;
    protected $commandBus;
    protected $loop;
    protected $eventEmitter;

    public function run($loop = null, $commandBus = null)
    {
        $this->id = uniqid();
        $this->eventEmitter = new EventEmitter;
        $this->eventEmitter->on('respond', function (Command$command) {
            $this->sendCommand($command);
        });

        if (! $this->loop = $loop) {
            $this->loop = Factory::create();
        }

        if (! $this->commandBus = $commandBus) {
            $dns    = new DnsResolver();
            $socket = new Connector($this->loop, $dns->createCached('8.8.8.8', $this->loop));
            $socket->create('127.0.0.1', 4000)
                ->then(function (Stream $stream) {
                    $this->commandBus = $stream;
                    $this->sendCommand(new Command('identify', [static::class, $this->id]));

                    $stream->on('data', function ($data) {
                        $command = unserialize($data);
                        $this->incomingCommand($command);
                    });

                    $stream->on('end', function () {
                        $this->loop->stop();
                    });

                    $this->serve($this->loop, $this->commandBus);
                });

            $this->loop->run();
            return;
        }

        $this->serve($this->loop, $this->commandBus);
        return;
    }

    public function sendCommand(Command $command)
    {
        $this->loop->nextTick(function () use ($command) {
            if ($command->deferred) {
                $command->sender = $this->id;

                $timeout = $this->loop->addTimer(3, function () use ($command) {
                    $command->deferred->reject();
                });

                $this->eventEmitter->on($command->id, function ($value) use ($command, $timeout) {
                    $timeout->cancel();
                    $command->deferred->resolve($value);
                });
            }

            $this->commandBus->write(serialize($command));
        });

        return $command;
    }

    protected function incomingCommand(Command $command)
    {
        if ($command->receipt) {
            $this->eventEmitter->emit($command->id, [$command]);
            $command->eventEmitter = $this->eventEmitter;
        }

        $this->receiveCommand($command);
    }

    protected function receiveCommand(Command $command)
    {

    }

    abstract public function serve(LoopInterface $loop, Stream $commandBus);
}
