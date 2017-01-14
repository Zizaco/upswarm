<?php

namespace Upswarm\Instruction;

/**
 * Instruction to spawn a new service service instance.
 */
class SpawnService
{
    /**
     * Name of the service to be spawned
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
