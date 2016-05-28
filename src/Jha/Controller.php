<?php

namespace Jha;

/**
* Controller for jcdecaux_history_api
*/
class Controller
{
    protected $logger;
    protected $dao;
    protected $http_cache;

    public function __construct($container)
    {
        $this->logger = new \Monolog\Logger(__CLASS__);
        $this->logger->pushHandler($container['log_stream']);
        $this->logger->debug(__METHOD__, func_get_args());

        $this->dao = $container['jha_dao'];
        //print_r($container);
        $this->http_cache = $container['http_cache'];
    }

    public function getDates($request, $response, $args)
    {
        $dates = $this->dao->getDates();
        $response = $response->withJson($dates);
        return $this->doCache($response);
    }

    public function doCache($response, $duration = 3600)
    {
        return $this->http_cache->withExpires($response, time() + $duration);
    }
}
