<?php

namespace Upswarm\Cli;

use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Upswarm\Message;
use Upswarm\Service;

/**
 * A minimalist service that will send an Message to the Upswarm supervisor and
 * then exit. This service is used by console commands that extends
 * SUpervisorIntructionCommand class in order to execute something in a
 * running Upswarm server.
 */
class CommandRunnerService extends Service
{
    /**
     * Instruction to be send to the Supervisor
     * @var Message
     */
    protected $instruction;

    /**
     * For writting output.
     * @var OutputInterface
     */
    protected $output;

    /**
     * Injects the message that will be sent to the Upswarm service.
     *
     * @param Message         $instruction Message to be sent.
     * @param OutputInterface $output      For writting output.
     */
    public function __construct(Message $instruction, OutputInterface $output)
    {
        $this->instruction = $instruction;
        $this->output = $output;
    }

    /**
     * Provide the given service. This is the initialization point of the
     * service, the initialization point of the service.
     *
     * @param  LoopInterface $loop ReactPHP loop.
     *
     * @return void
     */
    public function serve(LoopInterface $loop)
    {
        $this->instruction->getPromise()->done(
            function ($response) {
                // Output success message and exit.
                if ('string' == $response->getDataType()) {
                    $this->output->writeln($response->getData());
                }
                $this->exit();
            },
            function ($response) {
                // Outputs error message and exit.
                if ('string' == $response->getDataType()) {
                    $this->output->writeln("<error>{$response->getData()}</error>");
                }
                $this->exit();
            }
        );

        $loop->addTimer(0.5, function () {
            $this->sendMessage($this->instruction);
        });
    }
}
