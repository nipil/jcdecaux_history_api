<?php

namespace Jha;

/**
* Middleware: automatic logging of route performance
*/
class PerfLogger
{
    protected $logger;

    public function __construct($container)
    {
        $this->logger = new \Monolog\Logger(__CLASS__);
        $this->logger->pushHandler($container['log_stream']);
    }

    public function __invoke($request, $response, $next)
    {
        $response = $next($request, $response);
        $this->logEslapsed($request);
        return $response;
    }

    public function logEslapsed($request)
    {
        $time = (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']);
        $this->logger->debug($request->getUri(), array('time' => $time));
    }
}
