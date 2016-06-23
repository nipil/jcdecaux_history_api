<?php

// APPLICATION

require __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('UTC'); // jcd data uses UTC

$config['displayErrorDetails'] = true;
$config['log_path'] = __DIR__ . "/../logs/jha.log";
$config['jcd_data_abs_path'] = '/var/jcd_v2';
$config['do_log_performance'] = true;
$config['determineRouteBeforeAppMiddleware'] = true;
$config['redis'] = array(
    'default_ttl' => 3600,
    'database' => 0,
    /*
     * connect_mode:
     * - "unixsocket"
     * - "network"
     */
    'connect_mode' => 'network',
    'unixsocket' => '/var/run/redis/redis.sock',
    'host' => 'localhost',
    'port' => 6379
);

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

    $app->get('/infos', '\Jha\Controller:getInfos');

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

    $app->group('/stats', function () use ($app) {

        $app->group('/activity/{period:day|week|month|year}', function () use ($app) {
            $app->get('/global', '\Jha\Controller:getActivityGlobal');

            $app->group('/contracts/{cid:[0-9]+}', function () use ($app) {
                $app->get('', '\Jha\Controller:getActivityContract');

                $app->get('/stations/{sid:[0-9]+}', '\Jha\Controller:getActivityStation');
            });
        });

        $app->group('/minmax/{period:day}', function () use ($app) {
            $app->get('/global', '\Jha\Controller:getMinMaxGlobal');

            $app->group('/contracts/{cid:[0-9]+}', function () use ($app) {
                $app->get('', '\Jha\Controller:getMinMaxContract');

                $app->get('/stations/{sid:[0-9]+}', '\Jha\Controller:getMinMaxStation');
            });
        });
    });
});

// MIDDLEWARE

$app->add(new \Slim\HttpCache\Cache('public', 86400));

$app->add(function ($request, $response, $next) {
    $response = $next($request, $response);
    return $response->withHeader(
        "Access-Control-Allow-Origin",
        "*"
    );
});

$group->add($container['jha_redis']);

$group->add($container['jha_expires']);

$group->add($container['perf_logger']);

$group->add('\Jha\Controller:middlewareClearCacheHint');

// RUN

$app->run();
