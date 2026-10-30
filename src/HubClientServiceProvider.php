<?php

declare(strict_types=1);

namespace Krw\HubClient;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;

class HubClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/krw-hub.php', 'krw-hub');

        $this->app->singleton(HubClient::class, function (Application $app): HubClient {
            return new HubClient(
                $app->make(Factory::class),
                (array) $app['config']->get('krw-hub', []),
            );
        });

        $this->app->alias(HubClient::class, 'krw-hub');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/krw-hub.php' => config_path('krw-hub.php'),
            ], 'krw-hub-config');
        }
    }
}
