<?php

namespace Jha;

/**
* Controller for jcdecaux_history_api
*/
class Controller
{
    protected $logger;

    public function __construct($container)
    {
        $this->logger = new \Monolog\Logger(__CLASS__);
        $this->logger->pushHandler($container['logstream']);
        $this->logger->debug(__METHOD__, func_get_args());
    }

    public function noop()
    {
        $this->logger->debug(__METHOD__, func_get_args());
        return "controller works";
    }

    public function root()
    {
        $this->logger->debug(__METHOD__, func_get_args());
        return "api root";
    }
}
