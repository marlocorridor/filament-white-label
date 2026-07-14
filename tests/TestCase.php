<?php

namespace MuazzamBuilds\FilamentWhiteLabel\Tests;

use MuazzamBuilds\FilamentWhiteLabel\FilamentWhiteLabelServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            FilamentWhiteLabelServiceProvider::class,
        ];
    }
}
