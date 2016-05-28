<?php

namespace Jha;

/**
* Controller for jcdecaux_history_api
*/
class Controller
{
    protected $logger;
    protected $dao;

    public function __construct($container)
    {
        $this->logger = new \Monolog\Logger(__CLASS__);
        $this->logger->pushHandler($container['log_stream']);

        $this->dao = $container['jha_dao'];
    }

    public function getDates($request, $response, $args)
    {
        $dates = $this->dao->getDates();
        $response = $response->withJson($dates);
        return $response;
    }

    public function getContracts($request, $response, $args)
    {
        $contracts = $this->dao->getContracts();
        $response = $response->withJson($contracts);
        return $response;
    }
}
