<?php
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Services\SitemapService;

/**
 * @var SitemapService $service
 */
$service = Container::run()->use(SitemapService::class);
$service->handleRequest();
