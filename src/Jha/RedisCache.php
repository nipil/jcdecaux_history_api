<?php

namespace Jha;

/**
* Middleware: automatic Redis caching generated pages
*/
class RedisCache
{
    protected $logger;

    protected $redis;

    protected $config;

    protected $cacheDuration;

    public function __construct($container)
    {
        $this->logger = new \Monolog\Logger(__CLASS__);
        $this->logger->pushHandler($container['log_stream']);

        $this->redis = new \Redis();

        $this->config = $container['settings']['redis'];

        $this->cacheDuration = $container['settings']['caching_duration'];
    }

    public function __invoke($request, $response, $next)
    {
        $response = $next($request, $response);

        return $response;
    }
}
