<?php

use IndifferentKetchup\Iblogs\Api\ApiRouter;
use IndifferentKetchup\Iblogs\Config\Config;
use IndifferentKetchup\Iblogs\Config\ConfigKey;
use IndifferentKetchup\Iblogs\Frontend\FrontendRouter;
use IndifferentKetchup\Iblogs\Storage\MongoDBClient;
use IndifferentKetchup\Iblogs\Util\URL;

require_once __DIR__ . '/vendor/autoload.php';

try {
    MongoDBClient::getInstance()->ensureIndexes();
} catch (Exception $e) {
    error_log("Failed to ensure MongoDB indexes: " . $e->getMessage());
}

$requestCount = 0;
$maxRequests = Config::getInstance()->get(ConfigKey::WORKER_REQUESTS);

do {
    $running = \frankenphp_handle_request(function () {

        MongoDBClient::getInstance()->reset();
        URL::clear();

        if (URL::isApi()) {
            ApiRouter::getInstance()->run();
        } else {
            FrontendRouter::getInstance()->run();
        }
    });

    gc_collect_cycles();

    $requestCount++;
} while ($running && $requestCount < $maxRequests);