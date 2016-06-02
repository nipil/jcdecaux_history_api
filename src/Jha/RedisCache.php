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

    const ERR_CONNECT_USUPPORTED = "redis unsupported connect mode";
    const ERR_CONNECT_NETWORK_FAILED = "redis network connect failed";
    const ERR_CONNECT_UNIXSOCKET_FAILED = "redis unixsocket connect failed";
    const ERR_CANNOT_SET_KEY = "redis cannot set key";

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
        $this->redis->setOption(\Redis::OPT_SERIALIZER, $this->config['serializer']);
        $this->redis->select($this->config['database']);

        $cacheEntry = $this->getPage($path);

        if ($cacheEntry === false) {
            // actually generate page
            $response = $next($request, $response);
            // convert generated page to cacheEntry
            $cacheEntry = $this->responseToCacheEntry($response);
            // store cacheEntry to redis db
            $result = $this->setPage($path, $cacheEntry);
            if ($result !== true) {
                throw new \Exception(self::ERR_CANNOT_SET_KEY);
            }
            return $response;
        }

        return $this->responseFromCacheEntry($cacheEntry, $response);
    }

    public function keyPage($path) {
        return "page:" . $path;
    }

    public function getPage($path) {
        $key = $this->keyPage($path);
        return $this->redis->get($key);
    }

    public function setPage($path, $data) {
        $key = $this->keyPage($path);
        return $this->redis->setEx($key, $this->cacheDuration, $data);
    }

    public function responseToCacheEntry($response) {
        $code = $response->getStatusCode();
        $body = $response->getBody();
        $body->rewind();
        $text = $body->getContents();
        $content_type = $response->getHeaderLine("Content-Type");
        $cacheEntry = array(
            'code' => $code,
            'content_type' => $content_type,
            'body' => $text,
        );
        return $cacheEntry;
    }

    public function responseFromCacheEntry($cacheEntry, $response_template) {
        // write to body
        $body = $response_template->getBody();
        $body->rewind();
        $body->write($cacheEntry['body']);
        // set header and code
        return $response_template->withStatus(
            $cacheEntry['code']
        )->withHeader(
            "Content-Type",
            $cacheEntry['content_type']
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
                throw new \Exception(self::ERR_USUPPORTED_CONNECT);
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
