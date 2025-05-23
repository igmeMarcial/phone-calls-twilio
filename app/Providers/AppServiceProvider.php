<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Client::class, function () {
            $sid = config('services.twilio.sid');
            $token = config('services.twilio.token');
            if (empty($sid) || empty($token)) {
                Log::error('Twilio credentials (SID or Auth Token) are not configured.');
            }
            return new Client($sid, $token);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
    }
}


