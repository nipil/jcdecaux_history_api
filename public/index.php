<?php

// APPLICATION

require __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('UTC'); // jcd data uses UTC

$config['displayErrorDetails'] = true;
$config['log_path'] = __DIR__ . "/../logs/jha.log";
$config['jcd_data_abs_path'] = '/var/jcd_v2';
$config['caching_duration'] = 3600;
$config['do_log_performance'] = true;
$config['redis'] = [
    'database' => 0,
    'host' => 'localhost',
    'port' => 6379
];

$app = new \Slim\App(['settings' => $config]);

$container = $app->getContainer();

// FILE LOG

$container['log_stream'] = function ($c) {
    return new \Monolog\Handler\StreamHandler($c['settings']['log_path']);
};

// JHA DAO

$container['jha_dao'] = function ($c) {
    return new \Jha\Dao($c);
};

// PERF LOGGER

$container['perf_logger'] = function ($c) {
    return new \Jha\PerfLogger($c);
};

// HTTP CACHE HEADERS

$container['http_cache'] = function ($c) {
    return new \Slim\HttpCache\CacheProvider();
};

$container['jha_expires'] = function ($c) {
    return new \Jha\HttpExpire($c);
};

// REDIS CACHING

$container['jha_redis'] = function ($c) {
    return new \Jha\RedisCache($c);
};

// ROUTES

$group = $app->group('/jcdecaux_history_api', function () use ($app) {

    $app->get('/dates', '\Jha\Controller:getDates');

    $app->group('/contracts', function () use ($app) {
        $app->get('', '\Jha\Controller:getContracts');

        $app->group('/{cid:[0-9]+}', function () use ($app) {
            $app->get('', '\Jha\Controller:getContract');

            $app->group('/stations', function () use ($app) {
                $app->get('', '\Jha\Controller:getStations');

                $app->get('/{sid:[0-9]+}', '\Jha\Controller:getStation');
            });
        });
    });

    $app->get(
        '/samples'
        . '/dates/{date:[0-9]{4}-[0-9]{2}-[0-9]{2}}'
        . '/contracts/{cid:[0-9]+}'
        . '/stations/{sid:[0-9]+}',
        '\Jha\Controller:getSamples'
    );

});

// MIDDLEWARE

$app->add(new \Slim\HttpCache\Cache('public', 86400));

$group->add($container['jha_redis']);

$group->add($container['jha_expires']);

$group->add($container['perf_logger']);

// RUN

$app->run();
