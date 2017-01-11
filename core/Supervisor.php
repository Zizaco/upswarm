<?php

namespace Core;

use Core\Instruction\Identify;
use Core\Instruction\SpawnService;
use Core\Message;
use Evenement\EventEmitterInterface;
use React\ChildProcess\Process;
use React\EventLoop\Factory;
use React\Socket\Server;
use React\Stream\Stream;

/**
 * Upswarm supervisor orchestrate services and handle message exchanging
 * between then.
 */
class Supervisor
{
    /**
     * How to name an unknow service in $connections array.
     */
    const UNKNOW_SERVICE = 'unknow';

    /**
     * Socket that will be used to receive connections from Services and enxange
     * messages with then.
     * @var \React\Socket\Server
     */
    protected $remoteStream;

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
     * Connections that are open within $remoteStream. Whenever a new connection
     * is openned that connection is placed as an 'unknow' service. After that
     * same service sends a Message with the data type 'Identify' it is placed
     * in the correct array.
     *
     * @example [
     *              'unknow' => [ // New connections
     *                  '<id>' => <connection>,
     *                  '<id>' => <connection>
     *              ],
     *              'ServiceAName' => [ // Connections of services identified as "ServiceAName"
     *                  '<id>' => <connection>,
     *                  '<id>' => <connection>
     *              ],
     *              'ServiceBName' => [
     *                  '<id>' => <connection>,
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
        $this->remoteStream = new Server($this->loop);
        $this->port         = $port;

        $this->prepareMessageHandling($this->remoteStream);
    }

    /**
     * Register the basic events on how incoming messages will be handled by
     * the Supervisor.
     * @param  EventEmitterInterface $stream Socket that will be used to receive connections from Services and enxange messages with then.
     * @return void
     */
    protected function prepareMessageHandling(EventEmitterInterface $stream)
    {
        // Whenever a new connection is received
        $stream->on('connection', function ($conn) {
            // Place it as an unknow connections
            $this->connections[static::UNKNOW_SERVICE][] = $conn;

            // If data is received from it, dispatch or evaluate message
            $conn->on('data', function ($data) use ($conn) {
                $message = unserialize($data);
                if ($message instanceof Message) {
                    $this->loop->nextTick(function () use ($message, $conn) {
                        $this->dispatchMessage($message, $conn);
                    });
                }
            });

            // If connection is terminated, remove it from connections
            $conn->on('end', function () use ($conn) {
                $key = array_search($conn, $this->connections[static::UNKNOW_SERVICE]);
                if ($key) {
                    unset($this->connections[static::UNKNOW_SERVICE][$key]);
                }
            });
        });
    }

    /**
     * Dispatchs incoming message to an Service or to be evaluated by the
     * Supervisor.
     *
     * @param  Message $message Incoming message.
     * @param  Stream  $conn    Connection from where the message came from.
     *
     * @return void
     */
    protected function dispatchMessage(Message $message, Stream $conn)
    {
        echo "command: $message\n";

        // If message have an receipt. Redirect message to it.
        if ($message->receipt) {
            $this->deliverMessage($message, $message->receipt);

            return;
        }

        $this->evaluateMessageToSupervisor($message, $conn);
    }

    /**
     * Evaluates a message that was directed to the Supervisor.
     *
     * @param  Message $message Incoming message.
     * @param  Stream  $conn    Connection from where the message came from.
     *
     * @return void
     */
    protected function evaluateMessageToSupervisor(Message $message, Stream $conn)
    {
        switch ($message->getDataType()) {
            case SpawnService::class:
                $this->spawn($message->getData()->service);
                break;

            case Identify::class:
                $this->identify($message->getData(), $conn);
                break;

            default:
                echo "Unknow instruction in Message to supervisor: ".$message->getDataType();
                break;
        }
    }

    /**
     * Delives the given Message to the receipt Service
     *
     * @param  Message $message Incoming message.
     * @param  string  $receipt String identifying the receipt. It may be the name or an id of a Service.
     *
     * @return void
     */
    protected function deliverMessage(Message $message, string $receipt)
    {
        // If the receipt is not an Id (it's a name then)
        if (! ctype_xdigit($receipt)) {
            // Deliver the message to any Service instance of that name.
            if (isset($this->connections[$receipt])) {
                $random_key = array_rand($this->connections[$receipt]);
                $this->connections[$receipt][$random_key]->write(serialize($message));
            }
            return;
        }

        // If the receipt is an Id
        foreach ($this->connections as $service) {
            // Iterate throught the connections and deliver the message.
            foreach ($service as $id => $conn) {
                if ($id == $receipt) {
                    $conn->write(serialize($message));
                    return;
                }
            }
        }
    }

    /**
     * Spawn a new instance of $service
     *
     * @param  string $serviceName Name of the service to be spawned.
     *
     * @return void
     */
    public function spawn(string $serviceName)
    {
        // Prepares to create new process
        $process = new Process("exec php main.php ".str_replace('\\', '\\\\', $serviceName));

        $this->loop->nextTick(function () use ($process, $serviceName) {
            // Starts process and pipe outputs to supervisor
            $process->start($this->loop);
            $this->processes[$serviceName][] = $process;

            $echoChildOutput = function ($output) use ($serviceName) {
                echo "[{$serviceName}]: {$output}";
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
     * Parse Itentify instruction that came from a connection.
     *
     * @param  Identify $instruction Intetification instruction.
     * @param  Stream   $conn        Connection to be identified.
     *
     * @return void
     */
    public function identify(Identify $instruction, Stream $conn)
    {
        if (! (is_string($instruction->serviceName) && is_string($instruction->serviceId))) {
            return;
        }

        // Register connection in the correct name and with it's id
        $this->connections[$instruction->serviceName][$instruction->serviceId] = $conn;

        // Removes connection from the unknow connections
        $key = array_search($conn, $this->connections[static::UNKNOW_SERVICE]);
        unset($this->connections[static::UNKNOW_SERVICE]);

        // Registers callback to remove connection if it ends.
        $conn->on('end', function () use ($instruction) {
            unset($this->connections[$instruction->serviceName][$instruction->serviceId]);
        });
    }

    /**
     * Runs supervisor process.
     *
     * @return void
     */
    public function run()
    {
        $this->remoteStream->listen($this->port);

        // Spawn main service
        $this->loop->nextTick(function () {
            $this->spawn('App\\Main');
        });

        $this->loop->addPeriodicTimer(5, function () {
            // foreach (array_keys($this->connections) as $key) {
            //     echo "$key:";
            //     echo json_encode(array_keys($this->connections[$key])).PHP_EOL;
            // }
        });

        $this->loop->run();
    }
}
