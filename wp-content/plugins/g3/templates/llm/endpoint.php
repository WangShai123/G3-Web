<?php
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Services\LLMService;

/**
 * @var LLMService $service
 */
$service = Container::run()->use(LLMService::class);
$service->handleRequest();