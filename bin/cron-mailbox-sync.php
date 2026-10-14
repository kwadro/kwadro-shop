<?php

/**
 * Hosting cron entrypoint (no CLI args needed).
 *
 * Example:
 * /usr/bin/php8.2 -f /path/to/bin/cron-mailbox-sync.php
 * /usr/local/php82/bin/php -f /home/kwadro/kvadro.if.ua/www/current/bin/cron-mailbox-sync.php
 *
 * Requires PHP IMAP extension (ext-imap). Prefer php8.2 on this project.
 */

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Dotenv\Dotenv;

if (!\function_exists('imap_open')) {
    fwrite(STDERR, "Mailbox sync aborted: PHP extension ext-imap is missing in ".PHP_BINARY."\n");
    fwrite(STDERR, "Run with a PHP that has imap enabled, e.g. /usr/bin/php8.2 -f ".__FILE__."\n");
    exit(1);
}

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
