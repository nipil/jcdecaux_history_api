<?php

// APPLICATION

require __DIR__ . '/../vendor/autoload.php';

// jcd data uses UTC
date_default_timezone_set('UTC');

$config['displayErrorDetails'] = true;
$config['log_path'] = __DIR__ . "/../logs/jha.log";
$config['jcd_data_abs_path'] = '/var/jcd_v2';

$app = new \Slim\App(['settings' => $config]);

$container = $app->getContainer();

// MONOLOG

$container['log_stream'] = function ($c) {
    return new \Monolog\Handler\StreamHandler($c['settings']['log_path']);
};

// JHA

$container['jha_dao'] = function ($c) {
    return new \Jha\Dao($c);
};

// MIDDLEWARES

// ROUTES

$app->group('/jcdecaux_history_api', function () use ($app) {

    $app->get('/dates', '\Jha\Controller:getDates');

});

$app->run();
