<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\FailoverInitiated;
use App\Listeners\NotifyOnFailover;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        FailoverInitiated::class => [
            NotifyOnFailover::class,
        ],
    ];

    public function boot(): void {}

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
