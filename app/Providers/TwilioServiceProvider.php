<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Twilio\Rest\Client;

class TwilioServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(Client::class, function () {
            return new Client(config('services.twilio.sid'), config('services.twilio.token'));
        });
    }

    /**
     * Bootstrap services.
     */
}