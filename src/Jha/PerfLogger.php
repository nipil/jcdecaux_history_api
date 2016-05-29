<?php

namespace Jha;

/**
* Middleware: automatic logging of route performance
*/
class PerfLogger
{
    protected $logger;
    protected $logPerformance;

    public function __construct($container)
    {
        $this->logger = new \Monolog\Logger(__CLASS__);
        $this->logger->pushHandler($container['log_stream']);

        $this->logPerformance = $container['settings']['do_log_performance'];
    }

    public function __invoke($request, $response, $next)
    {
        $response = $next($request, $response);
        if ($this->logPerformance) {
            $this->logEslapsed($request);
        }
        return $response;
    }

    public function logEslapsed($request)
    {
        // BUG: cannot use filter_input, does not recognize REQUEST_TIME_FLOAT
        $time = (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']);
        $this->logger->debug($request->getUri(), array('time' => $time));
    }
}
