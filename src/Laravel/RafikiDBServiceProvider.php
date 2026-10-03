<?php

declare(strict_types=1);

namespace RafikiDB\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use RafikiDB\RafikiDB;
use RafikiDB\Client;

/**
 * Laravel service provider (auto-discovered via composer extra.laravel).
 *
 * Publishes:
 *   php artisan vendor:publish --tag=rafikidb-config
 *
 * Usage:
 *   $db = app('rafikidb');                    // or the facade
 *   $rows = $db->from('messages')->select('id, message')->limit(10)->execute();
 */
class RafikiDBServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/rafikidb.php', 'rafikidb');

        $this->app->singleton('rafikidb', function (Application $app): RafikiDB {
            $config = $app['config']['rafikidb'];

            $client = new Client(
                projectId: (string) $config['project_id'],
                apiKey: (string) $config['api_key'],
                baseUrl: (string) $config['base_url'],
            );

            return new RafikiDB($client);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/rafikidb.php' => config_path('rafikidb.php'),
            ], 'rafikidb-config');
        }

        $guardEnabled = (bool) ($this->app['config']['rafikidb.auth_guard'] ?? true);
        if ($guardEnabled && $this->app->bound('auth')) {
            $this->app['auth']->provider('rafikidb', fn (Application $app, array $config): \Illuminate\Contracts\Auth\UserProvider => new RafikiDBUserProvider($app));
        }
    }

    /**
     * @return string[]
     */
    public function provides(): array
    {
        return ['rafikidb'];
    }
}