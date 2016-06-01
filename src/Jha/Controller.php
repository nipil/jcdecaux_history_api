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

    public function getContract($request, $response, $args)
    {
        $contract = $this->dao->getContract($args['cid']);
        if ($contract === null) {
            return $response->withJson(
                array('error' => $this->dao->getLastError()),
                404
            );
        }
        $response = $response->withJson($contract);
        return $response;
    }

    public function getStations($request, $response, $args)
    {
        $stations = $this->dao->getStations($args['cid']);
        if ($stations === null) {
            return $response->withJson(
                array('error' => $this->dao->getLastError()),
                404
            );
        }
        $response = $response->withJson($stations);
        return $response;
    }

    public function getStation($request, $response, $args)
    {
        $stations = $this->dao->getStation($args['cid'], $args['sid']);
        if ($stations === null) {
            return $response->withJson(
                array('error' => $this->dao->getLastError()),
                404
            );
        }
        $response = $response->withJson($stations);
        return $response;
    }

    public function getSamples($request, $response, $args)
    {
        $samples = $this->dao->getSamples(
            $args['date'],
            $args['cid'],
            $args['sid']
        );
        if ($samples === null) {
            return $response->withJson(
                array('error' => $this->dao->getLastError()),
                404
            );
        }
        return $response->withJson($samples);
    }

    public function getActivityGlobal($request, $response, $args)
    {
        $counters = $this->dao->getActivityGlobal($args['period']);
        if ($counters === null) {
            return $response->withJson(
                array('error' => $this->dao->getLastError()),
                404
            );
        }
        return $response->withJson($counters);
    }

    public function getActivityContract($request, $response, $args)
    {
        $counters = $this->dao->getActivityContract($args['period'], $args['cid']);
        if ($counters === null) {
            return $response->withJson(
                array('error' => $this->dao->getLastError()),
                404
            );
        }
        return $response->withJson($counters);
    }

    public function getActivityStation($request, $response, $args)
    {
        $counters = $this->dao->getActivityStation($args['period'], $args['cid'], $args['sid']);
        if ($counters === null) {
            return $response->withJson(
                array('error' => $this->dao->getLastError()),
                404
            );
        }
        return $response->withJson($counters);
    }

    public function getMinMaxGlobal($request, $response, $args)
    {
        $args["method"] = __METHOD__;
        return $response->withJson($args);
    }

    public function getMinMaxContract($request, $response, $args)
    {
        $args["method"] = __METHOD__;
        return $response->withJson($args);
    }

    public function getMinMaxStation($request, $response, $args)
    {
        $args["method"] = __METHOD__;
        return $response->withJson($args);
    }
}
