<?php

namespace Jha;

/**
* Middleware: automatic setup of http-caching headers
*/
class HttpExpire
{
    protected $httpCache;
    protected $cacheDuration;
    protected $logger;

    const INVALID_CUSTOM_EXPIRE = "invalid custom expire value";
    const HEADER_EXPIRE = "Expires";

    public function __construct($container)
    {
        $this->logger = new \Monolog\Logger(__CLASS__);
        $this->logger->pushHandler($container['log_stream']);

        $this->httpCache = $container['http_cache'];
        $this->cacheDuration = $container['settings']['caching_duration'];
    }

    public function __invoke($request, $response, $next)
    {
        $response = $next($request, $response);

        $max_timestamp = null;

        if ($response->hasHeader(\Jha\Controller::HEADER_CACHE_HINT)) {
            // integer check already done in "responseFromPageEntry"
            $max_timestamp = (int) $response->getHeaderLine(\Jha\Controller::HEADER_CACHE_HINT);
        } else {
            return $response;
        }

        return $this->httpCache->withExpires(
            $response,
            $max_timestamp
        );
    }
}
