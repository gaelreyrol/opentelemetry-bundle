<?php

use App\Controller\IncrementController;
use App\Controller\Traceable\ActionTraceableController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routingConfigurator): void {
    $routingConfigurator->add('php-config', '/php-config')
        ->controller([ActionTraceableController::class, 'phpConfig'])
        ->methods(['GET']);

    $routingConfigurator->add('increment', '/increment/{value}')
        ->controller(IncrementController::class)
        ->methods(['GET'])
        ->requirements(['value' => '-?\\d+']);
};
