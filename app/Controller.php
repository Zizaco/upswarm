<?php
namespace App;

use Core\Command;
use Core\Service;
use React\EventLoop\LoopInterface;
use React\Stream\Stream;

class Controller extends Service
{
    public function serve(LoopInterface $loop, Stream $commandBus)
    {
    }

    protected function receiveCommand(Command $command)
    {
        if ('request' == $command->command) {
            $command->respond(new Command('response', "Hello async, multi-threaded world!"));
        }
    }
}
