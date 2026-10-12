<?php

use App\Http\AppHttpCache;
use App\Kernel;
use App\Service\Cache\FullPageCachePolicy;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    $kernel = new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);

    return FullPageCachePolicy::isSymfonyCacheEnabled($context) ? new AppHttpCache($kernel) : $kernel;
};
