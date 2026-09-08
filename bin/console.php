<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Symfony\Component\Console\Application;
use JEALER\G3\Commands\CreateCommand;
use JEALER\G3\Commands\CollectPostCommand;

$application = new Application();
$application->add(new CreateCommand());
$application->add(new CollectPostCommand());
$application->run();
