<?php

require __DIR__ . '/../vendor/autoload.php';

$app = new \Slim\App();

$app->group('/jcdecaux_history_api', function () use ($app) {

    $app->get('', function ($request, $response, $args) {
        $dao = new \Jha\Dao();
        return "jcdecaux_history_api is working";
    });

});

$app->run();
