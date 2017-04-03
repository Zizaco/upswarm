<?php

namespace Upswarm\Cli;

use Upswarm\Supervisor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Initializes Upswarm server
 */
class ServeCommand extends Command
{
    /**
     * Configure
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('serve')
            ->setDescription('Runs Upswarm supervisor.')
            ->setHelp(
                "This command runs the upswarm supervisor. The supervisor orchestrate ".
                "services and handle message exchanging between then."
            )
            ->addOption(
                'topology',
                't',
                InputOption::VALUE_REQUIRED,
                'Topology file. Use "0" for null',
                'topology.json'
            )
            ->addOption(
                'port',
                'p',
                InputOption::VALUE_REQUIRED,
                'Supervisor port',
                8300
            )
            ->addOption(
                'daemon',
                'd',
                InputOption::VALUE_NONE,
                'Run server in background',
                null
            );
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
        $output->writeln("<info>Running Upwsarm supervisor</info>");

        if ($input->getOption('daemon')) {
            $pid = exec('php upswarm serve > /dev/null 2>&1 & echo $!');
            $output->writeln("PID: $pid");

            return;
        }

        (new Supervisor($input->getOption('port'), $input->getOption('topology')))->run();
    }

    /**
     * Tells if the server is running windows
     * @return boolean
     */
    protected function isWindows()
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }
}
