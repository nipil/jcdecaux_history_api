<?php

namespace Jha;

/**
* Middleware: automatic setup of http-caching headers
*/
class HttpExpire
{
    protected $httpCache;
    protected $cacheDuration;

    public function __construct($container)
    {
        $this->httpCache = $container['http_cache'];
        $this->cacheDuration = $container['settings']['caching_duration'];
    }

    public function __invoke($request, $response, $next)
    {
        $response = $next($request, $response);
        $response = $this->setExpireHeaders($response);
        return $response;
    }

    public function setExpireHeaders($response)
    {
        return $this->httpCache->withExpires(
            $response,
            time() + $this->cacheDuration
        );
    }
}
