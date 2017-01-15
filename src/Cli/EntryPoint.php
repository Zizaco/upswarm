<?php

namespace Upswarm\Cli;

use Symfony\Component\Console\Application;
use Upswarm\Cli\ServeCommand;

/**
 * Command line interface entry point of Upswarm.
 */
class EntryPoint
{
    /**
     * Console application
     * @var Application
     */
    protected $app;

    /**
     * Constructs new Cli instance
     */
    public function __construct()
    {
        $this->app = new Application($this->getLogo(), '0.1 beta | build12341');

        $this->registerCommands([
            ServeCommand::class,
            SpawnCommand::class,
        ]);
    }

    /**
     * Instantiate and register commands
     *
     * @param  string[] $commands Class names of the commands to be registered.
     *
     * @return void
     */
    protected function registerCommands(array $commands)
    {
        foreach ($commands as $class) {
            $this->app->add(new $class);
        }
    }

    /**
     * Run Cli application
     * @return void
     */
    public function run()
    {
        $this->app->run();
    }

    /**
     * Returns upswarm logo
     *
     * @return string
     */
    protected function getLogo()
    {
        return <<<EOD
 █    ██  ██▓███    ██████  █     █░ ▄▄▄       ██▀███   ███▄ ▄███▓
 ██  ▓██▒▓██░  ██▒▒██    ▒ ▓█░ █ ░█░▒████▄    ▓██ ▒ ██▒▓██▒▀█▀ ██▒
▓██  ▒██░▓██░ ██▓▒░ ▓██▄   ▒█░ █ ░█ ▒██  ▀█▄  ▓██ ░▄█ ▒▓██    ▓██░
▓▓█  ░██░▒██▄█▓▒ ▒  ▒   ██▒░█░ █ ░█ ░██▄▄▄▄██ ▒██▀▀█▄  ▒██    ▒██
▒▒█████▓ ▒██▒ ░  ░▒██████▒▒░░██▒██▓  ▓█   ▓██▒░██▓ ▒██▒▒██▒   ░██▒
░▒▓▒ ▒ ▒ ▒▓▒░ ░  ░▒ ▒▓▒ ▒ ░░ ▓░▒ ▒   ▒▒   ▓▒█░░ ▒▓ ░▒▓░░ ▒░   ░  ░
░░▒░ ░ ░ ░▒ ░     ░ ░▒  ░ ░  ▒ ░ ░    ▒   ▒░ ░  ░▒ ░ ▒░░  ░      ░
 ░░░ ░ ░ ░░       ░  ░  ░    ░   ░    ░   ░     ░░   ░ ░      ░
   ░                    ░      ░
EOD;
    }
}
