<?php

require 'vendor/autoload.php';

$cli = new League\CLImate\CLImate;
$cli->arguments->add([
    'service' => [
        'description' => 'Service to run',
    ],
]);
$cli->arguments->parse();

if ($service = $cli->arguments->get('service')) {
    if ('.' === $service) {
        (new Core\Supervisor)->run();
    }

    $className = ucfirst($service);
    (new $className)->run();
    exit;
}

$cli->description('Microservice POC');
$cli->usage();
exit;

// $loop = React\EventLoop\Factory::create();
