<?php

namespace Upswarm;

use Evenement\EventEmitterInterface;
use React\ChildProcess\Process;
use React\EventLoop\Factory;
use React\Socket\Server;
use React\Stream\Stream;
use React\ZMQ\Context;
use Upswarm\Instruction\Identify;
use Upswarm\Instruction\KillService;
use Upswarm\Instruction\SpawnService;
use Upswarm\Message;
use ZMQ;

/**
 * Upswarm supervisor orchestrate services and handle message exchanging
 * between then.
 */
class Supervisor
{
    /**
     * Socket that will be used to send messages to Services
     * @var \React\ZMQ\SocketWrapper
     */
    protected $outputStream;

    /**
     * Socket that will be used to receive messages from services
     * @var \React\ZMQ\SocketWrapper
     */
    protected $inputStream;

    /**
     * Port that the supervisor will listen to
     * @var int
     */
    protected $port;

    /**
     * ReactPHP loop.
     * @var \React\EventLoop\LoopInterface;
     */
    protected $loop;

    /**
     * Services that are running.
     *
     * @example [
     *              'ServiceAName' => [
     *                  <Process>,
     *                  <Process>
     *              ],
     *              'ServiceBName' => [
     *                  <Process>,
     *                  <Process>
     *              ]
     *          ];
     *
     * @var array
     */
    protected $processes = [];

    /**
     * The target service topology
     *
     * @example [
     *              'ServiceAName' => 3,
     *              'ServiceBName' => 1
     *          ];
     *
     * @var array
     */
    protected $topology = [];

    /**
     * Connections that are open within $outputStream. Whenever a new connection
     * is openned that connection is placed as an 'unknow' service. After that
     * same service sends a Message with the data type 'Identify' it is placed
     * in the correct array.
     *
     * @example [
     *              'unknow' => [ // New connections
     *                  '<id>',
     *                  '<id>'
     *              ],
     *              'ServiceAName' => [ // Connections of services identified as "ServiceAName"
     *                  '<id>',
     *                  '<id>'
     *              ],
     *              'ServiceBName' => [
     *                  '<id>'
     *              ]
     *          ];
     *
     * @var array
     */
    protected $connections = [];

    /**
     * Initializes the Supervisor
     *
     * @param integer $port Port to listen to.
     */
    public function __construct(int $port = 8300)
    {
        $this->loop         = Factory::create();
        $zmqContext         = new Context($this->loop);
        $this->outputStream = $zmqContext->getSocket(ZMQ::SOCKET_PUB);
        $this->inputStream  = $zmqContext->getSocket(ZMQ::SOCKET_PULL);
        $this->port         = $port;

        $this->prepareMessageHandling($this->inputStream);
        $this->prepareTopology();
    }

    /**
     * Listen to a TopologyReader update events in order to be able to tell
     * how is the topology in real time.
     *
     * @return void
     */
    protected function prepareTopology()
    {
        $this->topologyReader = new TopologyReader($this->loop);

        $this->topologyReader->on('info', function ($message) {
            echo "$message\n";
        });

        $this->topologyReader->on('error', function ($message) {
            echo "<error>$message</error>\n";
        });

        $this->topologyReader->on('update', function ($topology) {
            $this->topology = $topology;
            $this->updateTopology();
        });
    }

    /**
     * Updates the topology (the amount of services of each type) running based
     * in the $topology property of the Supervisor.
     *
     * @return void
     */
    protected function updateTopology()
    {
        foreach ($this->topology as $serviceName => $amount) {
            $diff = $amount - count($this->processes[$serviceName] ?? []);

            if ($diff > 0) {
                for ($i=0; $i < $diff; $i++) {
                    $this->loop->addTimer($i, function () use ($serviceName) {
                        $this->spawn($serviceName);
                    });
                }
            } elseif ($diff < 0) {
                for ($i=0; $i < $diff*-1; $i++) {
                    $this->loop->addTimer($i, function () use ($serviceName) {
                        $this->stop($serviceName);
                    });
                }
            }
        }
    }

    /**
     * Register the basic events on how incoming messages will be handled by
     * the Supervisor.
     *
     * @param  EventEmitterInterface $inputStream Socket that will be used to receive connections from Services and enxange messages with then.
     *
     * @return void
     */
    protected function prepareMessageHandling(EventEmitterInterface $inputStream)
    {
        // If data is received from it, dispatch or evaluate message
        $inputStream->on('messages', function ($data) {
            list($recipientAddress, $senderAddress, $serializedMessage) = $data;

            $this->loop->nextTick(function () use ($serializedMessage, $recipientAddress, $senderAddress) {
                $this->dispatchMessage($serializedMessage, $recipientAddress, $senderAddress);
            });
        });
    }

    /**
     * Dispatchs incoming message to an Service or to be evaluated by the
     * Supervisor.
     *
     * @param  string $serializedMessage Incoming message.
     * @param  string $recipientAddress  Address to where the message is going.
     * @param  string $senderAddress     Address from where the message came from.
     *
     * @return void
     */
    protected function dispatchMessage(string $serializedMessage, string $recipientAddress, string $senderAddress)
    {
        // If message have an recipient. Redirect message to it.
        if ('' != $recipientAddress) {
            $this->deliverMessage($recipientAddress, $senderAddress, $serializedMessage);

            return;
        }

        $message = @unserialize($serializedMessage);
        if ($message && $message instanceof Message) {
            $this->evaluateMessageToSupervisor($message, $senderAddress);
        }
    }

    /**
     * Evaluates a message that was directed to the Supervisor.
     *
     * @param  Message $message       Incoming message.
     * @param  string  $senderAddress Address from where the message came from.
     *
     * @return void
     */
    protected function evaluateMessageToSupervisor(Message $message, string $senderAddress)
    {
        switch ($message->getDataType()) {
            case SpawnService::class:
                $this->spawn($message->getData()->service);
                break;

            case KillService::class:
                $this->kill($message);
                break;

            case Identify::class:
                $this->identify($message->getData(), $senderAddress);
                break;

            default:
                echo "Unknow instruction in Message to supervisor: ".$message->getDataType();
                break;
        }
    }

    /**
     * Delives the given string to the recipient Service
     *
     * @param  string $recipientAddress  String identifying the recipient. It may be the name or an id of a Service.
     * @param  string $senderAddress     Sender to be identified.
     * @param  string $serializedMessage Message to be delivered.
     *
     * @return void
     */
    protected function deliverMessage(string $recipientAddress, string $senderAddress, string $serializedMessage)
    {
        // If the receipt is not an Id (it's a name then)
        if (! ctype_xdigit($recipientAddress)) {
            // Deliver the message to any Service instance of that name.
            if (isset($this->connections[$recipientAddress])) {
                $random_key = array_rand($this->connections[$recipientAddress]);
                if (null !== $random_key) {
                    $recipientAddress = $this->connections[$recipientAddress][$random_key];
                }
            } else {
                return;
            }
        }

        $this->outputStream->sendmulti([$recipientAddress, $senderAddress, $serializedMessage]);
    }

    /**
     * Spawn a new instance of $serviceName
     *
     * @param  string $serviceName Name of the service to be spawned.
     *
     * @return void
     */
    public function spawn(string $serviceName)
    {
        echo "Spawnning $serviceName\n";

        // Prepares to create new process
        $process = new Process("exec ./upswarm spawn ".str_replace('\\', '\\\\', $serviceName));

        $this->loop->nextTick(function () use ($process, $serviceName) {
            // Starts process and pipe outputs to supervisor
            $process->start($this->loop);
            $this->processes[$serviceName][] = $process;

            $echoChildOutput = function ($output) {
                echo $output;
            };

            $process->stdout->on('data', $echoChildOutput);
            $process->stderr->on('data', $echoChildOutput);
        });

        // Register exit event of process
        $process->on('exit', function ($exitCode, $termSignal) use ($process, $serviceName) {
            $key = array_search($process, $this->processes[$serviceName]);
            echo "[$serviceName] exit $exitCode $termSignal\n";
            unset($this->processes[$serviceName][$key]);
        });
    }

    /**
     * Kills a service or an instance
     *
     * @param  Message $killingMessage Message containing a KillService instruction.
     *
     * @return void
     */
    public function kill(Message $killingMessage)
    {
        if ($killingMessage->getDataType() !== KillService::class) {
            echo "Invalid KillService instruction received.";
            return;
        }

        $instruction = $killingMessage->getData();
        $serviceName = $instruction->service;

        echo "Killing {$instruction->service}\n";

        // Kills processes
        if (isset($this->processes[$serviceName])) {
            foreach ($this->processes[$serviceName] as $process) {
                $process->terminate();
            }
        }

        // Send response
        $response = new Message("'$serviceName' killed successfully.");
        $killingMessage->respond($response);
        $this->deliverMessage($response->recipient, '', serialize($response));
    }

    /**
     * Stops an instance of $serviceName
     *
     * @param  string $serviceName Name of the service to be stopped.
     *
     * @return void
     */
    public function stop(string $serviceName)
    {
        echo "Stopping $serviceName\n";

        if (isset($this->processes[$serviceName]) && count($this->processes[$serviceName]) > 0) {
            $key = array_rand($this->processes[$serviceName]);
            $this->processes[$serviceName][$key]->terminate();
        }
    }

    /**
     * Parse Itentify instruction that came from a connection.
     *
     * @param  Identify $instruction   Intetification instruction.
     * @param  string   $senderAddress Sender to be identified.
     *
     * @return void
     */
    public function identify(Identify $instruction, string $senderAddress)
    {
        if (! (is_string($instruction->serviceName) && is_string($instruction->serviceId))) {
            return;
        }

        // Register connection in the correct name and with it's id
        $this->connections[$instruction->serviceName][] = $instruction->serviceId;

        // Registers callback to remove connection if it ends.
        // TODO
    }

    /**
     * Runs supervisor process.
     *
     * @return void
     */
    public function run()
    {
        $this->inputStream->bind('tcp://*:'.$this->port);
        $this->outputStream->bind('ipc://upswarm:'.$this->port);

        $this->loop->addTimer(5, function () {
            $this->loop->addPeriodicTimer(2, function () {
                $this->updateTopology();
            });
        });

        $this->loop->run();
    }
}
