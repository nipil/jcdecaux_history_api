<?php

// APPLICATION

require __DIR__ . '/../vendor/autoload.php';

$config['displayErrorDetails'] = true;
$config['log_path'] = __DIR__ . "/../logs/jha.log";
$config['jcd_data_abs_path'] = '/var/jcd_v2';

$app = new \Slim\App(['settings' => $config]);

$container = $app->getContainer();

// MONOLOG

$container['log_stream'] = function ($c) {
    return new \Monolog\Handler\StreamHandler($c['settings']['log_path']);
};

$container['jha_dao'] = function ($c) {
    return new \Jha\Dao($c);
};

// ROUTES

$app->group('/jcdecaux_history_api', function () use ($app) {

    $app->get('', '\Jha\Controller:root');

    $app->get('/dates', '\Jha\Controller:getDates');

});

$app->run();
