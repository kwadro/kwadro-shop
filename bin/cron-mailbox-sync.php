<?php

/**
 * Hosting cron entrypoint (no CLI args needed).
 *
 * Example:
 * /usr/local/php82/bin/php -f /home/kwadro/kvadro.if.ua/www/current/bin/cron-mailbox-sync.php
 */

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

$application = new Application($kernel);
$application->setAutoExit(false);

$status = $application->run(
    new ArrayInput([
        'command' => 'app:mailbox:sync',
        '--no-interaction' => true,
    ]),
    new ConsoleOutput()
);

$kernel->shutdown();

exit($status);
