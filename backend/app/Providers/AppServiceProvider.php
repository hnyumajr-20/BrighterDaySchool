<?php

namespace App\Providers;

use App\Mail\Transport\BirdTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use MessageBird\Bird;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Mail::extend('bird', function () {
            return new BirdTransport(new Bird(config('services.bird.key')));
        });
    }
}
