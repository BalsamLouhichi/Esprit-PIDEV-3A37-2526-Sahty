<?php

use App\Kernel;

ini_set('default_charset', 'UTF-8');
date_default_timezone_set('Africa/Lagos');

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
