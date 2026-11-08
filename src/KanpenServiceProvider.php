<?php

namespace Mydnic\Kanpen;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Mydnic\Kanpen\Commands\DispatchScheduledCampaignsCommand;
use Mydnic\Kanpen\Commands\SyncSubscribersCommand;

class KanpenServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerPublishing();
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'kanpen');
        $this->registerRateLimiter();
        $this->registerRoutes();
        $this->registerSchedule();

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncSubscribersCommand::class,
                DispatchScheduledCampaignsCommand::class,
            ]);
        }
    }

    protected function registerRateLimiter(): void
    {
        RateLimiter::for('kanpen-subscribe', function (Request $request) {
            if (! config('kanpen.throttle.enabled', true)) {
                return Limit::none();
            }

            $perIp = config('kanpen.throttle.per_ip', ['attempts' => 10, 'minutes' => 1]);
            $perEmail = config('kanpen.throttle.per_email', ['attempts' => 3, 'minutes' => 60]);

            // Hashed so the limiter cache never holds a plain email address.
            $email = sha1(Str::lower(trim((string) $request->input('email'))));

            return [
                Limit::perMinutes($perIp['minutes'], $perIp['attempts'])->by('kanpen-ip|'.$request->ip()),
                Limit::perMinutes($perEmail['minutes'], $perEmail['attempts'])->by('kanpen-email|'.$email),
            ];
        });
    }

    protected function registerSchedule(): void
    {
        if (! config('kanpen.campaigns.schedule', true)) {
            return;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command(DispatchScheduledCampaignsCommand::class)->everyMinute();
        });
    }

    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../database/migrations/2018_01_01_000000_create_subscribers_table.php' => database_path('migrations/2018_01_01_000000_kanpen_create_subscribers_table.php'),
                __DIR__.'/../database/migrations/2024_01_01_000001_create_campaigns_table.php' => database_path('migrations/2024_01_01_000001_kanpen_create_campaigns_table.php'),
                __DIR__.'/../database/migrations/2024_01_01_000002_create_campaign_deliveries_table.php' => database_path('migrations/2024_01_01_000002_kanpen_create_campaign_deliveries_table.php'),
                __DIR__.'/../database/migrations/2024_01_01_000004_create_campaign_clicks_table.php' => database_path('migrations/2024_01_01_000004_kanpen_create_campaign_clicks_table.php'),
            ], 'kanpen-migrations');

            $this->publishes([
                __DIR__.'/../config/kanpen.php' => config_path('kanpen.php'),
            ], 'kanpen-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/kanpen'),
            ], 'kanpen-views');

            $this->publishes([
                __DIR__.'/Notifications/SubscriberVerifyEmail.php' => app_path('Notifications/SubscriberVerifyEmail.php'),
            ], 'kanpen-notifications');
        }
    }

    protected function registerRoutes(): void
    {
        if (config('kanpen.routes.web', true)) {
            Route::group($this->webRouteConfiguration(), function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });
        }

        if (config('kanpen.routes.api', true)) {
            Route::group($this->apiRouteConfiguration(), function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
            });
        }

        if (config('kanpen.campaigns.enabled', true)) {
            Route::group($this->campaignRouteConfiguration(), function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/campaigns.php');
            });
        }
    }

    protected function webRouteConfiguration(): array
    {
        return [
            'as' => 'kanpen.',
            'prefix' => 'kanpen',
            'middleware' => 'web',
        ];
    }

    protected function apiRouteConfiguration(): array
    {
        return [
            'as' => 'kanpen.api.',
            'prefix' => 'kanpen-api',
            'middleware' => 'api',
        ];
    }

    protected function campaignRouteConfiguration(): array
    {
        return [
            'as' => 'kanpen.api.',
            'prefix' => 'kanpen-api',
            'middleware' => config('kanpen.campaigns.middleware', ['api']),
        ];
    }

    public function register(): void
    {
        if (! $this->app->configurationIsCached()) {
            $this->mergeConfigFrom(__DIR__.'/../config/kanpen.php', 'kanpen');
        }
    }
}
