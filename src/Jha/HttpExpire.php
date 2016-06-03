<?php

namespace Jha;

/**
* Middleware: automatic setup of http-caching headers
*/
class HttpExpire
{
    protected $httpCache;
    protected $cacheDuration;

    const INVALID_CUSTOM_EXPIRE = "invalid custom expire value";
    const HEADER_EXPIRE = "Expires";

    public function __construct($container)
    {
        $this->httpCache = $container['http_cache'];
        $this->cacheDuration = $container['settings']['caching_duration'];
    }

    public function __invoke($request, $response, $next)
    {
        $response = $next($request, $response);

        $duration = $this->cacheDuration;

        if ($response->hasHeader(\Jha\Controller::HEADER_CACHE_HINT)) {
            $duration = $response->getHeaderLine(\Jha\Controller::HEADER_CACHE_HINT);
        }

        $response = $this->setExpireHeaders($response, $duration);

        return $response;
    }

    public function setExpireHeaders($response, $duration)
    {
        if ($duration === null) {
            $duration = $this->cacheDuration;
        }

        return $this->httpCache->withExpires(
            $response,
            time() + $duration
        );
    }
}
