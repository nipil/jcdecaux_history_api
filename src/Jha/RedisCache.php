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

    const ERR_CONNECT_UNSUPPORTED = "redis unsupported connect mode";
    const ERR_CONNECT_NETWORK_FAILED = "redis network connect failed";
    const ERR_CONNECT_UNIXSOCKET_FAILED = "redis unixsocket connect failed";
    const ERR_CANNOT_SET_KEY = "redis cannot set key";
    const ERR_CANNOT_SET_TTL = "redis cannot set ttl";
    const ERR_NON_NUMERIC_HTTP_CODE = "non numeric http code";
    const ERR_NON_NUMERIC_CACHE_HINT = "non numeric cache hint";

    const HEADER_CONTENT_TYPE = "Content-Type";

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
        $path = $request->getUri()->getPath();

        $this->connectRedis();
        $this->redis->setOption(\Redis::OPT_PREFIX, 'Jha:');
        $this->redis->select($this->config['database']);

        // handle page cache/generation
        $pageKey = $this->keyPage($path);
        $exists = $this->existsPage($pageKey);
        if ($exists) {
            // get actual cached data
            $pageEntry = $this->retrievePage($pageKey);
            // build response out of pageEntry
            $response = $this->responseFromPageEntry($pageEntry);
        } else {
            // actually generate page
            $response = $next($request, $response);
            // convert generated page to pageEntry
            $pageEntry = $this->responseToPageEntry($response);
            // store pageEntry to redis db
            $this->storePage($pageKey, $pageEntry);
        }

        // serve page
        return $response;
    }

    public function keyPage($path) {
        return "page:" . $path;
    }

    public function existsPage($key) {
        $result = $this->redis->exists($key);
        return $result;
    }

    public function retrievePage($key) {
        $pageEntry = $this->redis->hMGet(
            $key,
            array(
                'code',
                'content_type',
                'body',
                'cache_hint'
            )
        );
        return $pageEntry;
    }

    public function storePage($key, $pageEntry) {
        $result = $this->redis->hMSet(
            $key,
            $pageEntry
        );
        if ($result !== true) {
            throw new \Exception(self::ERR_CANNOT_SET_KEY);
        }
        $result = $this->redis->expire(
            $key,
            $pageEntry['cache_hint']
        );
        if ($result !== true) {
            throw new \Exception(self::ERR_CANNOT_SET_TTL);
        }
    }

    public function responseToPageEntry($response) {
        $code = $response->getStatusCode();
        $body = $response->getBody();
        $body->rewind();
        $text = $body->getContents();
        $content_type = $response->getHeaderLine(self::HEADER_CONTENT_TYPE);
        $cacheHint = $this->cacheDuration;
        if ($response->hasHeader(\Jha\Controller::HEADER_CACHE_HINT)) {
            $cacheHint = $response->getHeaderLine(\Jha\Controller::HEADER_CACHE_HINT);
        }
        $pageEntry = array(
            'code' => $code,
            'content_type' => $content_type,
            'body' => $text,
            'cache_hint' => $cacheHint,
        );
        return $pageEntry;
    }

    public function responseFromPageEntry($pageEntry) {
        $response = new \Slim\Http\Response();
        $response->getBody()->write($pageEntry['body']);
        if (!is_numeric($pageEntry['code'])) {
            throw new \Exception(self::ERR_NON_NUMERIC_HTTP_CODE);
        }
        if (!is_numeric($pageEntry['cache_hint'])) {
            throw new \Exception(self::ERR_NON_NUMERIC_CACHE_HINT);
        }
        return $response->withStatus(
            (int) $pageEntry['code']
        )->withHeader(
            \Jha\Controller::HEADER_CACHE_HINT,
            $pageEntry['cache_hint']
        )->withHeader(
            self::HEADER_CONTENT_TYPE,
            $pageEntry['content_type']
        );
    }

    private function connectRedis() {
        switch($this->config['connect_mode']) {
            case "unixsocket":
                $this->connectUnixSocket($this->config['unixsocket']);
                break;
            case "network":
                $this->connectNetwork(
                    $this->config['host'],
                    $this->config['port']
                );
                break;
            default:
                throw new \Exception(self::ERR_CONNECT_UNSUPPORTED);
        }
    }

    private function connectNetwork($host, $port)
    {
        $result = $this->redis->connect(
            $host,
            $port,
            1, // timeout in sec
            NULL, // persistant_id
            300 // retry in msec
        );
        if ($result === false) {
            throw new \Exception(self::ERR_CONNECT_NETWORK_FAILED);
        }
    }

    private function connectUnixSocket($filename)
    {
        $result = $this->redis->open($filename);
        if ($result === false) {
            throw new \Exception(self::ERR_CONNECT_UNIXSOCKET_FAILED);
        }
    }
}
