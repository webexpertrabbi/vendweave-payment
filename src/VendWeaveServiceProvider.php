<?php

namespace VendWeave\Gateway;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use VendWeave\Gateway\Contracts\PaymentGatewayInterface;
use VendWeave\Gateway\Services\OrderAdapter;
use VendWeave\Gateway\Services\PaymentManager;
use VendWeave\Gateway\Services\TransactionVerifier;
use VendWeave\Gateway\Services\VendWeaveApiClient;

class VendWeaveServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/vendweave.php',
            'vendweave'
        );

        // Register API Client as singleton
        $this->app->singleton(VendWeaveApiClient::class, function ($app) {
            return new VendWeaveApiClient(
                config('vendweave.endpoint'),
                config('vendweave.api_key'),
                config('vendweave.api_secret'),
                config('vendweave.store_slug')
            );
        });

        // Register Transaction Verifier
        $this->app->singleton(TransactionVerifier::class, function ($app) {
            return new TransactionVerifier(
                $app->make(VendWeaveApiClient::class)
            );
        });

        // Register Payment Manager with interface binding
        $this->app->singleton(PaymentGatewayInterface::class, function ($app) {
            return new PaymentManager(
                $app->make(TransactionVerifier::class)
            );
        });

        // Register Order Adapter for flexible field mapping
        $this->app->singleton(OrderAdapter::class, function ($app) {
            return new OrderAdapter();
        });

        $this->app->alias(PaymentGatewayInterface::class, 'vendweave');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPublishables();
        $this->registerRoutes();
        $this->registerViews();
        $this->registerRateLimiting();
        $this->registerMigrations();
        $this->registerCommands();
    }

    /**
     * Register publishable resources.
     */
    protected function registerPublishables(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__ . '/../config/vendweave.php' => config_path('vendweave.php'),
            ], 'vendweave-config');

            // Publish views
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/vendweave'),
            ], 'vendweave-views');

            // Publish migrations
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'vendweave-migrations');

            // Publish assets (payment gateway logos)
            $this->publishes([
                __DIR__ . '/../resources/images' => public_path('vendor/vendweave/images'),
            ], 'vendweave-assets');

            // Publish all
            $this->publishes([
                __DIR__ . '/../config/vendweave.php' => config_path('vendweave.php'),
                __DIR__ . '/../resources/views' => resource_path('views/vendor/vendweave'),
                __DIR__ . '/../database/migrations' => database_path('migrations'),
                __DIR__ . '/../resources/images' => public_path('vendor/vendweave/images'),
            ], 'vendweave');
        }
    }

    /**
     * Register package routes.
     */
    protected function registerRoutes(): void
    {
        // Web routes
        Route::group([
            'prefix' => config('vendweave.routes.prefix', 'vendweave'),
            'middleware' => config('vendweave.routes.middleware', ['web']),
            'namespace' => 'VendWeave\\Gateway\\Http\\Controllers',
        ], function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        });

        // API routes
        Route::group([
            'prefix' => 'api/' . config('vendweave.routes.prefix', 'vendweave'),
            'middleware' => config('vendweave.routes.api_middleware', ['api']),
            'namespace' => 'VendWeave\\Gateway\\Http\\Controllers',
        ], function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        });
    }

    /**
     * Register package views.
     */
    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'vendweave');
    }

    /**
     * Register rate limiting for poll endpoint.
     */
    protected function registerRateLimiting(): void
    {
        $this->app->booted(function () {
            $limiter = $this->app->make(\Illuminate\Cache\RateLimiter::class);

            $limiter->for('vendweave-poll', function ($request) {
                return \Illuminate\Cache\RateLimiting\Limit::perMinute(
                    config('vendweave.rate_limit.max_attempts', 60)
                )->by($request->ip() . '|' . $request->route('order'));
            });
        });
    }

    /**
     * Register package migrations.
     */
    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    /**
     * Register package commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Phase-5: Reference Governance
                \VendWeave\Gateway\Console\ExpireReferencesCommand::class,
                
                // Phase-6: Financial Reconciliation
                \VendWeave\Gateway\Console\GenerateSettlementCommand::class,
                \VendWeave\Gateway\Console\ExportLedgerCommand::class,
                \VendWeave\Gateway\Console\ReconcileCommand::class,
                
                // Phase-8: Certification Badge System
                \VendWeave\Gateway\Console\CertStatusCommand::class,
                \VendWeave\Gateway\Console\CertRequestCommand::class,
                \VendWeave\Gateway\Console\CertVerifyCommand::class,
                \VendWeave\Gateway\Console\CertRenewCommand::class,
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            'vendweave',
            VendWeaveApiClient::class,
            TransactionVerifier::class,
            PaymentGatewayInterface::class,
        ];
    }
}
