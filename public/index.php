<?php

// APPLICATION

require __DIR__ . '/../vendor/autoload.php';

$config['displayErrorDetails'] = true;
$config['log_path'] = __DIR__ . "/../logs/jha.log";
$config['jcd_data'] = '/var/jcd_v2';

$app = new \Slim\App(['settings' => $config]);

$container = $app->getContainer();

// MONOLOG

$container['logstream'] = function ($c) {
    return new \Monolog\Handler\StreamHandler($c['settings']['log_path']);
};

$container['slim_logger'] = function ($c) {
    $logger = new \Monolog\Logger('slim');
    $logger->pushHandler($c['logstream']);
    return $logger;
};

$container['dao_logger'] = function ($c) {
    $logger = new \Monolog\Logger('dao');
    $logger->pushHandler($c['logstream']);
    return $logger;
};

$container['jha_dao'] = function ($c) {
    return new \Jha\Dao($c['dao_logger'], $c['settings']['jcd_data']);
};

// ROUTES

$app->group('/jcdecaux_history_api', function () use ($app) {

    $app->get('', function ($request, $response, $args) {
        $this->slim_logger->debug($request->getMethod(), array('route' => $request->getUri()->__toString()));
        $this->jha_dao->noop();
        return "jcdecaux_history_api is working";
    });

    $app->get('/dates', function ($request, $response, $args) {
        $this->slim_logger->debug($request->getMethod(), array('route' => $request->getUri()->__toString()));
        return print_r($this->jha_dao->getDates(), true);
    });

});

$app->run();
