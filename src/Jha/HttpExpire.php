<?php

namespace Jha;

/**
* Middleware: automatic setup of http-caching headers
*/
class HttpExpire
{
    protected $http_cache;
    protected $cache_duration;

    public function __construct($container)
    {
        $this->http_cache = $container['http_cache'];
        $this->cache_duration = $container['settings']['caching_duration'];
    }

    public function __invoke($request, $response, $next)
    {
        $response = $next($request, $response);
        $response = $this->setExpireHeaders($response);
        return $response;
    }

    public function setExpireHeaders($response)
    {
        return $this->http_cache->withExpires(
            $response,
            time() + $this->cache_duration
        );
    }
}
