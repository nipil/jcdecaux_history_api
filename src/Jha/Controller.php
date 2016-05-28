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
        $this->logger = $container->controller_logger;
        $this->logger->debug(__METHOD__, func_get_args());
    }

    public function noop()
    {
        $this->logger->debug(__METHOD__, func_get_args());
        return "controller works";
    }
}
