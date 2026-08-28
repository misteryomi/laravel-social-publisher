<?php

namespace Misteryomi\SocialPublisher;

use Illuminate\Support\ServiceProvider;

class SocialPublisherServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/social-publisher.php', 'social-publisher');

        $this->app->singleton(SocialPublisherManager::class, fn ($app) => new SocialPublisherManager($app));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/social-publisher.php' => config_path('social-publisher.php'),
            ], 'social-publisher-config');

            $this->publishes([
                __DIR__.'/../database/migrations/' => database_path('migrations'),
            ], 'social-publisher-migrations');
        }
    }
}
