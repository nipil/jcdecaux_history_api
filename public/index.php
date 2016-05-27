<?php

// APPLICATION

require __DIR__ . '/../vendor/autoload.php';

$settings = [
    'settings' => [
        'displayErrorDetails' => true,
    ],
];

$app = new \Slim\App($settings);

$container = $app->getContainer();

// MONOLOG

$container['logstream'] = function ($c) {
    return new \Monolog\Handler\StreamHandler(__DIR__ . "/../logs/jha.log");
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

$container['jha_dao'] =function ($c) {
    return new \Jha\Dao($c['dao_logger']);
};

// ROUTES

$app->group('/jcdecaux_history_api', function () use ($app) {

    $app->get('', function ($request, $response, $args) {
        $this->slim_logger->debug($request->getMethod(), array('route' => $request->getUri()->__toString()));
        $this->jha_dao->noop();
        return "jcdecaux_history_api is working";
    });

});

$app->run();
