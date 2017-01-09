<?php

namespace Core;

use Evenement\EventEmitterInterface;
use React\ChildProcess\Process;
use React\EventLoop\Factory;
use React\Socket\Server;
use React\Stream\Stream;

class Supervisor
{
    protected $localStream;
    protected $remoteStream;
    protected $loop;
    protected $services = [];
    protected $connections = [];

    public function __construct()
    {
        $this->loop = Factory::create();
        $this->remoteStream = new Server($this->loop);
        // $this->localStream = new Stream('/tmp/microservices.stream', $this->loop);

        $this->prepareCommandHub($this->remoteStream);
        // $this->prepareCommandHub($this->localStream);
    }

    public function prepareCommandHub(EventEmitterInterface $stream)
    {
        $service = 'unknow';

        $stream->on('connection', function ($conn) use ($service) {
            $this->connections[$service][] = $conn;

            $conn->on('data', function ($data) use ($conn) {
                $command = unserialize($data);
                $this->runCommand($command, $conn);
            });

            $conn->on('end', function () use ($service, $conn) {
                $key = array_search($conn, $this->connections[$service]);
                if ($key) {
                    unset($this->connections[$service][$key]);
                }
            });
        });
    }

    protected function runCommand(Command $command, $conn)
    {
        echo "    command: ".json_encode($command)."\n";

        if ($command->receipt) {
            $this->loop->nextTick(function () use ($command) {
                $this->deliverCommand($command, $command->receipt);
            });
        }

        if ('spawn' == $command->command) {
            $this->loop->nextTick(function () use ($command) {
                $this->spawn($command->params->param0);
            });
        }

        if ('identify' == $command->command) {
            $this->loop->nextTick(function () use ($command, $conn) {
                $this->connections[$command->params->param0][$command->params->param1] = $conn;

                $key = array_search($conn, $this->connections['unknow']);
                unset($this->connections['unknow']);

                $conn->on('end', function () use ($command) {
                    unset ($this->connections[$command->params->param0][$command->params->param1]);
                });
            });
        }

        if ('echo' == $command->command) {
            $this->loop->nextTick(function () use ($command) {
                echo $command->params->param0;
            });
        }

        if ('dump' == $command->command) {
            $this->loop->nextTick(function () use ($command) {
                var_dump($command->params->param0);
            });
        }
    }

    protected function deliverCommand(Command $command, $receipt)
    {
        echo "Delivering command {$command->command} to {$receipt}: ";
        if (! ctype_xdigit($receipt)) {
            if (isset($this->connections[$receipt])) {
                foreach ($this->connections[$receipt] as $conn) {
                    $conn->write(serialize($command));
                    echo "Done\n";
                    break;
                }
            }
            return;
        }

        foreach ($this->connections as $service) {
            foreach ($service as $id => $conn) {
                if ($id == $receipt) {
                    echo "Do";
                    $conn->write(serialize($command));
                    echo "ne\n";
                    return;
                }
            }
        }
    }

    public function spawn($service)
    {
        $service = str_replace('\\', '\\\\', $service);
        $process = new Process("exec php main.php $service");

        $this->loop->nextTick(function () use ($process, $service) {
            $process->start($this->loop);
            $this->services[$service][] = $process;

            $process->stdout->on('data', function ($output) use ($service) {
                echo "Child {$service}: {$output}";
            });

            $process->stderr->on('data', function ($output) use ($service) {
                echo "Child {$service}: {$output}";
            });
        });

        $process->on('exit', function ($exitCode, $termSignal) use ($process, $service) {
            $key = array_search($process, $this->services[$service]);
            echo "Child $service exit $exitCode $termSignal\n";
            unset($this->services[$service][$key]);
        });
    }

    public function run()
    {
        $this->remoteStream->listen(4000);

        $this->loop->addPeriodicTimer(5, function () {
            // foreach (array_keys($this->connections) as $key) {
            //     echo "$key:";
            //     echo json_encode(array_keys($this->connections[$key])).PHP_EOL;
            // }
        });

        $this->loop->nextTick(function () {
            $this->spawn('App\\Main');
        });

        $this->loop->run();
    }
}
