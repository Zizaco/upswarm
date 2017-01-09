<?php

namespace Core;

use stdClass;

class CommandWithResponse extends Command
{
    public $eventEmitter;

    public function respond(Command $command)
    {
        $command->id = $this->id;
        $eventEmitter->emit('respond', $command);
    }
}
