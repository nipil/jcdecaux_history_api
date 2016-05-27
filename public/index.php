<?php

require __DIR__ . '/../vendor/autoload.php';

$app = new \Slim\App();

$container = $app->getContainer();

$container['logstream'] = function ($c) {
    return new \Monolog\Handler\StreamHandler(__DIR__ . "/../logs/jha.log");
};

$container['slim_logger'] = function ($c) {
    $logger = new \Monolog\Logger('slim');
    $logger->pushHandler($c['logstream']);
    return $logger;
};

$app->group('/jcdecaux_history_api', function () use ($app) {

    $app->get('', function ($request, $response, $args) {
        $this->slim_logger->debug($request->getMethod(), array('route' => $request->getUri()->__toString()));
        $dao = new \Jha\Dao();
        return "jcdecaux_history_api is working";
    });

});

$app->run();
