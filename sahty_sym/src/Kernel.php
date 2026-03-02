<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        $timezone = $_SERVER['APP_TIMEZONE'] ?? $_ENV['APP_TIMEZONE'] ?? 'Africa/Lagos';
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            $timezone = 'Africa/Lagos';
        }

        if (date_default_timezone_get() !== $timezone) {
            date_default_timezone_set($timezone);
        }

        parent::boot();
    }
}
