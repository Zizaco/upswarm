<?php

namespace Upswarm\Instruction;

/**
 * Instruction to kill a new service service instance.
 */
class KillService
{
    /**
     * Name of the service to be killed
     * @var string
     */
    public $service;

    /**
     * Constructs an instance with properties
     * @param string $service Name of the service.
     */
    public function __construct(string $service)
    {
        $this->service = $service;
    }
}
