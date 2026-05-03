<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\IQAService;
use App\Services\MICRService;
use App\Services\OCRService;
use App\Services\PKISignatureService;
use App\Services\FraudDetectionService;
use App\Services\PositivePayService;
use App\Services\CHIDEMService;
use App\Services\ReplicationMonitorService;
use App\Services\NotificationService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind all CTS services as singletons
        $this->app->singleton(IQAService::class);
        $this->app->singleton(MICRService::class);
        $this->app->singleton(OCRService::class);
        $this->app->singleton(NotificationService::class);
        $this->app->singleton(ReplicationMonitorService::class);

        $this->app->singleton(PKISignatureService::class, function ($app) {
            return new PKISignatureService();
        });

        $this->app->singleton(PositivePayService::class, function ($app) {
            return new PositivePayService();
        });

        $this->app->singleton(CHIDEMService::class, function ($app) {
            return new CHIDEMService($app->make(PKISignatureService::class));
        });

        $this->app->singleton(FraudDetectionService::class, function ($app) {
            return new FraudDetectionService(
                $app->make(OCRService::class),
                $app->make(MICRService::class)
            );
        });
    }

    public function boot(): void
    {
        // Force HTTPS in production
        if ($this->app->environment('production')) {
            \URL::forceScheme('https');
        }

        // Set Oracle session date format
        \DB::statement("ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS'");
    }
}
