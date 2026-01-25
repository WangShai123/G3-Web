<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Console\Application;

use JEALER\G3\Commands\TestCommand;
use JEALER\G3\Commands\CreateCommand;
use JEALER\G3\Commands\TaCommand;

$application = new Application();

// Register commands
$application->add(new TestCommand());
$application->add(new CreateCommand());
$application->add(new TaCommand());

$application->run();