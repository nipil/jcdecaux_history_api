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

        $this->dao = $container['jha_dao'];
    }

    public function root()
    {
        $this->logger->debug(__METHOD__, func_get_args());
        return "api root";
    }

    public function getDates()
    {
        return print_r($this->dao->getDates(), true);
    }
}
