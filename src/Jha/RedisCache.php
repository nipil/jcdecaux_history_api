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
        $path = $request->getUri()->getPath();

        $this->connectRedis();

        $this->logger->debug("redis "
            . $this->config['connect_mode']
            . " isConnected "
            . $this->redis->isConnected());

        $response = $next($request, $response);

        return $response;
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
                throw new \Exception("unsupported redis connect mode");
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
            throw new \Exception("connectNetwork failed");
        }
    }

    private function connectUnixSocket($filename)
    {
        $result = $this->redis->open($filename);
        if ($result === false) {
            throw new \Exception("connectUnixSocket failed");
        }
    }
}
