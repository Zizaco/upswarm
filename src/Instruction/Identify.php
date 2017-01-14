<?php

namespace Upswarm\Instruction;

/**
 * Instruction to identify a service within the Supervisor.
 */
class Identify
{
    public $serviceName;
    public $serviceId;

    /**
     * Constructs an instance with properties
     * @param string $serviceName Name of the service.
     * @param string $serviceId   Id of the service.
     */
    public function __construct(string $serviceName, string $serviceId)
    {
        $this->serviceName = $serviceName;
        $this->serviceId   = $serviceId;
    }
}
