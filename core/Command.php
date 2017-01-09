<?php

namespace Core;

use React\Promise\Deferred;
use stdClass;

class Command
{
    public $id;
    public $command;
    public $params;
    public $sender;
    public $receipt;
    public $deferred;
    public $eventEmitter;

    public function __construct(string $command, $params, $receipt = null)
    {
        $this->id = uniqid('', true);
        $this->command = $command;
        $this->params = new stdClass;
        $this->receipt = $receipt;
        $this->receipt = $receipt;

        if (is_object($params)) {
            $params = [$params];
        }

        foreach ((array)$params as $key => $value) {
            if (is_numeric($key)) {
                $key = "param$key";
            }

            $this->params->$key = $value;
        }
    }

    public function getPromisse()
    {
        $this->deferred = new Deferred();
        return $this->deferred->promise();
    }

    public function respond(Command $command)
    {
        $command->id = $this->id;
        $command->receipt = $this->sender;
        $this->eventEmitter->emit('respond', [$command]);
    }

    public function __sleep() {
        return [
            'id',
            'command',
            'params',
            'sender',
            'receipt',
        ];
    }
}
