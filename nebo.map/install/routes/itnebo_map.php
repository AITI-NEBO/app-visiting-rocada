<?php

use Bitrix\Main\Routing\RoutingConfigurator;


use Nebo\Map\Controller\Objects;


return function (RoutingConfigurator $routes) {

    if(\Bitrix\Main\Loader::includeModule('nebo.map')) {

        // MAIN PREFIX API
        $routes->prefix('map')->group(function (RoutingConfigurator $routes) {

            // API - VERSION 1
            $routes->prefix('v1')->group(function (RoutingConfigurator $routes) {

                $routes->any('get', [Objects::class, 'get']);
				$routes->any('visits', [Objects::class, 'visits']);

            });
        });
    }
};
