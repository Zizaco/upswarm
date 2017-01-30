<?php

namespace Upswarm\Cli;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Upswarm\Message;

/**
 * This abstract class represents an Cli command that will comunicate with the
 * Upswarm server.
 */
abstract class SupervisorInstructionCommand extends Command
{
    /**
     * Sends an instruction to the Upswarm server.
     *
     * @param  Message         $instruction Message to be sent to the Upswarm supervisor.
     * @param  InputInterface  $input       For reading input.
     * @param  OutputInterface $output      For writting output.
     *
     * @return void
     */
    protected function sendInstructionMessage(Message $instruction, InputInterface $input, OutputInterface $output)
    {
        (new CommandRunnerService($instruction, $output))
            ->run($input->getOption('superHost'), $input->getOption('port'));
    }
}
