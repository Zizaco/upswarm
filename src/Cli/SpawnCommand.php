<?php

namespace Upswarm\Cli;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Upswarm\Supervisor;

/**
 * Spawn new service instance
 */
class SpawnCommand extends Command
{
    /**
     * Configure
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('spawn')
            ->setDescription('Spawns new service instance.')
            ->setHelp(
                "This command spawns a new process running an specific service"
            )
            ->addArgument('service', InputArgument::REQUIRED, 'Service class to be spawned.')
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
        $className = str_replace('_', '\\', ucfirst($input->getArgument('service')));

        if (class_exists($className)) {
            (new $className())->run($input->getOption('superHost'), $input->getOption('port'));
            return;
        } else {
            $output->writeln("<error>Unable to find class '$className'. Make sure that the class is being autoloaded.</error>");
        }
    }
}
