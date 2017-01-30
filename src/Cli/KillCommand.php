<?php

namespace Upswarm\Cli;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Upswarm\Instruction\KillService;
use Upswarm\Message;
use Upswarm\Supervisor;

/**
 * Kill service instance
 */
class KillCommand extends SupervisorInstructionCommand
{
    /**
     * Configure
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('kill')
            ->setDescription('Kills service instance.')
            ->setHelp(
                "This command kills a process running an specific service"
            )
            ->addArgument('service', InputArgument::REQUIRED, 'Class or id of the service to be killed.')
            ->addOption(
                'superHost',
                's',
                InputOption::VALUE_REQUIRED,
                'Supervisor host address',
                '127.0.0.1'
            )
            ->addOption(
                'port',
                'p',
                InputOption::VALUE_REQUIRED,
                'Supervisor port',
                8300
            )
        ;
    }

    /**
     * When executing command.
     *
     * @param  InputInterface  $input  For reading input.
     * @param  OutputInterface $output For writting output.
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $serviceName = str_replace('_', '\\', ucfirst($input->getArgument('service')));

        $output->writeln("Killing '$serviceName'...");
        $this->sendInstructionMessage(new Message(new KillService($serviceName)), $input, $output);
    }
}
